/**
 * NectarBlocks outdated WooCommerce templates notice
 *
 * @package NectarBlocks
 * @author NectarBlocks
 */
 /* global jQuery */
 /* global notice_params */

 (function($) {

	 "use strict";

	 jQuery( document ).ready( function() {

		 jQuery( document ).on( 'click', '.nectar-dismiss-notice .notice-dismiss', function() {

			 var data = {
				 action: 'nectar_dismiss_older_woo_templates_notice',
				 nonce: notice_params.nonce,
			 };

			 jQuery.post( notice_params.ajaxurl, data, function() {
			 });

		 });

	 });

 })(jQuery);
