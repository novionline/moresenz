<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Admin flush control and storage health notices for snippet asset cache.
 */
class SnippetCacheAdminComponent extends Singleton
{
    const FLUSH_LOCK_TRANSIENT = 'ncs_flush_snippet_cache_lock';

    const FLUSH_LOCK_SECONDS = 30;

    protected function __construct()
    {
        add_action('restrict_manage_posts', [$this, 'renderFlushButton'], 10, 2);
        add_action('wp_ajax_ncs_flush_snippet_cache', [$this, 'ajaxFlushSnippetCache']);
        add_action('admin_notices', [$this, 'maybeShowStorageNotice']);
        add_action('admin_notices', [$this, 'maybeShowFlushNotice']);
    }

    /**
     * @param string $postType
     * @param string $which
     * @return void
     */
    public function renderFlushButton(string $postType, string $which): void
    {
        if ($postType !== CodeSnippetsPostType::POST_TYPE || $which !== 'top') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        $nonce = wp_create_nonce('ncs_flush_snippet_cache');
        echo '<button type="button" class="button" id="ncs-flush-snippet-cache" data-nonce="' . esc_attr($nonce) . '">';
        echo esc_html__('Flush snippet cache', NoviCodeSnippets::TEXT_DOMAIN);
        echo '</button>';
        echo '<span id="ncs-flush-snippet-cache-status" style="margin-left:8px;"></span>';
        add_action('admin_footer', [$this, 'outputFlushScript']);
    }

    /**
     * @return void
     */
    public function outputFlushScript(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        ?>
        <script>
        (function () {
            const button = document.getElementById('ncs-flush-snippet-cache')
            const status = document.getElementById('ncs-flush-snippet-cache-status')
            if (!button || !status) {
                return
            }
            button.addEventListener('click', function () {
                if (!confirm('<?php echo esc_js(__('Regenerate all snippet CSS/JS cache files?', NoviCodeSnippets::TEXT_DOMAIN)); ?>')) {
                    return
                }
                button.disabled = true
                status.textContent = '<?php echo esc_js(__('Flushing…', NoviCodeSnippets::TEXT_DOMAIN)); ?>'
                const body = new URLSearchParams()
                body.set('action', 'ncs_flush_snippet_cache')
                body.set('nonce', button.getAttribute('data-nonce') || '')
                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json()
                }).then(function (data) {
                    button.disabled = false
                    if (data && data.success) {
                        status.textContent = data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Cache flushed.', NoviCodeSnippets::TEXT_DOMAIN)); ?>'
                    } else {
                        status.textContent = data && data.data && data.data.message ? data.data.message : '<?php echo esc_js(__('Flush failed.', NoviCodeSnippets::TEXT_DOMAIN)); ?>'
                    }
                }).catch(function () {
                    button.disabled = false
                    status.textContent = '<?php echo esc_js(__('Flush failed.', NoviCodeSnippets::TEXT_DOMAIN)); ?>'
                })
            })
        })()
        </script>
        <?php
    }

    /**
     * @return void
     */
    public function ajaxFlushSnippetCache(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', NoviCodeSnippets::TEXT_DOMAIN)], 403);
        }
        check_ajax_referer('ncs_flush_snippet_cache', 'nonce');
        if (get_transient(self::FLUSH_LOCK_TRANSIENT)) {
            wp_send_json_error(['message' => __('Please wait before flushing again.', NoviCodeSnippets::TEXT_DOMAIN)], 429);
        }
        set_transient(self::FLUSH_LOCK_TRANSIENT, 1, self::FLUSH_LOCK_SECONDS);
        $counts = SnippetAssetCache::flushAll(true);
        self::clearExternalCaches();
        $message = sprintf(
            __('Regenerated %1$d CSS and %2$d JS files; removed %3$d orphan(s).', NoviCodeSnippets::TEXT_DOMAIN),
            (int) $counts['css'],
            (int) $counts['js'],
            (int) $counts['orphans']
        );
        set_transient('ncs_flush_snippet_cache_notice', $message, 60);
        wp_send_json_success(['message' => $message, 'counts' => $counts]);
    }

    /**
     * @return void
     */
    public function maybeShowStorageNotice(): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        if (SnippetAssetCache::isStorageWritable()) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Novi Code Snippets cannot write to the uploads cache directory. Snippet assets may not load on the front-end.', NoviCodeSnippets::TEXT_DOMAIN);
        echo '</p></div>';
    }

    /**
     * @return void
     */
    public function maybeShowFlushNotice(): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        $message = get_transient('ncs_flush_snippet_cache_notice');
        if (!is_string($message) || $message === '') {
            return;
        }
        delete_transient('ncs_flush_snippet_cache_notice');
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * @return void
     */
    protected static function clearExternalCaches(): void
    {
        SnippetCacheHooksComponent::suppressExternalFlush(true);
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
        if (isset($GLOBALS['comet_cache']) && is_object($GLOBALS['comet_cache']) && method_exists($GLOBALS['comet_cache'], 'clearCache')) {
            $GLOBALS['comet_cache']->clearCache(true);
        }
        SnippetCacheHooksComponent::suppressExternalFlush(false);
    }
}
