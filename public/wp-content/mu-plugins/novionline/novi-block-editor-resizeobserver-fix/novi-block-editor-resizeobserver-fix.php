<?php

/**
 * Plugin Name:     Novi Block Editor ResizeObserver Fix
 * Plugin URI:      https://novionline.nl
 * Description:     Backports Gutenberg #79178 — null-safe blockView?.ResizeObserver in useBlockToolbarPopoverProps (WP 7.0 crash when editing synced patterns).
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache directory for patched block-editor scripts.
 */
function noviBlockEditorRoFixCacheDir(): string
{
    return WP_CONTENT_DIR . '/cache/novi-block-editor-resizeobserver-fix';
}

/**
 * Whether a JS blob still contains the buggy unguarded ResizeObserver access.
 */
function noviBlockEditorRoFixNeedsPatch(string $js): bool
{
    //minified: p.ResizeObserver&&(h=new p.ResizeObserver
    //unminified: if (blockView.ResizeObserver) {
    return str_contains($js, 'p.ResizeObserver&&(h=new p.ResizeObserver')
        || str_contains($js, 'if (blockView.ResizeObserver)')
        || str_contains($js, 'addEventHandler?.("resize"');
}

/**
 * Apply Gutenberg #79178 string replacements.
 */
function noviBlockEditorRoFixPatchJs(string $js): string
{
    $replacements = [
        //minified null guard + event listener typo fix
        'm?.addEventHandler?.("resize",f);let h,p=o?.ownerDocument?.defaultView;return p.ResizeObserver&&(h=new p.ResizeObserver(f),h.observe(o)),()=>{m?.removeEventHandler?.("resize",f),h&&h.disconnect()}'
            => 'm?.addEventListener?.("resize",f);let h,p=o?.ownerDocument?.defaultView;return p?.ResizeObserver&&(h=new p.ResizeObserver(f),h.observe(o)),()=>{m?.removeEventListener?.("resize",f),h&&h.disconnect()}',
        //unminified
        'contentView?.addEventHandler?.("resize", updateProps);'
            => 'contentView?.addEventListener?.("resize", updateProps);',
        'contentView?.removeEventHandler?.("resize", updateProps);'
            => 'contentView?.removeEventListener?.("resize", updateProps);',
        'if (blockView.ResizeObserver) {'
            => 'if (blockView?.ResizeObserver) {',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $js);
}

/**
 * Build or reuse a patched copy of the core block-editor script.
 *
 * @param bool $minified Whether to patch the .min.js build.
 * @return string|null Absolute path to patched file, or null on failure.
 */
function noviBlockEditorRoFixEnsurePatchedFile(bool $minified): ?string
{
    $basename = $minified ? 'block-editor.min.js' : 'block-editor.js';
    $corePath = ABSPATH . 'wp-includes/js/dist/' . $basename;
    if (!is_readable($corePath)) {
        return null;
    }

    $cacheDir = noviBlockEditorRoFixCacheDir();
    if (!is_dir($cacheDir) && !wp_mkdir_p($cacheDir)) {
        return null;
    }

    $coreHash = md5_file($corePath);
    if ($coreHash === false) {
        return null;
    }

    $patchedPath = $cacheDir . '/' . $basename;
    $metaPath = $cacheDir . '/' . $basename . '.corehash';

    if (
        is_readable($patchedPath)
        && is_readable($metaPath)
        && trim((string) file_get_contents($metaPath)) === $coreHash
    ) {
        return $patchedPath;
    }

    $js = file_get_contents($corePath);
    if ($js === false) {
        return null;
    }

    if (!noviBlockEditorRoFixNeedsPatch($js)) {
        //core already fixed — use original
        return null;
    }

    $patched = noviBlockEditorRoFixPatchJs($js);
    if ($patched === $js || noviBlockEditorRoFixNeedsPatch($patched)) {
        //patch failed to apply expected replacements
        return null;
    }

    if (file_put_contents($patchedPath, $patched) === false) {
        return null;
    }

    file_put_contents($metaPath, $coreHash);

    return $patchedPath;
}

/**
 * Public URL for a patched file inside wp-content/cache.
 */
function noviBlockEditorRoFixFileUrl(string $absolutePath): string
{
    $relative = ltrim(str_replace(WP_CONTENT_DIR, '', $absolutePath), '/');
    return content_url($relative);
}

/**
 * Swap wp-block-editor script src for the patched build when needed.
 */
add_filter('script_loader_src', function ($src, $handle) {
    if ($handle !== 'wp-block-editor' || !is_string($src) || $src === '') {
        return $src;
    }

    //only needed in admin (block editor / patterns)
    if (!is_admin()) {
        return $src;
    }

    //wp serves block-editor.js when SCRIPT_DEBUG, otherwise .min.js
    $minified = !(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);

    $patchedPath = noviBlockEditorRoFixEnsurePatchedFile($minified);
    if ($patchedPath === null) {
        return $src;
    }

    $url = noviBlockEditorRoFixFileUrl($patchedPath);

    //match scheme of the original core src (avoid http rewrite on https admin)
    $scheme = wp_parse_url($src, PHP_URL_SCHEME);
    if (is_string($scheme) && $scheme !== '') {
        $url = set_url_scheme($url, $scheme);
    }

    //preserve cache-busting query args from core src
    $query = wp_parse_url($src, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    }

    //also bust when our patch content changes
    $url = add_query_arg('novi_ro_fix', '79178b', $url);

    return $url;
}, 10, 2);
