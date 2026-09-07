<?php
/**
 * Enqueue style and script.
 *
 * @package Notification_Attachments_For_Gravity_Forms
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Enqueue script for notification editor.
 *
 * Checks for global variable existence to prevent fatal errors.
 *
 * @since   0.1
 * @package Notification_Attachments_For_Gravity_Forms
 * @return  void
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
		if ( 'notification_edit' === GFForms::get_page() ) {
			$script_url = $plugin->plugin_url . 'assets/script.js';

			/* The Add Attachment button opens the WordPress media modal, so wp.media has to be
			   on the page. Gravity Forms calls wp_enqueue_media() only on the form editor screen,
			   never on this one: here wp.media is present only as a side effect of the notification
			   message field rendering a wp_editor() with media buttons. Enqueue it ourselves so the
			   button does not silently die if that side effect ever goes away. */
			wp_enqueue_media();

			/* jquery is declared explicitly even though gform_gravityforms already depends on it.
			   GF 3.0 removed jQuery from several of its libraries, and this script uses jQuery
			   directly, so the dependency belongs here rather than being inherited by luck. */
			wp_enqueue_script( $plugin->text_domain, $script_url, array( 'jquery', 'gform_gravityforms' ), $plugin->version, true );

			// Localize strings for accessible JS UI (remove button label, media modal title)
			wp_localize_script( $plugin->text_domain, 'gf_kgm_i18n', array(
				/* translators: %s: attachment file name */
				'removeAttachment' => __( 'Remove %s', 'notification-attachments-for-gravity-forms' ),
				'mediaTitle'       => __( 'Select Notification Attachments', 'notification-attachments-for-gravity-forms' ),
				'mediaButton'      => __( 'Attach', 'notification-attachments-for-gravity-forms' ),
			) );
		}
	}
}