<?php
/*
Send Functions
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
 * Add attachments to Gravity Forms notification email
 * Validates attachment IDs and file paths to prevent security issues
 * 
 * @param array $notification Notification settings array
 * @param array $form         Form settings array
 * @param array $lead         Entry/lead data array
 * @return array Modified notification array with attachments added
 */
function gf_kgm_notification_attachment_send( $notification, $form, $lead ) {
	$attachment_id_raw = rgar( $notification, 'attachment_id' );
	
	// Return early if no attachment IDs provided
	if ( empty( $attachment_id_raw ) ) {
		return $notification;
	}
	
	// Sanitize and validate attachment IDs
	$attachment_id_raw = sanitize_text_field( $attachment_id_raw );
	
	// Validate format: should only contain numbers and commas
	if ( ! preg_match( '/^[\d,]+$/', $attachment_id_raw ) ) {
		// Invalid format - skip attachments to prevent security issues
		return $notification;
	}
	
	$attachment_ids = explode( ',', $attachment_id_raw );
	$wp_upload_dir  = wp_upload_dir();
	
	// Ensure attachments array exists and is an array
	// Merge with existing attachments if any (for compatibility with other plugins)
	if ( ! isset( $notification['attachments'] ) || ! is_array( $notification['attachments'] ) ) {
		$notification['attachments'] = array();
	}
	
	if ( ! empty( $attachment_ids ) ) {
		foreach ( $attachment_ids as $attachment_id ) {
			// Validate and sanitize attachment ID
			$attachment_id = absint( trim( $attachment_id ) );
			if ( empty( $attachment_id ) ) {
				continue; // Skip invalid attachment IDs
			}
			
			// Verify attachment exists and is actually an attachment
			if ( get_post_type( $attachment_id ) !== 'attachment' ) {
				continue; // Skip if not a valid attachment
			}
			
			// Get attachment file path using WordPress function (handles CDN and custom configurations)
			// This is more reliable than converting URL to path manually
			$path = get_attached_file( $attachment_id );
			
			// Fallback: if get_attached_file fails, try URL conversion method
			if ( empty( $path ) ) {
				$attachment_url = wp_get_attachment_url( $attachment_id );
				if ( ! empty( $attachment_url ) ) {
					// Convert URL to file path (fallback method)
					// Filter for upload dir by @effakt
					$path = str_replace( $wp_upload_dir['baseurl'], $wp_upload_dir['basedir'], $attachment_url );
				}
			}
			
			// Apply filter (allows customization but should be used carefully)
			$path = apply_filters( 'gf_kgm_notification_attachment_path', $path, $attachment_id, $form, $lead );
			
			// Validate path after filter
			if ( empty( $path ) ) {
				continue; // Skip if path is empty after filter
			}
			
			// Security check: verify path is within upload directory to prevent path traversal
			$upload_basedir = realpath( $wp_upload_dir['basedir'] );
			
			// Check if upload directory path is valid
			if ( $upload_basedir === false ) {
				continue; // Skip if upload directory cannot be resolved
			}
			
			$file_path = realpath( $path );
			
			// Check if file path is valid and within upload directory
			if ( $file_path === false ) {
				continue; // Skip if path cannot be resolved
			}
			
			// Prevent path traversal attacks by ensuring file is within upload directory
			// Use strict comparison and check that upload_basedir is not empty
			if ( empty( $upload_basedir ) || strpos( $file_path, $upload_basedir ) !== 0 ) {
				continue; // Skip if path is outside upload directory
			}
			
			// Verify file exists and is readable
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				continue; // Skip if file doesn't exist or is not readable
			}
			
			// Verify it's actually a file (not a directory)
			if ( ! is_file( $file_path ) ) {
				continue; // Skip if not a regular file
			}
			
			// All checks passed - add to attachments
			// Check for duplicates to avoid adding the same file multiple times
			if ( ! in_array( $file_path, $notification['attachments'], true ) ) {
				$notification['attachments'][] = $file_path;
			}
		}
	}
	
	return $notification;
}