<?php

namespace NoviOnline\ContentTranslator\Core;

final class MenuBlockIdReplacer
{
    /**
     * Replace `acf/block-novi-menu` ACF field `menu` (attrs.data.menu) inside Gutenberg content.
     *
     * @param string $content
     * @param callable(int):int $mapFn
     * @return array{content:string,updated_blocks:int}
     */
    public static function replaceNoviMenuBlockMenuIds(string $content, callable $mapFn): array
    {
        if (!function_exists('has_blocks') || !function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
            return ['content' => $content, 'updated_blocks' => 0];
        }
        if (!has_blocks($content)) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $blocks = parse_blocks($content);
        if (empty($blocks) || !is_array($blocks)) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $updatedBlocks = 0;
        $changed = self::replaceInBlocks($blocks, $mapFn, $updatedBlocks);
        if (!$changed) {
            return ['content' => $content, 'updated_blocks' => 0];
        }

        $serialized = serialize_blocks($blocks);
        $serialized = is_string($serialized) ? $serialized : $content;

        return ['content' => $serialized, 'updated_blocks' => $updatedBlocks];
    }

    /**
     * Deterministic menu ID mapper based on menu name suffix swap (e.g. "- EN" -> "- NL").
     *
     * @param string $sourceLang
     * @param string $targetLang
     * @param array{mapped:int,unmapped:int} $stats
     * @return callable(int):int
     */
    public static function buildMenuIdMapperBySuffix(string $sourceLang, string $targetLang, array &$stats): callable
    {
        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        $cache = [];

        return static function (int $sourceMenuId) use ($sourceLang, $targetLang, &$stats, &$cache): int {
            if ($sourceMenuId <= 0) {
                return 0;
            }
            if (isset($cache[$sourceMenuId])) {
                return (int) $cache[$sourceMenuId];
            }
            if (!function_exists('get_term') || !function_exists('wp_get_nav_menus')) {
                $cache[$sourceMenuId] = 0;
                $stats['unmapped'] = (int) ($stats['unmapped'] ?? 0) + 1;
                return 0;
            }

            $t = get_term($sourceMenuId, 'nav_menu');
            $sourceName = is_object($t) && isset($t->name) ? (string) $t->name : '';
            $targetName = self::swapLocaleSuffix($sourceName, strtoupper($sourceLang), strtoupper($targetLang));
            if ($targetName === '') {
                $cache[$sourceMenuId] = 0;
                $stats['unmapped'] = (int) ($stats['unmapped'] ?? 0) + 1;
                return 0;
            }

            $targetMenuId = 0;
            $menus = wp_get_nav_menus();
            if (is_array($menus)) {
                foreach ($menus as $m) {
                    if (!is_object($m) || !isset($m->term_id, $m->name)) {
                        continue;
                    }
                    if ((string) $m->name === $targetName) {
                        $targetMenuId = (int) $m->term_id;
                        break;
                    }
                }
            }

            $cache[$sourceMenuId] = $targetMenuId;
            if ($targetMenuId > 0) {
                $stats['mapped'] = (int) ($stats['mapped'] ?? 0) + 1;
            } else {
                $stats['unmapped'] = (int) ($stats['unmapped'] ?? 0) + 1;
            }
            return $targetMenuId;
        };
    }

    private static function replaceInBlocks(array &$blocks, callable $mapFn, int &$updatedBlocks): bool
    {
        $changed = false;
        foreach ($blocks as &$block) {
            if (!is_array($block)) {
                continue;
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                if (self::replaceInBlocks($block['innerBlocks'], $mapFn, $updatedBlocks)) {
                    $changed = true;
                }
            }

            $name = (string) ($block['blockName'] ?? '');
            if ($name !== 'acf/block-novi-menu') {
                continue;
            }
            if (!isset($block['attrs']) || !is_array($block['attrs'])) {
                continue;
            }
            if (!isset($block['attrs']['data']) || !is_array($block['attrs']['data'])) {
                continue;
            }
            if (!array_key_exists('menu', $block['attrs']['data'])) {
                continue;
            }

            $raw = $block['attrs']['data']['menu'];
            $sourceMenuId = is_numeric($raw) ? (int) $raw : 0;
            if ($sourceMenuId <= 0) {
                continue;
            }

            $targetMenuId = (int) $mapFn($sourceMenuId);
            if ($targetMenuId <= 0 || $targetMenuId === $sourceMenuId) {
                continue;
            }

            //acf typically stores taxonomy ids as strings in the saved block comment JSON
            $block['attrs']['data']['menu'] = (string) $targetMenuId;
            $updatedBlocks++;
            $changed = true;
        }
        return $changed;
    }

    private static function swapLocaleSuffix(string $menuName, string $sourceLocaleUpper, string $targetLocaleUpper): string
    {
        $menuName = trim($menuName);
        if ($menuName === '' || $sourceLocaleUpper === '' || $targetLocaleUpper === '') {
            return '';
        }
        $needle = ' - ' . $sourceLocaleUpper;
        if (!str_ends_with($menuName, $needle)) {
            return '';
        }
        return substr($menuName, 0, -strlen($needle)) . ' - ' . $targetLocaleUpper;
    }
}

