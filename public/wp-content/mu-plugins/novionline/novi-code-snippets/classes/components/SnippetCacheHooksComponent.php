<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Regenerates snippet asset cache on post lifecycle and external cache purges.
 */
class SnippetCacheHooksComponent extends Singleton
{
    private static bool $suppressExternalFlush = false;

    protected function __construct()
    {
        add_action('before_delete_post', [$this, 'onBeforeDeletePost']);
        add_action('trashed_post', [$this, 'onTrashedPost']);
        add_action('autoptimize_action_cachepurged', [$this, 'onExternalCachePurged']);
        add_action('admin_init', [$this, 'onCometCacheAdminAction'], 5);
        add_action('init', [$this, 'maybeUpgradeAfterDeploy'], 0);
    }

    /**
     * Regenerate cache files and rewrite rules after a plugin deploy.
     * @return void
     */
    public function maybeUpgradeAfterDeploy(): void
    {
        $storedVersion = (string) get_option(NoviCodeSnippets::VERSION_OPTION, '');
        if ($storedVersion === NoviCodeSnippets::VERSION) {
            return;
        }

        delete_option(SnippetAssetServeComponent::REWRITE_FLAG_OPTION);
        SnippetAssetCache::flushAll(false);
        update_option(NoviCodeSnippets::VERSION_OPTION, NoviCodeSnippets::VERSION, false);
    }

    /**
     * @param int $postId
     * @return void
     */
    public function onBeforeDeletePost(int $postId): void
    {
        if (get_post_type($postId) !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        SnippetAssetCache::deleteForPost($postId);
    }

    /**
     * @param int $postId
     * @return void
     */
    public function onTrashedPost(int $postId): void
    {
        if (get_post_type($postId) !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        SnippetAssetCache::deleteForPost($postId);
    }

    /**
     * @param bool $suppress
     * @return void
     */
    public static function suppressExternalFlush(bool $suppress): void
    {
        self::$suppressExternalFlush = $suppress;
    }

    /**
     * @return void
     */
    public function onExternalCachePurged(): void
    {
        if (self::$suppressExternalFlush) {
            return;
        }
        SnippetAssetCache::flushAll(true);
    }

    /**
     * Comet Cache does not expose standard WP purge hooks; detect admin wipe/clear actions.
     * @return void
     */
    public function onCometCacheAdminAction(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $namespace = 'comet_cache';
        if (empty($_REQUEST[$namespace]) || !is_array($_REQUEST[$namespace])) {
            return;
        }
        $actions = $_REQUEST[$namespace];
        $purgeKeys = ['wipeCache', 'clearCache', 'ajaxWipeCache', 'ajaxClearCache'];
        foreach ($purgeKeys as $purgeKey) {
            if (!empty($actions[$purgeKey])) {
                SnippetAssetCache::flushAll(true);
                return;
            }
        }
    }
}
