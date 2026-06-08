<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * Class AdminColorComponent
 * Registers and forces the "Novi" WP admin color scheme.
 * The scheme stylesheet lives at assets/admin-color-novi.css and is a copy
 * of WP's "modern" scheme with the blue hex values swapped for Novi purple.
 * When NectarBlocks accentPrimary is set, a recolored copy is cached in uploads.
 *
 * Maintenance: when WP updates the modern scheme CSS, re-copy
 * wp-admin/css/colors/modern/colors.css, re-apply the color substitutions,
 * and re-prepend the body.admin-color-novi variable block.
 */
class AdminColorComponent extends Singleton
{
    /**
     * Slug used to register and force the admin color scheme
     */
    private const SCHEME_SLUG = 'novi';

    /**
     * Relative path to the scheme stylesheet inside the plugin
     */
    private const SCHEME_FILE = 'assets/admin-color-novi.css';

    /**
     * Novi purple fallback when NB accentPrimary is missing or invalid
     */
    private const NOVI_PURPLE = '#945ef0';

    /**
     * Default purple palette tokens baked into the static scheme CSS
     */
    private const PURPLE_TOKENS = [
        'base' => '#945ef0',
        'lighter' => '#9c6af1',
        'darker10' => '#7c41ed',
        'darker20' => '#6325e0',
        'baseRgb' => '148, 94, 240',
        'darker10Rgb' => '124, 65, 237',
        'darker20Rgb' => '99, 37, 224',
    ];

    /**
     * AdminColorComponent constructor.
     */
    protected function __construct()
    {
        //register the Novi scheme when WP builds its color scheme list
        add_action('admin_init', [$this, 'registerScheme']);

        //force the scheme for every user (per request, never persisted)
        add_filter('get_user_option_admin_color', [$this, 'forceScheme']);

        //hide the color scheme picker from the user profile screen
        add_action('admin_init', [$this, 'removePicker']);

        //replace the default WordPress admin footer text with Novi branding
        add_filter('admin_footer_text', static function () {
            $text = sprintf(
                __('Powered by %1s 🚀', Core::TEXT_DOMAIN),
                '<a href="https://novionline.nl" target="_blank">Novi Online</a>'
            );

            //allow themes to override the admin footer branding text
            return apply_filters('novi_admin_footer_text', $text);
        });

        //replace the WP logo in the admin bar with the site favicon
        add_action('admin_head', [$this, 'setAdminBranding']);

        //recolor third-party admin charts (e.g. Redis Object Cache) to the active accent
        add_action('admin_enqueue_scripts', [$this, 'enqueueChartAccent'], 100);
    }

    /**
     * Register the Novi scheme with WP.
     * The 4 colors define the swatch preview shown in the picker (now hidden).
     *
     * @return void
     */
    public function registerScheme(): void
    {
        $schemeUrl = $this->getActiveSchemeUrl();
        $palette = $this->getActivePalette();

        wp_admin_css_color(
            self::SCHEME_SLUG,
            esc_html__('Novi', Core::TEXT_DOMAIN),
            $schemeUrl,
            ['#1e1e1e', '#160237', $palette['base'], $palette['lighter']],
            ['base' => '#a7aaad', 'focus' => '#fff', 'current' => '#fff']
        );
    }

    /**
     * Force the Novi scheme for every user, regardless of stored preference.
     *
     * @param mixed $color The currently stored admin color slug.
     * @return string
     */
    public function forceScheme($color): string
    {
        return self::SCHEME_SLUG;
    }

    /**
     * Remove the color scheme picker from the user profile screen.
     * Users can't change away from Novi.
     *
     * @return void
     */
    public function removePicker(): void
    {
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    }

    /**
     * Set favicon instead of WP icon in admin for branding purposes.
     *
     * @return void
     */
    public function setAdminBranding(): void
    {
        //allow themes to override the favicon used as the admin bar logo
        $favicon = apply_filters('novi_admin_bar_favicon_url', get_site_icon_url(32));
        if (!$favicon) return;
        ?>
        <style>
            #wpadminbar > #wp-toolbar > #wp-admin-bar-root-default > #wp-admin-bar-wp-logo .ab-icon {
                background-image: url(<?php echo esc_url($favicon); ?>) !important;
                margin: 4px;
                width: 24px;
                height: 24px;
                background-size: contain;
                background-repeat: no-repeat;
            }

            #wpadminbar > #wp-toolbar > #wp-admin-bar-root-default > #wp-admin-bar-wp-logo .ab-icon:before {
                display: none;
            }
        </style>
        <?php
    }

    /**
     * Recolors third-party admin charts to the active accent.
     *
     * Some plugins (e.g. Redis Object Cache) render ApexCharts with a hardcoded
     * WP-blue line color that ignores the admin color scheme. We attach a small
     * inline script after their handle (before DOM ready, so it runs before the
     * chart renders) that overrides the accent. Scoped automatically because the
     * inline script only prints on screens where that handle is enqueued.
     *
     * @return void
     */
    public function enqueueChartAccent(): void
    {
        $accent = $this->getActiveAccent();

        //allow themes to override the accent used for admin charts specifically
        $accent = apply_filters('novi_admin_chart_accent', $accent);

        if (!is_string($accent) || !self::isValidHex($accent)) return;
        $accent = self::normalizeHex($accent);

        //Redis Object Cache: patch its ApexCharts color defaults + per-chart configs
        if (wp_script_is('redis-cache', 'enqueued') || wp_script_is('redis-cache', 'registered')) {
            $js = sprintf(
                '(function(){'
                . 'var accent=%s;'
                . 'window.Apex=window.Apex||{};if(!window.Apex.colors){window.Apex.colors=[accent];}'
                . 'if(!window.rediscache){return;}'
                . 'if(rediscache.chart_defaults&&Array.isArray(rediscache.chart_defaults.colors)){rediscache.chart_defaults.colors[0]=accent;}'
                . 'if(rediscache.charts){Object.keys(rediscache.charts).forEach(function(k){var c=rediscache.charts[k];if(c&&Array.isArray(c.colors)){c.colors[0]=accent;}});}'
                . '})();',
                wp_json_encode($accent)
            );

            wp_add_inline_script('redis-cache', $js, 'after');
        }
    }

    /**
     * Returns the active accent color: NB accentPrimary or Novi purple fallback.
     *
     * @return string Normalized hex (e.g. #1c9260)
     */
    private function getActiveAccent(): string
    {
        $nbColors = AdminBarLightComponent::getNectarGlobalColors();
        $accent = $nbColors['accentPrimary'] ?? '';

        $accent = is_string($accent) && self::isValidHex($accent)
            ? self::normalizeHex($accent)
            : self::NOVI_PURPLE;

        //allow themes to override the admin color scheme accent (hex)
        $accent = apply_filters('novi_admin_color_accent', $accent);

        //re-validate the filtered value so an invalid override never breaks the scheme
        return is_string($accent) && self::isValidHex($accent) ? self::normalizeHex($accent) : self::NOVI_PURPLE;
    }

    /**
     * Returns the palette derived from the active accent (HSL shades).
     *
     * @return array{base: string, lighter: string, darker10: string, darker20: string, baseRgb: string, darker10Rgb: string, darker20Rgb: string}
     */
    private function getActivePalette(): array
    {
        $base = $this->getActiveAccent();

        $palette = [
            'base' => $base,
            'lighter' => self::adjustLightness($base, 8),
            'darker10' => self::adjustLightness($base, -10),
            'darker20' => self::adjustLightness($base, -20),
            'baseRgb' => self::hexToRgbString($base),
            'darker10Rgb' => self::hexToRgbString(self::adjustLightness($base, -10)),
            'darker20Rgb' => self::hexToRgbString(self::adjustLightness($base, -20)),
        ];

        //allow themes to fine-tune the derived shades (base, lighter, darker10, darker20 + their rgb strings)
        return apply_filters('novi_admin_color_palette', $palette, $base);
    }

    /**
     * Returns the URL of the admin scheme stylesheet (static or recolored cache).
     *
     * @return string
     */
    private function getActiveSchemeUrl(): string
    {
        $staticFile = WCP_PLUGIN_PATH . '/' . self::SCHEME_FILE;
        $accent = $this->getActiveAccent();

        //use the baked static file when accent is the Novi purple fallback
        if (strcasecmp($accent, self::NOVI_PURPLE) === 0) {
            return $this->getStaticSchemeUrl($staticFile);
        }

        if (!file_exists($staticFile) || !is_readable($staticFile)) {
            return $this->getStaticSchemeUrl($staticFile);
        }

        $palette = $this->getActivePalette();
        //include the palette in the hash so a filtered palette regenerates the cache too
        $hash = md5($accent . wp_json_encode($palette) . (string) filemtime($staticFile));
        $uploadDir = wp_upload_dir();

        if (!empty($uploadDir['error'])) {
            return $this->getStaticSchemeUrl($staticFile);
        }

        $cacheDir = trailingslashit($uploadDir['basedir']) . 'novi-admin-color';
        $cacheFile = $cacheDir . '/admin-color-novi-' . $hash . '.css';
        $cacheUrl = trailingslashit($uploadDir['baseurl']) . 'novi-admin-color/admin-color-novi-' . $hash . '.css';

        if (!file_exists($cacheFile)) {
            $baseCss = file_get_contents($staticFile);
            if ($baseCss === false) {
                return $this->getStaticSchemeUrl($staticFile);
            }

            $recoloredCss = str_replace(
                [
                    self::PURPLE_TOKENS['base'],
                    self::PURPLE_TOKENS['lighter'],
                    self::PURPLE_TOKENS['darker10'],
                    self::PURPLE_TOKENS['darker20'],
                    self::PURPLE_TOKENS['baseRgb'],
                    self::PURPLE_TOKENS['darker10Rgb'],
                    self::PURPLE_TOKENS['darker20Rgb'],
                    'rgb(' . self::PURPLE_TOKENS['darker20Rgb'] . ')',
                ],
                [
                    $palette['base'],
                    $palette['lighter'],
                    $palette['darker10'],
                    $palette['darker20'],
                    $palette['baseRgb'],
                    $palette['darker10Rgb'],
                    $palette['darker20Rgb'],
                    'rgb(' . $palette['darker20Rgb'] . ')',
                ],
                $baseCss
            );

            if (!wp_mkdir_p($cacheDir)) {
                return $this->getStaticSchemeUrl($staticFile);
            }

            //prune stale cache files when writing a new accent variant
            $this->pruneSchemeCache($cacheDir, $hash);

            if (file_put_contents($cacheFile, $recoloredCss) === false) {
                return $this->getStaticSchemeUrl($staticFile);
            }
        }

        $version = file_exists($cacheFile) ? (string) filemtime($cacheFile) : (string) Core::PLUGIN_VERSION;

        return $cacheUrl . '?ver=' . $version;
    }

    /**
     * Returns the static scheme stylesheet URL with cache-busting version.
     *
     * @param string $staticFile
     * @return string
     */
    private function getStaticSchemeUrl(string $staticFile): string
    {
        $version = file_exists($staticFile) ? (string) filemtime($staticFile) : (string) Core::PLUGIN_VERSION;

        return trailingslashit(WCP_PLUGIN_URL) . self::SCHEME_FILE . '?ver=' . $version;
    }

    /**
     * Removes older cached scheme files, keeping the current hash.
     *
     * @param string $cacheDir
     * @param string $currentHash
     * @return void
     */
    private function pruneSchemeCache(string $cacheDir, string $currentHash): void
    {
        $pattern = $cacheDir . '/admin-color-novi-*.css';
        $files = glob($pattern);

        if (!is_array($files)) return;

        foreach ($files as $file) {
            if (!is_string($file) || !is_file($file)) continue;
            if (str_contains($file, $currentHash)) continue;
            @unlink($file);
        }
    }

    /**
     * @param string $hex
     * @return bool
     */
    private static function isValidHex(string $hex): bool
    {
        return (bool) preg_match('/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $hex);
    }

    /**
     * @param string $hex
     * @return string
     */
    private static function normalizeHex(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . strtolower($hex);
    }

    /**
     * @param string $hex
     * @return array{0: float, 1: float, 2: float} hue, saturation, lightness (0-100)
     */
    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim(self::normalizeHex($hex), '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        if ($max === $r) {
            $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
        } elseif ($max === $g) {
            $h = ($b - $r) / $d + 2;
        } else {
            $h = ($r - $g) / $d + 4;
        }

        $h *= 60;

        return [$h, $s * 100, $l * 100];
    }

    /**
     * @param float $h
     * @param float $s
     * @param float $l
     * @return string
     */
    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360);
        if ($h < 0) $h += 360;

        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        if ($s === 0.0) {
            $r = $g = $b = (int) round($l * 255);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToRgb($p, $q, $h + 120);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 120);

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    /**
     * @param float $p
     * @param float $q
     * @param float $t
     * @return float
     */
    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 360;
        if ($t >= 360) $t -= 360;

        if ($t < 60) return $p + ($q - $p) * $t / 60;
        if ($t < 180) return $q;
        if ($t < 240) return $p + ($q - $p) * (240 - $t) / 60;

        return $p;
    }

    /**
     * Adjusts the lightness of a hex color by the given percentage points.
     *
     * @param string $hex
     * @param float $lightnessDelta e.g. -10, -20, +8
     * @return string
     */
    private static function adjustLightness(string $hex, float $lightnessDelta): string
    {
        [$h, $s, $l] = self::hexToHsl($hex);
        $l = max(0, min(100, $l + $lightnessDelta));

        return self::normalizeHex(self::hslToHex($h, $s, $l));
    }

    /**
     * @param string $hex
     * @return string e.g. "148, 94, 240"
     */
    private static function hexToRgbString(string $hex): string
    {
        $hex = ltrim(self::normalizeHex($hex), '#');

        return sprintf(
            '%d, %d, %d',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }
}
