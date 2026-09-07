/*
Media Selector
Plugin: Notification Attachments for Gravity Forms
Since: 0.1 
Author: KGM Servizi
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

(function($) {
	'use strict';

	// Scoped to our own container: '.details' on its own is generic enough to match another
	// element on the notification screen, which would append attachments to the wrong list.
	var LIST_SELECTOR = '#gf_kgm_notification_attachment_li .details';

	// Initialize when DOM is ready
	$(document).ready(function() {
		// Event listener for "Add Attachment" button (WordPress best practice: no inline onClick)
		$(document).on('click', '.gf-kgm-add-attachment', function(e) {
			e.preventDefault();
			kgm_add_attachment();
		});

		// Event delegation for remove buttons (works with dynamically added elements)
		$(document).on('click', '.gf-kgm-remove-attachment', function(e) {
			e.preventDefault();
			kgm_remove_attachment(this);
		});
	});

	/**
	 * Open WordPress media library to select attachments
	 */
	function kgm_add_attachment() {
		var mediaTitle = (typeof gf_kgm_i18n !== 'undefined' && gf_kgm_i18n.mediaTitle)
			? gf_kgm_i18n.mediaTitle
			: 'Select Notification Attachments';
		var mediaButton = (typeof gf_kgm_i18n !== 'undefined' && gf_kgm_i18n.mediaButton)
			? gf_kgm_i18n.mediaButton
			: 'Attach';

		var mediaframes = wp.media.frames.items = wp.media( {
			title:    mediaTitle,
			button:   { text: mediaButton },
			multiple: true
		} );
		mediaframes.on('select', function() {
			var attachment = mediaframes.state().get('selection').toJSON();
			var currentIDS = $('#attachment_ids').val();

			for ( var i = 0; i < attachment.length; i++ ) {
				// Validate attachment ID is a number to prevent XSS
				var attachmentId = parseInt( attachment[i].id, 10 );
				if ( isNaN( attachmentId ) || attachmentId <= 0 ) {
					continue; // Skip invalid attachment IDs
				}

				if ( '' === currentIDS ) {
					currentIDS = String(attachmentId);
				} else {
					currentIDS = currentIDS + ',' + attachmentId;
				}

				// Get image URL safely
				var url_image = '';
				if ( attachment[i].sizes ) {
					if ( attachment[i].sizes.thumbnail !== undefined ) {
						url_image = attachment[i].sizes.thumbnail.url;
					} else if ( attachment[i].sizes.medium !== undefined ) {
						url_image = attachment[i].sizes.medium.url;
					} else if ( attachment[i].sizes.full !== undefined ) {
						url_image = attachment[i].sizes.full.url;
					}
				} else {
					url_image = attachment[i].icon || '';
				}

				// Create DOM elements safely to prevent XSS attacks
				var $li = $('<li>').attr('data-id', attachmentId);
				
				var $img = $('<img>')
					.attr('src', url_image)
					.attr('alt', '')
					.css('max-width', '150px');

				var $title = $('<span>').text(attachment[i].title || '');

				var $mime = $('<b>').text('[' + (attachment[i].mime || '') + ']');

				// Accessible button with aria-label including attachment title (localized via wp_localize_script)
				var titleText = attachment[i].title || '';
				var removeLabel = (typeof gf_kgm_i18n !== 'undefined' && gf_kgm_i18n.removeAttachment)
					? gf_kgm_i18n.removeAttachment.replace('%s', titleText)
					: 'Remove ' + titleText;

				var $removeBtn = $('<button>')
					.attr('type', 'button')
					.attr('aria-label', removeLabel)
					.addClass('remove gf-kgm-remove-attachment')
					.css({ background: 'none', border: 'none', padding: 0, cursor: 'pointer' });

				var $icon = $('<span>')
					.addClass('dashicons dashicons-dismiss')
					.attr('aria-hidden', 'true');

				$removeBtn.append($icon);

				// Build the structure safely
				$li.append($img);
				$li.append($('<br>'));
				$li.append($title);
				$li.append(' ');
				$li.append($mime);
				$li.append($removeBtn);
				
				$(LIST_SELECTOR).append($li);
			}

			// Write back once after the loop (avoids N redundant DOM writes)
			$('#attachment_ids').val(currentIDS);
		});
		mediaframes.open();
	}

	/**
	 * Remove attachment from list
	 * @param {HTMLElement} id - The remove button element
	 */
	function kgm_remove_attachment(id) {
		var $old = $(id).parent();
		$old.remove();

		// Rebuild attachment IDs list safely
		var attachmentIds = [];
		$(LIST_SELECTOR + ' li').each( function() {
			var attachmentId = parseInt( $( this ).data( 'id' ), 10 );
			// Only add valid numeric IDs to prevent XSS
			if ( ! isNaN( attachmentId ) && attachmentId > 0 ) {
				attachmentIds.push( attachmentId );
			}
		} );

		// Join valid IDs with comma
		var currentIDS = attachmentIds.join( ',' );
		$('#attachment_ids').val(currentIDS);
	}

})(jQuery);