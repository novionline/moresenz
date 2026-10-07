<?php

namespace CookieConfirm\Services;

class SettingsRepository
{
    private function __construct()
    {
    }

    public static function getInstance(): SettingsRepository
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SettingsRepository();
        }

        return $instance;
    }

    public function get(): array
    {
        $settings = get_option(COOKIE_CONFIRM_NAME . '_settings');
        if ($settings) {
            return array_merge($this->defaults(), $settings);
        }

        return $this->defaults();
    }

    public function update(array $data): void
    {
        update_option(COOKIE_CONFIRM_NAME . '_settings', $data, true);
    }

    public function defaults(): array
    {
        return [
            'script'           => null,
            'tagManager'       => false,
            'urlPassthrough'   => true,
            'adsDataRedaction' => true,
            'waitForUpdate'    => 2000,
            'consents' => [
                'ad_storage'              => 'denied',
                'ad_user_data'            => 'denied',
                'ad_personalization'      => 'denied',
                'analytics_storage'       => 'denied',
                'personalization_storage' => 'granted',
                'functionality_storage'   => 'granted',
                'security_storage'        => 'granted'
            ],
            'scripts'  => [],
            'iframes'  => []
        ];
    }
}