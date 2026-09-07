<?php

/**
 * Plugin Name:     Novi Cache Warmer Loopback
 * Plugin URI:      https://novionline.nl
 * Description:     Makes on-server Cache Warmer populate Comet Cache by binding warmer HTTP to a NIC where REMOTE_ADDR !== SERVER_ADDR.
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
 * Comet Lite skips cache when client IP === SERVER_ADDR (self-serve).
 * Cache Warmer uses wp_remote_get from the origin, so every warm is a forced miss.
 *
 * Pure 127.0.0.1 often fails (SERVER_ADDR becomes 127.0.0.1 too). This plugin
 * discovers a local IPv4 bind address that produces REMOTE_ADDR !== SERVER_ADDR
 * when connecting to the site over IPv4, then applies it only to warmer requests.
 *
 * Identify warmer requests via User-Agent containing NoviCacheWarmer
 * (set that token in Cache Warmer → User-Agent settings).
 *
 * Optional wp-config overrides:
 * - define('NOVI_CACHE_WARMER_UA_TOKEN', 'NoviCacheWarmer');
 * - define('NOVI_CACHE_WARMER_BIND_IP', '10.0.0.5'); // skip auto-detect
 * - define('NOVI_CACHE_WARMER_PROBE_TOKEN', 'secret'); // override auto-generated probe secret
 */
final class NoviCacheWarmerLoopback
{
    private const DEFAULT_UA_TOKEN = 'NoviCacheWarmer';
    private const BIND_IP_OPTION = 'novi_cache_warmer_bind_ip';
    private const BIND_IP_TRANSIENT = 'novi_cache_warmer_bind_ip_ok';
    private const BIND_FAIL_TRANSIENT = 'novi_cache_warmer_bind_fail';
    private const PROBE_SECRET_OPTION = 'novi_cache_warmer_probe_secret';
    private const REST_NAMESPACE = 'novi-cache-warmer-loopback/v1';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerProbeRoute']);
        add_filter('http_api_curl', [self::class, 'bindWarmerCurl'], 10, 3);
    }

    public static function registerProbeRoute(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/ip-probe', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleIpProbe'],
            'permission_callback' => [self::class, 'authorizeIpProbe'],
            'show_in_index' => false,
        ]);
    }

    public static function authorizeIpProbe(\WP_REST_Request $request): bool
    {
        $token = (string) $request->get_header('x-novi-cw-probe');
        if ($token === '') {
            $token = (string) $request->get_param('token');
        }

        $secret = self::getProbeSecret();
        if ($secret === '' || $token === '' || !hash_equals($secret, $token)) {
            return false;
        }

        //defense in depth: token alone is not enough from the public internet
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $remote !== '' && in_array($remote, self::localIpv4Addresses(), true);
    }

    public static function handleIpProbe(\WP_REST_Request $request): \WP_REST_Response
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $server = (string) ($_SERVER['SERVER_ADDR'] ?? '');

        return new \WP_REST_Response([
            'remote' => $remote,
            'server' => $server,
            'equal' => ($remote !== '' && $remote === $server),
        ], 200);
    }

    /**
     * @param \CurlHandle|resource $handle
     * @param array<string, mixed> $request
     */
    public static function bindWarmerCurl($handle, array $request, string $url): void
    {
        if (!self::isWarmerRequest($request, $url)) {
            return;
        }

        $bindIp = self::resolveBindIp();
        if ($bindIp === '') {
            return;
        }

        //force ipv4 so we do not self-hit an ipv6 address (where remote===server is common)
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        curl_setopt($handle, CURLOPT_INTERFACE, $bindIp);
        //avoid following a redirect back onto an equal-IP path
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function isWarmerRequest(array $request, string $url): bool
    {
        $token = defined('NOVI_CACHE_WARMER_UA_TOKEN')
            ? (string) NOVI_CACHE_WARMER_UA_TOKEN
            : self::DEFAULT_UA_TOKEN;

        if ($token === '') {
            return false;
        }

        //wp merges a default user-agent into $request['user-agent']; warmer UA lives in headers
        $userAgent = '';
        if (!empty($request['headers']) && is_array($request['headers'])) {
            foreach ($request['headers'] as $name => $value) {
                if (strcasecmp((string) $name, 'user-agent') === 0) {
                    $userAgent = is_array($value) ? (string) reset($value) : (string) $value;
                    break;
                }
            }
        }
        if ($userAgent === '' && !empty($request['user-agent']) && is_string($request['user-agent'])) {
            $userAgent = $request['user-agent'];
        }

        if ($userAgent === '' || stripos($userAgent, $token) === false) {
            return false;
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!is_string($siteHost) || $siteHost === '') {
            return false;
        }

        //only rewrite same-site warmer requests
        return strcasecmp($host, $siteHost) === 0;
    }

    private static function resolveBindIp(): string
    {
        if (defined('NOVI_CACHE_WARMER_BIND_IP') && is_string(NOVI_CACHE_WARMER_BIND_IP) && NOVI_CACHE_WARMER_BIND_IP !== '') {
            return NOVI_CACHE_WARMER_BIND_IP;
        }

        $cached = get_transient(self::BIND_IP_TRANSIENT);
        if (is_string($cached) && self::isValidIpv4($cached)) {
            return $cached;
        }

        $stored = get_option(self::BIND_IP_OPTION, '');
        if (is_string($stored) && self::isValidIpv4($stored)) {
            //re-validate occasionally via transient miss
            if (self::probeBindWorks($stored)) {
                set_transient(self::BIND_IP_TRANSIENT, $stored, DAY_IN_SECONDS);
                return $stored;
            }
        }

        //avoid hammering probes when nothing works on this host
        if (get_transient(self::BIND_FAIL_TRANSIENT)) {
            return '';
        }

        $discovered = self::discoverBindIp();
        if ($discovered !== '') {
            update_option(self::BIND_IP_OPTION, $discovered, false);
            set_transient(self::BIND_IP_TRANSIENT, $discovered, DAY_IN_SECONDS);
            delete_transient(self::BIND_FAIL_TRANSIENT);
            return $discovered;
        }

        set_transient(self::BIND_FAIL_TRANSIENT, 1, HOUR_IN_SECONDS);
        return '';
    }

    private static function discoverBindIp(): string
    {
        $candidates = self::localIpv4Addresses();
        if ($candidates === []) {
            return '';
        }

        $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $destIps = is_string($siteHost) ? self::resolveHostIpv4($siteHost) : [];

        //prefer private addresses that are not the destination we connect to
        usort($candidates, static function (string $a, string $b) use ($destIps): int {
            return self::bindIpScore($b, $destIps) <=> self::bindIpScore($a, $destIps);
        });

        foreach ($candidates as $candidate) {
            if (self::probeBindWorks($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function probeBindWorks(string $bindIp): bool
    {
        $result = self::probeWithBindIp($bindIp);
        if ($result === null) {
            return false;
        }

        $remote = (string) ($result['remote'] ?? '');
        $server = (string) ($result['server'] ?? '');

        return $remote !== '' && $server !== '' && $remote !== $server;
    }

    /**
     * @return array{remote?: string, server?: string, equal?: bool}|null
     */
    private static function probeWithBindIp(string $bindIp): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $secret = self::getProbeSecret();
        if ($secret === '') {
            return null;
        }

        $probeUrl = rest_url(self::REST_NAMESPACE . '/ip-probe');
        $ch = curl_init($probeUrl);
        if ($ch === false) {
            return null;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_INTERFACE => $bindIp,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Novi-Cw-Probe: ' . $secret,
            ],
        ];
        if (defined('CURL_IPRESOLVE_V4')) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code !== 200) {
            return null;
        }

        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    private static function getProbeSecret(): string
    {
        if (defined('NOVI_CACHE_WARMER_PROBE_TOKEN') && is_string(NOVI_CACHE_WARMER_PROBE_TOKEN) && NOVI_CACHE_WARMER_PROBE_TOKEN !== '') {
            return NOVI_CACHE_WARMER_PROBE_TOKEN;
        }

        $existing = get_option(self::PROBE_SECRET_OPTION, '');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        try {
            $secret = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $secret = wp_generate_password(64, true, true);
        }

        update_option(self::PROBE_SECRET_OPTION, $secret, false);
        return $secret;
    }

    /**
     * @return list<string>
     */
    private static function localIpv4Addresses(): array
    {
        if (!function_exists('net_get_interfaces')) {
            return [];
        }

        $out = [];
        $interfaces = net_get_interfaces();
        if (!is_array($interfaces)) {
            return [];
        }

        foreach ($interfaces as $info) {
            if (empty($info['unicast']) || !is_array($info['unicast'])) {
                continue;
            }
            foreach ($info['unicast'] as $addr) {
                if (!is_array($addr) || empty($addr['address'])) {
                    continue;
                }
                $ip = (string) $addr['address'];
                if (!self::isValidIpv4($ip) || $ip === '127.0.0.1') {
                    continue;
                }
                $out[] = $ip;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private static function resolveHostIpv4(string $host): array
    {
        $records = @dns_get_record($host, DNS_A);
        if (!is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $row) {
            if (!empty($row['ip']) && filter_var($row['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = (string) $row['ip'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @param list<string> $destIps
     */
    private static function bindIpScore(string $ip, array $destIps): int
    {
        $score = 0;

        //private ranges are more likely to differ from SERVER_ADDR on the public vhost
        if (self::isPrivateIpv4($ip)) {
            $score += 200;
        }

        //avoid binding to an address that is also a site A-record when possible
        if (in_array($ip, $destIps, true)) {
            $score -= 100;
        }

        return $score;
    }

    private static function isValidIpv4(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    private static function isPrivateIpv4(string $ip): bool
    {
        if (!self::isValidIpv4($ip)) {
            return false;
        }

        //true when the address is private/reserved (fails the "public only" check)
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}

NoviCacheWarmerLoopback::boot();
