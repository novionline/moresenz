<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Serves cached snippet assets via AO-safe rewrite URLs.
 */
class SnippetAssetServeComponent extends Singleton
{
    const REWRITE_FLAG_OPTION = 'ncs_rewrite_flushed';

    protected function __construct()
    {
        add_action('init', [$this, 'registerRewriteRules'], 1);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'maybeServeAsset'], 0);
    }

    /**
     * @return void
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            '^novi-code-snippets/(css|js)/([0-9]+-[a-f0-9]{12}\.(css|js))/?$',
            'index.php?ncs_asset_type=$matches[1]&ncs_asset_file=$matches[2]',
            'top'
        );
        if (self::shouldFlushRewriteRules()) {
            flush_rewrite_rules(false);
            update_option(self::REWRITE_FLAG_OPTION, 1, false);
        }
    }

    /**
     * Flush rewrite rules on first run and after plugin version changes.
     * @return bool
     */
    protected static function shouldFlushRewriteRules(): bool
    {
        if (!get_option(self::REWRITE_FLAG_OPTION)) {
            return true;
        }

        $storedVersion = (string) get_option(NoviCodeSnippets::VERSION_OPTION, '');
        return $storedVersion !== NoviCodeSnippets::VERSION;
    }

    /**
     * @param array $vars
     * @return array
     */
    public function addQueryVars(array $vars): array
    {
        $vars[] = 'ncs_asset_type';
        $vars[] = 'ncs_asset_file';
        return $vars;
    }

    /**
     * @return void
     */
    public function maybeServeAsset(): void
    {
        $type = get_query_var('ncs_asset_type');
        $basename = get_query_var('ncs_asset_file');
        if (!is_string($type) || !is_string($basename) || $type === '' || $basename === '') {
            return;
        }
        if ($type !== 'css' && $type !== 'js') {
            status_header(404);
            exit;
        }
        $path = SnippetAssetCache::resolveSecurePath($type, $basename);
        if ($path === null) {
            status_header(404);
            exit;
        }
        $contentType = $type === 'css' ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8';
        header('Content-Type: ' . $contentType);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($path);
        exit;
    }
}
