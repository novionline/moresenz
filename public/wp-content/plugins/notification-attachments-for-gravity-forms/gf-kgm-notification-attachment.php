<?php
/* 
Plugin Name: Notification Attachments for Gravity Forms
Version: 0.6.4
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

define( 'GF_KGM_NOTIFICATION_ATTACHMENT_VERSION', '0.6.4' );

/*
 * PHPCS Suppressions
 *
 * includes/form.php and includes/save.php carry phpcs:ignore comments for
 * WordPress.Security.NonceVerification.Missing.
 *
 * Reason: our fields live inside the Gravity Forms notification settings form, not our own,
 * so there is no nonce of ours to verify. GF verifies its own nonce first: on save,
 * GF_Settings::process_postback() calls
 * check_admin_referer( 'gform_settings_save', 'gform_settings_save_nonce' ) before reaching
 * the save callback that applies gform_pre_notification_save. Both files additionally perform
 * their own capability check, because GF's notification settings renderer sets no per-page
 * capability of its own.
 *
 * No line numbers are given on purpose: they rot on the first edit.
 */

global $gf_kgm_notification_attachment;
add_action( 'init', 'gf_kgm_notification_attachment_init' );

/**
 * Initialize plugin.
 *
 * Registers hooks and filters, initializes global variables.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @return  object|null Plugin object if Gravity Forms is active, null otherwise.
 */
function gf_kgm_notification_attachment_init() {
	global $gf_kgm_notification_attachment;

	if ( class_exists( 'GFForms' ) ) {
		// Load plugin files only when Gravity Forms is active (avoids parsing on every request)
		require_once plugin_dir_path( __FILE__ ) . 'includes/form.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/save.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/send.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/enqueue.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/security.php';

		// Use priority 20 to ensure attachments are added after most other plugins
		// This helps prevent conflicts with plugins that modify notifications
		add_filter( 'gform_notification', 'gf_kgm_notification_attachment_send', 20, 3 );
		add_action( 'admin_enqueue_scripts', 'gf_kgm_notification_attachment_attach_script' );
		add_filter( 'gform_pre_notification_save', 'gf_kgm_notification_attachment_save', 10, 2 );
		add_filter( 'gform_noconflict_scripts', 'gf_kgm_notification_attachment_gform_noconflict' );
		add_filter( 'gform_notification_settings_fields', 'gf_kgm_notification_attachment_editor', 10, 3 );
		
		$gf_kgm_notification_attachment = (object) array(
			'text_domain' => 'notification-attachments-for-gravity-forms',
			'version'     => GF_KGM_NOTIFICATION_ATTACHMENT_VERSION,
			'plugin_url'  => trailingslashit( plugin_dir_url( __FILE__ ) ),
		);
		
		// Check for other filters on notifications after our filter is registered
		add_action( 'admin_notices', 'gf_kgm_notification_attachment_check_conflicts' );
		
		return $gf_kgm_notification_attachment;
	} else {
		add_action( 'admin_notices', 'gf_kgm_notification_attachment_admin_notices' );
	}
}

// notification.php must be loaded unconditionally because it provides the
// admin notice function used when Gravity Forms is not active.
require_once plugin_dir_path( __FILE__ ) . 'includes/notification.php';
