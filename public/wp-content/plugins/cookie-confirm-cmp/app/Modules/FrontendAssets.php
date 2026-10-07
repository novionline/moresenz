<?php

namespace CookieConfirm\Modules;

use CookieConfirm\Enums\ScriptSrc;
use CookieConfirm\Services\SettingsRepository;

class FrontendAssets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScript'], PHP_INT_MIN);
    }

    public function enqueueScript(): void
    {
        $settings = SettingsRepository::getInstance()->get();
        if ($settings['tagManager']) {
            return;
        }
        $src = $settings['script'] ?: ScriptSrc::DEFAULT;

        wp_enqueue_script(
            COOKIE_CONFIRM_SLUG,
            $src,
            [],
            COOKIE_CONFIRM_VERSION,
            [
                'in_footer' => false,
                'strategy'  => 'async',
            ]
        );
    }
}