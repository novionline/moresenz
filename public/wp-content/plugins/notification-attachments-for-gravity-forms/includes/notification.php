<?php
/*
Notification
Plugin: Notification Attachments for Gravity Forms
Since: 0.1
Author: KGM Servizi
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Displays admin notice when Gravity Forms is not active
 * 
 * @since 0.1
 * @return void
 */
function gf_kgm_notification_attachment_admin_notices() {
	echo '<div class="error"><p>' . esc_html__( 'You must have Gravity Forms activated in order to use Notification Attachments for Gravity Forms.', 'notification-attachments-for-gravity-forms' ) . '</p></div>';
}

/**
 * Check for other filters on gform_notification and display notice if found
 * 
 * @since 0.1
 * @return void
 */
function gf_kgm_notification_attachment_check_conflicts() {
	// Check if there are other filters on gform_notification hook
	$filter_count = has_filter( 'gform_notification' );
	
	// If more than 1 filter is registered, there might be conflicts
	if ( $filter_count !== false && $filter_count > 1 ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Other plugins are modifying Gravity Forms notifications. If attachments are not being sent, try increasing the plugin priority or check for conflicts.', 'notification-attachments-for-gravity-forms' ) . '</p></div>';
	}
}