<?php
/**
 * Save in registration functions.
 *
 * @package Notification_Attachments_For_Gravity_Forms
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Save attachment ID in Gravity Forms notification settings.
 *
 * Validates and sanitizes attachment IDs to prevent security issues.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @param   array $notification Notification settings array.
 * @param   array $form         Form settings array.
 * @return  array Modified notification array with validated attachment_id.
 */
function gf_kgm_notification_attachment_save( $notification, $form ) {
	/* Security check: verify user has permission to edit Gravity Forms notifications.
	   Both capabilities below are real ones, declared in GFCommon::all_caps(). GF's own
	   settings renderer sets no per-page capability, so this is a real gate on top of the
	   nonce GF checks below, not a duplicate of a check GF already performs. */
	if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_create_form' ) ) ) {
		return $notification;
	}

	/* Nonce verification is handled by Gravity Forms: this filter fires inside GF's own save
	   routine, and GF_Settings::process_postback() calls
	   check_admin_referer( 'gform_settings_save', 'gform_settings_save_nonce' ) before it ever
	   reaches the save callback that applies gform_pre_notification_save. Verified against the
	   GF source, not assumed. */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gravity Forms handles nonce verification
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
	
	// Split by comma, sanitize, and validate all IDs in a single query
	$attachment_ids = explode( ',', $attachment_id_raw );
	$candidates = array_filter( array_map( 'absint', $attachment_ids ) );

	if ( empty( $candidates ) ) {
		$notification['attachment_id'] = '';
		return $notification;
	}

	// Single query to validate all attachment IDs at once (replaces N individual get_post_type calls).
	// 'orderby' => 'post__in' preserves the original order of IDs for backward compatibility.
	$valid_ids = get_posts( array(
		'post__in'               => $candidates,
		'post_type'              => 'attachment',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'orderby'                => 'post__in',
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	) );

	// Rejoin valid IDs
	$notification['attachment_id'] = implode( ',', $valid_ids );
	
	return $notification;
}