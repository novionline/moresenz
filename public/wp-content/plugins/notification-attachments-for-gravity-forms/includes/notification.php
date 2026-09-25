<?php
/**
 * Notification admin notices.
 *
 * @package Notification_Attachments_For_Gravity_Forms
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Displays admin notice when Gravity Forms is not active.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @return  void
 */
function gf_kgm_notification_attachment_admin_notices() {
	/* Only show this to someone who can actually act on it. Without the check the notice is
	   printed on every admin screen for every logged-in role, including ones that cannot see
	   the plugins page at all. */
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error" role="alert"><p>'
		. esc_html__( 'You must have Gravity Forms activated in order to use Notification Attachments for Gravity Forms.', 'notification-attachments-for-gravity-forms' )
		. ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">'
		. esc_html__( 'Manage plugins', 'notification-attachments-for-gravity-forms' )
		. '</a></p></div>';
}

/**
 * Check for other filters on gform_notification and display notice if found.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @return  void
 */
function gf_kgm_notification_attachment_check_conflicts() {
	// Only relevant on the Gravity Forms notification edit screen
	if ( ! class_exists( 'GFForms' ) || 'notification_edit' !== GFForms::get_page() ) {
		return;
	}

	// Count callbacks on gform_notification hook.
	// Note: has_filter() returns priority, not count. Iterate WP_Hook callbacks to get actual count.
	global $wp_filter;
	$callback_count = 0;
	if ( isset( $wp_filter['gform_notification'] ) ) {
		foreach ( $wp_filter['gform_notification']->callbacks as $priority_callbacks ) {
			$callback_count += count( $priority_callbacks );
		}
	}

	// If more than 1 callback is registered, there might be conflicts
	if ( $callback_count > 1 ) {
		echo '<div class="notice notice-warning is-dismissible" role="status" aria-live="polite"><p>' . esc_html__( 'Other plugins are modifying Gravity Forms notifications. If attachments are not being sent, try increasing the plugin priority or check for conflicts.', 'notification-attachments-for-gravity-forms' ) . '</p></div>';
	}
}