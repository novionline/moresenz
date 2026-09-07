<?php

/**
 * Plugin Name:     Novi TTFB Profile
 * Plugin URI:      https://novionline.nl
 * Description:     Cache-miss JSONL logger + gated Server-Timing for uncached HTML TTFB diagnosis.
 * Version:         1.1.1
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TTFB helpers for Comet cache misses (plugin only runs when WP boots — i.e. a miss).
 *
 * Always (lightweight): append one JSONL line for public front-end HTML GETs.
 * Gated (heavy): Server-Timing + stage metrics when token/env enabled.
 *
 * Enable heavy profiling with one of:
 * - define('NOVI_TTFB_PROFILE_TOKEN', 'secret'); and request ?novi_ttfb_debug=secret
 * - env NOVI_TTFB_PROFILE=1 (profiles all front-end requests — use sparingly)
 *
 * On Comet HIT, advanced-cache serves HTML before WP — this plugin never runs (expected).
 */
final class NoviTtfbProfile
{
    private static float $requestStart = 0.0;
    private static float $pluginStart = 0.0;
    private static array $at = [];
    private static bool $profileActive = false;
    private static bool $missLogActive = false;
    private static float $queryTimeMs = 0.0;
    private static int $queryCount = 0;

    public static function boot(): void
    {
        self::$requestStart = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);
        self::$pluginStart = microtime(true);
        self::$at['request'] = self::$requestStart;
        self::$at['plugin'] = self::$pluginStart;

        $profile = self::shouldProfile();
        $missLog = self::shouldLogMiss();

        if (!$profile && !$missLog) {
            return;
        }

        self::$profileActive = $profile;
        self::$missLogActive = $missLog;

        //avoid page caches that honor DONOTCACHEPAGE — only when intentionally profiling
        if ($profile && !defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if ($profile) {
            add_action('plugins_loaded', [self::class, 'onPluginsLoaded'], 0);
            add_action('init', [self::class, 'onInit'], 0);
            add_action('wp', [self::class, 'onWp'], 0);
            add_action('template_redirect', [self::class, 'onTemplateRedirect'], 0);
            add_filter('log_query_custom_data', [self::class, 'onQueryLog'], 10, 3);
            add_filter('query', [self::class, 'onQuery'], 999);
        }

        add_action('shutdown', [self::class, 'onShutdown'], 0);
    }

    private static function shouldProfile(): bool
    {
        $forceAll = self::envFlag('NOVI_TTFB_PROFILE');

        if (!$forceAll) {
            if (defined('WP_CLI') && WP_CLI) {
                return false;
            }
            if (defined('DOING_CRON') && DOING_CRON) {
                return false;
            }
            if (function_exists('is_admin') && is_admin()) {
                return false;
            }
        }

        if ($forceAll) {
            return true;
        }

        $token = self::configuredToken();
        if ($token === '') {
            return false;
        }

        $provided = isset($_GET['novi_ttfb_debug']) ? (string) $_GET['novi_ttfb_debug'] : '';
        return $provided !== '' && hash_equals($token, $provided);
    }

    /**
     * Lightweight miss logging for public HTML GETs (no token required).
     */
    private static function shouldLogMiss(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return false;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');

        //skip admin / api / feeds / static-ish paths
        if (str_starts_with($path, '/wp-admin') || str_starts_with($path, '/wp-json')) {
            return false;
        }
        if (preg_match('#/(wp-login|xmlrpc|admin-ajax)\.php$#', $path)) {
            return false;
        }
        if (preg_match('#/(feed|comments/feed)/?$#i', $path)) {
            return false;
        }
        if ($query !== '' && preg_match('#(?:^|&)rest_route=#i', $query)) {
            return false;
        }
        //scanner / probe noise (not useful for CWV miss diagnosis)
        if (preg_match('#\.(php)$#i', $path) && !preg_match('#^/index\.php$#i', $path)) {
            return false;
        }
        if (preg_match('#/\.(git|env|aws)(/|$)|/\.gitconfig$|/\.git-credentials$|/rclone\.conf$#i', $path)) {
            return false;
        }
        if (preg_match('#^/(graphql|api/graphql|v1/graphql|login|signin|admin|dashboard|app|auth/login|admin/login|user/login|manage|portal|account|my|profile|console|settings)(/|$)#i', $path)) {
            return false;
        }
        if (preg_match('#\.(css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|txt|xml|json)$#i', $path)) {
            return false;
        }

        //always log when debug token is present (visibility during diagnosis)
        $token = self::configuredToken();
        if ($token !== '') {
            $provided = isset($_GET['novi_ttfb_debug']) ? (string) $_GET['novi_ttfb_debug'] : '';
            if ($provided !== '' && hash_equals($token, $provided)) {
                return true;
            }
        }

        //prefer HTML accepts; allow empty Accept (bots / curl)
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && !str_contains($accept, 'text/html') && !str_contains($accept, '*/*')) {
            return false;
        }

        return true;
    }

    private static function configuredToken(): string
    {
        if (defined('NOVI_TTFB_PROFILE_TOKEN') && is_string(NOVI_TTFB_PROFILE_TOKEN)) {
            return NOVI_TTFB_PROFILE_TOKEN;
        }

        $env = getenv('NOVI_TTFB_PROFILE_TOKEN');
        return is_string($env) ? $env : '';
    }

    private static function envFlag(string $name): bool
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return false;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function msBetween(string $fromKey, string $toKey): ?float
    {
        if (!isset(self::$at[$fromKey], self::$at[$toKey])) {
            return null;
        }
        return round((self::$at[$toKey] - self::$at[$fromKey]) * 1000, 2);
    }

    public static function onPluginsLoaded(): void
    {
        self::$at['plugins_loaded'] = microtime(true);
    }

    public static function onInit(): void
    {
        self::$at['init'] = microtime(true);
    }

    public static function onWp(): void
    {
        self::$at['wp'] = microtime(true);
    }

    public static function onTemplateRedirect(): void
    {
        self::$at['template_redirect'] = microtime(true);
    }

    /**
     * @param string $query SQL
     * @return string
     */
    public static function onQuery(string $query): string
    {
        if (self::$profileActive) {
            self::$queryCount++;
        }
        return $query;
    }

    /**
     * @param array|null $queryData
     * @param string $query
     * @param float $queryTime seconds
     * @return array|null
     */
    public static function onQueryLog($queryData, $query, $queryTime)
    {
        if (self::$profileActive && is_numeric($queryTime)) {
            self::$queryTimeMs += ((float) $queryTime) * 1000;
        }
        return $queryData;
    }

    public static function onShutdown(): void
    {
        if (!self::$profileActive && !self::$missLogActive) {
            return;
        }

        //late filters: skip admin/rest/404 probes if detected after boot
        if (self::$missLogActive && !self::$profileActive) {
            if (function_exists('is_admin') && is_admin()) {
                return;
            }
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return;
            }
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return;
            }
            //404 theme renders (scanner login/graphql probes) inflate the miss log
            if (function_exists('is_404') && is_404()) {
                return;
            }
        }

        self::$at['shutdown'] = microtime(true);

        global $wpdb;
        $dbQueries = isset($wpdb) && is_object($wpdb) ? (int) $wpdb->num_queries : self::$queryCount;

        if (self::$profileActive) {
            $metrics = [
                'bootstrap_ms' => self::msBetween('request', 'plugin'),
                'plugins_loaded_ms' => self::msBetween('plugin', 'plugins_loaded'),
                'init_ms' => self::msBetween('plugins_loaded', 'init'),
                'wp_ms' => self::msBetween('init', 'wp'),
                'template_ms' => self::msBetween('template_redirect', 'shutdown'),
                'total_ms' => self::msBetween('request', 'shutdown'),
                'db_query_count' => $dbQueries,
                'db_query_time_ms' => self::$queryTimeMs > 0 ? round(self::$queryTimeMs, 2) : null,
                'object_cache' => self::objectCacheLabel(),
                'path' => self::safePath(),
            ];
            self::sendServerTiming($metrics);
            self::appendLog($metrics, true);
            return;
        }

        //lightweight miss line
        $row = [
            'ts' => gmdate('c'),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path' => self::safePath(),
            'total_ms' => self::msBetween('request', 'shutdown'),
            'object_cache' => self::objectCacheLabel(),
            'db_query_count' => $dbQueries,
            'profiled' => false,
        ];
        self::appendMissRow($row);
    }

    private static function objectCacheLabel(): string
    {
        if (!function_exists('wp_using_ext_object_cache') || !wp_using_ext_object_cache()) {
            return 'none';
        }
        if (defined('WP_REDIS_VERSION') || class_exists('Redis_Object_Cache', false)) {
            return 'redis';
        }
        return 'external';
    }

    private static function safePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        if ($query === '') {
            return $path;
        }
        parse_str($query, $params);
        $keys = array_keys(is_array($params) ? $params : []);
        sort($keys);
        return $path . '?' . implode('&', array_map(static fn($k) => $k . '=', $keys));
    }

    private static function sendServerTiming(array $metrics): void
    {
        if (headers_sent()) {
            return;
        }

        $parts = [];
        foreach (
            [
                'bootstrap' => 'bootstrap_ms',
                'plugins' => 'plugins_loaded_ms',
                'init' => 'init_ms',
                'wp' => 'wp_ms',
                'template' => 'template_ms',
                'total' => 'total_ms',
                'db' => 'db_query_time_ms',
            ] as $name => $key
        ) {
            if (!isset($metrics[$key]) || $metrics[$key] === null) {
                continue;
            }
            $parts[] = sprintf('%s;desc="%s";dur=%.2f', $name, $name, (float) $metrics[$key]);
        }

        if ($parts) {
            header('Server-Timing: ' . implode(', ', $parts), false);
            header('X-Novi-TTFB-Profile: 1', false);
            header('X-Novi-TTFB-DB-Queries: ' . (string) ($metrics['db_query_count'] ?? 0), false);
            header('X-Novi-TTFB-Object-Cache: ' . (string) ($metrics['object_cache'] ?? 'unknown'), false);
        }
    }

    private static function appendLog(array $metrics, bool $profiled): void
    {
        $row = [
            'ts' => gmdate('c'),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path' => $metrics['path'] ?? self::safePath(),
            'total_ms' => $metrics['total_ms'] ?? null,
            'object_cache' => $metrics['object_cache'] ?? self::objectCacheLabel(),
            'db_query_count' => $metrics['db_query_count'] ?? null,
            'profiled' => $profiled,
            'metrics' => $metrics,
        ];
        self::appendMissRow($row);
    }

    private static function appendMissRow(array $row): void
    {
        $dir = WP_CONTENT_DIR . '/uploads/novi-ttfb';
        if (!is_dir($dir)) {
            if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($dir)) {
                return;
            }
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Deny from all\n");
            }
            $index = $dir . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php\n//silence\n");
            }
        }

        $line = wp_json_encode($row) . "\n";
        @file_put_contents($dir . '/misses.jsonl', $line, FILE_APPEND | LOCK_EX);
    }
}

NoviTtfbProfile::boot();
