<?php

namespace NoviOnline\NectarBlocksCssGuard;

use NoviOnline\Core\Singleton;

/**
 * Guards NectarBlocks live CSS meta and enqueues stable blockId editor script.
 *
 * @package NoviOnline\NectarBlocksCssGuard
 */
class NectarBlocksCssGuard extends Singleton {

    public const LIVE_CSS_META_KEY = '_nectar_blocks_css';
    public const PREVIEW_CSS_META_KEY = '_nectar_blocks_css_preview';
    public const SCRIPT_HANDLE = 'novionline-nectar-blocks-stable-uids';
    public const CHILD_THEME_UID_HANDLE = 'novionline_fix_gutenberg_duplicate_ids';

    /**
     * @var bool
     */
    private bool $allowLiveCssMetaWrite = false;

    /**
     * NectarBlocksCssGuard constructor.
     */
    protected function __construct() {
        add_filter('rest_pre_dispatch', [$this, 'guardCssRestWrite'], 10, 3);
        add_filter('update_post_metadata', [$this, 'guardLiveCssMetaUpdate'], 10, 5);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueStableUidScript'], 20);
    }

    /**
     * Intercept Nectar CSS REST routes before live meta can diverge from published content.
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function guardCssRestWrite($result, $server, $request) {
        if ($result !== null) {
            return $result;
        }

        $route = $request->get_route();
        if (!$this->isNectarCssUpdateRoute($route)) {
            return $result;
        }

        if (strtoupper($request->get_method()) !== 'POST') {
            return $result;
        }

        $jsonBody = $request->get_json_params();
        if (!is_array($jsonBody)) {
            $jsonBody = [];
        }

        $autosave = !empty($jsonBody['autosave']);
        if ($autosave) {
            //let nectar write preview meta only
            return $result;
        }

        //widgets css is stored in an option, not post meta
        if (($jsonBody['type'] ?? '') === 'widgets') {
            return $result;
        }

        $css = isset($jsonBody['css']) ? (string) $jsonBody['css'] : '';
        $postId = $this->resolvePostIdFromCssRequest($route, $jsonBody);
        if (!$postId) {
            return $result;
        }

        if ($this->cssMatchesPublishedContent($css, $postId)) {
            return $result;
        }

        //keep live css intact; stash mismatched css as preview and ack success
        update_post_meta($postId, self::PREVIEW_CSS_META_KEY, $css);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[nectar-blocks-css-guard] blocked live CSS write for post %d (CSS blockIds do not match published content)',
                $postId
            ));
        }

        return new \WP_REST_Response(['status' => 'success', 'novi_css_guard' => 'blocked_live_write'], 200);
    }

    /**
     * Belt-and-suspenders: block direct live CSS meta writes that fail the invariant.
     *
     * @param mixed $check
     * @param int $objectId
     * @param string $metaKey
     * @param mixed $metaValue
     * @param mixed $prevValue
     * @return mixed
     */
    public function guardLiveCssMetaUpdate($check, $objectId, $metaKey, $metaValue, $prevValue) {
        if ($check !== null) {
            return $check;
        }

        if ($metaKey !== self::LIVE_CSS_META_KEY) {
            return $check;
        }

        if ($this->allowLiveCssMetaWrite) {
            return $check;
        }

        $css = is_string($metaValue) ? $metaValue : '';
        if ($this->cssMatchesPublishedContent($css, (int) $objectId)) {
            return $check;
        }

        update_post_meta((int) $objectId, self::PREVIEW_CSS_META_KEY, $css);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[nectar-blocks-css-guard] blocked update_post_meta live CSS for post %d',
                (int) $objectId
            ));
        }

        //short-circuit as success so callers do not retry/error
        return true;
    }

    /**
     * Enqueue stable UID script and dequeue the always-regenerate child theme script.
     *
     * @return void
     */
    public function enqueueStableUidScript(): void {
        wp_dequeue_script(self::CHILD_THEME_UID_HANDLE);
        wp_deregister_script(self::CHILD_THEME_UID_HANDLE);

        $scriptPath = NECTAR_BLOCKS_CSS_GUARD_PLUGIN_PATH . 'assets/stable-block-uids.js';
        if (!is_file($scriptPath)) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            NECTAR_BLOCKS_CSS_GUARD_PLUGIN_URL . '/assets/stable-block-uids.js',
            ['wp-data', 'wp-blocks', 'wp-element'],
            (string) filemtime($scriptPath),
            true
        );
    }

    /**
     * Allow a trusted live CSS meta write (e.g. recovery scripts).
     *
     * @param callable $callback
     * @return mixed
     */
    public function withLiveCssWriteAllowed(callable $callback) {
        $this->allowLiveCssMetaWrite = true;
        try {
            return $callback();
        } finally {
            $this->allowLiveCssMetaWrite = false;
        }
    }

    /**
     * @param string $route
     * @return bool
     */
    private function isNectarCssUpdateRoute(string $route): bool {
        $routes = [
            '/nectar/v1/meta/css/update',
            '/nectar/v1/meta/css/update/pattern',
            '/nectar/v1/meta/css/update/wp_template_part',
        ];

        foreach ($routes as $allowedRoute) {
            if ($route === $allowedRoute || str_ends_with($route, $allowedRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $route
     * @param array $jsonBody
     * @return int
     */
    private function resolvePostIdFromCssRequest(string $route, array $jsonBody): int {
        if (str_contains($route, '/update/wp_template_part')) {
            $templateId = isset($jsonBody['post_id']) ? (string) $jsonBody['post_id'] : '';
            $partType = isset($jsonBody['part_type']) ? (string) $jsonBody['part_type'] : 'wp_template_part';
            if ($templateId === '' || !function_exists('get_block_template')) {
                return 0;
            }

            $blockTemplate = get_block_template($templateId, $partType);
            if (!$blockTemplate || empty($blockTemplate->wp_id)) {
                return 0;
            }

            return (int) $blockTemplate->wp_id;
        }

        return isset($jsonBody['post_id']) ? (int) $jsonBody['post_id'] : 0;
    }

    /**
     * Live CSS may only reference blockIds that exist in published post_content.
     *
     * @param string $css
     * @param int $postId
     * @return bool
     */
    public function cssMatchesPublishedContent(string $css, int $postId): bool {
        if ($postId <= 0) {
            return true;
        }

        $cssIds = $this->extractBlockIdsFromCss($css);
        if (!$cssIds) {
            return true;
        }

        $post = get_post($postId);
        if (!$post) {
            return true;
        }

        $contentIds = $this->extractBlockIdsFromContent((string) $post->post_content);

        //first paint / empty content: allow write
        if (!$contentIds) {
            return true;
        }

        foreach ($cssIds as $cssId => $present) {
            if (!isset($contentIds[$cssId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $css
     * @return array<string, true>
     */
    public function extractBlockIdsFromCss(string $css): array {
        $ids = [];
        if ($css === '') {
            return $ids;
        }

        if (preg_match_all('/#((?:block-)[a-zA-Z0-9]+)/', $css, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param string $content
     * @return array<string, true>
     */
    public function extractBlockIdsFromContent(string $content): array {
        $ids = [];
        if ($content === '') {
            return $ids;
        }

        $patterns = [
            '/"blockId"\s*:\s*"(block-[^"]+)"/',
            '/"id"\s*:\s*"(block-[^"]+)"/',
            '/\bid="(block-[^"]+)"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }
}
