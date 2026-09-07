<?php
/*
Save in registration functions
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
 * Save attachment ID in Gravity Forms notification settings
 * Validates and sanitizes attachment IDs to prevent security issues
 * 
 * @param array $notification Notification settings array
 * @param array $form         Form settings array
 * @return array Modified notification array with validated attachment_id
 */
function gf_kgm_notification_attachment_save( $notification, $form ) {
	$attachment_id_raw = rgpost( 'gf_kgm_notification_attachment_id' );
	
	// Return empty string if no value provided
	if ( empty( $attachment_id_raw ) ) {
		$notification['attachment_id'] = '';
		return $notification;
	}
	
	// Sanitize the input
	$attachment_id_raw = sanitize_text_field( $attachment_id_raw );
	
	// Validate format: should only contain numbers and commas
	// This prevents injection of malicious data
	if ( ! preg_match( '/^[\d,]+$/', $attachment_id_raw ) ) {
		// Invalid format - return empty to prevent security issues
		$notification['attachment_id'] = '';
		return $notification;
	}
	
	// Split by comma and validate each ID
	$attachment_ids = explode( ',', $attachment_id_raw );
	$valid_ids = array();
	
	foreach ( $attachment_ids as $id ) {
		// Convert to integer and validate
		$id = absint( trim( $id ) );
		if ( $id > 0 ) {
			// verify attachment exists (more secure but slower)
			if ( get_post_type( $id ) === 'attachment' ) {
				$valid_ids[] = $id;
			}
		}
	}
	
	// Rejoin valid IDs
	$notification['attachment_id'] = implode( ',', $valid_ids );
	
	return $notification;
}