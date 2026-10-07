<?php

namespace CookieConfirm\Modules;

use CookieConfirm\Matcher\MatcherFactory;
use CookieConfirm\Services\SettingsRepository;

class OutputModifier
{
    private array $iframes = [];
    private array $scripts = [];

    public function __construct()
    {
        add_action('template_redirect', [$this, 'startBuffering']);
    }

    public function startBuffering(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = SettingsRepository::getInstance()->get();
        $this->iframes = $settings['iframes'] ?? [];
        $this->scripts = $settings['scripts'] ?? [];

        if (!$this->iframes && !$this->scripts) {
            return;
        }

        ob_start([$this, 'modifyOutput']);
    }

    public function modifyOutput(string $html): string
    {
        if ($this->iframes) {
            $html = $this->modifyIframes($html, $this->iframes);
        }

        if ($this->scripts) {
            $html = $this->modifyInlineScripts($html, $this->scripts);
        }

        return $html;
    }

    private function modifyIframes(string $html, array $iframes): string
    {
        // Match opening <iframe> with attributes
        return preg_replace_callback('/<iframe\b[^>]*>/i', function ($match) use ($iframes) {
            $tag = $match[0];

            // Extract the src
            if (!preg_match('/\bsrc=["\']([^"\']*)["\']/', $tag, $srcMatch)) {
                return $tag;
            }

            $src = $srcMatch[1];
            foreach ($iframes as $iframe) {
                $matcher = MatcherFactory::create($iframe['type']);

                if ($matcher->matches($iframe['src'], $src)) {
                    $tag = str_replace('src=', "data-cc-consent='" . esc_attr($iframe['consent']) . "' data-cc-src=", $tag);
                    break;
                }
            }

            return $tag;
        }, $html);
    }

    private function modifyInlineScripts(string $html, array $scripts): string
    {
        return preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($match) use ($scripts) {
            $tag = $match[0];

            // Skip external scripts (with src)
            if (preg_match('/\bsrc=["\']/', $tag)) {
                return $tag;
            }

            // Extract the id
            if (!preg_match('/\bid=["\']([^"\']*)["\']/', $tag, $idMatch)) {
                return $tag;
            }

            $id = $idMatch[1];
            foreach ($scripts as $script) {
                $matcher = MatcherFactory::create($script['type']);

                if ($matcher->matches($script['src'], $id)) {
                    // Replace existing type with text/plain (block execution)
                    $tag = preg_replace('/(<script\b[^>]*)\btype=["\'][^"\']*["\']/', '$1type="text/plain"', $tag, 1, $count);
                    if ($count === 0) {
                        $tag = preg_replace('/<script\b/', '<script type="text/plain"', $tag, 1);
                    }
                    $tag = preg_replace('/<script\b/', '<script data-cc-consent=\'' . esc_attr($script['consent']) . '\'', $tag, 1);
                    break;
                }
            }

            return $tag;
        }, $html);
    }
}
