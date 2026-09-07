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
		var mediaframes = wp.media.frames.items = wp.media({
			'multiple': true
		});
		mediaframes.on('select', function() {
			var attachment = mediaframes.state().get('selection').toJSON();			
			for(var i = 0; i < mediaframes.state().get('selection').length; i++)
			{
				// Validate attachment ID is a number to prevent XSS
				var attachmentId = parseInt(attachment[i].id, 10);
				if (isNaN(attachmentId) || attachmentId <= 0) {
					continue; // Skip invalid attachment IDs
				}

				var currentIDS = $('#attachment_ids').val();

				if (currentIDS == '') {
					currentIDS = currentIDS + attachmentId;				   
				} else {
					currentIDS = currentIDS + ',' + attachmentId;
				}

				// Get image URL safely
				var url_image = '';
				if (attachment[i].sizes) {
					if (attachment[i].sizes.thumbnail !== undefined) {
						url_image = attachment[i].sizes.thumbnail.url; 
					} else if (attachment[i].sizes.medium !== undefined) {
						url_image = attachment[i].sizes.medium.url;
					} else if (attachment[i].sizes.full !== undefined) {
						url_image = attachment[i].sizes.full.url;
					}
				} else {
					url_image = attachment[i].icon || '';
				}

				// Create DOM elements safely to prevent XSS attacks
				var $li = $('<li>').attr('data-id', attachmentId);
				
				var $img = $('<img>')
					.attr('src', url_image)
					.css('max-width', '150px');
				
				var $title = $('<span>').text(attachment[i].title || '');
				
				var $mime = $('<b>').text('[' + (attachment[i].mime || '') + ']');
				
				// Use class for event delegation instead of inline event handler
				var $removeBtn = $('<div>')
					.addClass('remove dashicons dashicons-dismiss gf-kgm-remove-attachment');
				
				// Build the structure safely
				$li.append($img);
				$li.append($('<br>'));
				$li.append($title);
				$li.append(' ');
				$li.append($mime);
				$li.append($removeBtn);
				
				$('#attachment_ids').val(currentIDS);
				$('.details').append($li);
			}				
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
		$('.details li').each(function(){
			var attachmentId = parseInt($(this).data('id'), 10);
			// Only add valid numeric IDs to prevent XSS
			if (!isNaN(attachmentId) && attachmentId > 0) {
				attachmentIds.push(attachmentId);
			}
		});
		
		// Join valid IDs with comma
		var currentIDS = attachmentIds.join(',');
		$('#attachment_ids').val(currentIDS);
	}

})(jQuery);