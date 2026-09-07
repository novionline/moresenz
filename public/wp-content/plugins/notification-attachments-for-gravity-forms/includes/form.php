<?php
/**
 * Form inside Gravity Forms Notification setting structure and function.
 *
 * @package Notification_Attachments_For_Gravity_Forms
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add attachment selector field to Gravity Forms notification settings.
 *
 * Code for form inside Gravity Forms Notification setting
 * (edited for Gravity Forms 2.5 -> https://docs.gravityforms.com/gform_notification_settings_fields/).
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @param   array $fields       Array of notification setting fields.
 * @param   array $notification Current notification settings.
 * @param   array $form         Current form settings.
 * @return  array Modified fields array with attachment selector added.
 */
function gf_kgm_notification_attachment_editor( $fields, $notification, $form ) {
	/* Security check: verify user has permission to edit Gravity Forms notifications.
	   Both capabilities below are real ones, declared in GFCommon::all_caps(); it is
	   'gravityforms_edit_forms' that actually gates the notification screen, since GF
	   registers the Forms submenu with it. GF's settings renderer sets no per-page
	   capability of its own, so this check is a real gate, not a duplicate of GF's. */
	if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_create_form' ) ) ) {
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
	} else {
		// Use saved notification data
		$attachment_ids_raw = sanitize_text_field( rgar( $notification, 'attachment_id' ) );
	}

	/* Accept digits and commas only, the same whitelist save.php and send.php enforce.
	   The value is escaped on output regardless, so this is defence in depth: it stops the
	   three code paths from each deciding on their own what a valid value looks like. */
	if ( '' !== $attachment_ids_raw && ! preg_match( '/^[\d,]+$/', $attachment_ids_raw ) ) {
		$attachment_ids_raw = '';
	}

	$attachment_ids = explode( ',', $attachment_ids_raw );

	$attachments = '';
	if ( $attachment_ids && is_array( $attachment_ids ) ) {
		$attachments .= '<div id="gf_kgm_notification_attachment_li"><ul class="details" aria-label="' . esc_attr__( 'Selected attachments', 'notification-attachments-for-gravity-forms' ) . '" aria-live="polite" aria-atomic="false">';
		// Check if array is not empty and first element is not empty
		if ( ! empty( $attachment_ids ) && ! empty( $attachment_ids[0] ) ) {
			// Batch-prime the post and postmeta cache to avoid N+1 queries inside the loop
			$sanitized_ids = array_filter( array_map( 'absint', $attachment_ids ) );
			if ( ! empty( $sanitized_ids ) ) {
				get_posts( array(
					'post__in'               => $sanitized_ids,
					'post_type'              => 'attachment',
					'posts_per_page'         => -1,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				) );
			}

			foreach ( $attachment_ids as $attachment_id ) {
				// Sanitize attachment ID before use
				$attachment_id = absint( $attachment_id );
				if ( empty( $attachment_id ) ) {
					continue; // Skip invalid attachment IDs
				}
				
				$attachment = gf_kgm_notification_attachment_get_meta( $attachment_id );
				
				// Skip if attachment doesn't exist
				if ( empty( $attachment ) || empty( $attachment->mime_file ) ) {
					continue;
				}
				
				$attachments .= '<li data-id="' . esc_attr( $attachment_id ) . '">'
					. '<img src="' . esc_url( $attachment->mime_file ) . '" alt="" style="max-width:150px;" /><br />'
					. esc_html( $attachment->title ) . ' <b>[' . esc_html( $attachment->mime ) . ']</b>'
					. '<button type="button" class="remove gf-kgm-remove-attachment" style="background:none;border:none;padding:0;cursor:pointer;" aria-label="' . esc_attr( sprintf(
					/* translators: %s: attachment file name */
					__( 'Remove %s', 'notification-attachments-for-gravity-forms' ),
					$attachment->title
				) ) . '">'
					. '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>'
					. '</button></li>';
			}
		}
		$attachments .= '</ul></div>';
	}

	/* $attachment_ids_raw is sanitized on both branches above and then narrowed to the
	   digits-and-commas whitelist, so by here it is either '' or a safe list of IDs. */
	$attachments .= '<div id="gf_kgm_notification_attachment_input"><input type="hidden" name="gf_kgm_notification_attachment_id" id="attachment_ids" value="' . esc_attr( $attachment_ids_raw ) . '" />'
		. '<button type="button" class="button add gf_kgm_notification_attachment gf-kgm-add-attachment">'
		. esc_html__( 'Add Attachment', 'notification-attachments-for-gravity-forms' )
		. '</button></div>';

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
 * Retrieve attachment metadata for form display.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @param   int $attachment_id Attachment ID from database.
 * @return  object|null Attachment metadata object with id, mime_file, mime, and title, or null if not found.
 */
function gf_kgm_notification_attachment_get_meta( $attachment_id ) {
	// Sanitize attachment ID
	$attachment_id = absint( $attachment_id );
	
	// Return empty if invalid ID
	if ( empty( $attachment_id ) ) {
		return null;
	}

	$attachment = get_post( $attachment_id );

	// Return null if attachment doesn't exist
	if ( empty( $attachment ) || is_wp_error( $attachment ) ) {
		return null;
	}

	$image = wp_get_attachment_image_src( $attachment_id, array( 150, 150 ), true );
	$image = ! empty( $image ) ? $image[0] : null;

	if ( is_null( $image ) && ! empty( $attachment->post_mime_type ) ) {
		// Static cache to avoid repeated filesystem icon-directory scans for the same MIME type
		static $mime_icon_cache = array();
		if ( ! isset( $mime_icon_cache[ $attachment->post_mime_type ] ) ) {
			$mime_icon_cache[ $attachment->post_mime_type ] = wp_mime_type_icon( $attachment->post_mime_type );
		}
		$image = $mime_icon_cache[ $attachment->post_mime_type ];
	}

	return (object) apply_filters(
		'gf_kgm_notification_attachment_get_meta',
		array(
			'id'        => $attachment_id,
			'mime_file' => $image,
			'mime'      => $attachment->post_mime_type,
			'title'     => $attachment->post_title,
		),
		$attachment_id,
		$attachment
	);
}