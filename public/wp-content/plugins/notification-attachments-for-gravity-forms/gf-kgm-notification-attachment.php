<?php
/* 
Plugin Name: Notification Attachments for Gravity Forms
Version: 0.6.3
Description: Send attachment in Gravity Forms Notification
Author: KGM Servizi
Author URI: https://kgmservizi.com
License: GPLv2 or later
Text Domain: notification-attachments-for-gravity-forms
Requires at least: 5.0
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PHPCS Suppressions
 * 
 * This file contains phpcs:ignore comments for the following cases:
 * - includes/form.php Line 38, 40: WordPress.Security.NonceVerification.Missing
 *   Reason: Form data processing is integrated within Gravity Forms notification settings form.
 *   Gravity Forms handles nonce verification for the entire form, including our custom fields.
 *   We cannot verify nonce directly as this is not our form, but part of Gravity Forms' form structure.
 */

global $gf_kgm_notification_attachment;
add_action( 'init', 'gf_kgm_notification_attachment_init' );

/**
 * Initialize plugin
 * Registers hooks and filters, initializes global variables
 * 
 * @return object|null Plugin object if Gravity Forms is active, null otherwise
 */
function gf_kgm_notification_attachment_init() {
	global $gf_kgm_notification_attachment;

	if ( class_exists( 'GFForms' ) ) {
		// Use priority 20 to ensure attachments are added after most other plugins
		// This helps prevent conflicts with plugins that modify notifications
		add_filter( 'gform_notification', 'gf_kgm_notification_attachment_send', 20, 3 );		
		add_action( 'admin_enqueue_scripts', 'gf_kgm_notification_attachment_attach_script');
		add_filter( 'gform_pre_notification_save', 'gf_kgm_notification_attachment_save', 10, 2 );
		add_filter( 'gform_noconflict_scripts', 'gf_kgm_notification_attachment_gform_noconflict' );
		add_filter( 'gform_notification_settings_fields', 'gf_kgm_notification_attachment_editor', 10, 3 );
		
		$gf_kgm_notification_attachment = (object) array(
			'text_domain' => 'notification-attachments-for-gravity-forms',
			'version'     => '0.6.3',
			'plugin_url'  => trailingslashit( plugin_dir_url( __FILE__ ) )
			);
		
		// Check for other filters on notifications after our filter is registered
		add_action( 'admin_notices', 'gf_kgm_notification_attachment_check_conflicts' );
		
		return $gf_kgm_notification_attachment;
	} else {
		add_action( 'admin_notices', 'gf_kgm_notification_attachment_admin_notices' );
	}
}

// Include plugin files (using require_once to prevent multiple inclusions)
require_once( plugin_dir_path( __FILE__ ) . 'includes/form.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/save.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/send.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/enqueue.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/security.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/notification.php' );
