<?php

if (is_blog_installed()) {

    //load third party MU plugins
    require WPMU_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php';

    //autoload Novi Online core plugin
    require WPMU_PLUGIN_DIR . '/novionline/wordpress-core-plugin/wordpress-core-plugin.php';

    //load Novi Login
    require WPMU_PLUGIN_DIR . '/novionline/novi-login/novi-login.php';

    //cache warmer ↔ comet: bind warmer HTTP to private NIC (see novi-cache-warmer-loopback README)
    require WPMU_PLUGIN_DIR . '/novionline/novi-cache-warmer-loopback/novi-cache-warmer-loopback.php';

    //log Comet / Autoptimize / object-cache wipe events for attribution
    require WPMU_PLUGIN_DIR . '/novionline/novi-cache-wipe-logger/novi-cache-wipe-logger.php';

    //gated TTFB miss logger + Server-Timing (token/env; see novi-ttfb-profile README)
    require WPMU_PLUGIN_DIR . '/novionline/novi-ttfb-profile/novi-ttfb-profile.php';

    //WP 7.0 synced-pattern crash: null-safe ResizeObserver (Gutenberg #79178 backport)
    require WPMU_PLUGIN_DIR . '/novionline/novi-block-editor-resizeobserver-fix/novi-block-editor-resizeobserver-fix.php';

    //keep NectarBlocks blockIds stable and guard live CSS meta writes
    require WPMU_PLUGIN_DIR . '/novionline/nectar-blocks-css-guard/nectar-blocks-css-guard.php';

    //load Novi Code Snippets (requires Core + ACF)
    if (class_exists('NoviOnline\Core') && function_exists('acf_add_local_field_group')) {
        require WPMU_PLUGIN_DIR . '/novionline/novi-code-snippets/novi-code-snippets.php';
    }
}
