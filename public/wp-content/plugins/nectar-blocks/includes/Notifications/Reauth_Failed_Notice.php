<?php

declare(strict_types=1);

namespace Nectar\Notifications;

use Nectar\Licensing\Token_Service;

/**
 * Reauth_Failed_Notice
 *
 * Dismissible admin notice shown on Nectarblocks screens when Token_Service::give_up()
 * set the flag: the seat was released server-side (portal deregister, refund, key
 * cancelled) or automated JWT V2 token recovery could neither refresh nor
 * re-register. Either way the stored pair is gone and the user must reactivate. The
 * flag is cleared on the next successful register; dismissible in the meantime.
 * Modeled on Expired_License_Notice but driven by a simple boolean option rather
 * than a signature.
 *
 * @since 3.1.0
 */
class Reauth_Failed_Notice {
  const SLUG = 'nectarblocks_reauth_failed';

  public function __construct() {
    add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_dismiss_script' ] );
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

  private function should_display(): bool {
    return (bool) get_option( Token_Service::REAUTH_FAILED_OPTION, false );
  }

  /**
   * Register the dismiss handler on admin_enqueue_scripts (before the head's
   * scripts flush) so the inline script attached to wp-util is reliably printed.
   */
  public function maybe_enqueue_dismiss_script() {
    if ( ! $this->is_nectar_screen() || ! current_user_can( 'manage_options' ) || ! $this->should_display() ) {
      return;
    }

    $nonce = wp_create_nonce( self::SLUG . '_dismiss' );

    wp_enqueue_script( 'wp-util' );
    wp_add_inline_script(
        'wp-util',
        sprintf(
            '( function() {
              document.body.addEventListener( "click", function( e ) {
                if ( e.target.closest( ".notice.%1$s button.notice-dismiss" ) ) {
                  wp.ajax.post( %2$s, { nonce: %3$s } );
                }
              } );
            } )();',
            esc_js( self::SLUG ),
            wp_json_encode( self::SLUG . '_dismissed', JSON_HEX_TAG ),
            wp_json_encode( $nonce, JSON_HEX_TAG )
        )
    );
  }

  public function render() {
    if ( ! $this->is_nectar_screen() || ! current_user_can( 'manage_options' ) || ! $this->should_display() ) {
      return;
    }

    $manage_url = admin_url( 'admin.php?page=nectar-blocks&tab=authorization' );
    $message = '<strong>'
      . esc_html__( 'Nectarblocks: This site\'s license is no longer active.', 'nectar-blocks' )
      . '</strong> '
      . esc_html__( 'It was released from your account or could not be renewed automatically. Reactivate it to restore updates and the Template Library.', 'nectar-blocks' )
      . ' <a href="' . esc_url( $manage_url ) . '"><strong>'
      . esc_html__( 'Manage license', 'nectar-blocks' )
      . '</strong></a>.';

    echo '<div class="notice notice-error is-dismissible ' . esc_attr( self::SLUG ) . '"><p>'
      . wp_kses(
          $message,
          [
            'strong' => [],
            'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
          ]
      )
      . '</p></div>';
  }

  /**
   * Clear the failure flag on dismiss. Nonce-verified and capability-gated (writes
   * a site-wide option). A later recovery failure re-sets the flag, re-surfacing
   * the notice.
   */
  public function handle_dismiss() {
    check_ajax_referer( self::SLUG . '_dismiss', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( null, 403 );
      return;
    }

    delete_option( Token_Service::REAUTH_FAILED_OPTION );
    wp_send_json_success();
  }
}
