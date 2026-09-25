<?php

//bail if executed from WordPress runtime unintentionally
if (defined('ABSPATH') && function_exists('add_action')) {
    return;
}

//minimal WP shims for unit tests
if (!defined('ABSPATH')) {
    //tests/ -> plugin -> novionline -> mu-plugins -> wp-content -> public
    define('ABSPATH', realpath(__DIR__ . '/../../../../../') . '/');
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url): array|false
    {
        $parts = parse_url($url);
        return is_array($parts) ? $parts : false;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '/'): string
    {
        $base = (string) ($GLOBALS['__nct_home_url'] ?? 'https://example.test/');
        $base = rtrim($base, '/') . '/';
        $path = ltrim($path, '/');
        return $path === '' ? $base : ($base . $path);
    }
}

if (!function_exists('get_home_url')) {
    function get_home_url(): string
    {
        return home_url('/');
    }
}

// theme mods shim (nav_menu_locations used by MenuTranslator)
if (!function_exists('get_theme_mod')) {
    function get_theme_mod(string $name, $default = false)
    {
        $mods = $GLOBALS['__nct_theme_mods'] ?? [];
        if (!is_array($mods)) {
            $mods = [];
        }
        return array_key_exists($name, $mods) ? $mods[$name] : $default;
    }
}
if (!function_exists('set_theme_mod')) {
    function set_theme_mod(string $name, $value): void
    {
        if (!isset($GLOBALS['__nct_theme_mods']) || !is_array($GLOBALS['__nct_theme_mods'])) {
            $GLOBALS['__nct_theme_mods'] = [];
        }
        $GLOBALS['__nct_theme_mods'][$name] = $value;
    }
}

if (!function_exists('get_stylesheet')) {
    function get_stylesheet(): string
    {
        $theme = $GLOBALS['__nct_stylesheet'] ?? 'test-theme';
        return is_string($theme) && $theme !== '' ? $theme : 'test-theme';
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false)
    {
        $opts = $GLOBALS['__nct_options'] ?? [];
        if (!is_array($opts)) {
            $opts = [];
        }
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value, $autoload = null): bool
    {
        if (!isset($GLOBALS['__nct_options']) || !is_array($GLOBALS['__nct_options'])) {
            $GLOBALS['__nct_options'] = [];
        }
        $GLOBALS['__nct_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('pll_home_url')) {
    function pll_home_url(string $lang = ''): string
    {
        $base = home_url('/');
        $lang = trim($lang);
        if ($lang === '') {
            return $base;
        }
        return rtrim($base, '/') . '/' . $lang . '/';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(int $postId): string
    {
        $posts = $GLOBALS['__nct_posts'] ?? [];
        if (is_array($posts) && isset($posts[$postId]) && is_array($posts[$postId])) {
            return (string) ($posts[$postId]['post_title'] ?? '');
        }
        return '';
    }
}

if (!function_exists('get_posts')) {
    /**
     * Minimal get_posts shim for auto don’t-translate title collection in unit tests.
     *
     * @param array<string, mixed> $args
     * @return array<int, object>
     */
    function get_posts(array $args = []): array
    {
        $all = $GLOBALS['__nct_posts'] ?? [];
        if (!is_array($all) || $all === []) {
            return [];
        }

        $postTypes = $args['post_type'] ?? null;
        if (is_string($postTypes)) {
            $postTypes = [$postTypes];
        }
        if (!is_array($postTypes) || $postTypes === []) {
            $postTypes = null;
        } else {
            $postTypes = array_map('strval', $postTypes);
        }

        $statuses = $args['post_status'] ?? null;
        if (is_string($statuses)) {
            $statuses = [$statuses];
        }
        if (!is_array($statuses) || $statuses === []) {
            $statuses = null;
        } else {
            $statuses = array_map('strval', $statuses);
        }

        $lang = isset($args['lang']) ? strtolower(trim((string) $args['lang'])) : '';
        $out = [];

        foreach ($all as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) $id;
            $type = (string) ($row['post_type'] ?? 'post');
            $status = (string) ($row['post_status'] ?? 'publish');
            if ($postTypes !== null && !in_array($type, $postTypes, true)) {
                continue;
            }
            if ($statuses !== null && !in_array($status, $statuses, true)) {
                continue;
            }
            if ($status === 'trash') {
                continue;
            }
            if ($lang !== '' && function_exists('pll_get_post_language')) {
                $postLang = strtolower(trim((string) pll_get_post_language($id)));
                if ($postLang !== '' && $postLang !== $lang) {
                    continue;
                }
            }
            $out[] = (object) array_merge(['ID' => $id], $row);
        }

        return $out;
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('get_page_by_path')) {
    function get_page_by_path(string $path, $output = OBJECT, $postType = 'post')
    {
        $path = trim($path, '/');
        $types = is_array($postType) ? $postType : [$postType];
        $posts = $GLOBALS['__nct_posts'] ?? [];
        if (!is_array($posts)) {
            return null;
        }
        foreach ($posts as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['post_name'] ?? '');
            $type = (string) ($row['post_type'] ?? 'post');
            if ($name === $path && in_array($type, array_map('strval', $types), true)) {
                return get_post((int) $id);
            }
        }
        return null;
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types($args = [], $output = 'names')
    {
        $types = $GLOBALS['__nct_post_types'] ?? ['post', 'page', 'article', 'project'];
        if (!is_array($types)) {
            $types = ['post', 'page'];
        }
        if ($output === 'names') {
            return array_values(array_map('strval', $types));
        }
        return $types;
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object(string $postType): ?object
    {
        $map = $GLOBALS['__nct_post_type_objects'] ?? [];
        if (is_array($map) && isset($map[$postType]) && is_object($map[$postType])) {
            return $map[$postType];
        }
        $rewriteSlugs = $GLOBALS['__nct_post_type_rewrite_slugs'] ?? [];
        $slug = is_array($rewriteSlugs) && isset($rewriteSlugs[$postType])
            ? (string) $rewriteSlugs[$postType]
            : $postType;
        return (object) [
            'name' => $postType,
            'rewrite' => ['slug' => $slug],
        ];
    }
}

// minimal $wpdb shim for InternalLinkTranslator fuzzy slug matching
if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $posts = 'wp_posts';

        public function esc_like(string $text): string
        {
            return $text;
        }

        public function prepare(string $query, ...$args): string
        {
            // store args for get_results inspection; return query marker
            $GLOBALS['__nct_wpdb_last_prepare'] = ['query' => $query, 'args' => $args];
            return $query;
        }

        public function get_col(string $query): array
        {
            $rows = $this->get_results($query, ARRAY_A);
            $out = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['ID'])) {
                    $out[] = (int) $row['ID'];
                }
            }
            return $out;
        }

        public function get_results(string $query, $output = OBJECT): array
        {
            $map = $GLOBALS['__nct_wpdb_fuzzy_rows'] ?? null;
            if (is_callable($map)) {
                $prepared = $GLOBALS['__nct_wpdb_last_prepare'] ?? ['query' => $query, 'args' => []];
                $rows = $map($prepared);
                return is_array($rows) ? $rows : [];
            }
            if (is_array($map)) {
                return $map;
            }
            return [];
        }
    };
}
$wpdb = $GLOBALS['wpdb'];

if (!function_exists('wp_remote_head')) {
    function wp_remote_head(string $url, array $args = [])
    {
        $map = $GLOBALS['__nct_http_redirect_map'] ?? [];
        if (!is_array($map) || !isset($map[$url])) {
            return ['response' => ['code' => 404]];
        }
        $target = (string) $map[$url];
        return [
            'response' => ['code' => 301],
            'headers' => ['location' => $target],
        ];
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int
    {
        if (is_array($response) && isset($response['response']['code'])) {
            return (int) $response['response']['code'];
        }
        return 0;
    }
}
if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, string $header)
    {
        if (!is_array($response) || !isset($response['headers']) || !is_array($response['headers'])) {
            return '';
        }
        $header = strtolower($header);
        foreach ($response['headers'] as $k => $v) {
            if (strtolower((string) $k) === $header) {
                return $v;
            }
        }
        return '';
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return is_object($thing) && is_a($thing, 'WP_Error');
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null, $output = 'OBJECT', $filter = 'raw')
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        if ($id <= 0) {
            return null;
        }
        $posts = $GLOBALS['__nct_posts'] ?? [];
        if (!is_array($posts) || !isset($posts[$id]) || !is_array($posts[$id])) {
            return null;
        }
        $obj = (object) array_merge(['ID' => $id], $posts[$id]);
        if (class_exists('\\WP_Post')) {
            return new \WP_Post($obj);
        }
        return $obj;
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = wp_slash($v);
            }
            return $value;
        }
        return is_string($value) ? addslashes($value) : $value;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = wp_unslash($v);
            }
            return $value;
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false)
    {
        if (!is_array($postarr)) {
            return $wp_error ? new WP_Error('invalid_post', 'Invalid post') : 0;
        }
        //mirror WP: wp_insert_post() always unslashes array input
        $postarr = wp_unslash($postarr);
        $id = (int) ($postarr['ID'] ?? 0);
        if ($id <= 0) {
            return $wp_error ? new WP_Error('invalid_post', 'Invalid post') : 0;
        }
        if (!isset($GLOBALS['__nct_posts']) || !is_array($GLOBALS['__nct_posts'])) {
            $GLOBALS['__nct_posts'] = [];
        }
        if (!isset($GLOBALS['__nct_posts'][$id]) || !is_array($GLOBALS['__nct_posts'][$id])) {
            $GLOBALS['__nct_posts'][$id] = [];
        }
        foreach ($postarr as $key => $value) {
            if ($key === 'ID') {
                continue;
            }
            $GLOBALS['__nct_posts'][$id][$key] = $value;
        }
        return $id;
    }
}

if (!function_exists('is_post_type_hierarchical')) {
    function is_post_type_hierarchical($postType): bool
    {
        return (string) $postType === 'page';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(int $postId): string
    {
        $map = $GLOBALS['__nct_permalink_map'] ?? [];
        if (is_array($map) && isset($map[$postId])) {
            return (string) $map[$postId];
        }
        return home_url('/?p=' . $postId);
    }
}

// Minimal nav menu shims for MenuTranslator tests
if (!function_exists('wp_get_nav_menus')) {
    function wp_get_nav_menus(): array
    {
        $menus = $GLOBALS['__nct_menus'] ?? [];
        if (!is_array($menus)) {
            return [];
        }
        $out = [];
        foreach ($menus as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $o = new \stdClass();
            $o->term_id = (int) $id;
            $o->name = (string) ($row['name'] ?? '');
            $out[] = $o;
        }
        return $out;
    }
}

if (!function_exists('wp_get_nav_menu_object')) {
    function wp_get_nav_menu_object(int $menuId): ?object
    {
        $menus = $GLOBALS['__nct_menus'] ?? [];
        if (!is_array($menus) || !isset($menus[$menuId]) || !is_array($menus[$menuId])) {
            return null;
        }
        $o = new \stdClass();
        $o->term_id = (int) $menuId;
        $o->name = (string) ($menus[$menuId]['name'] ?? '');
        return $o;
    }
}

if (!function_exists('wp_create_nav_menu')) {
    function wp_create_nav_menu(string $menuName): int
    {
        $next = isset($GLOBALS['__nct_next_menu_id']) ? (int) $GLOBALS['__nct_next_menu_id'] : 3000;
        $id = $next;
        $GLOBALS['__nct_next_menu_id'] = $next + 1;
        if (!isset($GLOBALS['__nct_menus']) || !is_array($GLOBALS['__nct_menus'])) {
            $GLOBALS['__nct_menus'] = [];
        }
        $GLOBALS['__nct_menus'][$id] = ['name' => $menuName];
        if (!isset($GLOBALS['__nct_menu_items']) || !is_array($GLOBALS['__nct_menu_items'])) {
            $GLOBALS['__nct_menu_items'] = [];
        }
        $GLOBALS['__nct_menu_items'][$id] = [];
        return $id;
    }
}

if (!function_exists('wp_get_nav_menu_items')) {
    function wp_get_nav_menu_items(int $menuId, array $args = []): array
    {
        $items = $GLOBALS['__nct_menu_items'] ?? [];
        if (!is_array($items) || !isset($items[$menuId]) || !is_array($items[$menuId])) {
            return [];
        }
        $out = [];
        foreach ($items[$menuId] as $itemId => $row) {
            if (!is_array($row)) {
                continue;
            }
            $o = new \stdClass();
            $o->ID = (int) $itemId;
            $o->type = (string) ($row['type'] ?? 'custom');
            $o->object = (string) ($row['object'] ?? 'custom');
            $o->object_id = (int) ($row['object_id'] ?? 0);
            $o->url = (string) ($row['url'] ?? '');
            $o->title = (string) ($row['title'] ?? '');
            $o->menu_order = (int) ($row['menu_order'] ?? 0);
            $o->menu_item_parent = (int) ($row['parent'] ?? 0);
            $o->classes = isset($row['classes']) && is_array($row['classes']) ? $row['classes'] : [];
            $o->target = (string) ($row['target'] ?? '');
            $o->xfn = (string) ($row['xfn'] ?? '');
            $o->attr_title = (string) ($row['attr_title'] ?? '');
            $o->description = (string) ($row['description'] ?? '');
            $out[] = $o;
        }
        return $out;
    }
}

if (!function_exists('wp_update_nav_menu_item')) {
    function wp_update_nav_menu_item(int $menuId, int $menuItemId, array $args = [], bool $fireAfterHooks = true): int
    {
        if (!isset($GLOBALS['__nct_menu_items']) || !is_array($GLOBALS['__nct_menu_items'])) {
            $GLOBALS['__nct_menu_items'] = [];
        }
        if (!isset($GLOBALS['__nct_menu_items'][$menuId]) || !is_array($GLOBALS['__nct_menu_items'][$menuId])) {
            $GLOBALS['__nct_menu_items'][$menuId] = [];
        }

        if ($menuItemId <= 0) {
            $next = isset($GLOBALS['__nct_next_menu_item_id']) ? (int) $GLOBALS['__nct_next_menu_item_id'] : 4000;
            $menuItemId = $next;
            $GLOBALS['__nct_next_menu_item_id'] = $next + 1;
        }

        $row = $GLOBALS['__nct_menu_items'][$menuId][$menuItemId] ?? [];
        $row = is_array($row) ? $row : [];

        if (isset($args['menu-item-type'])) {
            $row['type'] = (string) $args['menu-item-type'];
        }
        if (isset($args['menu-item-object'])) {
            $row['object'] = (string) $args['menu-item-object'];
        }
        if (array_key_exists('menu-item-object-id', $args)) {
            $row['object_id'] = (int) $args['menu-item-object-id'];
        }
        if (isset($args['menu-item-url'])) {
            $row['url'] = (string) $args['menu-item-url'];
        }
        if (isset($args['menu-item-title'])) {
            $row['title'] = (string) $args['menu-item-title'];
        }
        if (isset($args['menu-item-position'])) {
            $row['menu_order'] = (int) $args['menu-item-position'];
        }
        if (isset($args['menu-item-parent-id'])) {
            $row['parent'] = (int) $args['menu-item-parent-id'];
        }
        if (isset($args['menu-item-classes'])) {
            $row['classes'] = array_values(array_filter(explode(' ', (string) $args['menu-item-classes'])));
        }
        foreach ([
            'menu-item-target' => 'target',
            'menu-item-xfn' => 'xfn',
            'menu-item-attr-title' => 'attr_title',
            'menu-item-description' => 'description',
        ] as $k => $dest) {
            if (isset($args[$k])) {
                $row[$dest] = (string) $args[$k];
            }
        }

        $GLOBALS['__nct_menu_items'][$menuId][$menuItemId] = $row;
        return $menuItemId;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post(int $postId, bool $forceDelete = false): bool
    {
        $all = $GLOBALS['__nct_menu_items'] ?? [];
        if (!is_array($all)) {
            return false;
        }
        foreach ($all as $menuId => $items) {
            if (!is_array($items)) {
                continue;
            }
            if (isset($all[$menuId][$postId])) {
                unset($all[$menuId][$postId]);
            }
        }
        $GLOBALS['__nct_menu_items'] = $all;
        return true;
    }
}

// Minimal post meta shims (needed for Polylang menu language switcher cloning).
if (!function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false)
    {
        $all = $GLOBALS['__nct_post_meta'] ?? [];
        if (!is_array($all) || !isset($all[$postId]) || !is_array($all[$postId])) {
            return $key === '' ? [] : ($single ? '' : []);
        }
        // WP behaviour: empty key returns all meta as key => list of values.
        if ($key === '') {
            return $all[$postId];
        }
        $values = [];
        if (isset($all[$postId][$key])) {
            $values = (array) $all[$postId][$key];
        }
        return $single ? (isset($values[0]) ? $values[0] : '') : $values;
    }
}
if (!function_exists('get_post_custom')) {
    function get_post_custom(int $postId = 0): array
    {
        $all = get_post_meta($postId, '', false);
        return is_array($all) ? $all : [];
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, $value): void
    {
        if (!isset($GLOBALS['__nct_post_meta']) || !is_array($GLOBALS['__nct_post_meta'])) {
            $GLOBALS['__nct_post_meta'] = [];
        }
        if (!isset($GLOBALS['__nct_post_meta'][$postId]) || !is_array($GLOBALS['__nct_post_meta'][$postId])) {
            $GLOBALS['__nct_post_meta'][$postId] = [];
        }
        $GLOBALS['__nct_post_meta'][$postId][$key] = [$value];
    }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta(int $postId, string $key, $metaValue = ''): bool
    {
        if (!isset($GLOBALS['__nct_post_meta'][$postId]) || !is_array($GLOBALS['__nct_post_meta'][$postId])) {
            return false;
        }
        if (!array_key_exists($key, $GLOBALS['__nct_post_meta'][$postId])) {
            return false;
        }
        unset($GLOBALS['__nct_post_meta'][$postId][$key]);
        return true;
    }
}
if (!function_exists('get_post_custom_keys')) {
    function get_post_custom_keys(int $postId): ?array
    {
        $all = $GLOBALS['__nct_post_meta'][$postId] ?? null;
        if (!is_array($all) || $all === []) {
            return null;
        }
        return array_keys($all);
    }
}
if (!function_exists('get_term_link')) {
    function get_term_link(int $termId): string
    {
        $map = $GLOBALS['__nct_term_link_map'] ?? [];
        if (is_array($map) && isset($map[$termId])) {
            return (string) $map[$termId];
        }
        return home_url('/?term=' . $termId);
    }
}

if (!function_exists('url_to_postid')) {
    function url_to_postid(string $url): int
    {
        $map = $GLOBALS['__nct_url_to_postid_map'] ?? [];
        if (is_array($map) && isset($map[$url])) {
            return (int) $map[$url];
        }
        return 0;
    }
}

if (!function_exists('wp_url_to_termid')) {
    function wp_url_to_termid(string $url): int
    {
        $map = $GLOBALS['__nct_url_to_termid_map'] ?? [];
        if (is_array($map) && isset($map[$url])) {
            return (int) $map[$url];
        }
        return 0;
    }
}

//transients shim for unit tests
if (!function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        $store = $GLOBALS['__nct_transients'] ?? [];
        if (!is_array($store) || !isset($store[$key])) {
            return false;
        }
        $entry = $store[$key];
        if (!is_array($entry) || !isset($entry['value'], $entry['expires_at'])) {
            return false;
        }
        if ($entry['expires_at'] !== 0 && time() > (int) $entry['expires_at']) {
            unset($store[$key]);
            $GLOBALS['__nct_transients'] = $store;
            return false;
        }
        return $entry['value'];
    }
}
if (!function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $store = $GLOBALS['__nct_transients'] ?? [];
        if (!is_array($store)) {
            $store = [];
        }
        $expiresAt = $expiration > 0 ? (time() + $expiration) : 0;
        $store[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
        $GLOBALS['__nct_transients'] = $store;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        $store = $GLOBALS['__nct_transients'] ?? [];
        if (!is_array($store) || !array_key_exists($key, $store)) {
            return false;
        }
        unset($store[$key]);
        $GLOBALS['__nct_transients'] = $store;
        return true;
    }
}

//very small hooks shim (enough for wp-includes/blocks.php in unit tests)
if (!function_exists('apply_filters')) {
    function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
    {
        $filters = $GLOBALS['__nct_filters'][$hookName] ?? [];
        if (!is_array($filters) || empty($filters)) {
            return $value;
        }

        foreach ($filters as $filter) {
            $callback = $filter['callback'] ?? null;
            if (!is_callable($callback)) {
                continue;
            }

            $acceptedArgs = (int) ($filter['acceptedArgs'] ?? 1);
            $acceptedArgs = max(1, $acceptedArgs);

            $extraArgs = $acceptedArgs > 1 ? array_slice($args, 0, $acceptedArgs - 1) : [];
            $value = $callback($value, ...$extraArgs);
        }

        return $value;
    }
}
if (!function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        if (!isset($GLOBALS['__nct_filters']) || !is_array($GLOBALS['__nct_filters'])) {
            $GLOBALS['__nct_filters'] = [];
        }

        if (!isset($GLOBALS['__nct_filters'][$hookName]) || !is_array($GLOBALS['__nct_filters'][$hookName])) {
            $GLOBALS['__nct_filters'][$hookName] = [];
        }

        $GLOBALS['__nct_filters'][$hookName][] = [
            'priority' => $priority,
            'acceptedArgs' => $acceptedArgs,
            'callback' => $callback,
        ];

        // keep stable order for same priority
        usort($GLOBALS['__nct_filters'][$hookName], static function ($a, $b) {
            $aPriority = (int) ($a['priority'] ?? 10);
            $bPriority = (int) ($b['priority'] ?? 10);
            return $aPriority <=> $bPriority;
        });

        return true;
    }
}

//polylang shim for core/block tests (toggle via global)
if (!function_exists('pll_get_post')) {
    function pll_get_post(int $postId, string $lang): int
    {
        $map = $GLOBALS['__nct_pll_post_map'] ?? [];
        if (!is_array($map)) {
            $map = [];
        }
        $key = $postId . '|' . $lang;
        if (isset($map[$key])) {
            return (int) $map[$key];
        }

        // fallback: try translation groups if present
        if (function_exists('pll_get_post_translations')) {
            $translations = (array) pll_get_post_translations($postId);
            if (isset($translations[$lang]) && is_numeric($translations[$lang])) {
                return (int) $translations[$lang];
            }
        }

        return 0;
    }
}

if (!function_exists('pll_get_post_language')) {
    function pll_get_post_language(int $postId, string $field = 'slug'): string
    {
        $lang = $GLOBALS['__nct_pll_post_language'][$postId] ?? '';
        return is_string($lang) ? $lang : '';
    }
}

if (!function_exists('pll_set_post_language')) {
    function pll_set_post_language(int $postId, string $lang): void
    {
        $GLOBALS['__nct_pll_post_language'][$postId] = (string) $lang;
    }
}

if (!function_exists('pll_get_post_translations')) {
    function pll_get_post_translations(int $postId): array
    {
        $groups = $GLOBALS['__nct_pll_post_translations_groups'] ?? [];
        if (!is_array($groups)) {
            return [];
        }
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $lang => $id) {
                if (is_numeric($id) && (int) $id === (int) $postId) {
                    return $group;
                }
            }
        }
        return [];
    }
}

if (!function_exists('pll_save_post_translations')) {
    function pll_save_post_translations(array $translations): void
    {
        if (!isset($GLOBALS['__nct_pll_post_translations_groups']) || !is_array($GLOBALS['__nct_pll_post_translations_groups'])) {
            $GLOBALS['__nct_pll_post_translations_groups'] = [];
        }

        // normalize
        $normalized = [];
        foreach ($translations as $lang => $id) {
            $lang = is_string($lang) ? trim($lang) : '';
            $id = is_numeric($id) ? (int) $id : 0;
            if ($lang === '' || $id <= 0) {
                continue;
            }
            $normalized[$lang] = $id;
        }
        if ($normalized === []) {
            return;
        }

        // Remove any existing group that contains any of these IDs
        $existing = $GLOBALS['__nct_pll_post_translations_groups'];
        $ids = array_values($normalized);
        $filtered = [];
        foreach ($existing as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupIds = array_values(array_map('intval', $group));
            $intersects = false;
            foreach ($ids as $id) {
                if (in_array((int) $id, $groupIds, true)) {
                    $intersects = true;
                    break;
                }
            }
            if (!$intersects) {
                $filtered[] = $group;
            }
        }
        $filtered[] = $normalized;
        $GLOBALS['__nct_pll_post_translations_groups'] = $filtered;

        // Update fast lookup map too (used by some tests)
        if (!isset($GLOBALS['__nct_pll_post_map']) || !is_array($GLOBALS['__nct_pll_post_map'])) {
            $GLOBALS['__nct_pll_post_map'] = [];
        }
        foreach ($normalized as $lang => $id) {
            foreach ($normalized as $lang2 => $id2) {
                $GLOBALS['__nct_pll_post_map'][(int) $id . '|' . (string) $lang2] = (int) $id2;
            }
        }
    }
}

//polylang shim for taxonomy-grid term-id remapping tests (toggle via global)
if (!function_exists('pll_get_term')) {
    function pll_get_term(int $termId, string $lang): int
    {
        $map = $GLOBALS['__nct_pll_term_map'] ?? [];
        if (!is_array($map)) {
            $map = [];
        }
        $key = $termId . '|' . $lang;
        return isset($map[$key]) ? (int) $map[$key] : 0;
    }
}

if (!function_exists('pll_is_translated_taxonomy')) {
    function pll_is_translated_taxonomy(string $taxonomy): bool
    {
        $list = $GLOBALS['__nct_pll_translated_taxonomies'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        return in_array($taxonomy, array_map('strval', $list), true);
    }
}

if (!function_exists('pll_set_term_language')) {
    function pll_set_term_language(int $termId, string $lang): void
    {
        $GLOBALS['__nct_pll_term_language'][$termId] = (string) $lang;
    }
}

if (!function_exists('pll_get_term_language')) {
    function pll_get_term_language(int $termId, string $field = ''): string
    {
        $lang = $GLOBALS['__nct_pll_term_language'][$termId] ?? '';
        return is_string($lang) ? $lang : '';
    }
}

if (!function_exists('pll_get_term_translations')) {
    function pll_get_term_translations(int $termId): array
    {
        $map = $GLOBALS['__nct_pll_term_translations'] ?? [];
        if (!is_array($map)) {
            return [];
        }
        return isset($map[$termId]) && is_array($map[$termId]) ? $map[$termId] : [];
    }
}

if (!function_exists('pll_save_term_translations')) {
    function pll_save_term_translations(array $arr): void
    {
        // Persist the same translation map for all involved terms.
        foreach ($arr as $lang => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $GLOBALS['__nct_pll_term_translations'][$id] = $arr;
        }

        // Also populate pll_get_term lookup map for convenience.
        foreach ($arr as $lang => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            foreach ($arr as $lang2 => $id2) {
                $id2 = (int) $id2;
                if ($id2 <= 0) {
                    continue;
                }
                $GLOBALS['__nct_pll_term_map'][$id . '|' . (string) $lang2] = $id2;
            }
        }
    }
}

//term + taxonomy shims
if (!function_exists('get_term')) {
    function get_term(int $termId, string $taxonomy = ''): ?object
    {
        $terms = $GLOBALS['__nct_terms'] ?? [];
        if (!is_array($terms) || !isset($terms[$termId])) {
            return null;
        }
        $row = (array) $terms[$termId];
        $obj = (object) $row;
        $obj->term_id = $termId;
        $obj->taxonomy = $taxonomy !== '' ? $taxonomy : (string) ($row['taxonomy'] ?? '');
        return $obj;
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by(string $field, string $value, string $taxonomy): ?object
    {
        if ($field !== 'slug') {
            return null;
        }
        $terms = $GLOBALS['__nct_terms'] ?? [];
        if (!is_array($terms)) {
            return null;
        }
        foreach ($terms as $termId => $row) {
            $row = (array) $row;
            if ((string) ($row['taxonomy'] ?? '') !== $taxonomy) {
                continue;
            }
            if ((string) ($row['slug'] ?? '') === $value) {
                return get_term((int) $termId, $taxonomy);
            }
        }
        return null;
    }
}

if (!function_exists('get_terms')) {
    function get_terms(array $args = []): array
    {
        $taxonomy = isset($args['taxonomy']) ? (string) $args['taxonomy'] : '';
        $fields = isset($args['fields']) ? (string) $args['fields'] : '';
        $terms = $GLOBALS['__nct_terms'] ?? [];
        if (!is_array($terms) || $taxonomy === '') {
            return [];
        }

        $out = [];
        foreach ($terms as $termId => $row) {
            $row = (array) $row;
            if ((string) ($row['taxonomy'] ?? '') !== $taxonomy) {
                continue;
            }
            $out[] = (int) $termId;
        }

        if ($fields === 'ids') {
            return $out;
        }

        $objects = [];
        foreach ($out as $id) {
            $objects[] = get_term($id, $taxonomy);
        }
        return array_values(array_filter($objects));
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        private $code;
        /** @var mixed */
        private $data;

        public function __construct(string $code = '', string $message = '', $data = null)
        {
            $this->code = $code;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_data(string $code = '')
        {
            if ($code === '' || $code === $this->code) {
                return $this->data;
            }
            return null;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return is_object($thing) && $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term(string $name, string $taxonomy, array $args = [])
    {
        $slug = (string) ($args['slug'] ?? '');
        $key = $taxonomy . '|' . $slug;
        $forced = $GLOBALS['__nct_wp_insert_term_term_exists'] ?? [];
        if (is_array($forced) && $slug !== '' && isset($forced[$key])) {
            return new WP_Error('term_exists', 'Term exists', (int) $forced[$key]);
        }

        $next = isset($GLOBALS['__nct_next_term_id']) ? (int) $GLOBALS['__nct_next_term_id'] : 1000;
        $termId = $next;
        $GLOBALS['__nct_next_term_id'] = $next + 1;

        $GLOBALS['__nct_terms'][$termId] = [
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'description' => (string) ($args['description'] ?? ''),
            'parent' => (int) ($args['parent'] ?? 0),
        ];

        return ['term_id' => $termId];
    }
}

if (!function_exists('wp_update_term')) {
    function wp_update_term(int $termId, string $taxonomy, array $args = []): array
    {
        if (!isset($GLOBALS['__nct_terms'][$termId])) {
            return ['term_id' => $termId];
        }
        $row = (array) $GLOBALS['__nct_terms'][$termId];
        $row['taxonomy'] = $taxonomy;
        foreach (['name', 'slug', 'description', 'parent'] as $k) {
            if (array_key_exists($k, $args)) {
                $row[$k] = $args[$k];
            }
        }
        $GLOBALS['__nct_terms'][$termId] = $row;
        return ['term_id' => $termId];
    }
}

if (!function_exists('get_term_meta')) {
    function get_term_meta(int $termId, string $key, bool $single = false)
    {
        $all = $GLOBALS['__nct_term_meta'] ?? [];
        $values = [];
        if (is_array($all) && isset($all[$termId]) && is_array($all[$termId]) && isset($all[$termId][$key])) {
            $values = (array) $all[$termId][$key];
        }
        return $single ? (isset($values[0]) ? $values[0] : '') : $values;
    }
}

if (!function_exists('delete_term_meta')) {
    function delete_term_meta(int $termId, string $key): void
    {
        if (!isset($GLOBALS['__nct_term_meta'][$termId]) || !is_array($GLOBALS['__nct_term_meta'][$termId])) {
            return;
        }
        unset($GLOBALS['__nct_term_meta'][$termId][$key]);
    }
}

if (!function_exists('add_term_meta')) {
    function add_term_meta(int $termId, string $key, $value): void
    {
        if (!isset($GLOBALS['__nct_term_meta'][$termId]) || !is_array($GLOBALS['__nct_term_meta'][$termId])) {
            $GLOBALS['__nct_term_meta'][$termId] = [];
        }
        if (!isset($GLOBALS['__nct_term_meta'][$termId][$key]) || !is_array($GLOBALS['__nct_term_meta'][$termId][$key])) {
            $GLOBALS['__nct_term_meta'][$termId][$key] = [];
        }
        $GLOBALS['__nct_term_meta'][$termId][$key][] = $value;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms(int $objectId, $terms, string $taxonomy): void
    {
        //record calls for assertions
        if (!isset($GLOBALS['__nct_object_terms_calls']) || !is_array($GLOBALS['__nct_object_terms_calls'])) {
            $GLOBALS['__nct_object_terms_calls'] = [];
        }
        $GLOBALS['__nct_object_terms_calls'][] = [
            'objectId' => $objectId,
            'taxonomy' => $taxonomy,
            'terms' => $terms,
        ];

        if (!isset($GLOBALS['__nct_object_terms'][$objectId]) || !is_array($GLOBALS['__nct_object_terms'][$objectId])) {
            $GLOBALS['__nct_object_terms'][$objectId] = [];
        }
        $GLOBALS['__nct_object_terms'][$objectId][$taxonomy] = $terms;
    }
}

//polylang shim for title prefix logic
if (!function_exists('pll_languages_list')) {
    function pll_languages_list(array $args = []): array
    {
        return $GLOBALS['__nct_pll_languages_list'] ?? [];
    }
}

//polylang shim for translate slugs model (toggle via global)
if (!function_exists('PLL')) {
    function PLL(): object
    {
        $slugs = $GLOBALS['__nct_pll_translated_slugs'] ?? [];
        if (!is_array($slugs)) {
            $slugs = [];
        }

        $pll = new \stdClass();

        $pll->model = new class {
            public function get_language(string $slug): ?object
            {
                $slug = trim((string) $slug);
                if ($slug === '') {
                    return null;
                }
                return (object) ['slug' => $slug];
            }
        };

        $pll->translate_slugs = new \stdClass();
        $pll->translate_slugs->slugs_model = new class($slugs) {
            public array $translated_slugs;

            public function __construct(array $slugs)
            {
                $this->translated_slugs = $slugs;
            }

            public function switch_translated_slug(string $link, object $lang, string $type): string
            {
                $langSlug = isset($lang->slug) ? (string) $lang->slug : '';
                if ($langSlug === '' || !isset($this->translated_slugs[$type])) {
                    return $link;
                }
                $entry = $this->translated_slugs[$type];
                $from = isset($entry['slug']) ? (string) $entry['slug'] : '';
                $to = isset($entry['translations'][$langSlug]) ? (string) $entry['translations'][$langSlug] : '';
                if ($from === '' || $to === '') {
                    return $link;
                }
                return str_replace('/' . $from . '/', '/' . $to . '/', $link);
            }
        };

        return $pll;
    }
}

//use WordPress' block parser/serializer without full WP bootstrap
require_once ABSPATH . 'wp-includes/class-wp-block-parser.php';
require_once ABSPATH . 'wp-includes/blocks.php';

//load only the classes needed for unit tests (avoid WP admin components that depend on Novi core)
require_once realpath(__DIR__ . '/../classes/utils/DeepLTranslationCache.php');
require_once realpath(__DIR__ . '/../classes/utils/StringOverrules.php');
require_once realpath(__DIR__ . '/../classes/utils/DeepLTranslator.php');
require_once realpath(__DIR__ . '/../classes/utils/InternalLinkTranslator.php');
require_once realpath(__DIR__ . '/../classes/utils/BlockContentTranslator.php');
require_once realpath(__DIR__ . '/../classes/utils/PostDuplicator.php');
require_once realpath(__DIR__ . '/../classes/utils/TermDuplicator.php');
require_once realpath(__DIR__ . '/../classes/utils/MenuTranslator.php');
require_once realpath(__DIR__ . '/../classes/utils/MenuBlockIdReplacer.php');
require_once realpath(__DIR__ . '/../classes/utils/GravityFormsTranslator.php');

//configure deterministic translations for tests
//dictionary mapping first, fallback to prefix
$GLOBALS['__nct_default_test_translator'] = function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
    $dictionary = [
        'Welcome to Datalyzer' => 'Welkom bij Datalyzer',
        'Certified' => 'Gecertificeerd',
        'Trusted by' => 'Vertrouwd door',
        'Industry leaders' => 'Marktleiders',
        'Dark buttons' => 'Donkere knoppen',
        'List Item' => 'Lijst item',
        'Button' => 'Knop',
        'Get a demo' => 'Plan een demo',
        'United States' => 'Verenigde Staten',
        'This is an alt tag' => 'Deze is een alt-tag',
        'This is a title' => 'Deze is een titel',
        'This is a menu heading' => 'Dit is een menu kop',
        'Pharma' => 'Farmacie',
        'Food & Beverage' => 'Voeding & Drank',
        'Aerospace' => 'Luchtvaart',
        'Automotive' => 'Automotive',
        'Electronics' => 'Elektronica',
        'Factory icon' => 'Fabriek pictogram',
        'Factory badge' => 'Fabriekslabel',
        'Grid alt one' => 'Raster alt een',
        'Grid caption one' => 'Raster bijschrift een',
        'Grid description one' => 'Raster beschrijving een',
        'Grid title one' => 'Raster titel een',
        'Gallery alt one' => 'Galerij alt een',
        'Gallery caption one' => 'Galerij bijschrift een',
        'Gallery description one' => 'Galerij beschrijving een',
        'Gallery title one' => 'Galerij titel een',
        'SPC Module' => 'SPC module',
        'Detect issues early' => 'Problemen vroeg detecteren',
        'Diagram icon' => 'Diagram pictogram',
        'Diagram badge' => 'Diagram label',
        'FMEA Module' => 'FMEA module',
        'DataLyzer is a key partner for Philips Healthcare.' => 'DataLyzer is een belangrijke partner voor Philips Healthcare.',
        'Yield Engineer, Philips' => 'Yield engineer, Philips',
        'Testimonial portrait' => 'Testimonial portret',
        'Testimonial headshot' => 'Testimonial profielfoto',
        'This policy provides the framework for establishing a secure and resilient environment that ensures trust, compliance, and operational excellence.' => 'Dit beleid biedt het kader voor een veilige en veerkrachtige omgeving die vertrouwen, naleving en operationele excellentie waarborgt.',
        '<p>This policy provides the framework for establishing a secure and resilient environment that ensures trust, compliance, and operational excellence.</p>' => '<p>Dit beleid biedt het kader voor een veilige en veerkrachtige omgeving die vertrouwen, naleving en operationele excellentie waarborgt.</p>',
        'Information security objectives are established, monitored, and reviewed regularly to support our business goals.' => 'Doelstellingen voor informatiebeveiliging worden vastgesteld, gemonitord en regelmatig herzien ter ondersteuning van onze bedrijfsdoelen.',
        '<li>Information security objectives are established, monitored, and reviewed regularly to support our business goals.</li>' => '<li>Doelstellingen voor informatiebeveiliging worden vastgesteld, gemonitord en regelmatig herzien ter ondersteuning van onze bedrijfsdoelen.</li>',
        'All applicable legal, regulatory, contractual, and stakeholder requirements are identified and complied with.' => 'Alle toepasselijke wettelijke, regelgevende, contractuele en stakeholdervereisten worden geïdentificeerd en nageleefd.',
        '<li>All applicable legal, regulatory, contractual, and stakeholder requirements are identified and complied with.</li>' => '<li>Alle toepasselijke wettelijke, regelgevende, contractuele en stakeholdervereisten worden geïdentificeerd en nageleefd.</li>',
        'Risks to information assets are systematically assessed and managed through appropriate controls.' => 'Risico’s voor informatie-assets worden systematisch beoordeeld en beheerd met passende beheersmaatregelen.',
        '<li>Risks to information assets are systematically assessed and managed through appropriate controls.</li>' => '<li>Risico’s voor informatie-assets worden systematisch beoordeeld en beheerd met passende beheersmaatregelen.</li>',
        //simulate DeepL returning double-encoded angle bracket entities
        '&lt;Promise on service to customer&gt' => '&lt;Belofte over service aan klant&amp;gt',
    ];

    $translations = [];
    foreach ($texts as $t) {
        $trimmed = is_string($t) ? trim($t) : '';
        if ($trimmed === '') {
            $translations[] = $t;
            continue;
        }
        $translations[] = $dictionary[$trimmed] ?? ('[NL]' . $t);
    }
    return [
        'success' => true,
        'translations' => $translations,
        'error' => null,
    ];
};
NoviOnline\ContentTranslator\Core\DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator']);

