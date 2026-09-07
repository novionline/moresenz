<?php

declare(strict_types=1);

namespace Nectar\Notifications;

use Nectar\Licensing\Expired_Updates_Service;

/**
 * Expired_License_Notice
 *
 * Renders a dismissible "your subscription expired — resubscribe" admin notice
 * on Nectarblocks admin screens, styled like the existing "Nectarblocks Alert"
 * conflict notices (see NotificationManager). Driven by the once-a-day cached
 * data from Expired_Updates_Service.
 *
 * Dismissal is *signature-aware*: we store the signature of the alert the user
 * X'd out, and only re-show the notice when a NEW alert arrives (a different
 * signature). An unchanged alert stays hidden. Active subscribers never see it
 * (the endpoint returns hasExpiredLicense:false for them).
 *
 * @since 3.0.0
 */
class Expired_License_Notice {
  const SLUG = 'nectarblocks_expired_license';

  const DISMISSED_OPTION = 'nectarblocks_expired_notice_dismissed_sig';

  // Stored when the user dismisses while the backend hasn't yet supplied a
  // signature (pre paired-backend-change). It records "explicitly dismissed"
  // without a stable identity, so the notice stays hidden across page loads but
  // still re-surfaces once a real signature arrives.
  const DISMISSED_NO_SIG = '__dismissed_no_signature__';

  /**
   * Endpoint data to render, stashed by maybe_enqueue_dismiss_script() for
   * render() to reuse on the same request. Null means "don't render".
   *
   * @var object|null
   */
  private $render_data = null;

  public function __construct() {
    add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_dismiss_script' ] );
    add_action( 'admin_notices', [ $this, 'render' ] );
    add_action( 'wp_ajax_' . self::SLUG . '_dismissed', [ $this, 'handle_dismiss' ] );
  }

  /**
   * Limit the notice to Nectarblocks admin screens (the plugin's own pages)
   * rather than every wp-admin page.
   */
  private function is_nectar_screen() {
    if ( ! function_exists( 'get_current_screen' ) ) {
      return false;
    }
    // Match the plugin's own screens — id contains 'nectar-blocks' (e.g.
    // 'toplevel_page_nectar-blocks'). The 'nectar-blocks' needle is tighter than a
    // bare 'nectar' so an unrelated "nectar*" plugin doesn't trip the notice.
    $screen = get_current_screen();
    return $screen && strpos( $screen->id, 'nectar-blocks' ) !== false;
  }

  /**
   * Decide whether to show the notice and, if so, register the dismiss handler.
   * Runs on admin_enqueue_scripts (before the head's scripts are flushed) so the
   * inline script attached to wp-util is reliably printed — registering it in
   * admin_notices is too late if wp-util was already output in the head.
   */
  public function maybe_enqueue_dismiss_script() {
    // Billing concern — only show (and allow dismissing) to admins, matching
    // the capability the dismiss handler enforces.
    if ( ! $this->is_nectar_screen() || ! current_user_can( 'manage_options' ) ) {
      return;
    }

    // Don't double up with the persistent renewal card on the Authorization tab
    // itself — the card (ExpiredLicenseCard.tsx) already carries the full nudge
    // there. Read-only screen gating, so no nonce is required.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['tab'] ) && sanitize_key( wp_unslash( $_GET['tab'] ) ) === 'authorization' ) {
      return;
    }

    $data = Expired_Updates_Service::get_data();
    $dismissed = get_option( self::DISMISSED_OPTION, '' );

    if ( ! self::should_display( $data, $dismissed ) ) {
      return;
    }

    // Stash for render() (same request) so the decision isn't recomputed.
    $this->render_data = $data;

    $signature = isset( $data->signature ) ? (string) $data->signature : '';
    $nonce = wp_create_nonce( self::SLUG . '_dismiss' );

    // Attach the dismiss handler via wp_add_inline_script (on wp-util, which
    // provides wp.ajax) rather than a raw <script> echo, so it inherits
    // WordPress's CSP nonce on sites that enforce script-src.
    wp_enqueue_script( 'wp-util' );
    wp_add_inline_script(
        'wp-util',
        sprintf(
            '( function() {
              document.body.addEventListener( "click", function( e ) {
                if ( e.target.closest( ".notice.%1$s button.notice-dismiss" ) ) {
                  wp.ajax.post( %2$s, { signature: %3$s, nonce: %4$s } );
                }
              } );
            } )();',
            esc_js( self::SLUG ),
            wp_json_encode( self::SLUG . '_dismissed', JSON_HEX_TAG ),
            wp_json_encode( $signature, JSON_HEX_TAG ),
            wp_json_encode( $nonce, JSON_HEX_TAG )
        )
    );
  }

  /**
   * Echo the notice markup. The show/hide decision and dismiss-script
   * registration happen earlier, in maybe_enqueue_dismiss_script().
   */
  public function render() {
    if ( ! $this->render_data ) {
      return;
    }

    // build_message() already escapes every dynamic value, but run the output
    // through wp_kses with an explicit allowlist as a belt-and-braces guard so a
    // future unescaped addition can't silently become an XSS sink.
    echo '<div class="notice notice-warning is-dismissible ' . esc_attr( self::SLUG ) . '"><p>'
      . wp_kses(
          $this->build_message( $this->render_data ),
          [
            'strong' => [],
            'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
          ]
      )
      . '</p></div>';
  }

  /**
   * Pure decision: should the notice render for this data + the signature the
   * user previously dismissed? Hidden for active subscribers (no expired
   * license / null data) and when the current alert's signature matches the
   * dismissed one. Re-shows only when a NEW alert (different signature) arrives.
   *
   * When the response has no signature yet (pre paired-backend-change), there's
   * no stable identity, so dismissal is honored via the DISMISSED_NO_SIG
   * sentinel; a real signature later overrides it and re-surfaces the notice.
   *
   * @param object|null $data                Endpoint data from Expired_Updates_Service.
   * @param string      $dismissed_signature Signature stored at last dismissal.
   */
  public static function should_display( $data, $dismissed_signature ): bool {
    if ( ! $data || empty( $data->hasExpiredLicense ) ) {
      return false;
    }

    $signature = isset( $data->signature ) ? (string) $data->signature : '';

    if ( $signature === '' ) {
      // No stable identity yet — honor an explicit "dismissed" until a real
      // signature exists.
      return $dismissed_signature !== self::DISMISSED_NO_SIG;
    }

    // Real signature — re-show only when it differs from the dismissed one.
    return $signature !== $dismissed_signature;
  }

  /**
   * The value to persist when the user dismisses an alert carrying $signature.
   * Falls back to the sentinel when no signature is available yet, so the
   * dismissal still persists. Mirror of expiredNoticeDismissValue() in the
   * editor banner.
   *
   * @param string $signature Signature posted from the dismissed notice.
   */
  public static function dismiss_value( $signature ): string {
    return $signature !== '' ? $signature : self::DISMISSED_NO_SIG;
  }

  /**
   * Builds the notice copy from the endpoint data. Returns trusted HTML — all
   * dynamic values are escaped before interpolation.
   *
   * Deliberately lightweight: this global banner just drives the user to the
   * Authorization tab, where the renewal card (ExpiredLicenseCard.tsx) carries the
   * full detail (counts + Renew CTA). Keeping that in one place avoids duplication.
   */
  private function build_message( $data ) {
    $latest = isset( $data->latestVersion ) && $data->latestVersion ? (string) $data->latestVersion : '';

    $headline = $latest !== ''
      /* translators: %s: latest Nectarblocks version, e.g. "3.2.0". */
      ? sprintf( esc_html__( 'Nectarblocks: Your subscription has expired — you are missing Nectarblocks %s.', 'nectar-blocks' ), esc_html( $latest ) )
      : esc_html__( 'Nectarblocks: Your subscription has expired.', 'nectar-blocks' );

    // Point at the Authorization tab (the renewal card's home), not the external
    // renew URL — the tab is where the detail + Renew CTA live.
    $manage_url = admin_url( 'admin.php?page=nectar-blocks&tab=authorization' );
    $cta = ' <a href="' . esc_url( $manage_url ) . '"><strong>'
      . esc_html__( 'Manage license', 'nectar-blocks' )
      . '</strong></a>.';

    return '<strong>' . $headline . '</strong>' . $cta;
  }

  /**
   * Persist the dismissed signature so the notice stays hidden until a new
   * alert (different signature) arrives. Nonce-verified and capability-gated:
   * dismissal writes a site-wide option, so it's restricted to admins and
   * protected against CSRF.
   */
  public function handle_dismiss() {
    check_ajax_referer( self::SLUG . '_dismiss', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( null, 403 );
      return;
    }

    // The signature is an opaque server token, not prose. Use wp_strip_all_tags,
    // not sanitize_text_field: the latter strips %xx octets / collapses whitespace
    // and would mangle a non-hex signature, breaking the match in should_display().
    // It's $_POST hygiene only — the value is compared/json-encoded, never output raw.
    $signature = isset( $_POST['signature'] ) ? wp_strip_all_tags( wp_unslash( $_POST['signature'] ) ) : '';
    // autoload=false: this option is only read on Nectar admin screens, so keep
    // it out of the autoloaded set that loads on every (incl. front-end) request.
    update_option( self::DISMISSED_OPTION, self::dismiss_value( $signature ), false );
    wp_send_json_success();
  }
}
