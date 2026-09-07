<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class SnippetsOutputComponent
 * Outputs active, valid CSS/JS snippets via the WordPress asset API so Autoptimize can aggregate them.
 * @package NoviOnline\CodeSnippets
 */
class SnippetsOutputComponent extends Singleton
{
    protected function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendSnippets'], 100);
        add_action('enqueue_block_assets', [$this, 'enqueueSnippetCssInBlockEditor'], 1);
        add_filter('style_loader_tag', [$this, 'filterSnippetStyleTag'], 10, 4);
        add_filter('script_loader_tag', [$this, 'filterSnippetScriptTag'], 10, 3);
        add_filter('autoptimize_filter_css_exclude', [$this, 'filterAutoptimizeCssExclude']);
        add_filter('autoptimize_filter_js_exclude', [$this, 'filterAutoptimizeJsExclude']);
        add_filter('autoptimize_filter_base_getpath_path', [$this, 'filterAutoptimizeGetpath'], 10, 2);
    }

    /**
     * WordPress enqueue handle (kept short so Autoptimize exclude rules do not match the style/script id).
     * @param \WP_Post $post
     * @return string
     */
    protected static function getSnippetHandle(\WP_Post $post): string
    {
        return 'ncs-' . (int) $post->ID;
    }

    /**
     * Legacy DOM id for snippet elements: novi-code-snippet-{post_id}
     * @param \WP_Post $post
     * @return string
     */
    protected static function getSnippetDomId(\WP_Post $post): string
    {
        return 'novi-code-snippet-' . (int) $post->ID;
    }

    /**
     * Get attribute-safe snippet title for data-snippet-title
     * @param \WP_Post $post
     * @return string
     */
    protected static function getSnippetTitleAttr(\WP_Post $post): string
    {
        $slug = sanitize_title($post->post_title);
        return $slug !== '' ? $slug : 'snippet-' . (int) $post->ID;
    }

    /**
     * Get published snippets that are active and valid
     * @return \WP_Post[]
     */
    protected static function getActiveSnippets(): array
    {
        $query = new \WP_Query([
            'post_type' => CodeSnippetsPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'ncs_type' => ['key' => 'snippet_type', 'compare' => 'EXISTS'],
                'ncs_priority' => ['key' => 'snippet_priority', 'compare' => 'EXISTS', 'type' => 'NUMERIC']
            ],
            'orderby' => ['ncs_type' => 'ASC', 'ncs_priority' => 'ASC'],
            'order' => 'ASC'
        ]);
        $snippets = [];
        foreach ($query->posts as $post) {
            $enabled = get_field('snippet_enabled', $post->ID);
            if (!$enabled) {
                continue;
            }
            $code = get_field('snippet_code', $post->ID);
            if (!is_string($code) || trim($code) === '') {
                continue;
            }
            $valid = get_post_meta($post->ID, SnippetValidator::META_VALID, true);
            //only output when explicitly valid; missing or '0' means do not output
            if ($valid !== '1') {
                continue;
            }
            $snippets[] = $post;
        }
        wp_reset_postdata();
        return $snippets;
    }

    /**
     * Resolve minified snippet code for output.
     * @param \WP_Post $post
     * @param string $type css|js
     * @return string|null
     */
    protected static function getMinifiedCode(\WP_Post $post, string $type): ?string
    {
        $code = get_post_meta($post->ID, SnippetValidator::META_MINIFIED, true);
        if ($code === '' || $code === false) {
            $code = get_field('snippet_code', $post->ID);
            if (!is_string($code) || trim($code) === '') {
                return null;
            }
            try {
                $code = $type === 'css' ? Minify::css($code) : Minify::js($code);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Prefer cached file assets; fall back to inline output when write or disk read fails.
     * @param \WP_Post $post
     * @param string $type css|js
     * @return array{mode: 'file', url: string, version: string}|array{mode: 'inline', code: string}|null
     */
    protected static function resolveSnippetOutput(\WP_Post $post, string $type): ?array
    {
        $code = self::getMinifiedCode($post, $type);
        if ($code === null) {
            return null;
        }
        $url = SnippetAssetCache::ensureForPost($post, $type);
        if ($url !== null) {
            $basename = get_post_meta((int) $post->ID, SnippetAssetCache::getMetaKey($type), true);
            if (is_string($basename) && $basename !== '') {
                $version = SnippetAssetCache::getHashVersionFromBasename($basename);
                $storagePath = SnippetAssetCache::resolveSecurePath($type, $basename);
                if ($version !== null && $storagePath !== null && is_readable($storagePath)) {
                    return ['mode' => 'file', 'url' => $url, 'version' => $version];
                }
            }
        }
        return ['mode' => 'inline', 'code' => $code];
    }

    /**
     * @param string $handle
     * @param array{mode: 'file', url: string, version: string}|array{mode: 'inline', code: string} $output
     * @param string|null $depHandle
     * @return void
     */
    protected static function enqueueSnippetCss(string $handle, array $output, ?string $depHandle): void
    {
        $deps = $depHandle ? [$depHandle] : [];
        if ($output['mode'] === 'file') {
            wp_register_style($handle, $output['url'], $deps, $output['version']);
            wp_enqueue_style($handle);
            return;
        }
        wp_register_style($handle, false, $deps);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $output['code']);
    }

    /**
     * @param string $handle
     * @param array{mode: 'file', url: string, version: string}|array{mode: 'inline', code: string} $output
     * @return void
     */
    protected static function enqueueSnippetJs(string $handle, array $output): void
    {
        if ($output['mode'] === 'file') {
            wp_enqueue_script($handle, $output['url'], [], $output['version'], true);
            return;
        }
        wp_register_script($handle, false, [], null, true);
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, $output['code']);
    }

    /**
     * Enqueue CSS and JS snippets on the front-end through WordPress so Autoptimize can merge them.
     * @return void
     */
    public function enqueueFrontendSnippets(): void
    {
        if (is_admin()) {
            return;
        }

        $snippets = self::getActiveSnippets();
        $prevCssHandle = null;

        foreach ($snippets as $post) {
            $type = get_field('snippet_type', $post->ID);
            $handle = self::getSnippetHandle($post);

            if ($type === 'css') {
                $output = self::resolveSnippetOutput($post, 'css');
                if ($output === null) {
                    continue;
                }
                self::enqueueSnippetCss($handle, $output, $prevCssHandle);
                $prevCssHandle = $handle;
                continue;
            }

            if ($type === 'js') {
                $output = self::resolveSnippetOutput($post, 'js');
                if ($output === null) {
                    continue;
                }
                self::enqueueSnippetJs($handle, $output);
            }
        }
    }

    /**
     * Enqueue each CSS snippet as a separate style in the Gutenberg editor (same order and structure as front-end).
     * @return void
     */
    public function enqueueSnippetCssInBlockEditor(): void
    {
        if (!is_admin()) {
            return;
        }
        $snippets = self::getActiveSnippets();
        $baseHandle = wp_style_is('nectar-block-editor-styles', 'registered') ? 'nectar-block-editor-styles' : 'wp-block-library';
        $prevHandle = $baseHandle;
        foreach ($snippets as $post) {
            $type = get_field('snippet_type', $post->ID);
            if ($type !== 'css') {
                continue;
            }
            $handle = self::getSnippetHandle($post);
            $output = self::resolveSnippetOutput($post, 'css');
            if ($output === null) {
                continue;
            }
            self::enqueueSnippetCss($handle, $output, $prevHandle);
            $prevHandle = $handle;
        }
    }

    /**
     * Add data-snippet-title to snippet style tags.
     * @param string $tag
     * @param string $handle
     * @param string $href
     * @param string $media
     * @return string
     */
    public function filterSnippetStyleTag(string $tag, string $handle, string $href, string $media): string
    {
        if (strpos($handle, 'ncs-') !== 0) {
            return $tag;
        }
        $postId = (int) substr($handle, 4);
        if ($postId <= 0) {
            return $tag;
        }
        $post = get_post($postId);
        $titleAttr = $post ? self::getSnippetTitleAttr($post) : 'snippet-' . $postId;
        $domId = $post ? self::getSnippetDomId($post) : 'novi-code-snippet-' . $postId;
        $attr = ' data-snippet-id="' . esc_attr($domId) . '" data-snippet-title="' . esc_attr($titleAttr) . '"';
        if (preg_match('/^(<link\b[^>]*?)(\s*\/?>)/', $tag, $matches)) {
            return $matches[1] . $attr . $matches[2];
        }
        return preg_replace('/^(<style\b[^>]*?)(\s*\/?>)/', '$1' . $attr . '$2', $tag, 1);
    }

    /**
     * Add data-snippet-title to snippet script tags.
     * @param string $tag
     * @param string $handle
     * @param string $src
     * @return string
     */
    public function filterSnippetScriptTag(string $tag, string $handle, string $src): string
    {
        if (strpos($handle, 'ncs-') !== 0) {
            return $tag;
        }
        $postId = (int) substr($handle, 4);
        if ($postId <= 0) {
            return $tag;
        }
        $post = get_post($postId);
        $titleAttr = $post ? self::getSnippetTitleAttr($post) : 'snippet-' . $postId;
        $domId = $post ? self::getSnippetDomId($post) : 'novi-code-snippet-' . $postId;
        $attr = ' data-snippet-id="' . esc_attr($domId) . '" data-snippet-title="' . esc_attr($titleAttr) . '"';
        return preg_replace('/^<script\b/', '<script' . $attr, $tag, 1);
    }

    /**
     * Map AO-safe rewrite URLs to physical uploads paths so Autoptimize can aggregate snippet assets.
     * @param string $path
     * @param string $url
     * @return string
     */
    public function filterAutoptimizeGetpath(string $path, string $url): string
    {
        if (!preg_match('#/novi-code-snippets/(css|js)/([0-9]+-[a-f0-9]{12}\.(?:css|js))(?:\?|$)#', $url, $matches)) {
            return $path;
        }
        $storagePath = SnippetAssetCache::getStorageDir($matches[1]) . '/' . $matches[2];
        if (is_file($storagePath) && is_readable($storagePath)) {
            return $storagePath;
        }
        return $path;
    }

    /**
     * Prevent Autoptimize CSS exclude rules from matching snippet handles by accident.
     * @param string $exclude
     * @return string
     */
    public function filterAutoptimizeCssExclude(string $exclude): string
    {
        return self::removeAutoptimizeExcludeFragments($exclude);
    }

    /**
     * Prevent Autoptimize JS exclude rules from matching snippet handles by accident.
     * @param string $exclude
     * @return string
     */
    public function filterAutoptimizeJsExclude(string $exclude): string
    {
        return self::removeAutoptimizeExcludeFragments($exclude);
    }

    /**
     * Strip novi-code-snippet fragments from an Autoptimize comma-separated exclude list.
     * @param string $exclude
     * @return string
     */
    protected static function removeAutoptimizeExcludeFragments(string $exclude): string
    {
        if ($exclude === '') {
            return $exclude;
        }
        $parts = array_filter(array_map('trim', explode(',', $exclude)));
        $parts = array_values(array_filter($parts, static function (string $part): bool {
            return stripos($part, 'novi-code-snippet') === false;
        }));
        return implode(',', $parts);
    }
}
