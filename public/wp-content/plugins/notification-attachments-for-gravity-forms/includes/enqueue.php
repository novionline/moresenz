<?php
/*
Enqueue style and script
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
 * Enqueue script for notification editor
 * Checks for global variable existence to prevent fatal errors
 * 
 * @since 0.1
 * @return void
 */
function gf_kgm_notification_attachment_attach_script() {
	global $gf_kgm_notification_attachment;
	
	// Check if global variable exists and has required properties
	if ( ! isset( $gf_kgm_notification_attachment ) || ! is_object( $gf_kgm_notification_attachment ) ) {
		return; // Exit early if global variable is not initialized
	}
	
	$plugin = $gf_kgm_notification_attachment;
	
	// Verify required properties exist
	if ( ! isset( $plugin->text_domain ) || ! isset( $plugin->plugin_url ) || ! isset( $plugin->version ) ) {
		return; // Exit early if required properties are missing
	}

	if ( class_exists( 'GFForms' ) ) {
		if ( GFForms::get_page() == 'notification_edit' ) {
			$script_url = esc_url_raw( $plugin->plugin_url . '/assets/script.js' );
			$script_handle = sanitize_key( $plugin->text_domain );
			$script_version = sanitize_text_field( $plugin->version );
			
			wp_enqueue_script( $script_handle, $script_url, array( 'gform_gravityforms' ), $script_version, true );	
		}
	}
}