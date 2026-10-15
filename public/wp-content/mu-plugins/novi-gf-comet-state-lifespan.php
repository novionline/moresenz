<?php
/**
 * Plugin Name: Novi GF Comet state lifespan
 * Description: Aligns Gravity Forms state and AJAX nonce lifespan with Comet Cache max age so cached form HTML stays valid for the full page-cache window.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Comet Cache max age in seconds, plus one day of buffer.
 *
 * @return int
 */
function novi_gf_comet_aligned_lifespan() {
	static $lifespan = null;

	if ( null !== $lifespan ) {
		return $lifespan;
	}

	$opts = is_multisite() ? get_site_option( 'comet_cache_options' ) : get_option( 'comet_cache_options' );
	$age  = ( is_array( $opts ) && ! empty( $opts['cache_max_age'] ) ) ? $opts['cache_max_age'] : '28 days';
	$cut  = strtotime( '-' . $age );

	$lifespan = is_int( $cut )
		? ( time() - $cut ) + DAY_IN_SECONDS
		: 29 * DAY_IN_SECONDS;

	return $lifespan;
}

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		add_filter(
			'gform_state_lifespan',
			static function ( $lifespan, $form_id ) {
				return novi_gf_comet_aligned_lifespan();
			},
			10,
			2
		);

		add_filter(
			'gform_nonce_life',
			static function ( $lifespan, $action = '' ) {
				return novi_gf_comet_aligned_lifespan();
			},
			10,
			2
		);
	}
);
