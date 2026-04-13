<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class SnippetsOutputComponent
 * Outputs active, valid CSS snippets in wp_head and JS snippets in wp_footer.
 * @package NoviOnline\CodeSnippets
 */
class SnippetsOutputComponent extends Singleton
{
    protected function __construct()
    {
        add_action('wp_head', [$this, 'outputCssSnippets'], 25);
        add_action('wp_footer', [$this, 'outputJsSnippets'], 25);
        add_action('enqueue_block_assets', [$this, 'enqueueSnippetCssInBlockEditor'], 1);
        add_filter('style_loader_tag', [$this, 'filterSnippetStyleTagInEditor'], 10, 4);
    }

    /**
     * Get snippet id attribute: novi-code-snippet-{post_id}
     * @param \WP_Post $post
     * @return string
     */
    protected static function getSnippetId(\WP_Post $post): string
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
            $code = get_post_meta($post->ID, SnippetValidator::META_MINIFIED, true);
            if ($code === '' || $code === false) {
                $code = get_field('snippet_code', $post->ID);
                if (!is_string($code) || trim($code) === '') {
                    continue;
                }
                try {
                    $code = Minify::css($code);
                } catch (\Throwable $e) {
                    continue;
                }
            }
            $handle = self::getSnippetId($post);
            wp_register_style($handle, false, [$prevHandle]);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $code);
            $prevHandle = $handle;
        }
    }

    /**
     * Add data-snippet-title to our snippet style tags in the editor so they match the front-end.
     * @param string $tag
     * @param string $handle
     * @param string $href
     * @param string $media
     * @return string
     */
    public function filterSnippetStyleTagInEditor(string $tag, string $handle, string $href, string $media): string
    {
        if (strpos($handle, 'novi-code-snippet-') !== 0) {
            return $tag;
        }
        $postId = (int) str_replace('novi-code-snippet-', '', $handle);
        if ($postId <= 0) {
            return $tag;
        }
        $post = get_post($postId);
        $titleAttr = $post ? self::getSnippetTitleAttr($post) : 'snippet-' . $postId;
        $attr = ' data-snippet-title="' . esc_attr($titleAttr) . '"';
        return preg_replace('/^(<(?:style|link)[^>]+)/', '$1' . $attr, $tag, 1);
    }

    /**
     * Output CSS snippets in head
     * @return void
     */
    public function outputCssSnippets(): void
    {
        $snippets = self::getActiveSnippets();
        foreach ($snippets as $post) {
            $type = get_field('snippet_type', $post->ID);
            if ($type !== 'css') {
                continue;
            }
            $code = get_post_meta($post->ID, SnippetValidator::META_MINIFIED, true);
            if ($code === '' || $code === false) {
                $code = get_field('snippet_code', $post->ID);
                if (!is_string($code) || trim($code) === '') {
                    continue;
                }
                try {
                    $code = Minify::css($code);
                } catch (\Throwable $e) {
                    continue;
                }
            }
            $id = self::getSnippetId($post);
            $titleAttr = self::getSnippetTitleAttr($post);
            echo '<style id="' . esc_attr($id) . '" data-snippet-title="' . esc_attr($titleAttr) . '">' . $code . '</style>' . "\n";
        }
    }

    /**
     * Output JS snippets in footer
     * @return void
     */
    public function outputJsSnippets(): void
    {
        $snippets = self::getActiveSnippets();
        foreach ($snippets as $post) {
            $type = get_field('snippet_type', $post->ID);
            if ($type !== 'js') {
                continue;
            }
            $code = get_post_meta($post->ID, SnippetValidator::META_MINIFIED, true);
            if ($code === '' || $code === false) {
                $code = get_field('snippet_code', $post->ID);
                if (!is_string($code) || trim($code) === '') {
                    continue;
                }
                try {
                    $code = Minify::js($code);
                } catch (\Throwable $e) {
                    continue;
                }
            }
            $id = self::getSnippetId($post);
            $titleAttr = self::getSnippetTitleAttr($post);
            echo '<script id="' . esc_attr($id) . '" data-snippet-title="' . esc_attr($titleAttr) . '">' . $code . '</script>' . "\n";
        }
    }
}
