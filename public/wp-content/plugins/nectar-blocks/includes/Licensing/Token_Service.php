<?php

declare(strict_types=1);

namespace Nectar\Licensing;

use Nectar\Global_Settings\Nectar_Blocks_Options;
use Nectar\Shared\Hostname;
use Nectar\Update\Updaters;

/**
 * Token_Service
 *
 * Owns the JWT V2 access/refresh token lifecycle: proactive (WP-Cron) and reactive
 * (on 401) refresh, atomic single-use rotation of the refresh token, and re-register
 * recovery when a refresh token is lost in transit or replayed.
 *
 * V1 (eternal-token) sites are a hard no-op everywhere here — every entry point
 * short-circuits when tokenVersion !== 2, so existing installs behave exactly as
 * before with no cron and no expiry enforcement.
 *
 * The token store is the shared `nectar_blocks_options` WP option (also read by the
 * plugin/theme/importer updaters), so a rotated token propagates to all consumers
 * by writing that one option — no cross-plugin coordination needed.
 *
 * @since 3.1.0
 */
class Token_Service {
  const CRON_HOOK = 'nectar_blocks_token_refresh';

  const CRON_SCHEDULE = 'nectar_blocks_twice_daily';

  const LOCK_KEY = 'nectar_token_refresh_lock';

  // Seconds. Longer than License_Client::TIMEOUT so a crashed refresh's lock
  // self-expires rather than wedging rotation.
  const LOCK_TTL = 30;

  // Refresh once the 24h access token is within half its life of expiring.
  const REFRESH_THRESHOLD = 12 * HOUR_IN_SECONDS;

  const REREGISTER_BACKOFF_KEY = 'nectar_token_reregister_backoff';

  const REREGISTER_BACKOFF_TTL = HOUR_IN_SECONDS;

  // Option flag consumed by Reauth_Failed_Notice when automated recovery gives up.
  const REAUTH_FAILED_OPTION = 'nectar_blocks_reauth_failed';

  // Domain_Changed_Notice dismissal, stored as "old|new" so a later, different move
  // re-surfaces the notice. Never cleared: after a successful transfer the stored
  // registeredHostname IS the current host, so domain_move() returns null and the
  // notice is gone regardless; a later move produces a different pair key, which no
  // longer matches this one.
  const DOMAIN_CHANGED_DISMISSED_OPTION = 'nectar_blocks_domain_changed_dismissed';

  // /license/transfer outcomes where the stored token can no longer be presented
  // for a transfer but the seat is still wanted: the source row is gone
  // (not_found) or the token itself is rejected before the activation is even
  // looked up (signature, issuer, revoked jti → invalid_token). A plain register
  // at the live host is the only client-side recovery either way; deregister would
  // 401 on the same token and leave the site wedged. For invalid_token the source
  // MAY still be ACTIVE — same trade-off refresh() already accepts for
  // invalid_refresh_token — and register's seat cap bounds the damage on a
  // fully-used key. activation_inactive is deliberately NOT here: that seat was
  // released on purpose, and REFRESH_GIVE_UP_CODES applies to transfer too.
  const TRANSFER_FALLBACK_TO_REGISTER = [ 'activation_not_found', 'invalid_token' ];

  // /license/refresh 401 error_codes that mean this refresh token can never rotate
  // again but the activation itself is still wanted, so a re-register at the live
  // host is the right client-side recovery: a rotation lost in transit / replayed
  // (invalid_refresh_token), or a stored hostname the backend can't canonicalize
  // (activation_hostname_invalid — defensive, unreachable for hosts we registered).
  // Any *other* 401 code is something new we don't have a recovery for, and burning
  // the license key on a register would be the wrong guess — leave the stored pair
  // alone and retry later.
  const REFRESH_REREGISTER_CODES = [
    'invalid_refresh_token',
    'activation_hostname_invalid',
  ];

  // /license/refresh 401 error_codes that mean someone deliberately deactivated this
  // seat or key server-side (portal deregister, refund, transfer, key cancelled).
  // Re-registering here would silently undo that decision by minting a NEW
  // activation seconds later — the backend never reuses an INACTIVE row — so these
  // go straight to give_up() from refresh() AND transfer(): pair cleared, local
  // inactive, admin notice, cron off. The admin can reactivate on purpose from the
  // Authorization tab.
  const REFRESH_GIVE_UP_CODES = [
    'activation_inactive',
    'key_inactive',
  ];

  // Token-consumer 401 error_code that no refresh can fix: the presented token's
  // `aud` doesn't match the host that presented it, and a refreshed token is minted
  // against the same stored host. Recovery is the admin-confirmed transfer.
  const AUD_MISMATCH_CODE = 'aud_mismatch';

  /* -------------------- reads (static-options half) -------------------- */

  private static function options(): array {
    $opts = Nectar_Blocks_Options::get_options();
    return is_array( $opts ) ? $opts : [];
  }

  private static function token_version( array $opts ): int {
    return (int) ( $opts['tokenVersion'] ?? 1 );
  }

  private static function is_v2( array $opts ): bool {
    return self::token_version( $opts ) === 2 && ( $opts['refreshToken'] ?? '' ) !== '';
  }

  /**
   * The hostname the stored activation is bound to, for external calls that must
   * match it (deregister): the exact value captured at registration, or the current
   * site host as a fallback for activations that predate the registeredHostname key
   * (it was introduced without a backfill). Refresh deliberately does NOT use this —
   * it must send the stored value the access token's aud was minted with — and
   * reregister/transfer send current_hostname(), where the site actually lives.
   */
  public static function registered_hostname( array $opts ): string {
    $hostname = (string) ( $opts['registeredHostname'] ?? '' );
    if ( $hostname === '' ) {
      return self::current_hostname();
    }
    return Hostname::canonicalize( $hostname );
  }

  /**
   * The current site's hostname from home_url() — the same source the updaters
   * present on an upgrade check, which is why both go through the one shared
   * derivation; '' when home_url() has no parseable host.
   */
  public static function current_hostname(): string {
    return Hostname::site_host();
  }

  /**
   * True when this active V2 site's home_url() host no longer matches the host its
   * activation was registered at. Under strict audience enforcement the backend
   * 401s every hostname-bearing call from such a site (the token's aud stays bound
   * to the stored host, see nectar-backend #906), and no refresh can fix that —
   * only /license/transfer or a fresh register can.
   *
   * Computed from existing state, nothing persisted: it self-corrects if home_url
   * flips back. Deliberately NOT automatic recovery — a DB clone (staging copy,
   * second install) looks identical to a moved site, and a transfer always
   * deactivates the source activation, so the admin decides via Domain_Changed_Notice.
   *
   * V1 sites are never "moved" (their tokens aren't aud-enforced); sites without a
   * stored hostname or with an unparseable home_url() aren't either.
   *
   * @param array|null $opts The licensing option, when the caller already read it.
   */
  public static function hostname_moved( ?array $opts = null ): bool {
    return self::domain_move( $opts ) !== null;
  }

  /**
   * The move itself, for callers that need to name both hosts (Domain_Changed_Notice
   * renders them and keys its dismissal on the pair): [ 'from' => registered host,
   * 'to' => live host ], both canonicalized, or null when the site has not moved.
   * hostname_moved() is the boolean face of this.
   *
   * @param array|null $opts The licensing option, when the caller already read it.
   * @return array{from: string, to: string}|null
   */
  public static function domain_move( ?array $opts = null ): ?array {
    $opts = $opts ?? self::options();
    if ( ! self::is_v2( $opts ) || ( $opts['token'] ?? '' ) === '' || empty( $opts['isLicenseActive'] ) ) {
      return null;
    }
    $registered = (string) ( $opts['registeredHostname'] ?? '' );
    $current = self::current_hostname();
    if ( $registered === '' || $current === '' ) {
      return null;
    }
    $registered = Hostname::canonicalize( $registered );

    return $registered === $current ? null : [ 'from' => $registered, 'to' => $current ];
  }

  /**
   * The atomic-pair rule: a result claiming tokenVersion 2 counts only when the
   * whole rotatable pair is present (token + refreshToken + expiresIn). Shared
   * by register, re-register and storage so the invariant has one spelling.
   */
  public static function is_complete_v2_pair( array $r ): bool {
    return $r['tokenVersion'] === 2 && $r['token'] !== null && $r['refreshToken'] !== null && $r['expiresIn'] !== null;
  }

  /**
   * Whether a License_Client result may be stored as this site's activation: the
   * call succeeded, it carries a token, and — if it claims V2 — the whole rotatable
   * pair. A partial V2 pair stored as an eternal V1 would 401 permanently once the
   * 24h JWT expired, with no cron and no reauth path, so every write path
   * (register, transfer, re-register) asks this one question.
   */
  public static function is_storable_activation( array $r ): bool {
    return $r['ok'] && $r['token'] !== null && ( $r['tokenVersion'] !== 2 || self::is_complete_v2_pair( $r ) );
  }

  /* -------------------- pure decision (WP-free, unit-testable) -------------------- */

  /**
   * True once the access token is within REFRESH_THRESHOLD of expiring (or already
   * expired). Kept pure — no options/clock reads — so the cron gate is testable in
   * isolation (mirrors Expired_License_Notice::should_display).
   */
  public static function is_within_refresh_window( int $expires_at, int $now ): bool {
    return $now >= ( $expires_at - self::REFRESH_THRESHOLD );
  }

  /* -------------------- entry points -------------------- */

  /**
   * WP-Cron callback. Refreshes only a V2 token that is within its refresh window;
   * self-cleans the schedule if the site is no longer on a V2 token.
   */
  public static function cron_refresh(): void {
    $opts = self::options();

    if ( self::token_version( $opts ) !== 2 ) {
      self::unschedule_cron();
      return;
    }
    if ( ( $opts['refreshToken'] ?? '' ) === '' ) {
      return;
    }
    if ( ! self::is_within_refresh_window( (int) ( $opts['tokenExpiresAt'] ?? 0 ), time() ) ) {
      return;
    }

    self::refresh();
  }

  /**
   * Reactive entry point for a 401 from a licensing call. Returns a fresh access
   * token (from a refresh or a recovery re-register) or null. A V1 site has no
   * refresh token, so this is a no-op there.
   *
   * A 401 on a still-valid token from a moved site is an aud mismatch, not an
   * expiry: refreshing would mint another token bound to the OLD host and burn a
   * rotation for nothing, every throttle window. Bail without HTTP and leave
   * recovery to the admin-confirmed transfer. An actually expired token is still
   * refreshed (the cron may have missed it) so `warn`-mode moved sites keep working.
   *
   * $error_code is the failing response's own reason, when the caller could read one
   * (see RemoteVersionCheck::fetch()). It is authoritative where present — the
   * backend knows why it rejected the token, we're only guessing:
   * - aud_mismatch → no refresh, no HTTP.
   * - any other code (token_expired, token_revoked, activation_inactive,
   *   activation_not_found, invalid_token) → refresh, which lets /license/refresh
   *   say whether the seat is still wanted (rotate / re-register) or was
   *   deactivated on purpose (give up); a moved site never gets them (the aud
   *   check runs first).
   * - null (an older backend that doesn't send a code, or a caller that can't read
   *   one) → the local hostname_moved() inference this shipped with.
   */
  public static function reauthorize( ?string $error_code = null ): ?string {
    $opts = self::options();
    if ( ! self::is_v2( $opts ) ) {
      return null;
    }
    if ( $error_code === self::AUD_MISMATCH_CODE ) {
      return null;
    }
    if ( $error_code === null && self::hostname_moved( $opts ) && (int) ( $opts['tokenExpiresAt'] ?? 0 ) > time() ) {
      return null;
    }
    return self::refresh();
  }

  /**
   * Locked, single-use rotation. The stored refresh token is overwritten ONLY after
   * a fully-parsed success (token + refreshToken + expiresIn all present). See the
   * implementation plan for the full control-flow rationale.
   *
   * Refresh 401 error_codes route by REFRESH_REREGISTER_CODES (the pair is dead but
   * the seat is wanted → reregister()) and REFRESH_GIVE_UP_CODES (the seat or key
   * was deactivated server-side on purpose → give_up(), never a new seat). Anything
   * else — an unknown code, or no parseable code at all (a WAF/proxy error page) —
   * leaves the stored pair alone for a later retry.
   *
   * @param bool $allow_reregister Whether a terminal 401 may fall through to the
   *   recovery re-register. False for callers that must not mint a *new* activation
   *   behind the admin's back — the deregister retry, which is trying to release the
   *   activation it already has.
   */
  public static function refresh( bool $allow_reregister = true ): ?string {
    $opts = self::options();

    if ( self::token_version( $opts ) !== 2 ) {
      return null; // V1: nothing to rotate
    }

    $refresh_token = (string) ( $opts['refreshToken'] ?? '' );
    $hostname = (string) ( $opts['registeredHostname'] ?? '' );
    if ( $refresh_token === '' ) {
      return null;
    }

    // Cross-process mutex: only one worker may consume the single-use refresh token.
    // If another refresh already holds it, bail without rotating. Held until the
    // outcome is persisted — through the recovery re-register too — so no other
    // writer (a second rotation, an admin-confirmed transfer()) can land between
    // the round-trip and the write. Released in `finally`: a crash mid-way only
    // leaks the self-expiring lock, never a rotation.
    if ( ! self::acquire_lock() ) {
      return null;
    }

    try {
      $r = License_Client::refresh( $refresh_token, $hostname );

      // 401 = this refresh token can never rotate again. Still under the lock:
      // a deliberate server-side deactivation ends recovery outright (a register
      // would just mint a replacement seat behind the owner's back); a dead pair on
      // a still-wanted seat re-registers, when the caller allows it.
      if ( $r['code'] === 401 ) {
        if ( self::is_give_up_code( $r['error_code'] ) ) {
          return self::give_up();
        }
        if ( ! $allow_reregister || ! self::is_reregister_code( $r['error_code'] ) ) {
          return null;
        }
        return self::reregister();
      }

      // Transport error / non-200 (5xx, 429, …): leave the stored tokens untouched
      // and let a later cron tick or reactive 401 try again.
      if ( ! $r['ok'] ) {
        return null;
      }

      // Rotation guard: a partial success (any of token/refreshToken/expiresIn
      // missing) must NOT overwrite the stored refresh token.
      if ( $r['token'] === null || $r['refreshToken'] === null || $r['expiresIn'] === null ) {
        return null;
      }

      // Defensive: the backend pins tokenVersion to 2 in the refresh envelope, but
      // the store must never depend on it — only a V2 activation can be rotated at
      // all and the guard above proved the pair whole, so say so before handing it
      // to token_fields() (a silent V1 downgrade would drop the refresh token and
      // disarm the cron).
      $r['tokenVersion'] = 2;
      self::write_options( self::token_fields( $r ) );
      delete_option( self::REAUTH_FAILED_OPTION );

      return $r['token'];
    } finally {
      self::release_lock();
    }
  }

  /**
   * Whether a /license/refresh 401 is one of the terminal outcomes only a
   * re-register recovers from.
   *
   * A 401 with no parseable error_code is NOT one: the backend always sends a code
   * (refresh.test.ts pins the set), so a bare 401 is a WAF/CDN/proxy error page or
   * a truncated body, and minting a new activation off that guess is the one
   * outcome this class exists to prevent. Leave the pair alone and retry later.
   */
  private static function is_reregister_code( ?string $error_code ): bool {
    return in_array( $error_code, self::REFRESH_REREGISTER_CODES, true );
  }

  /**
   * Whether a /license/refresh or /license/transfer 401 reports a deliberate
   * server-side deactivation of this seat or its key — the one 401 that must NOT be
   * answered with a register.
   */
  private static function is_give_up_code( ?string $error_code ): bool {
    return in_array( $error_code, self::REFRESH_GIVE_UP_CODES, true );
  }

  /**
   * Recovery path for a REFRESH_REREGISTER_CODES 401 from /license/refresh — a
   * rotation lost in transit or replayed. Re-registers with the stored license key at the
   * host the site actually lives on now (current_hostname()) to mint a fresh pair,
   * and records that host as registeredHostname. The old activation is already
   * dead here (that's what the 401 means), so there is nothing to transfer, and
   * registering the stored host from a site that has since moved would bind the
   * new token to the wrong aud. Backoff-guarded so a down license server can't be
   * hammered; never calls refresh() (loop-free: refresh → 401 → reregister →
   * register, one hop). Runs under refresh()'s lock, which the caller still holds
   * here, so its write can't race a transfer() or another rotation.
   */
  private static function reregister(): ?string {
    if ( get_transient( self::REREGISTER_BACKOFF_KEY ) ) {
      return null;
    }
    set_transient( self::REREGISTER_BACKOFF_KEY, 1, self::REREGISTER_BACKOFF_TTL );

    $opts = self::options();
    $license = (string) ( $opts['licenseKey'] ?? '' );
    if ( $license === '' ) {
      return self::give_up();
    }

    // current_hostname() is only empty when home_url() has no parseable host, which
    // no live site has; fall back to the stored value so the recovery register still
    // sends a real hostname.
    $hostname = self::current_hostname() ?: Hostname::canonicalize( (string) ( $opts['registeredHostname'] ?? '' ) );

    $r = License_Client::register( $license, $hostname, self::server_analytics() );

    // Stores whatever the backend issued — a fresh V2 pair, or an eternal V1 token if
    // it downgraded us — and arms or stops the cron to match.
    if ( self::is_storable_activation( $r ) ) {
      $fields = self::token_fields( $r );
      $fields['registeredHostname'] = $hostname;
      self::write_options( $fields );
      self::sync_cron( $fields['tokenVersion'] );
      delete_option( self::REAUTH_FAILED_OPTION );
      return $r['token'];
    }

    // An HTTP-ok response the storable check above refused (e.g. a V2 claim missing
    // refreshToken/expiresIn) is a partial response, not a dead key: leave stored
    // tokens and isLicenseActive alone — the backoff transient set on entry paces
    // the retry. Non-ok outcomes (invalid key, seat limit, transport) still exhaust
    // recovery below.
    if ( $r['ok'] ) {
      return null;
    }

    return self::give_up();
  }

  /**
   * Terminal: the seat was deactivated server-side, or automated recovery is
   * exhausted. Drop the dead token pair, mark the license inactive, flag the admin
   * notice, and stop the cron. Clearing the pair is what makes this terminal — with
   * an empty token the updaters stop presenting it, and with an empty refreshToken
   * is_v2() is false so reauthorize()/refresh() bail before any HTTP; otherwise
   * every later 401 would re-run this, re-arming the notice the admin dismissed and
   * burning a /license/refresh per update check. licenseKey stays so the admin can
   * reactivate with one click.
   */
  private static function give_up(): ?string {
    $opts = self::options();
    $opts['isLicenseActive'] = false;
    $opts['token'] = '';
    $opts['refreshToken'] = '';
    $opts['tokenExpiresAt'] = 0;
    $opts['tokenVersion'] = 1;
    Nectar_Blocks_Options::update_options( $opts ); // read-merge-write
    update_option( self::REAUTH_FAILED_OPTION, 1, false );
    self::unschedule_cron();
    return null;
  }

  /* -------------------- admin-confirmed domain transfer -------------------- */

  /**
   * Move this site's activation to its current home_url() host via
   * POST /license/transfer, then persist the new pair with registeredHostname set
   * to that host. Called from the License_API transfer route after the admin
   * confirms the move in Domain_Changed_Notice — never automatically (see
   * hostname_moved()). Enforces the notice's own gate server-side: unless
   * domain_move() says this active V2 site has moved, it refuses locally
   * (transfer_failed, no HTTP) — a stale tab or a direct POST from a site already
   * registered where it lives would otherwise burn one of the rate-limited
   * transfers, and a V1 site (token not aud-bound) has nothing to transfer.
   *
   * Outcomes, keyed on the backend's TransferErrorCode:
   * - ok → persisted, cron re-armed, update caches purged so the next upgrade check
   *   runs immediately against the new token.
   * - activation_not_found / invalid_token → the stored token can't be presented
   *   for a transfer (see TRANSFER_FALLBACK_TO_REGISTER); fall back to a plain
   *   register at the live host (same net effect a transfer would have had).
   * - activation_inactive / key_inactive → the seat or key was deactivated on
   *   purpose (REFRESH_GIVE_UP_CODES): give_up() locally, returned as-is for the UI.
   * - max_activations_reached / max_dev_activations_reached / license_mismatch /
   *   invalid_hostname / transfer_rate_limited (3 per key per 7 days) /
   *   transfer_failed → returned as-is for the UI, no state change, no retry.
   *   Rate limiting needs no client backoff: the action is click-driven.
   *
   * The result is returned as the client produced it — including an HTTP-ok
   * response that carried no storable activation. Callers decide with
   * is_storable_activation(); nothing here rewrites ok/error_code.
   *
   * Runs under the same mutex as refresh(): a transfer deactivates the source
   * activation server-side, so a cron/reactive rotation interleaving with it
   * could persist the pair of an activation that no longer exists (and the next
   * 401 would then reregister, orphaning the transferred seat). A held lock is
   * reported as transfer_failed — the action is click-driven, and the notice
   * already says "try again later".
   *
   * @return array A License_Client result array (ok, code, error_code, message, …).
   */
  public static function transfer(): array {
    $opts = self::options();
    $move = self::domain_move( $opts );
    $license = (string) ( $opts['licenseKey'] ?? '' );

    if ( $move === null || $license === '' ) {
      return License_Client::failure( 'transfer_failed' );
    }

    // domain_move() already proved both non-empty.
    $token = (string) $opts['token'];
    $hostname = $move['to'];

    if ( ! self::acquire_lock() ) {
      return License_Client::failure( 'transfer_failed' );
    }

    try {
      $r = License_Client::transfer( $token, $hostname, $license );

      // The seat (or key) was deactivated on purpose while this site sat on the old
      // host: nothing to move, and a register would mint a new seat behind the
      // owner's back. Same terminal outcome as refresh(); the notice's copy tells the
      // admin to reactivate from the Authorization tab if they want it back.
      if ( ! $r['ok'] && self::is_give_up_code( $r['error_code'] ) ) {
        self::give_up();
        return $r;
      }

      if ( ! $r['ok'] && in_array( $r['error_code'], self::TRANSFER_FALLBACK_TO_REGISTER, true ) ) {
        $r = License_Client::register( $license, $hostname, self::server_analytics() );
      }

      if ( ! self::is_storable_activation( $r ) ) {
        return $r;
      }

      // Not a fresh activation: a transfer continues the existing one, so the admin's
      // autoUpdate choice survives it.
      self::store_activation( $license, $hostname, $r, false );

      // The updaters were 401ing against the old aud; drop their caches (and the
      // throttle floor) so the next check runs immediately on the new token.
      Updaters::purge_all();

      return $r;
    } finally {
      // Released AFTER persist, like refresh(): a crash in between only leaks the
      // self-expiring lock.
      self::release_lock();
    }
  }

  /* -------------------- registration writes (register/deregister routes) -------------------- */

  /**
   * The licensing option with the opaque refresh token stripped — the only shape
   * that may be returned to the browser. Used by the register/deregister routes and
   * Admin_API::get_options().
   */
  public static function public_auth(): array {
    $opts = self::options();
    unset( $opts['refreshToken'] );
    return $opts;
  }

  /**
   * Activate a license key for this site: call the external register endpoint and,
   * when the result is storable, persist it as a fresh activation. The client result
   * is returned either way so the caller can report the failure reason.
   *
   * @param array $analytics Environment payload from the browser, or [].
   * @return array A License_Client result array.
   */
  public static function activate( string $license_key, string $hostname, array $analytics ): array {
    $r = License_Client::register( $license_key, $hostname, $analytics );

    if ( self::is_storable_activation( $r ) ) {
      self::store_activation( $license_key, $hostname, $r, true );
    }

    return $r;
  }

  /**
   * Persist a storable activation: the token (pair) plus license key, hostname and
   * active flag. Arms the refresh cron for a V2 pair; a V1 (eternal) token is stored
   * with no cron.
   *
   * $fresh distinguishes a brand-new activation, which opts the site into auto
   * updates, from a continuation of an existing one (transfer), where the admin's
   * autoUpdate choice must survive. It is the ONLY thing that writes autoUpdate here.
   *
   * $r is a License_Client result array that passed is_storable_activation().
   */
  public static function store_activation( string $license_key, string $hostname, array $r, bool $fresh ): void {
    $fields = self::token_fields( $r );
    $fields['licenseKey'] = $license_key;
    $fields['registeredHostname'] = $hostname;
    $fields['isLicenseActive'] = true;
    if ( $fresh ) {
      $fields['autoUpdate'] = true;
    }

    self::write_options( $fields );
    self::sync_cron( $fields['tokenVersion'] );
    delete_option( self::REAUTH_FAILED_OPTION );
  }

  /**
   * Clear all licensing state after a deregister (or forced deactivation): token
   * pair, license key, active/autoUpdate flags, hostname — and stop the cron.
   */
  public static function clear_registration(): void {
    $opts = self::options();
    $opts['licenseKey'] = '';
    $opts['isLicenseActive'] = false;
    $opts['autoUpdate'] = false;
    $opts['token'] = '';
    $opts['refreshToken'] = '';
    $opts['tokenExpiresAt'] = 0;
    $opts['tokenVersion'] = 1;
    $opts['registeredHostname'] = '';

    self::write_options( $opts );
    self::unschedule_cron();
    delete_option( self::REAUTH_FAILED_OPTION );
  }

  /* -------------------- central write path (read-merge-write) -------------------- */

  /**
   * The four token fields for a storable client result — the one place the V2/V1
   * branch is spelled out. A claimed V2 pair is stored whole; anything else
   * (an eternal V1 token) is stored with the rotation fields zeroed.
   *
   * @return array{token: string, refreshToken: string, tokenExpiresAt: int, tokenVersion: int}
   */
  private static function token_fields( array $r ): array {
    if ( self::is_complete_v2_pair( $r ) ) {
      return [
        'token' => (string) $r['token'],
        'refreshToken' => (string) $r['refreshToken'],
        'tokenExpiresAt' => time() + (int) $r['expiresIn'],
        'tokenVersion' => 2,
      ];
    }

    return [
      'token' => (string) $r['token'],
      'refreshToken' => '',
      'tokenExpiresAt' => 0,
      'tokenVersion' => 1,
    ];
  }

  /**
   * The single option write for every licensing path. Reads the current option,
   * overlays $overlay and writes it back — never a bare update_options() with a
   * partial array (Settings_Base does a full-array replace, which would wipe
   * licenseKey/autoUpdate/etc.) — then drops the cached "expired license" payload,
   * which any token change invalidates.
   */
  private static function write_options( array $overlay ): void {
    Nectar_Blocks_Options::update_options( array_merge( self::options(), $overlay ) );

    Expired_Updates_Service::purge();
  }

  /**
   * Arm the rotation cron for a V2 token, stop it for anything else.
   */
  private static function sync_cron( int $version ): void {
    if ( $version === 2 ) {
      self::schedule_cron();
    } else {
      self::unschedule_cron();
    }
  }

  /* -------------------- lock (add_option mutex) -------------------- */

  /**
   * Atomic cross-process mutex. wp_options.option_name is UNIQUE, so of two
   * concurrent add_option() calls exactly one INSERT wins — a genuine
   * compare-and-swap that set_transient's get-then-set is not (and here a lost race
   * would replay the single-use refresh token → server marks the activation INACTIVE).
   */
  private static function acquire_lock(): bool {
    $existing = get_option( self::LOCK_KEY );
    if ( $existing !== false ) {
      if ( ( time() - (int) $existing ) < self::LOCK_TTL ) {
        return false; // a fresh lock is held by another worker
      }
      delete_option( self::LOCK_KEY ); // stale → clear, then contend
    }
    return add_option( self::LOCK_KEY, (string) time(), '', 'no' );
  }

  private static function release_lock(): void {
    delete_option( self::LOCK_KEY );
  }

  /* -------------------- cron lifecycle -------------------- */

  public static function schedule_cron(): void {
    if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
      // First tick now; cron_refresh's window gate decides whether to actually
      // rotate, so an early fire is harmless.
      wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
    }
  }

  public static function unschedule_cron(): void {
    wp_clear_scheduled_hook( self::CRON_HOOK );
  }

  /**
   * Minimal environment payload for a server-side (cron/reactive) re-register, since
   * the JS getNBSimpleAnalytics() isn't available in a PHP context.
   */
  private static function server_analytics(): array {
    global $wp_version;
    return [
      'phpVersion' => PHP_VERSION,
      'wordpressVersion' => is_string( $wp_version ?? null ) ? $wp_version : '',
      'pluginVersion' => NECTAR_BLOCKS_VERSION,
    ];
  }
}
