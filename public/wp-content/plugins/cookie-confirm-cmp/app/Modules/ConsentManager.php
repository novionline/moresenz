<?php

namespace CookieConfirm\Modules;

use CookieConfirm\Services\SettingsRepository;

class ConsentManager
{
    public function __construct()
    {
        add_action('wp_head', [$this, 'printConsent'], PHP_INT_MIN);
    }

    public function printConsent(): void
    {
        $settings = SettingsRepository::getInstance()->get();
        if ($settings['tagManager']) {
            return;
        }
        $c = $this->getConsent($settings);
        $a = $settings['adsDataRedaction'] ? 'true' : 'false';
        $u = $settings['urlPassthrough'] ? 'true' : 'false';

        wp_print_inline_script_tag(
            'window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag("consent", "default", ' . $c . '); gtag("set", "ads_data_redaction", ' . $a . '); gtag("set", "url_passthrough", ' . $u . ');'
        );
    }

    private function getConsent(array $settings): string
    {
        $consent = $settings['consents'];
        $consent['wait_for_update'] = $settings['waitForUpdate'];

        return wp_json_encode($consent);
    }
}