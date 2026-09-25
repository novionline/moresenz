<?php

namespace NoviOnline\ContentTranslator\Core;

final class MenuTranslator
{
    private static function normalizeCustomUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Prefer existing URL normalization helper when available.
        if (class_exists('\\NoviOnline\\Core\\Link') && method_exists('\\NoviOnline\\Core\\Link', 'parseLink')) {
            return (string) \NoviOnline\Core\Link::parseLink($url);
        }
        // Minimal normalization: remove accidental double slashes after domain.
        $url = preg_replace('#^([a-z][a-z0-9+.-]*://[^/]+)/+#i', '$1/', $url);
        return is_string($url) ? $url : '';
    }

    private static function shouldCopyCustomUrlVerbatim(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        // Bare hash / fragment-only (e.g. "#" CTA, "#section") — not language-specific pages.
        if ($url === '#' || str_starts_with($url, '#')) {
            return true;
        }
        $lower = strtolower($url);
        foreach (['mailto:', 'tel:', 'sms:', 'javascript:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return true;
            }
        }
        // Treat file endpoints as language-agnostic (sitemap, pdf, etc).
        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : false;
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path !== '' && preg_match('/\\.[a-z0-9]{2,6}$/i', $path)) {
            return true;
        }
        return false;
    }
    /**
     * @param array{force?:bool, only_missing?:bool, strict_links?:bool} $options
     * @return int|false Target menu ID on success
     */
    public static function translateMenu(int $sourceMenuId, string $sourceLang, string $targetLang, array $options = [])
    {
        $sourceMenuId = (int) $sourceMenuId;
        if ($sourceMenuId <= 0) {
            return false;
        }

        if (!function_exists('wp_get_nav_menu_object') || !function_exists('wp_get_nav_menu_items')) {
            return false;
        }

        $sourceLang = trim($sourceLang);
        $targetLang = trim($targetLang);
        if ($sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return false;
        }

        $force = array_key_exists('force', $options) ? (bool) $options['force'] : false;
        $onlyMissing = array_key_exists('only_missing', $options) ? (bool) $options['only_missing'] : false;
        $strictLinks = array_key_exists('strict_links', $options) ? (bool) $options['strict_links'] : true;

        $sourceMenu = wp_get_nav_menu_object($sourceMenuId);
        if (!is_object($sourceMenu) || !isset($sourceMenu->name)) {
            return false;
        }

        $sourceName = (string) $sourceMenu->name;
        $baseName = self::stripLocaleSuffix($sourceName, strtoupper($sourceLang));
        $targetName = rtrim($baseName) . ' - ' . strtoupper($targetLang);

        $targetMenuId = self::findMenuIdByName($targetName);
        if ($targetMenuId > 0 && $onlyMissing && !$force) {
            //menus already exist, but locations/term links may still be missing
            self::syncMenuLocations($sourceMenuId, $targetMenuId, $sourceLang, $targetLang);
            return $targetMenuId;
        }

        if ($targetMenuId <= 0) {
            if (!function_exists('wp_create_nav_menu')) {
                return false;
            }
            $created = wp_create_nav_menu($targetName);
            $targetMenuId = is_numeric($created) ? (int) $created : 0;
            if ($targetMenuId <= 0) {
                return false;
            }
        } elseif ($force) {
            self::deleteMenuItems($targetMenuId);
        }

        $items = wp_get_nav_menu_items($sourceMenuId, ['post_status' => 'any']);
        if (!is_array($items)) {
            $items = [];
        }
        usort($items, static function ($a, $b): int {
            $ao = is_object($a) && isset($a->menu_order) ? (int) $a->menu_order : 0;
            $bo = is_object($b) && isset($b->menu_order) ? (int) $b->menu_order : 0;
            return $ao <=> $bo;
        });

        // Phase 1: create all items without parents, track mapping.
        $idMap = []; // oldItemId => newItemId
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->ID)) {
                continue;
            }
            $newId = self::createTranslatedMenuItem($targetMenuId, $item, $sourceLang, $targetLang, $strictLinks, 0);
            if ($newId > 0) {
                $idMap[(int) $item->ID] = $newId;
            }
        }

        // Phase 2: set correct parents.
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->ID, $item->menu_item_parent)) {
                continue;
            }
            $oldId = (int) $item->ID;
            $newId = (int) ($idMap[$oldId] ?? 0);
            if ($newId <= 0) {
                continue;
            }

            $oldParent = (int) $item->menu_item_parent;
            if ($oldParent <= 0) {
                continue;
            }
            $newParent = (int) ($idMap[$oldParent] ?? 0);
            if ($newParent <= 0) {
                continue;
            }
            self::updateMenuItemParent($targetMenuId, $newId, $newParent);
        }

        self::syncMenuLocations($sourceMenuId, $targetMenuId, $sourceLang, $targetLang);

        return $targetMenuId;
    }

    /**
     * Assign the target menu to the same theme locations as the source menu.
     * Supports both Polylang theme_mod keys (`location___lang`) and Polylang's
     * `nav_menus` option (`nav_menus[stylesheet][location][lang]`).
     */
    private static function syncMenuLocations(int $sourceMenuId, int $targetMenuId, string $sourceLang, string $targetLang): void
    {
        if ($sourceMenuId <= 0 || $targetMenuId <= 0) {
            return;
        }

        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        if ($sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return;
        }

        $locationBases = [];

        if (function_exists('get_theme_mod') && function_exists('set_theme_mod')) {
            $loc = get_theme_mod('nav_menu_locations');
            if (is_array($loc) && $loc !== []) {
                $updated = false;
                foreach ($loc as $key => $menuId) {
                    $key = (string) $key;
                    $menuId = (int) $menuId;
                    if ($menuId !== $sourceMenuId) {
                        continue;
                    }

                    // Polylang location keys are often like: {base}___{lang}.
                    if (str_contains($key, '___')) {
                        $parts = explode('___', $key);
                        $base = (string) ($parts[0] ?? '');
                        $lang = strtolower((string) ($parts[1] ?? ''));
                        if ($base !== '' && $lang === $sourceLang) {
                            $targetKey = $base . '___' . $targetLang;
                            $loc[$targetKey] = $targetMenuId;
                            $locationBases[$base] = $base;
                            $updated = true;
                        }
                        continue;
                    }

                    // Unsuffixed key: treat as the source-language assignment and create/update the target key.
                    $locationBases[$key] = $key;
                    $targetKey = $key . '___' . $targetLang;
                    $loc[$targetKey] = $targetMenuId;
                    $updated = true;
                }

                if ($updated) {
                    set_theme_mod('nav_menu_locations', $loc);
                }
            }
        }

        //also sync Polylang's persisted per-language menu locations
        foreach ($locationBases as $base) {
            self::assignPolylangNavMenuLocation($base, $sourceLang, $sourceMenuId);
            self::assignPolylangNavMenuLocation($base, $targetLang, $targetMenuId);
        }
        self::syncPolylangNavMenusOption($sourceMenuId, $targetMenuId, $sourceLang, $targetLang);

        //connect menu term languages when Polylang APIs are available
        self::linkMenuTermTranslations($sourceMenuId, $targetMenuId, $sourceLang, $targetLang);
    }

    /**
     * Copy source-language Polylang nav_menus assignments onto the target language.
     */
    private static function syncPolylangNavMenusOption(
        int $sourceMenuId,
        int $targetMenuId,
        string $sourceLang,
        string $targetLang
    ): void {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }

        $opts = get_option('polylang');
        if (!is_array($opts)) {
            return;
        }

        $navMenus = $opts['nav_menus'] ?? null;
        if (!is_array($navMenus) || $navMenus === []) {
            return;
        }

        $changed = false;
        foreach ($navMenus as $theme => $locations) {
            if (!is_array($locations)) {
                continue;
            }
            foreach ($locations as $location => $langs) {
                if (!is_array($langs)) {
                    continue;
                }
                $assignedSource = (int) ($langs[$sourceLang] ?? 0);
                if ($assignedSource !== $sourceMenuId) {
                    continue;
                }
                if ((int) ($langs[$targetLang] ?? 0) === $targetMenuId) {
                    continue;
                }
                $navMenus[$theme][$location][$targetLang] = $targetMenuId;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $opts['nav_menus'] = $navMenus;
        update_option('polylang', $opts);
    }

    /**
     * Ensure one location/lang pair exists in Polylang's nav_menus option.
     */
    private static function assignPolylangNavMenuLocation(string $location, string $lang, int $menuId): void
    {
        $location = trim($location);
        $lang = strtolower(trim($lang));
        if ($location === '' || $lang === '' || $menuId <= 0) {
            return;
        }
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }

        $theme = function_exists('get_stylesheet') ? (string) get_stylesheet() : '';
        if ($theme === '') {
            return;
        }

        $opts = get_option('polylang');
        if (!is_array($opts)) {
            $opts = [];
        }
        if (!isset($opts['nav_menus']) || !is_array($opts['nav_menus'])) {
            $opts['nav_menus'] = [];
        }
        if (!isset($opts['nav_menus'][$theme]) || !is_array($opts['nav_menus'][$theme])) {
            $opts['nav_menus'][$theme] = [];
        }
        if (!isset($opts['nav_menus'][$theme][$location]) || !is_array($opts['nav_menus'][$theme][$location])) {
            $opts['nav_menus'][$theme][$location] = [];
        }

        if ((int) ($opts['nav_menus'][$theme][$location][$lang] ?? 0) === $menuId) {
            return;
        }

        $opts['nav_menus'][$theme][$location][$lang] = $menuId;
        update_option('polylang', $opts);
    }

    /**
     * Mark source/target menus with Polylang term languages and link translations.
     */
    private static function linkMenuTermTranslations(
        int $sourceMenuId,
        int $targetMenuId,
        string $sourceLang,
        string $targetLang
    ): void {
        if ($sourceMenuId <= 0 || $targetMenuId <= 0) {
            return;
        }

        if (function_exists('pll_set_term_language')) {
            pll_set_term_language($sourceMenuId, $sourceLang);
            pll_set_term_language($targetMenuId, $targetLang);
        }

        if (!function_exists('pll_get_term_translations') || !function_exists('pll_save_term_translations')) {
            return;
        }

        $map = [];
        foreach ([$sourceMenuId, $targetMenuId] as $menuId) {
            $existing = (array) pll_get_term_translations($menuId);
            foreach ($existing as $lang => $id) {
                $lang = strtolower(trim((string) $lang));
                $id = (int) $id;
                if ($lang === '' || $id <= 0) {
                    continue;
                }
                $map[$lang] = $id;
            }
        }
        $map[$sourceLang] = $sourceMenuId;
        $map[$targetLang] = $targetMenuId;
        pll_save_term_translations($map);
    }

    private static function stripLocaleSuffix(string $name, string $upperLocale): string
    {
        $suffix = ' - ' . $upperLocale;
        if ($suffix !== '' && substr($name, -strlen($suffix)) === $suffix) {
            return substr($name, 0, -strlen($suffix));
        }
        return $name;
    }

    private static function findMenuIdByName(string $name): int
    {
        if (!function_exists('wp_get_nav_menus')) {
            return 0;
        }
        $menus = wp_get_nav_menus();
        if (!is_array($menus)) {
            return 0;
        }
        foreach ($menus as $m) {
            if (is_object($m) && isset($m->term_id, $m->name) && (string) $m->name === $name) {
                return (int) $m->term_id;
            }
        }
        return 0;
    }

    private static function deleteMenuItems(int $menuId): void
    {
        if (!function_exists('wp_get_nav_menu_items') || !function_exists('wp_delete_post')) {
            return;
        }
        $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if (is_object($item) && isset($item->ID)) {
                wp_delete_post((int) $item->ID, true);
            }
        }
    }

    private static function updateMenuItemParent(int $menuId, int $menuItemId, int $newParentId): void
    {
        if (!function_exists('wp_update_nav_menu_item')) {
            return;
        }
        // Some stacks (e.g. Polylang) can wipe fields during parent updates if only parent-id is provided.
        // Always send a full payload derived from current stored fields, overriding only the parent.
        $args = [
            'menu-item-status' => 'publish',
            'menu-item-parent-id' => $newParentId,
        ];
        if (function_exists('get_post') && function_exists('wp_setup_nav_menu_item')) {
            $p = get_post($menuItemId);
            if (is_object($p)) {
                $it = wp_setup_nav_menu_item($p);
                if (is_object($it)) {
                    $args['menu-item-type'] = isset($it->type) ? (string) $it->type : 'custom';
                    $args['menu-item-object'] = isset($it->object) ? (string) $it->object : 'custom';
                    $args['menu-item-object-id'] = isset($it->object_id) ? (int) $it->object_id : 0;
                    $args['menu-item-url'] = isset($it->url) ? (string) $it->url : '';
                    $args['menu-item-title'] = isset($it->title) ? (string) $it->title : '';
                    if (isset($it->menu_order)) {
                        $args['menu-item-position'] = (int) $it->menu_order;
                    }
                }
            }
        }

        self::primeMenuItemGlobals($menuItemId, $args);
        wp_update_nav_menu_item($menuId, $menuItemId, $args);
    }

    private static function createTranslatedMenuItem(int $targetMenuId, object $sourceItem, string $sourceLang, string $targetLang, bool $strictLinks, int $parentId): int
    {
        if (!function_exists('wp_update_nav_menu_item')) {
            return 0;
        }

        $type = isset($sourceItem->type) ? (string) $sourceItem->type : '';
        $object = isset($sourceItem->object) ? (string) $sourceItem->object : '';
        $objectId = isset($sourceItem->object_id) ? (int) $sourceItem->object_id : 0;
        $url = isset($sourceItem->url) ? (string) $sourceItem->url : '';
        $label = isset($sourceItem->title) ? (string) $sourceItem->title : '';

        // Some nav menu items store empty title/url meta (WP derives display values from the linked object).
        // Ensure we always have deterministic label/url inputs for DeepL + strict link rewriting.
        if (trim($label) === '' && $objectId > 0) {
            $label = self::getObjectTitleForItem($type, $object, $objectId);
        }
        if (trim($url) === '' && $objectId > 0) {
            if ($type === 'post_type' && function_exists('get_permalink')) {
                $url = (string) get_permalink($objectId);
            } elseif ($type === 'taxonomy' && function_exists('get_term_link')) {
                $url = (string) get_term_link($objectId, $object);
            }
        }
        if (trim($url) === '' && $type === 'post_type_archive' && $object !== '' && function_exists('get_post_type_archive_link')) {
            $url = (string) get_post_type_archive_link($object);
        }

        $newArgs = self::baseMenuItemArgs($sourceItem, $targetLang);
        $newArgs['menu-item-parent-id'] = $parentId;

        // Polylang language switcher menu items must be copied verbatim (type + url + meta config).
        if ($url === '#pll_switcher') {
            $newArgs['menu-item-type'] = 'custom';
            $newArgs['menu-item-object'] = 'custom';
            $newArgs['menu-item-object-id'] = 0;
            $newArgs['menu-item-url'] = '#pll_switcher';
            $newArgs['menu-item-title'] = $label;

            $created = wp_update_nav_menu_item($targetMenuId, 0, $newArgs);
            $newId = is_numeric($created) ? (int) $created : 0;
            if ($newId > 0) {
                self::primeMenuItemGlobals($newId, $newArgs);
                wp_update_nav_menu_item($targetMenuId, $newId, $newArgs);
            }
            if ($newId > 0 && isset($sourceItem->ID) && function_exists('get_post_meta') && function_exists('update_post_meta')) {
                $meta = get_post_meta((int) $sourceItem->ID, '_pll_menu_item', true);
                if (!empty($meta)) {
                    update_post_meta($newId, '_pll_menu_item', $meta);
                }
            }
            return $newId;
        }

        $translatedObjectId = 0;
        $translatedUrl = '';
        $hasTypedTarget = false;

        if ($type === 'post_type' && $objectId > 0 && function_exists('pll_get_post')) {
            $translatedObjectId = (int) pll_get_post($objectId, $targetLang);
            if ($translatedObjectId > 0) {
                $newArgs['menu-item-type'] = 'post_type';
                $newArgs['menu-item-object'] = $object;
                $newArgs['menu-item-object-id'] = $translatedObjectId;
                $translatedUrl = function_exists('get_permalink') ? (string) get_permalink($translatedObjectId) : '';
                $hasTypedTarget = true;
            }
        }

        if (!$hasTypedTarget && $type === 'taxonomy' && $objectId > 0 && function_exists('pll_get_term')) {
            $translatedObjectId = (int) pll_get_term($objectId, $targetLang);
            if ($translatedObjectId > 0) {
                $newArgs['menu-item-type'] = 'taxonomy';
                $newArgs['menu-item-object'] = $object;
                $newArgs['menu-item-object-id'] = $translatedObjectId;
                $translatedUrl = function_exists('get_term_link') ? (string) get_term_link($translatedObjectId, $object) : '';
                $hasTypedTarget = true;
            }
        }

        if (!$hasTypedTarget && $type === 'post_type_archive' && $object !== '') {
            // Resolve the archive URL in the target language. WordPress blanks _menu_item_url for
            // post_type_archive items, so without a stored URL the link falls back to the default
            // language archive whenever PLL curlang is unset (CLI, caches, wrong context).
            // Store as a custom item with the absolute target-lang URL so it sticks.
            $translatedUrl = self::resolvePostTypeArchiveUrlForLang($url, $object, $targetLang);
            if ($translatedUrl !== '') {
                $newArgs['menu-item-type'] = 'custom';
                $newArgs['menu-item-object'] = 'custom';
                $newArgs['menu-item-object-id'] = 0;
                $hasTypedTarget = true;
            } else {
                // Last resort: keep typed archive (frontend may still rewrite via curlang).
                $newArgs['menu-item-type'] = 'post_type_archive';
                $newArgs['menu-item-object'] = $object;
                $newArgs['menu-item-object-id'] = 0;
                $hasTypedTarget = true;
            }
        }

        if (!$hasTypedTarget) {
            // Custom link: rewrite deterministically (strict).
            $newArgs['menu-item-type'] = 'custom';
            $newArgs['menu-item-object'] = 'custom';
            $newArgs['menu-item-object-id'] = 0;
            $normalizedUrl = self::normalizeCustomUrl($url);
            $url = $normalizedUrl !== '' ? $normalizedUrl : $url;

            if (self::shouldCopyCustomUrlVerbatim($url)) {
                $translatedUrl = $url;
            } elseif ($url !== '' && class_exists('\NoviOnline\ContentTranslator\Core\InternalLinkTranslator')) {
                $rewrite = InternalLinkTranslator::rewriteInternalUrl($url, $targetLang, $sourceLang, [
                    'strict' => $strictLinks,
                ]);
                if (is_array($rewrite) && isset($rewrite['url']) && is_string($rewrite['url'])) {
                    $translatedUrl = (string) $rewrite['url'];
                }
            }
        }

        // Determine label translation strategy.
        $newLabel = $label;
        if ($translatedObjectId > 0) {
            $sourceTitle = self::getObjectTitleForItem($type, $object, $objectId);
            if ($sourceTitle !== '' && $sourceTitle === $label) {
                $newLabel = self::getObjectTitleForItem($type, $object, $translatedObjectId);
            } else {
                $newLabel = DeepLTranslator::translateText($label, $sourceLang, $targetLang, ['context' => 'plain']);
            }
        } else {
            // Custom or missing translation: translate label (or keep if empty).
            if (trim($label) !== '') {
                $newLabel = DeepLTranslator::translateText($label, $sourceLang, $targetLang, ['context' => 'plain']);
            }
        }

        // Not found fallback. Archive→custom is covered when $translatedUrl differs from source;
        // typed post_type_archive remains a valid target even without a stored URL.
        $isVerbatimCustom = $newArgs['menu-item-type'] === 'custom' && $translatedUrl !== '' && self::shouldCopyCustomUrlVerbatim($translatedUrl);
        $hasTarget = $translatedObjectId > 0
            || ($translatedUrl !== '' && $translatedUrl !== $url)
            || (($newArgs['menu-item-type'] ?? '') === 'post_type_archive')
            || $isVerbatimCustom;
        if (!$hasTarget) {
            $newArgs['menu-item-type'] = 'custom';
            $newArgs['menu-item-object'] = 'custom';
            $newArgs['menu-item-object-id'] = 0;
            $newArgs['menu-item-url'] = '#';
            $newArgs['menu-item-title'] = 'Not found- ' . ($label !== '' ? $label : 'Item');
        } else {
            $newArgs['menu-item-title'] = $newLabel !== '' ? $newLabel : $label;
            if ($newArgs['menu-item-type'] === 'custom') {
                $newArgs['menu-item-url'] = $translatedUrl !== '' ? $translatedUrl : $url;
            } else {
                // For object items, WP will compute URL; still set for safety if we have it.
                if ($translatedUrl !== '') {
                    $newArgs['menu-item-url'] = $translatedUrl;
                }
            }
        }

        $created = wp_update_nav_menu_item($targetMenuId, 0, $newArgs);
        $newId = is_numeric($created) ? (int) $created : 0;
        if ($newId > 0) {
            self::primeMenuItemGlobals($newId, $newArgs);
            wp_update_nav_menu_item($targetMenuId, $newId, $newArgs);

            // Copy Nectar/ACF/custom menu item meta after creation (styles, mega menu, etc.).
            $sourceMenuItemId = isset($sourceItem->ID) ? (int) $sourceItem->ID : 0;
            self::copyNectarMenuItemMeta($sourceMenuItemId, $newId, $targetLang);
            self::copyCustomMenuItemMeta($sourceMenuItemId, $newId, $targetLang);
        }
        return $newId;
    }

    private static function copyNectarMenuItemMeta(int $sourceMenuItemId, int $targetMenuItemId, string $targetLang): void
    {
        if ($sourceMenuItemId <= 0 || $targetMenuItemId <= 0) {
            return;
        }
        if (!function_exists('get_post_meta') || !function_exists('update_post_meta')) {
            return;
        }

        // NectarBlocks menu item configuration is stored as serialized array.
        $options = get_post_meta($sourceMenuItemId, 'nectar_menu_options', true);
        if (is_array($options) && $options !== []) {
            foreach (['mega_menu_global_section', 'mega_menu_global_section_mobile'] as $k) {
                if (!isset($options[$k]) || !is_numeric($options[$k])) {
                    continue;
                }
                $id = (int) $options[$k];
                if ($id <= 0 || !function_exists('pll_get_post')) {
                    continue;
                }
                $translated = (int) pll_get_post($id, $targetLang);
                if ($translated > 0) {
                    $options[$k] = (string) $translated;
                }
            }
            update_post_meta($targetMenuItemId, 'nectar_menu_options', $options);
        } elseif (!empty($options)) {
            // If stored as scalar, still copy verbatim.
            update_post_meta($targetMenuItemId, 'nectar_menu_options', $options);
        }

        // Legacy button style meta (still read by theme walker).
        $legacyStyle = get_post_meta($sourceMenuItemId, 'menu-item-nectar-button-style', true);
        if (is_string($legacyStyle) && trim($legacyStyle) !== '') {
            update_post_meta($targetMenuItemId, 'menu-item-nectar-button-style', $legacyStyle);
        }
    }

    /**
     * Copy custom/ACF menu-item meta so theme styling (e.g. off_canvas_nav_item_style) survives translation.
     * Skips WordPress core `_menu_item_*` keys and keys already handled elsewhere.
     * Remaps Popup Maker `_pum_nav_item_options.popup_id` to the target-language twin when available.
     */
    private static function copyCustomMenuItemMeta(int $sourceMenuItemId, int $targetMenuItemId, string $targetLang = ''): void
    {
        if ($sourceMenuItemId <= 0 || $targetMenuItemId <= 0) {
            return;
        }
        if (!function_exists('update_post_meta')) {
            return;
        }

        $all = self::getAllPostMeta($sourceMenuItemId);
        if ($all === []) {
            return;
        }

        $skipExact = [
            'nectar_menu_options' => true,
            'menu-item-nectar-button-style' => true,
            '_pll_menu_item' => true,
        ];

        foreach ($all as $key => $values) {
            $key = (string) $key;
            if ($key === '' || isset($skipExact[$key])) {
                continue;
            }
            // WP core nav-menu item fields.
            if (str_starts_with($key, '_menu_item')) {
                continue;
            }
            if (!is_array($values) || $values === []) {
                continue;
            }
            // ACF + other custom meta: copy first stored value (same as single-meta read).
            $value = $values[0];
            if ($key === '_pum_nav_item_options') {
                $value = self::remapPumNavItemOptions($value, $targetLang);
            }
            update_post_meta($targetMenuItemId, $key, $value);
        }
    }

    /**
     * Remap Popup Maker menu popup_id to the translated popup post when a twin exists.
     *
     * @param mixed $options
     * @return mixed
     */
    private static function remapPumNavItemOptions($options, string $targetLang)
    {
        $targetLang = trim($targetLang);
        if ($targetLang === '' || !function_exists('pll_get_post')) {
            return $options;
        }

        // Stored as array or still serialized string depending on get_post_custom path.
        if (is_string($options)) {
            $maybe = maybe_unserialize($options);
            if (is_array($maybe)) {
                $options = $maybe;
            }
        }
        if (!is_array($options) || !isset($options['popup_id'])) {
            return $options;
        }

        $sourcePopupId = is_numeric($options['popup_id']) ? (int) $options['popup_id'] : 0;
        if ($sourcePopupId <= 0) {
            return $options;
        }

        $targetPopupId = (int) pll_get_post($sourcePopupId, $targetLang);
        if ($targetPopupId > 0 && $targetPopupId !== $sourcePopupId) {
            $options['popup_id'] = (string) $targetPopupId;
        }

        return $options;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function getAllPostMeta(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }
        if (function_exists('get_post_custom')) {
            $all = get_post_custom($postId);
            return is_array($all) ? $all : [];
        }
        if (function_exists('get_post_meta')) {
            // WP: empty key returns all meta as key => list of values.
            $all = get_post_meta($postId, '', false);
            if (is_array($all) && $all !== []) {
                return $all;
            }
        }
        // Test harness / fallback: read from shared meta bag if present.
        $bag = $GLOBALS['__nct_post_meta'][$postId] ?? null;
        return is_array($bag) ? $bag : [];
    }

    private static function primeMenuItemGlobals(int $menuItemId, array $args): void
    {
        if ($menuItemId <= 0) {
            return;
        }
        if (!isset($_POST) || !is_array($_POST)) {
            $_POST = [];
        }
        foreach ([
            'menu-item-type' => 'menu-item-type',
            'menu-item-object' => 'menu-item-object',
            'menu-item-object-id' => 'menu-item-object-id',
            'menu-item-url' => 'menu-item-url',
            'menu-item-title' => 'menu-item-title',
            'menu-item-parent-id' => 'menu-item-parent-id',
        ] as $argKey => $postKey) {
            if (!array_key_exists($argKey, $args)) {
                continue;
            }
            if (!isset($_POST[$postKey]) || !is_array($_POST[$postKey])) {
                $_POST[$postKey] = [];
            }
            $_POST[$postKey][(string) $menuItemId] = $args[$argKey];
        }
    }

    private static function primeMenuItemGlobalsFromExisting(int $menuItemId): void
    {
        if ($menuItemId <= 0 || !function_exists('get_post') || !function_exists('wp_setup_nav_menu_item')) {
            return;
        }
        $p = get_post($menuItemId);
        if (!is_object($p)) {
            return;
        }
        $it = wp_setup_nav_menu_item($p);
        if (!is_object($it)) {
            return;
        }
        self::primeMenuItemGlobals($menuItemId, [
            'menu-item-type' => isset($it->type) ? (string) $it->type : '',
            'menu-item-object' => isset($it->object) ? (string) $it->object : '',
            'menu-item-object-id' => isset($it->object_id) ? (int) $it->object_id : 0,
            'menu-item-url' => isset($it->url) ? (string) $it->url : '',
            'menu-item-title' => isset($it->title) ? (string) $it->title : '',
            'menu-item-parent-id' => isset($it->menu_item_parent) ? (int) $it->menu_item_parent : 0,
        ]);
    }

    /**
     * Build the post-type archive URL for a Polylang language.
     * Prefer translate-slugs; fall back to temporarily switching PLL curlang + get_post_type_archive_link.
     */
    private static function resolvePostTypeArchiveUrlForLang(string $sourceUrl, string $postType, string $targetLang): string
    {
        $fromSlugs = self::translateArchiveUrlWithPolylang($sourceUrl, $postType, $targetLang);
        if ($fromSlugs !== '') {
            return $fromSlugs;
        }

        $postType = trim($postType);
        $targetLang = trim($targetLang);
        if ($postType === '' || $targetLang === '' || !function_exists('get_post_type_archive_link') || !function_exists('PLL')) {
            return '';
        }

        try {
            $pll = \PLL();
            // Polylang model uses __call — do not gate on method_exists('get_language').
            $modelApi = is_object($pll) ? ($pll->model ?? null) : null;
            if (!is_object($modelApi)) {
                return '';
            }
            $lang = $modelApi->get_language($targetLang);
            if (!is_object($lang)) {
                return '';
            }
            $prev = $pll->curlang ?? null;
            $pll->curlang = $lang;
            $link = (string) get_post_type_archive_link($postType);
            $pll->curlang = $prev;
            return trim($link);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function translateArchiveUrlWithPolylang(string $url, string $postType, string $targetLang): string
    {
        $url = trim($url);
        $postType = trim($postType);
        $targetLang = trim($targetLang);
        if ($postType === '' || $targetLang === '' || !function_exists('PLL') || !function_exists('pll_home_url')) {
            return '';
        }

        try {
            $pll = \PLL();
            // Avoid isset() on Polylang magic props where it is unreliable.
            $slugsRoot = is_object($pll) ? ($pll->translate_slugs ?? null) : null;
            $model = is_object($slugsRoot) ? ($slugsRoot->slugs_model ?? null) : null;
            if (!is_object($model)) {
                return '';
            }

            // Switch base to the target language (subdirectory mode).
            $base = rtrim((string) \pll_home_url($targetLang), '/') . '/';

            // Fast path: read translated_slugs entry directly (works well for archives like archive_vacancy).
            // Does not need PLL language object.
            $translatedSlugs = is_array($model->translated_slugs ?? null) ? $model->translated_slugs : [];
            $directKeys = [
                'archive_' . $postType,
                'slug_archive_' . $postType,
                'archive_' . $postType . 's',
                'slug_archive_' . $postType . 's',
            ];
            foreach ($directKeys as $k) {
                if (!isset($translatedSlugs[$k]) || !is_array($translatedSlugs[$k])) {
                    continue;
                }
                $entry = (array) $translatedSlugs[$k];
                $trs = isset($entry['translations']) && is_array($entry['translations']) ? $entry['translations'] : [];
                $to = isset($trs[$targetLang]) ? trim((string) $trs[$targetLang], '/') : '';
                if ($to === '') {
                    continue;
                }
                return $base . $to . '/';
            }

            if ($url === '') {
                return '';
            }

            // Polylang model uses __call — do not gate on method_exists('get_language').
            $modelApi = is_object($pll) ? ($pll->model ?? null) : null;
            $lang = is_object($modelApi) ? $modelApi->get_language($targetLang) : null;
            if (!is_object($lang) || !is_callable([$model, 'switch_translated_slug'])) {
                return '';
            }

            $parts = function_exists('wp_parse_url') ? \wp_parse_url($url) : false;
            $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
            if ($path === '') {
                return '';
            }
            $switched = $base . ltrim($path, '/');

            // Ask Polylang translate-slugs to switch this archive base.
            // We try a few conservative candidates because Polylang keys vary by setup:
            // - archive_{post_type} (observed on this stack, e.g. archive_vacancy)
            // - slug_archive_{post_type} (used in some environments)
            // - archive_{post_type}s (defensive plural)
            $candidates = [
                'archive_' . $postType,
                'slug_archive_' . $postType,
                'archive_' . $postType . 's',
                'slug_archive_' . $postType . 's',
            ];
            foreach ($candidates as $typeKey) {
                $out = $model->switch_translated_slug($switched, $lang, $typeKey);
                if (is_string($out) && $out !== '' && $out !== $switched) {
                    return $out;
                }
            }
            return '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function baseMenuItemArgs(object $item, string $targetLang = ''): array
    {
        $classes = [];
        if (isset($item->classes) && is_array($item->classes)) {
            $classes = array_values(array_filter(array_map('strval', $item->classes)));
        }
        //wp_get_nav_menu_items() already includes PUM-injected popmake-{id} from source popup_id;
        //strip/remap those so the twin menu does not keep a stale NL class alongside the remapped meta
        $classes = self::remapPopmakeMenuClasses($classes, $targetLang);

        $args = [
            'menu-item-status' => 'publish',
        ];

        if (isset($item->menu_order)) {
            $args['menu-item-position'] = (int) $item->menu_order;
        }
        if (isset($item->target)) {
            $args['menu-item-target'] = (string) $item->target;
        }
        if (isset($item->xfn)) {
            $args['menu-item-xfn'] = (string) $item->xfn;
        }
        if (isset($item->attr_title)) {
            $args['menu-item-attr-title'] = (string) $item->attr_title;
        }
        if (isset($item->description)) {
            $args['menu-item-description'] = (string) $item->description;
        }
        if ($classes !== []) {
            $args['menu-item-classes'] = implode(' ', $classes);
        }

        return $args;
    }

    /**
     * Remap or drop Popup Maker `popmake-{id}` CSS classes on nav items.
     * Prefer the Polylang twin; if none exists, keep the source class.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    private static function remapPopmakeMenuClasses(array $classes, string $targetLang): array
    {
        $targetLang = trim($targetLang);
        $out = [];
        foreach ($classes as $class) {
            $class = trim((string) $class);
            if ($class === '') {
                continue;
            }
            if (!preg_match('/^popmake-(\d+)$/', $class, $matches)) {
                $out[] = $class;
                continue;
            }
            $sourcePopupId = (int) $matches[1];
            if ($sourcePopupId <= 0) {
                continue;
            }
            $targetPopupId = $sourcePopupId;
            if ($targetLang !== '' && function_exists('pll_get_post')) {
                $twinId = (int) pll_get_post($sourcePopupId, $targetLang);
                if ($twinId > 0) {
                    $targetPopupId = $twinId;
                }
            }
            $out[] = 'popmake-' . $targetPopupId;
        }

        return array_values(array_unique($out));
    }

    private static function getObjectTitleForItem(string $type, string $object, int $objectId): string
    {
        if ($objectId <= 0) {
            return '';
        }
        if ($type === 'post_type' && function_exists('get_the_title')) {
            return (string) get_the_title($objectId);
        }
        if ($type === 'taxonomy' && function_exists('get_term')) {
            $t = get_term($objectId, $object);
            if (is_object($t) && isset($t->name)) {
                return (string) $t->name;
            }
        }
        return '';
    }
}

