<?php

namespace CookieConfirm\Modules;

use CookieConfirm\Enums\ScriptSrc;
use CookieConfirm\Services\SettingsRepository;

class AdminAssets
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addOptionsPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . COOKIE_CONFIRM_BASENAME, [$this, 'appendSettingsLink'], 10, 1);
    }

    public function addOptionsPage(): void
    {
        add_menu_page(
            __('Cookie Confirm', 'cookie-confirm-cmp'),
            __('Cookie Confirm', 'cookie-confirm-cmp'),
            'manage_options',
            'cookie-confirm-cmp',
            [$this, 'getOptionsPage']
        );
    }

    public function getOptionsPage(): void
    {
        echo '<div id="cookie-confirm-app"></div>';
    }

    public function enqueueAssets(string $hook): void
    {
        $manifestPath = COOKIE_CONFIRM_FILE . '/dist/.vite/manifest.json';
        if ($hook !== 'toplevel_page_cookie-confirm-cmp') {
            return;
        }

        if (wp_get_environment_type() === 'local' && defined('COOKIE_CONFIRM_DEBUG') && COOKIE_CONFIRM_DEBUG) {
            wp_enqueue_script_module('vite', 'http://localhost:5173/@vite/client');
            wp_enqueue_script_module(COOKIE_CONFIRM_SLUG, 'http://localhost:5173/src/main.js', ['vite']);
        } elseif (file_exists($manifestPath)) {
            $manifest  = json_decode(file_get_contents($manifestPath), true);
            $urlPrefix = COOKIE_CONFIRM_URL . 'dist/';
            wp_enqueue_script_module(COOKIE_CONFIRM_SLUG, $urlPrefix . $manifest['src/main.js']['file']);
        }

        $data = wp_json_encode([
            'endpoint'      => rest_url(COOKIE_CONFIRM_SLUG . '/settings'),
            'pluginUrl'     => COOKIE_CONFIRM_URL,
            'nonce'         => wp_create_nonce('wp_rest'),
            'defaultScript' => ScriptSrc::DEFAULT,
            'settings'      => SettingsRepository::getInstance()->get(),
            'url'           => 'https://cookieconfirm.com/',
            'platformUrl'   => 'https://platform.cookieconfirm.com/',
            'helpUrl'       => 'https://help.cookieconfirm.com/en/articles/7637634',
            'i18n' => [
                'dashboard'                       => __('Dashboard', 'cookie-confirm-cmp'),
                'help'                            => __('Help', 'cookie-confirm-cmp'),
                'general'                         => __('General', 'cookie-confirm-cmp'),
                'generalDescription'              => __('Override the default script and toggle Google Tag Manager integration.', 'cookie-confirm-cmp'),
                'useTagManager'                   => __('I use Google Tag Manager (GTM)', 'cookie-confirm-cmp'),
                'googleConsentMode'               => __('Google Consent Mode', 'cookie-confirm-cmp'),
                'googleConsentModeDescription'    => __('Controls how Google handles data when consent is limited, redacting ad-related information and optionally passing consent signals through URLs to maintain accurate tag behavior.', 'cookie-confirm-cmp'),
                'adsDataRedaction'                => __('Ads data redaction', 'cookie-confirm-cmp'),
                'urlPassthrough'                  => __('URL passthrough', 'cookie-confirm-cmp'),
                'waitForUpdate'                   => __('Wait for update', 'cookie-confirm-cmp'),
                'defaultConsentStates'            => __('Google Consent State (GCS)', 'cookie-confirm-cmp'),
                'defaultConsentStatesDescription' => __("Configure your site’s default Google Consent States (GCS). Control how advertising, analytics, personalization, functionality, and security related data are allowed or denied before visitors make their own choice.", 'cookie-confirm-cmp'),
                'scripts'                         => __('Scripts', 'cookie-confirm-cmp'),
                'scriptsDescription'              => __('Specify script URLs to delay execution until the user grants consent for the selected category.', 'cookie-confirm-cmp'),
                'save'                            => __('Save', 'cookie-confirm-cmp'),
                'saved'                           => __('Saved', 'cookie-confirm-cmp'),
                'copyright'                       => __('Cookie Confirm is a Dutch product 🇳🇱', 'cookie-confirm-cmp'),
                'granted'                         => __('Granted', 'cookie-confirm-cmp'),
                'denied'                          => __('Denied', 'cookie-confirm-cmp'),
                'src'                             => __('Src', 'cookie-confirm-cmp'),
                'consent'                         => __('Consent', 'cookie-confirm-cmp'),
                'action'                          => __('Action', 'cookie-confirm-cmp'),
                'delete'                          => __('Delete', 'cookie-confirm-cmp'),
                'noScript'                        => __('No scripts', 'cookie-confirm-cmp'),
                'addScript'                       => __('Add script', 'cookie-confirm-cmp'),
                'iframes'                         => __('Iframes', 'cookie-confirm-cmp'),
                'iframesDescription'              => __('Specify iframe URLs to delay loading until the user grants consent for the selected category.', 'cookie-confirm-cmp'),
                'noIframe'                        => __('No iframes', 'cookie-confirm-cmp'),
                'addIframe'                       => __('Add iframe', 'cookie-confirm-cmp'),
                'functional'                      => __('Functional', 'cookie-confirm-cmp'),
                'analytics'                       => __('Analytics', 'cookie-confirm-cmp'),
                'marketing'                       => __('Marketing', 'cookie-confirm-cmp'),
                'type'                            => __('Type', 'cookie-confirm-cmp'),
                'is'                              => __('IS', 'cookie-confirm-cmp'),
                'contains'                        => __('CONTAINS', 'cookie-confirm-cmp'),
                'regex'                           => __('REGEX', 'cookie-confirm-cmp'),
                'startsWith'                      => __('STARTS WITH', 'cookie-confirm-cmp'),
                'endsWith'                        => __('ENDS WITH', 'cookie-confirm-cmp'),
            ]
        ]);

        wp_print_inline_script_tag(
            'window.COOKIE_CONFIRM = ' . $data . ';',
            ['type' => 'module']
        );
    }

    public function appendSettingsLink(array $links): array
    {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=' . COOKIE_CONFIRM_SLUG)) . '">' . esc_html__('Settings', 'cookie-confirm-cmp') . '</a>';

        return $links;
    }
}