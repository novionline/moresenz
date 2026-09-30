<?php

/**
 * Plugin Name:     Novi Object Cache Privacy
 * Plugin URI:      https://novionline.nl
 * Description:     Blocks outbound Object Cache Pro licensing and telemetry requests to objectcache.pro.
 * Version:         1.1.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

final class NoviObjectCachePrivacy
{
    private const blockedHost = 'objectcache.pro';

    public static function boot(): void
    {
        add_filter('pre_http_request', [self::class, 'blockObjectCacheProRequests'], 10, 3);
        add_filter('site_status_tests', [self::class, 'removeObjectCacheProHealthTests']);
        add_action('plugins_loaded', [self::class, 'hideLicenseNotices'], 11);
    }

    /**
     * Block wp_remote_* calls to objectcache.pro (license, updates, API tests).
     */
    public static function blockObjectCacheProRequests($preempt, $args, $url)
    {
        if (!is_string($url) || !str_contains($url, self::blockedHost)) {
            return $preempt;
        }

        return new WP_Error(
            'novi_objectcache_blocked',
            'Outbound Object Cache Pro requests are disabled.'
        );
    }

    /**
     * Remove Site Health tests that trigger outbound API calls.
     *
     * Note: visiting Tools → Site Health still registers a shutdown callback that
     * uses file_get_contents() directly (bypasses pre_http_request). That edge case
     * is rare and not blocked here; layers 1–3 cover normal admin and cron traffic.
     */
    public static function removeObjectCacheProHealthTests($tests)
    {
        unset(
            $tests['async']['objectcache_license'],
            $tests['async']['objectcache_api'],
            $tests['async']['objectcache_filesystem']
        );

        return $tests;
    }

    /**
     * Hide Object Cache Pro license admin notices (missing token, invalid, etc.).
     */
    public static function hideLicenseNotices(): void
    {
        $plugin = $GLOBALS['ObjectCachePro'] ?? null;

        if (!is_object($plugin) || !method_exists($plugin, 'displayLicenseNotices')) {
            return;
        }

        remove_action('admin_notices', [$plugin, 'displayLicenseNotices'], -1);
        remove_action('network_admin_notices', [$plugin, 'displayLicenseNotices'], -1);
    }
}

NoviObjectCachePrivacy::boot();
