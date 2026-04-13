<?php

namespace NoviOnline\Login;

use NoviOnline\Core\Partial;
use NoviOnline\Core\Singleton;

//bail if accessed directly
if (!defined('ABSPATH')) exit;

class NoviLogin extends Singleton {

    /**
     * NoviLogin constructor
     */
    protected function __construct() {

        //run only on the login screens
        add_action('login_init', function () {

            //prevent caching plugins from caching the login page
            self::preventCaching();

            //init CSS for login customization
            add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);

            //alter the HTML of the login screen
            add_action('login_header', [$this, 'renderLayoutStart']);
            add_action('login_footer', [$this, 'renderLayoutEnd']);

            //update logo and its URL/title to match the site (allow theme/plugin to take over via filter)
            if (apply_filters('novi_login_handle_logo', true)) {
                add_action('login_head', [$this, 'changeLoginLogo']);
                add_filter('login_headerurl', [$this, 'changeLoginLogoUrl']);
                add_filter('login_headertext', [$this, 'changeLoginLogoUrlTitle']);
            }
        }, 1);
    }

    /**
     * Prevent caching plugins from caching the login page
     * @return void
     */
    private static function preventCaching(): void {

        //prevent Comet Cache from caching
        if (!defined('COMET_CACHE_ALLOWED')) define('COMET_CACHE_ALLOWED', false);

        //prevent WP Super Cache from caching
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

        //prevent W3 Total Cache from caching
        if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
        if (!defined('DONOTMINIFY')) define('DONOTMINIFY', true);
        if (!defined('DONOTCDN')) define('DONOTCDN', true);

        //prevent WP Rocket from caching
        if (!defined('DONOTROCKETOPTIMIZE')) define('DONOTROCKETOPTIMIZE', true);

        //prevent Autoptimize from optimizing
        if (!defined('AUTOPTIMIZE_CACHE_NOGZIP')) define('AUTOPTIMIZE_CACHE_NOGZIP', true);

        //add filter to prevent Autoptimize from processing
        add_filter('autoptimize_filter_noptimize', '__return_true');

        //prevent LiteSpeed Cache from caching
        if (!defined('LSCACHE_BYPASS')) define('LSCACHE_BYPASS', true);
    }

    /**
     * Enqueue login CSS and fonts
     * @return void
     */
    public function enqueueLoginAssets(): void {

        if (!defined('NOVI_LOGIN_PLUGIN_URL') || !defined('NOVI_LOGIN_PLUGIN_PATH')) {
            return;
        }

        $loginCssUrl = NOVI_LOGIN_PLUGIN_URL . '/assets/css/login.css';
        $cssVersion = file_exists(NOVI_LOGIN_PLUGIN_PATH . 'assets/css/login.css') ? (string) filemtime(NOVI_LOGIN_PLUGIN_PATH . 'assets/css/login.css') : null;

        //load Sora font from Google Fonts
        wp_enqueue_style(
            'novi-login-fonts',
            'https://fonts.googleapis.com/css2?family=Sora:wght@200;400;600;700&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'novi-login',
            $loginCssUrl,
            ['novi-login-fonts'],
            $cssVersion
        );

        //material input and login behaviour (vanilla JS, no build)
        $materialInputPath = NOVI_LOGIN_PLUGIN_PATH . 'assets/js/MaterialInput.js';
        $loginJsPath = NOVI_LOGIN_PLUGIN_PATH . 'assets/js/login.js';
        $jsVersion = (file_exists($materialInputPath) && file_exists($loginJsPath))
            ? (string) max(filemtime($materialInputPath), filemtime($loginJsPath))
            : null;

        wp_enqueue_script(
            'novi-login-material-input',
            NOVI_LOGIN_PLUGIN_URL . '/assets/js/MaterialInput.js',
            [],
            $jsVersion,
            true
        );

        wp_enqueue_script(
            'novi-login',
            NOVI_LOGIN_PLUGIN_URL . '/assets/js/login.js',
            ['novi-login-material-input'],
            $jsVersion,
            true
        );
    }

    /**
     * Return login panel copy in the appropriate language based on WP locale
     * Dutch when locale is nl_*, English otherwise
     * @return array<string, string>
     */
    private function getLoginCopy(): array {

        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $isDutch = (strpos($locale, 'nl') === 0);

        if ($isDutch) {
            return [
                'heading' => 'Welkom terug! 👋🏻',
                'intro' => 'Alles staat voor je klaar. Tijd om verder te bouwen.',
                'supportTitle' => 'Hulp nodig of even overleggen?',
                'supportText' => 'We denken graag met je mee.',
                'supportMailLabel' => 'Mail ons',
                'supportTelLabel' => 'Bel ons',
                'supportWebsite' => 'https://www.novionline.nl',
                'supportWebsiteLabel' => 'Novi Online',
            ];
        }

        return [
            'heading' => 'Welcome back! 👋🏻',
            'intro' => 'Everything\'s ready. Let\'s continue building.',
            'supportTitle' => 'Need help or want to talk things through?',
            'supportText' => 'We’re here for you.',
            'supportMailLabel' => 'Email us',
            'supportTelLabel' => 'Call us',
            'supportWebsite' => 'https://www.novionline.nl/en/',
            'supportWebsiteLabel' => 'Novi Online',
        ];
    }

    /**
     * Open custom login wrapper and output left panel content
     * @return void
     */
    public function renderLayoutStart(): void {

        $supportImageUrl = defined('NOVI_LOGIN_PLUGIN_URL') ? NOVI_LOGIN_PLUGIN_URL . '/assets/images/philip-kamsteeg-square.png' : '';
        $copy = $this->getLoginCopy();

        $args = [
            'heading' => $copy['heading'],
            'intro' => $copy['intro'],
            'supportTitle' => $copy['supportTitle'],
            'supportText' => $copy['supportText'],
            'supportMailLabel' => $copy['supportMailLabel'],
            'supportTelLabel' => $copy['supportTelLabel'],
            'supportName' => 'Philip Kamsteeg',
            'supportRole' => 'Commercial lead & Co-founder',
            'supportMailHref' => 'mailto:philip@novionline.nl',
            'supportTelHref' => 'tel:+31855055540',
            'supportWebsite' => $copy['supportWebsite'],
            'supportWebsiteLabel' => $copy['supportWebsiteLabel'],
            'supportImageUrl' => $supportImageUrl,
            'supportImageAlt' => 'Philip Kamsteeg',
        ];

        Partial::render('login-wrapper-start', $args, true, NOVI_LOGIN_PARTIAL_PATH);
    }

    /**
     * Close custom login wrapper
     * @return void
     */
    public function renderLayoutEnd(): void {
        Partial::render('login-wrapper-end', [], true, NOVI_LOGIN_PARTIAL_PATH);
    }

    /**
     * Replace the default WordPress login logo (in #login h1 a)
     * with the NectarBlocks logo from the customizer.
     *
     * @return void
     */
    public function changeLoginLogo(): void {

        $useLogo = get_theme_mod('use-logo');
        $logo = get_theme_mod('logo');

        $logoUrl = '';
        $logoWidth = 0;
        $logoHeight = 0;

        if ($useLogo === '1' && !empty($logo) && is_array($logo)) {
            if (!empty($logo['id'])) {
                $image = wp_get_attachment_image_src((int) $logo['id'], 'full');
                if ($image && isset($image[0])) {
                    $logoUrl = $image[0];
                    $logoWidth = isset($image[1]) ? (int) $image[1] : 0;
                    $logoHeight = isset($image[2]) ? (int) $image[2] : 0;
                }
            }

            if (!$logoUrl && !empty($logo['url'])) {
                $logoUrl = $logo['url'];
                $logoWidth = isset($logo['width']) ? (int) $logo['width'] : 0;
                $logoHeight = isset($logo['height']) ? (int) $logo['height'] : 0;
            }
        }

        if (!$logoUrl) {
            return;
        }

        //limit max width to 200px and keep ratio
        if ($logoWidth > 0 && $logoHeight > 0) {
            $maxWidth = min($logoWidth, 200);
            $maxHeight = (int) round($logoHeight * ($maxWidth / $logoWidth));
            $logoWidth = $maxWidth;
            $logoHeight = $maxHeight;
        } else {
            $logoWidth = 200;
            $logoHeight = 100;
        }

        ?>
        <style type="text/css">
            #login h1 a,
            .login h1 a {
                background-image: url(<?php echo esc_url($logoUrl); ?>) !important;
                background-size: contain;
                background-position: center center;
                background-repeat: no-repeat;
                width: <?php echo (int) $logoWidth; ?>px;
                height: <?php echo (int) $logoHeight; ?>px;
                margin-bottom: 0;
                padding: .5rem 0;
                box-shadow: none;
            }
        </style>
        <?php
    }

    /**
     * Changes login logo url to homepage
     * @return string
     */
    public function changeLoginLogoUrl(): string {
        return get_bloginfo('url');
    }

    /**
     * Changes image title of login logo
     * @return string
     */
    public function changeLoginLogoUrlTitle(): string {
        return get_bloginfo('name');
    }
}

