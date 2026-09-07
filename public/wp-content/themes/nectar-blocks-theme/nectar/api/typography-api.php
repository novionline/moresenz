<?php

/**
 * Typography API (standalone fallback)
 *
 * The Customizer's typography control is compiled from the plugin's React
 * source (`@Components/typography-selector/*` via the tsconfig path aliases),
 * so its font list always comes from `nectar/v1/settings/typography/google_fonts`
 * — a route the *plugin* registers. The theme, however, is usable on its own:
 * `nectar-blocks-customizer.php` registers the typography panel unconditionally
 * and has an explicit `! defined('NECTAR_BLOCKS_VERSION')` branch for enqueueing
 * Google Fonts itself.
 *
 * Without the plugin that route 404s and the font family dropdown renders empty
 * with no explanation. This serves the theme's own bundled snapshot on the same
 * route so the control works either way. It only registers while the plugin is
 * absent, so the two can never both claim the route.
 *
 * @version 3.1.1
 * @since 3.1.1
 */
class Theme_Typography_API {
    const REST_NAMESPACE = 'nectar/v1';

    const REST_ROUTE = '/settings/typography/google_fonts';

    /**
     * Bundled Google Fonts snapshot, kept byte-identical to the plugin's copy by
     * `plugin/assets/scripts/google-fonts.py`.
     */
    const FONTS_FILE = '/nectar/customizer/components/typography/google_fonts.json';

    public function hooks() {
        add_action('rest_api_init', [$this, 'build_routes']);
    }

    /**
     * The plugin owns this route whenever it is active — it is registered there by
     * `Nectar\API\Router`. A second `register_rest_route()` for the same route
     * appends to the existing endpoint rather than replacing it, so bail out.
     *
     * Split into its own method as a test seam: `NECTAR_BLOCKS_VERSION` can't be
     * undefined once set, leaving one branch unreachable in a single process.
     */
    protected function plugin_is_active() {
        return defined('NECTAR_BLOCKS_VERSION');
    }

    protected function fonts_file_path() {
        return NECTAR_THEME_DIRECTORY . static::FONTS_FILE;
    }

    public function build_routes() {
        if ($this->plugin_is_active()) {
            return;
        }

        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
          'methods' => 'GET',
          'callback' => [$this, 'get_google_fonts'],
          // This list is only ever read by the Customizer control, so gate it on
          // the capability that gates the Customizer itself.
          'permission_callback' => function () {
              return current_user_can('edit_theme_options');
          }
        ]);
    }

    public function get_google_fonts() {
        $path = $this->fonts_file_path();

        if (! file_exists($path)) {
            return new \WP_Error(
                'nectar_google_fonts_missing',
                __('The bundled Google Fonts list is missing from the theme.', 'nectar-blocks-theme'),
                ['status' => 500]
            );
        }

        $fonts = wp_json_file_decode($path, ['associative' => true]);

        if (! is_array($fonts)) {
            return new \WP_Error(
                'nectar_google_fonts_unreadable',
                __('The bundled Google Fonts list could not be read.', 'nectar-blocks-theme'),
                ['status' => 500]
            );
        }

        return new \WP_REST_Response($fonts, 200);
    }
}
