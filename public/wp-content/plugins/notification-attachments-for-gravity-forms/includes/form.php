<?php
/*
Form inside Gravity Forms Notification setting structure and function
Plugin: Notification attachment_ids for Gravity Forms
Since: 0.1
Author: KGM Servizi
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add attachment selector field to Gravity Forms notification settings
 * 
 * Code for form inside Gravity Forms Notification setting (edited for Gravity Forms 2.5 -> https://docs.gravityforms.com/gform_notification_settings_fields/)
 * 
 * @param array  $fields       Array of notification setting fields
 * @param array  $notification Current notification settings
 * @param array  $form         Current form settings
 * @return array Modified fields array with attachment selector added
 */
function gf_kgm_notification_attachment_editor( $fields, $notification, $form ) {
	// Security check: verify user has permission to edit Gravity Forms notifications
	if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_create_form', 'gravityforms_notification_settings' ) ) ) {
		return $fields; // Return unchanged if user doesn't have permission
	}
	
	// Security check: verify we're in admin context
	if ( ! is_admin() ) {
		return $fields; // Return unchanged if not in admin
	}
	
	// Get attachment IDs from POST (if submitted) or from saved notification data
	// Note: Nonce verification is handled by Gravity Forms since this is integrated in their form
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gravity Forms handles nonce verification for the entire form
	if ( isset( $_POST['gf_kgm_notification_attachment_id'] ) ) {
		// Unslash and sanitize input before processing (WordPress Coding Standards requirement)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gravity Forms handles nonce verification for the entire form
		$attachment_ids_raw = sanitize_text_field( wp_unslash( $_POST['gf_kgm_notification_attachment_id'] ) );
		$attachment_ids = explode( ',', $attachment_ids_raw );
	} else {
		// Use saved notification data
		$attachment_ids_raw = sanitize_text_field( rgar( $notification, 'attachment_id' ) );
		$attachment_ids = explode( ',', $attachment_ids_raw );
	}

	if ( !is_array( $attachment_ids ) && !empty( $attachment_ids ) ) {
		$attachment_ids = array($attachment_ids);
	}

	$attachments = '';
	if ( $attachment_ids && is_array( $attachment_ids ) ) {
		$attachments .= '<div id="gf_kgm_notification_attachment_li"><ul class="details">';
		// Check if array is not empty and first element is not empty
		if ( ! empty( $attachment_ids ) && ! empty( $attachment_ids[0] ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				// Sanitize attachment ID before use
				$attachment_id = absint( $attachment_id );
				if ( empty( $attachment_id ) ) {
					continue; // Skip invalid attachment IDs
				}
				
				$attachment = gf_kgm_notification_attachment_get_meta($attachment_id);
				
				// Skip if attachment doesn't exist
				if ( empty( $attachment ) || empty( $attachment->mime_file ) ) {
					continue;
				}
				
				$attachments .= '<li data-id="' . esc_attr( $attachment_id ) . '"><img src="' . esc_url( $attachment->mime_file ) . '" style="max-width:150px;" /><br />' . esc_html( $attachment->title ) . ' <b>[' . esc_html( $attachment->mime ) . ']</b><div class="remove dashicons dashicons-dismiss gf-kgm-remove-attachment"></div></li>';
			}
		}
		$attachments .= '</ul></div>';
	}

	$attachments .= '<div id="gf_kgm_notification_attachment_input"><input type="hidden" name="gf_kgm_notification_attachment_id" id="attachment_ids" value="' . esc_attr( $attachment_ids_raw ) . '" />
            	<a href="#" class="button add gf_kgm_notification_attachment gf-kgm-add-attachment" title="' . esc_attr__( 'Add Attachment', 'notification-attachments-for-gravity-forms' ) . '">
            		' . esc_html__( 'Add Attachment', 'notification-attachments-for-gravity-forms' ) . '</a></div>';

	$fields[] = array(
		'title'  => esc_html__( 'Attachments', 'notification-attachments-for-gravity-forms' ),
		'fields' => array(
			array(
				'name' => 'Attachments',
				'type' => 'html',
				'html' => $attachments,
			),
		),
	);

	return $fields;
}

/**
 * Retrieve attachment metadata for form display
 * 
 * @param int $attachment_id Attachment ID from database
 * @return object|null Attachment metadata object with id, mime_file, mime, and title, or null if not found
 */
function gf_kgm_notification_attachment_get_meta( $attachment_id ) {
	// Sanitize attachment ID
	$attachment_id = absint( $attachment_id );
	
	// Return empty if invalid ID
	if ( empty( $attachment_id ) ) {
		return null;
	}

	$attachment = get_post($attachment_id);
	
	// Return null if attachment doesn't exist
	if ( empty( $attachment ) || is_wp_error( $attachment ) ) {
		return null;
	}
	
	$image = wp_get_attachment_image_src($attachment_id, array(150,150), true); 
	$image = !empty($image) ? $image[0] : null;

	if ( is_null( $image ) && !empty( $attachment->post_mime_type ) ) {
		$image = wp_mime_type_icon($attachment->post_mime_type);
	}

	return (object) apply_filters('gf_kgm_notification_attachment_get_meta', array(
			'id'        => $attachment_id,
			'mime_file' => $image,
			'mime'      => $attachment->post_mime_type,
			'title'     => $attachment->post_title
		), $attachment_id, $attachment);
}