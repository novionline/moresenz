<?php

declare(strict_types=1);

namespace Nectar\Notifications;

use Nectar\API\Licensing\License_API;
use Nectar\API\Router;
use Nectar\Licensing\Token_Service;

/**
 * Domain_Changed_Notice
 *
 * Dismissible admin notice shown on Nectarblocks screens when the site's home_url()
 * host no longer matches the host its license was activated at
 * (Token_Service::domain_move()). The access token's audience stays bound to the
 * old host, so under strict enforcement updates and the Template Library 401 until
 * the activation is transferred — and the plugin deliberately does NOT do that on
 * its own (a staging/DB clone is indistinguishable from a real move, and a transfer
 * always deactivates the source). The "Transfer license" button POSTs to the
 * internal License_API transfer route; the admin decides.
 *
 * Dismissal is keyed to the old|new host pair, so a later, different move
 * re-surfaces the notice. A successful transfer needs no cleanup: the site is then
 * registered at its current host, so there is no move to report. Modeled on
 * Reauth_Failed_Notice.
 *
 * @since 3.3.0
 */
class Domain_Changed_Notice {
  const SLUG = 'nectarblocks_domain_changed';

  /**
   * The move to render, resolved once in maybe_enqueue_script() (which runs before
   * admin_notices) and consumed by render() — same stash-on-the-instance pattern as
   * Expired_License_Notice, so the option reads and host comparison happen once per
   * request. Null means "nothing to show".
   *
   * @var array{from: string, to: string}|null
   */
  private $move = null;

  public function __construct() {
    add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_script' ] );
    add_action( 'admin_notices', [ $this, 'render' ] );
    add_action( 'wp_ajax_' . self::SLUG . '_dismissed', [ $this, 'handle_dismiss' ] );
  }

  private function is_nectar_screen(): bool {
    if ( ! function_exists( 'get_current_screen' ) ) {
      return false;
    }
    $screen = get_current_screen();
    return $screen && strpos( $screen->id, 'nectar-blocks' ) !== false;
  }

  /**
   * "old|new" for a given move — the dismissal key.
   *
   * @param array{from: string, to: string} $move
   */
  private static function pair_key( array $move ): string {
    return $move['from'] . '|' . $move['to'];
  }

  /**
   * Translated copy for the /license/transfer failure codes, passed to the inline
   * script as a code → message map. The backend's `message` is English-only, so it
   * is only the fallback for a code this doesn't cover yet. Wording matches
   * SerialKeyInputBox's getLicenseErrorMessage for the codes both surfaces can hit.
   *
   * @return array<string,string>
   */
  private static function error_messages(): array {
    return [
      'invalid_hostname' => __( 'This site\'s address could not be used. Check the WordPress Address and Site Address settings, then try again.', 'nectar-blocks' ),
      // invalid_token / activation_not_found never reach the browser:
      // Token_Service::transfer() falls back to a register for those, so a failure
      // surfaces register's own code instead.
      'license_mismatch' => __( 'This license key does not match the activation being transferred.', 'nectar-blocks' ),
      'activation_inactive' => __( 'This site\'s activation was released from your Nectarblocks account. Reactivate it from the Authorization tab if you still want it here.', 'nectar-blocks' ),
      'key_inactive' => __( 'This license key is no longer active. Please contact support.', 'nectar-blocks' ),
      'max_activations_reached' => __( 'This license has reached its maximum number of activations.', 'nectar-blocks' ),
      'max_dev_activations_reached' => __( 'This license has reached its maximum number of development activations.', 'nectar-blocks' ),
      'transfer_rate_limited' => __( 'This license has been transferred too many times recently. Please try again later.', 'nectar-blocks' ),
      'transfer_failed' => __( 'The license could not be transferred. Please try again later or reactivate it from the Authorization tab.', 'nectar-blocks' ),
    ];
  }

  /**
   * Register the button/dismiss handlers on admin_enqueue_scripts (before the head's
   * scripts flush) so the inline script is reliably printed, and stash the move for
   * render(). wp-api-fetch brings its own root-URL + REST-nonce middleware, so the
   * transfer POST is nonce-checked by the REST layer and capability-checked by the
   * route.
   */
  public function maybe_enqueue_script() {
    if ( ! $this->is_nectar_screen() || ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $move = Token_Service::domain_move();
    if ( $move === null || get_option( Token_Service::DOMAIN_CHANGED_DISMISSED_OPTION, '' ) === self::pair_key( $move ) ) {
      return;
    }

    $this->move = $move;
    $messages = self::error_messages();

    wp_enqueue_script( 'wp-util' );
    wp_enqueue_script( 'wp-api-fetch' );
    wp_add_inline_script(
        'wp-api-fetch',
        sprintf(
            '( function() {
              var notice = ".notice.%1$s";
              var messages = %7$s;
              document.body.addEventListener( "click", function( e ) {
                if ( e.target.closest( notice + " button.notice-dismiss" ) ) {
                  wp.ajax.post( %2$s, { nonce: %3$s } );
                  return;
                }
                var button = e.target.closest( notice + " .nectar-transfer-license" );
                if ( ! button ) {
                  return;
                }
                var label = button.textContent;
                var status = button.closest( notice ).querySelector( ".nectar-transfer-status" );
                var fail = function( res ) {
                  var code = res ? res.error_code : "";
                  if ( status ) {
                    status.textContent = messages[ code ] || ( res && res.message ) || %6$s;
                  }
                  button.disabled = false;
                  button.textContent = label;
                };
                button.disabled = true;
                button.textContent = %4$s;
                if ( status ) {
                  status.textContent = "";
                }
                wp.apiFetch( { path: %5$s, method: "POST" } ).then( function( res ) {
                  if ( res && res.status === "success" ) {
                    window.location.reload();
                    return;
                  }
                  fail( res );
                } ).catch( function() {
                  fail( null );
                } );
              } );
            } )();',
            esc_js( self::SLUG ),
            wp_json_encode( self::SLUG . '_dismissed', JSON_HEX_TAG ),
            wp_json_encode( wp_create_nonce( self::SLUG . '_dismiss' ), JSON_HEX_TAG ),
            wp_json_encode( __( 'Transferring…', 'nectar-blocks' ), JSON_HEX_TAG ),
            wp_json_encode( '/' . Router::REST_NAMESPACE . License_API::TRANSFER_ROUTE, JSON_HEX_TAG ),
            wp_json_encode( $messages['transfer_failed'], JSON_HEX_TAG ),
            wp_json_encode( $messages, JSON_HEX_TAG )
        )
    );
  }

  /**
   * The button and the failure message live in their own elements, outside the
   * explanatory paragraph: the script writes the failure into .nectar-transfer-status
   * only, so the button survives it and can be clicked again.
   */
  public function render() {
    if ( $this->move === null ) {
      return;
    }

    $message = '<strong>'
      . sprintf(
          /* translators: 1: the hostname the license was activated at, 2: the site's current hostname */
          esc_html__( 'Nectarblocks: this site\'s domain changed from %1$s to %2$s.', 'nectar-blocks' ),
          '<code>' . esc_html( $this->move['from'] ) . '</code>',
          '<code>' . esc_html( $this->move['to'] ) . '</code>'
      )
      . '</strong> '
      . esc_html__( 'Updates and the Template Library stay tied to the old domain until you transfer the license. If this is a copy of another site (staging, a clone), dismiss this instead — transferring would deactivate the original.', 'nectar-blocks' );

    echo '<div class="notice notice-warning is-dismissible ' . esc_attr( self::SLUG ) . '"><p>'
      . wp_kses( $message, [ 'strong' => [], 'code' => [] ] )
      . '</p><p><button type="button" class="button button-primary nectar-transfer-license">'
      . sprintf(
          /* translators: %s: the site's current hostname */
          esc_html__( 'Transfer license to %s', 'nectar-blocks' ),
          esc_html( $this->move['to'] )
      )
      . '</button></p><p class="nectar-transfer-status" role="alert"></p></div>';
  }

  /**
   * Remember the dismissal for this specific old|new pair. Nonce-verified and
   * capability-gated (writes a site-wide option).
   */
  public function handle_dismiss() {
    check_ajax_referer( self::SLUG . '_dismiss', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( null, 403 );
      return;
    }

    // Resolved here rather than reused from the render pass: this is a separate
    // (AJAX) request, so nothing was stashed on this instance.
    $move = Token_Service::domain_move();
    if ( $move !== null ) {
      update_option( Token_Service::DOMAIN_CHANGED_DISMISSED_OPTION, self::pair_key( $move ), false );
    }

    wp_send_json_success();
  }
}
