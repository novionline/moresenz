<?php

namespace CookieConfirm\Modules;

use CookieConfirm\Matcher\MatcherFactory;
use CookieConfirm\Services\SettingsRepository;

class ScriptModifier
{
    public function __construct()
    {
        add_filter('script_loader_tag', [$this, 'modifyScript'], 10, 3);
    }

    public function modifyScript(string $tag, string $handle, string $src): string
    {
        $scripts = SettingsRepository::getInstance()->get()['scripts'] ?? [];
        if (!$scripts || is_admin()) {
            return $tag;
        }

        foreach ($scripts as $script) {
            $matcher = MatcherFactory::create($script['type']);

            if ($matcher->matches($script['src'], $src)) {
                $tag = str_replace('src=', "data-cc-consent='" . esc_attr($script['consent']) . "' data-cc-src=", $tag);
                break;
            }
        }

        return $tag;
    }
}