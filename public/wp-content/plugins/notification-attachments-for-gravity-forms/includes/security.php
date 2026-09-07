<?php
/*
Security function for help no dequeue if Gravity Forms setting remove third part script
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
 * Add plugin script to Gravity Forms no-conflict list
 * Checks for global variable existence to prevent fatal errors
 * 
 * @param array $allowed_script_keys Array of allowed script keys
 * @return array Modified array with plugin text domain added
 */
function gf_kgm_notification_attachment_gform_noconflict( $allowed_script_keys ) {
	global $gf_kgm_notification_attachment;
	
	// Check if global variable exists and has required properties
	if ( ! isset( $gf_kgm_notification_attachment ) || ! is_object( $gf_kgm_notification_attachment ) ) {
		return $allowed_script_keys; // Return unchanged if global variable is not initialized
	}
	
	$plugin = $gf_kgm_notification_attachment;
	
	// Verify text_domain property exists
	if ( isset( $plugin->text_domain ) ) {
		$allowed_script_keys[] = sanitize_key( $plugin->text_domain );
	}
	
	return $allowed_script_keys;
}