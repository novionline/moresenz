<?php

namespace NoviOnline\ContentTranslator;

use NoviOnline\Core\Singleton;

/**
 * Disable Polylang Pro machine translation (DeepL) while Novi translator is active.
 */
class PolylangMachineTranslationDisablerComponent extends Singleton
{
    /**
     * Register hooks.
     */
    protected function __construct()
    {
        // Force machine translation disabled in Polylang option payload.
        add_filter('pre_option_polylang', [$this, 'filterPolylangOptions']);
        add_filter('option_polylang', [$this, 'filterPolylangOptions']);

        // Hide machine translation settings module from Polylang settings UI.
        add_filter('pll_settings_modules', [$this, 'filterPolylangSettingsModules'], 200);
    }

    /**
     * Disable machine translation option at read-time.
     *
     * @param mixed $options
     * @return mixed
     */
    public function filterPolylangOptions($options)
    {
        if (!is_array($options)) {
            return $options;
        }

        $options['machine_translation_enabled'] = 0;

        return $options;
    }

    /**
     * Remove machine translation settings module.
     *
     * @param mixed $modules
     * @return mixed
     */
    public function filterPolylangSettingsModules($modules)
    {
        if (!is_array($modules)) {
            return $modules;
        }

        if (isset($modules['machine_translation'])) {
            unset($modules['machine_translation']);
        }

        foreach ($modules as $key => $moduleClass) {
            if (!is_string($moduleClass)) {
                continue;
            }

            if (
                strpos($moduleClass, 'Machine_Translation\\Module_Settings') !== false
                || strpos($moduleClass, 'PLL_Settings_Preview_Machine_Translation') !== false
            ) {
                unset($modules[$key]);
            }
        }

        return $modules;
    }
}

