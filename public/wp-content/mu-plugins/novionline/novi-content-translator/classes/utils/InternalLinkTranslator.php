<?php

namespace NoviOnline\ContentTranslator\Core;

/**
 * InternalLinkTranslator
 *
 * Rewrites internal <a href="..."> links inside small HTML fragments (e.g. Nectar text content)
 * so they point to the translated version for the given target language.
 *
 * Initial scope: robust internal URL normalization + post/term translation via Polylang.
 * Archive and slug fallbacks are intentionally conservative and can be extended later.
 */
class InternalLinkTranslator
{
    private static function trailingslashitCompat(string $path): string
    {
        if (function_exists('trailingslashit')) {
            return (string) trailingslashit($path);
        }
        return rtrim($path, '/') . '/';
    }

    private static function untrailingslashitCompat(string $path): string
    {
        if (function_exists('untrailingslashit')) {
            return (string) untrailingslashit($path);
        }
        return rtrim($path, '/');
    }

    /**
     * Hosts treated as "this site" for link rewriting (current home + related production/staging).
     * Filter: nct_internal_link_hosts
     *
     * @return array<int, string>
     */
    private static function getRelatedInternalHosts(): array
    {
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        $homeParts = $home !== '' ? wp_parse_url($home) : [];
        $homeHost = is_array($homeParts) ? strtolower((string) ($homeParts['host'] ?? '')) : '';

        $hosts = [];
        if ($homeHost !== '') {
            $hosts[] = $homeHost;
            if (str_starts_with($homeHost, 'www.')) {
                $hosts[] = substr($homeHost, 4);
            } else {
                $hosts[] = 'www.' . $homeHost;
            }

            //local Valet *.test → also treat matching production hosts as internal
            if (str_ends_with($homeHost, '.test')) {
                $base = substr($homeHost, 0, -strlen('.test'));
                if ($base !== '') {
                    $hosts[] = $base . '.nl';
                    $hosts[] = 'www.' . $base . '.nl';
                    $hosts[] = $base . '.com';
                    $hosts[] = 'www.' . $base . '.com';
                    $hosts[] = 'staging.' . $base . '.nl';
                    $hosts[] = 'staging.' . $base . '.com';
                }
            }
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('nct_internal_link_hosts', $hosts, $homeHost);
            if (is_array($filtered)) {
                $hosts = $filtered;
            }
        }

        $out = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $out[] = $host;
            }
        }

        return array_values(array_unique($out));
    }

    private static function isRelatedInternalHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        return in_array($host, self::getRelatedInternalHosts(), true);
    }

    /**
     * First-segment path aliases used when resolving posts (e.g. legacy /blog/ → /artikelen/).
     * Filter: nct_internal_link_path_aliases
     *
     * @return array<string, string>
     */
    private static function getPathSegmentAliases(): array
    {
        $aliases = [
            'blog' => 'artikelen',
        ];
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('nct_internal_link_path_aliases', $aliases);
            if (is_array($filtered)) {
                $aliases = $filtered;
            }
        }

        $out = [];
        foreach ($aliases as $from => $to) {
            $from = strtolower(trim((string) $from));
            $to = strtolower(trim((string) $to));
            if ($from !== '' && $to !== '') {
                $out[$from] = $to;
            }
        }
        return $out;
    }

    /**
     * @param array<int, string> $segments
     * @return array<int, string>
     */
    private static function applyPathSegmentAliases(array $segments): array
    {
        if ($segments === []) {
            return $segments;
        }
        $aliases = self::getPathSegmentAliases();
        $first = strtolower((string) ($segments[0] ?? ''));
        if ($first !== '' && isset($aliases[$first])) {
            $segments[0] = $aliases[$first];
        }
        return $segments;
    }

    /**
     * Preserve trailing slash style from an original URL/href.
     * - If original had a trailing slash in its path, ensure rewritten has one too.
     * - If original had no trailing slash, remove it from rewritten (unless it's the domain root).
     * - Never force trailing slashes on URLs with query/fragment, or file-like paths.
     */
    private static function preserveTrailingSlashStyle(string $original, string $rewritten): string
    {
        $original = trim($original);
        $rewritten = trim($rewritten);
        if ($original === '' || $rewritten === '') {
            return $rewritten;
        }

        // If the original includes query/fragment, keep exact output (we don't want to mutate it).
        $origParts = wp_parse_url($original);
        $origQuery = is_array($origParts) ? (string) ($origParts['query'] ?? '') : '';
        $origFragment = is_array($origParts) ? (string) ($origParts['fragment'] ?? '') : '';
        if ($origQuery !== '' || $origFragment !== '') {
            return $rewritten;
        }

        // Skip file-like URLs.
        $origPath = is_array($origParts) ? (string) ($origParts['path'] ?? '') : '';
        if ($origPath !== '' && str_contains(substr($origPath, max(0, strlen($origPath) - 6)), '.')) {
            return $rewritten;
        }

        $shouldHaveSlash = $origPath !== '' ? str_ends_with($origPath, '/') : str_ends_with($original, '/');

        $parts = wp_parse_url($rewritten);
        if (!is_array($parts)) {
            return $rewritten;
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            return $rewritten;
        }

        $query = (string) ($parts['query'] ?? '');
        $fragment = (string) ($parts['fragment'] ?? '');
        if ($query !== '' || $fragment !== '') {
            return $rewritten;
        }

        $newPath = $path;
        if ($shouldHaveSlash) {
            $newPath = self::trailingslashitCompat($path);
        } else {
            $newPath = self::untrailingslashitCompat($path);
        }

        if ($newPath === $path) {
            return $rewritten;
        }

        $scheme = isset($parts['scheme']) ? (string) $parts['scheme'] : '';
        $host = isset($parts['host']) ? (string) $parts['host'] : '';
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;

        // Preserve absolute vs relative style
        if ($host !== '') {
            $abs = ($scheme !== '' ? $scheme : 'https') . '://' . $host;
            if ($port > 0) {
                $abs .= ':' . $port;
            }
            return $abs . $newPath;
        }

        return $newPath;
    }
    /**
     * Cache per request: [key => [source|target|segment => translatedSegment]]
     * @var array<string, array<string, string>>
     */
    private static array $slugTranslationCache = [];

    /**
     * Cache per request: [cacheKey => map]
     * @var array<string, array<string, array{absolute: string, relative: string}>>
     */
    private static array $urlMapCache = [];

    /**
     * Cache per request: [cacheKey => stats]
     * @var array<string, array<string, int>>
     */
    private static array $statsByKey = [];

    /**
     * Cache per request: [absoluteUrl => canonicalAbsoluteUrl|'' ]
     * @var array<string, string>
     */
    private static array $redirectResolveCache = [];

    /**
     * Rewrite anchors in an HTML fragment.
     *
     * @param string $htmlFragment
     * @param string $targetLangSlug Polylang language slug (e.g. 'nl', 'en')
     * @param array{
     *   update_anchor_id?: bool,
     *   prefer_anchor_id?: bool
     * } $options
     * @return array{html: string, stats: array<string, int>}
     */
    public static function rewriteAnchorsInHtmlFragment(string $htmlFragment, string $targetLangSlug, array $options = []): array
    {
        $key = self::buildCacheKey($targetLangSlug);
        $stats = self::getEmptyStats();
        $stats['encountered'] = 0;

        if ($htmlFragment === '' || stripos($htmlFragment, '<a') === false) {
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['html' => $htmlFragment, 'stats' => self::$statsByKey[$key]];
        }

        $preferAnchorId = array_key_exists('prefer_anchor_id', $options) ? (bool) $options['prefer_anchor_id'] : true;
        $updateAnchorId = array_key_exists('update_anchor_id', $options) ? (bool) $options['update_anchor_id'] : true;
        $sourceLang = isset($options['source_lang']) && is_string($options['source_lang']) ? trim($options['source_lang']) : '';
        $strict = array_key_exists('strict', $options) ? (bool) $options['strict'] : false;

        if (!class_exists('\DOMDocument') || !class_exists('\DOMXPath')) {
            // Fallback: regex rewrite of href only (no id/type), very conservative.
            $rewritten = self::rewriteHrefsByRegex($htmlFragment, $targetLangSlug, $stats);
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['html' => $rewritten, 'stats' => self::$statsByKey[$key]];
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapper = '<div id="nct-internal-link-wrapper">' . $htmlFragment . '</div>';
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">' . $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            $stats['parse_failed']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['html' => $htmlFragment, 'stats' => self::$statsByKey[$key]];
        }

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//a[@href]');
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['html' => $htmlFragment, 'stats' => self::$statsByKey[$key]];
        }

        foreach ($nodes as $a) {
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $stats['encountered']++;
            $href = (string) $a->getAttribute('href');

            if (self::shouldSkipHref($href)) {
                $stats['skipped']++;
                continue;
            }

            $normalized = self::normalizeHref($href);
            if ($normalized['isFragmentOnly']) {
                $stats['fragment_only']++;
                continue;
            }

            if (!$normalized['isInternal']) {
                $stats['external']++;
                continue;
            }
            $stats['internal']++;

            // 1) Prefer metadata-based rewrite when available.
            if ($preferAnchorId) {
                $type = strtolower(trim((string) $a->getAttribute('type')));
                $idAttr = trim((string) $a->getAttribute('id'));
                $id = ctype_digit($idAttr) ? (int) $idAttr : 0;
                if ($id > 0 && $type !== '') {
                    $resolved = self::rewriteUsingAnchorMetadata($type, $id, $targetLangSlug, $href, $updateAnchorId);
                    if ($resolved['changed']) {
                        $a->setAttribute('href', $resolved['href']);
                        if ($resolved['newId'] !== null) {
                            $a->setAttribute('id', (string) $resolved['newId']);
                        }
                        $stats['rewritten_by_anchor_id']++;
                        continue;
                    }
                }
            }

            // 2) Exact match map (fast path).
            $map = self::getUrlMap($targetLangSlug);
            $keyAbs = $normalized['absoluteKey'];
            $keyRel = $normalized['relativeKey'];
            $isAbsoluteInput = $normalized['isAbsolute'];

            if ($keyAbs !== '' && isset($map[$keyAbs])) {
                $out = $isAbsoluteInput ? $map[$keyAbs]['absolute'] : $map[$keyAbs]['relative'];
                $a->setAttribute('href', self::preserveTrailingSlashStyle($href, $out));
                $stats['rewritten_by_exact_match']++;
                continue;
            }
            if ($keyRel !== '' && isset($map[$keyRel])) {
                $out = $isAbsoluteInput ? $map[$keyRel]['absolute'] : $map[$keyRel]['relative'];
                $a->setAttribute('href', self::preserveTrailingSlashStyle($href, $out));
                $stats['rewritten_by_exact_match']++;
                continue;
            }

            // 3) Dynamic resolution via url_to_postid/wp_url_to_termid (if available).
            $resolved = self::rewriteByDynamicResolution($href, $targetLangSlug);
            if ($resolved['changed']) {
                $a->setAttribute('href', self::preserveTrailingSlashStyle($href, (string) $resolved['href']));
                $stats['rewritten_by_object_translation']++;
                continue;
            }

            if (!$strict) {
                // Best-effort: language-switch + slug translate, but only persist when the
                // candidate resolves to a real published object in the target language.
                $fallback = self::switchLanguageInUrl($href, $targetLangSlug);
                if ($fallback !== '' && $fallback !== $href) {
                    $slugged = self::translateSlugsInUrl($fallback, $sourceLang, $targetLangSlug);
                    $candidate = ($slugged !== '' && $slugged !== $fallback) ? $slugged : $fallback;
                    if ($candidate !== $href && self::publishedObjectExistsForInternalUrl($candidate, $targetLangSlug)) {
                        $snapped = self::rewriteByDynamicResolution($candidate, $targetLangSlug);
                        $outHref = (!empty($snapped['changed']) && isset($snapped['href']) && is_string($snapped['href']) && $snapped['href'] !== '')
                            ? (string) $snapped['href']
                            : $candidate;
                        $a->setAttribute('href', self::preserveTrailingSlashStyle($href, $outHref));
                        if ($candidate !== $fallback) {
                            $stats['rewritten_by_slug_translation']++;
                        } else {
                            $stats['rewritten_by_language_switch']++;
                        }
                        $stats['rewritten_by_object_translation']++;
                        continue;
                    }
                }
            }

            $stats['not_rewritten']++;
        }

        // unwrap wrapper
        $newHtml = '';
        $root = $doc->getElementById('nct-internal-link-wrapper');
        if ($root instanceof \DOMElement) {
            foreach ($root->childNodes as $child) {
                $newHtml .= $doc->saveHTML($child);
            }
        } else {
            // fallback: return original fragment if wrapper vanished
            $newHtml = $htmlFragment;
        }

        self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
        return ['html' => $newHtml, 'stats' => self::$statsByKey[$key]];
    }

    /**
     * Rewrite a single internal URL to its translated equivalent (if resolvable).
     * Used for blocks that store URLs in attrs (e.g. NectarBlocks button link.href.value).
     *
     * @param string $url
     * @param string $targetLangSlug
     * @param string $sourceLang
     * @param array{strict?: bool} $options
     * @return array{url: string, changed: bool, reason: string, stats: array<string, int>}
     */
    public static function rewriteInternalUrl(string $url, string $targetLangSlug, string $sourceLang = '', array $options = []): array
    {
        $key = self::buildCacheKey($targetLangSlug);
        $stats = self::getEmptyStats();
        $url = trim($url);
        $strict = array_key_exists('strict', $options) ? (bool) $options['strict'] : false;

        if ($url === '' || self::shouldSkipHref($url)) {
            $stats['skipped']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $url, 'changed' => false, 'reason' => 'skipped', 'stats' => self::$statsByKey[$key]];
        }

        $normalized = self::normalizeHref($url);
        if ($normalized['isFragmentOnly']) {
            $stats['fragment_only']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $url, 'changed' => false, 'reason' => 'fragment_only', 'stats' => self::$statsByKey[$key]];
        }

        if (!$normalized['isInternal']) {
            $stats['external']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $url, 'changed' => false, 'reason' => 'external', 'stats' => self::$statsByKey[$key]];
        }

        $stats['internal']++;

        // 1) Exact match map (homepage etc).
        $map = self::getUrlMap($targetLangSlug);
        $keyAbs = $normalized['absoluteKey'];
        $keyRel = $normalized['relativeKey'];
        $isAbsoluteInput = $normalized['isAbsolute'];
        if ($keyAbs !== '' && isset($map[$keyAbs])) {
            $out = $isAbsoluteInput ? $map[$keyAbs]['absolute'] : $map[$keyAbs]['relative'];
            $out = self::preserveTrailingSlashStyle($url, $out);
            $stats['rewritten_by_exact_match']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $out, 'changed' => $out !== $url, 'reason' => 'exact_match', 'stats' => self::$statsByKey[$key]];
        }
        if ($keyRel !== '' && isset($map[$keyRel])) {
            $out = $isAbsoluteInput ? $map[$keyRel]['absolute'] : $map[$keyRel]['relative'];
            $out = self::preserveTrailingSlashStyle($url, $out);
            $stats['rewritten_by_exact_match']++;
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $out, 'changed' => $out !== $url, 'reason' => 'exact_match', 'stats' => self::$statsByKey[$key]];
        }

        // 2) Dynamic resolution (url_to_postid/wp_url_to_termid).
        $resolved = self::rewriteByDynamicResolution($url, $targetLangSlug);
        if (!empty($resolved['changed']) && isset($resolved['href']) && is_string($resolved['href'])) {
            $stats['rewritten_by_object_translation']++;
            $out = self::preserveTrailingSlashStyle($url, $resolved['href']);
            self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
            return ['url' => $out, 'changed' => $out !== $url, 'reason' => 'dynamic_resolution', 'stats' => self::$statsByKey[$key]];
        }

        // 3) Polylang full-path translated-slug mapping (deterministic).
        // Even in strict mode we may apply this mapping, but we must NOT return a mere language-switch
        // unless it yields a slug-mapped result. This avoids inventing URLs while still fixing known
        // Polylang String Translation cases like "about-us/jobs" => "over-ons/vacatures".
        $switched = self::switchLanguageInUrl($url, $targetLangSlug);
        if ($switched !== '' && $switched !== $url && $sourceLang !== '') {
            $mapped = self::applyPolylangTranslatedSlugsFullPath($switched, $sourceLang, $targetLangSlug);
            if ($mapped !== '' && $mapped !== $switched) {
                $stats['rewritten_by_language_switch']++;
                $stats['rewritten_by_slug_translation']++;
                $mapped = self::preserveTrailingSlashStyle($url, $mapped);
                self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
                return ['url' => $mapped, 'changed' => true, 'reason' => 'slug_translation', 'stats' => self::$statsByKey[$key]];
            }
        }

        if (!$strict) {
            // Best-effort: language-switch + slug translate, but only persist when the
            // candidate resolves to a real published object in the target language.
            // Never leave DeepL-invented /{lang}/… paths that 404.
            if ($switched !== '' && $switched !== $url) {
                $slugged = self::translateSlugsInUrl($switched, $sourceLang, $targetLangSlug);
                $candidate = ($slugged !== '' && $slugged !== $switched) ? $slugged : $switched;
                if ($candidate !== $url && self::publishedObjectExistsForInternalUrl($candidate, $targetLangSlug)) {
                    $snapped = self::rewriteByDynamicResolution($candidate, $targetLangSlug);
                    $outHref = (!empty($snapped['changed']) && isset($snapped['href']) && is_string($snapped['href']) && $snapped['href'] !== '')
                        ? (string) $snapped['href']
                        : $candidate;
                    if ($candidate !== $switched) {
                        $stats['rewritten_by_slug_translation']++;
                    } else {
                        $stats['rewritten_by_language_switch']++;
                    }
                    $stats['rewritten_by_object_translation']++;
                    $out = self::preserveTrailingSlashStyle($url, $outHref);
                    self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
                    return ['url' => $out, 'changed' => true, 'reason' => 'dynamic_resolution', 'stats' => self::$statsByKey[$key]];
                }
            }
        }

        $stats['not_rewritten']++;
        self::$statsByKey[$key] = self::mergeStats(self::$statsByKey[$key] ?? self::getEmptyStats(), $stats);
        return ['url' => $url, 'changed' => false, 'reason' => 'not_rewritten', 'stats' => self::$statsByKey[$key]];
    }

    /**
     * Clear per-request caches (primarily for unit tests).
     */
    public static function resetRuntimeCache(): void
    {
        self::$urlMapCache = [];
        self::$statsByKey = [];
        self::$slugTranslationCache = [];
        self::$redirectResolveCache = [];
    }

    /**
     * @return array<string, int>
     */
    private static function getEmptyStats(): array
    {
        return [
            'encountered' => 0,
            'skipped' => 0,
            'fragment_only' => 0,
            'external' => 0,
            'internal' => 0,
            'parse_failed' => 0,
            'rewritten_by_anchor_id' => 0,
            'rewritten_by_exact_match' => 0,
            'rewritten_by_object_translation' => 0,
            'rewritten_by_language_switch' => 0,
            'rewritten_by_slug_translation' => 0,
            'not_rewritten' => 0,
        ];
    }

    /**
     * @param array<string, int> $a
     * @param array<string, int> $b
     * @return array<string, int>
     */
    private static function mergeStats(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = (int) ($a[$k] ?? 0) + (int) $v;
        }
        return $a;
    }

    private static function buildCacheKey(string $targetLangSlug): string
    {
        return 'target:' . trim(strtolower($targetLangSlug));
    }

    /**
     * @return array<string, array{absolute: string, relative: string}>
     */
    private static function getUrlMap(string $targetLangSlug): array
    {
        $key = self::buildCacheKey($targetLangSlug);
        if (isset(self::$urlMapCache[$key])) {
            return self::$urlMapCache[$key];
        }

        $map = [];

        // Always include homepage mapping.
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        $targetHome = function_exists('pll_home_url') ? (string) pll_home_url($targetLangSlug) : $home;
        $homeNorm = self::normalizeHref($home);
        $targetNorm = self::normalizeHref($targetHome);

        if ($homeNorm['absoluteKey'] !== '' && $targetNorm['absoluteKey'] !== '') {
            $map[$homeNorm['absoluteKey']] = [
                'absolute' => $targetNorm['absoluteUrl'],
                'relative' => $targetNorm['relativePath'],
            ];
        }
        if ($homeNorm['relativeKey'] !== '' && $targetNorm['relativeKey'] !== '') {
            $map[$homeNorm['relativeKey']] = [
                'absolute' => $targetNorm['absoluteUrl'],
                'relative' => $targetNorm['relativePath'],
            ];
        }
        // Also map "/" explicitly.
        if ($targetNorm['relativeKey'] !== '') {
            $map['/'] = [
                'absolute' => $targetNorm['absoluteUrl'],
                'relative' => $targetNorm['relativePath'],
            ];
        }

        self::$urlMapCache[$key] = $map;
        return $map;
    }

    private static function shouldSkipHref(string $href): bool
    {
        $trimmed = trim($href);
        if ($trimmed === '') {
            return true;
        }
        $lower = strtolower($trimmed);
        foreach (['mailto:', 'tel:', 'sms:', 'javascript:', 'data:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return true;
            }
        }

        // Never rewrite static content asset links.
        // This avoids accidental language-prefixing/slug translation for media and file URLs.
        $parts = wp_parse_url($trimmed);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $pathLower = strtolower($path);
        if ($pathLower !== '' && (str_contains($pathLower, '/wp-content/uploads/') || str_contains($pathLower, '/wp-content/'))) {
            return true;
        }

        return false;
    }

    /**
     * Normalize a href into absolute+relative keys.
     *
     * @param string $href
     * @return array{
     *   isAbsolute: bool,
     *   isInternal: bool,
     *   isFragmentOnly: bool,
     *   absoluteUrl: string,
     *   relativePath: string,
     *   absoluteKey: string,
     *   relativeKey: string
     * }
     */
    private static function normalizeHref(string $href): array
    {
        $href = trim($href);
        $isFragmentOnly = $href !== '' && $href[0] === '#';

        $home = function_exists('home_url') ? (string) home_url('/') : '';
        $homeParts = $home !== '' ? wp_parse_url($home) : [];
        $homeHost = is_array($homeParts) ? (string) ($homeParts['host'] ?? '') : '';
        $homeScheme = is_array($homeParts) ? (string) ($homeParts['scheme'] ?? '') : '';

        $parts = wp_parse_url($href);
        $isAbsolute = is_array($parts) && isset($parts['host']);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
        $fragment = is_array($parts) ? (string) ($parts['fragment'] ?? '') : '';

        $relativePath = '';
        if ($isFragmentOnly) {
            $relativePath = '';
        } elseif ($isAbsolute) {
            $relativePath = $path !== '' ? $path : '/';
        } else {
            // relative URL: could be /path or path
            if ($href === '') {
                $relativePath = '';
            } elseif ($href[0] === '/') {
                $relativePath = $path !== '' ? $path : $href;
            } else {
                // make it root-relative for keying
                $relativePath = '/' . ltrim($path !== '' ? $path : $href, '/');
            }
            // strip query/fragment from relative path (keys ignore these)
            if (str_contains($relativePath, '?')) {
                $relativePath = strtok($relativePath, '?') ?: $relativePath;
            }
            if (str_contains($relativePath, '#')) {
                $relativePath = strtok($relativePath, '#') ?: $relativePath;
            }
        }

        $isInternal = false;
        $hrefHost = '';
        if ($isFragmentOnly) {
            $isInternal = true;
        } elseif ($isAbsolute) {
            $hrefHost = (string) ($parts['host'] ?? '');
            $isInternal = self::isRelatedInternalHost($hrefHost);
        } else {
            // treat all non-absolute (relative) URLs as internal
            $isInternal = true;
        }

        // absolute url normalized (without query/fragment for key).
        // related production hosts are rewritten onto the current home host so url_to_postid works locally.
        $absoluteUrl = '';
        if ($isAbsolute) {
            $scheme = (string) ($parts['scheme'] ?? $homeScheme);
            $host = (string) ($parts['host'] ?? '');
            if ($isInternal && $homeHost !== '') {
                $scheme = $homeScheme !== '' ? $homeScheme : ($scheme !== '' ? $scheme : 'https');
                $host = $homeHost;
            }
            $absoluteUrl = ($scheme !== '' ? $scheme : 'https') . '://' . $host . ($path !== '' ? $path : '/');
        } elseif ($relativePath !== '' && $homeHost !== '') {
            $scheme = $homeScheme !== '' ? $homeScheme : 'https';
            $absoluteUrl = $scheme . '://' . $homeHost . $relativePath;
        }

        $absoluteKey = $absoluteUrl;
        $relativeKey = $relativePath !== '' ? $relativePath : '';

        // Re-apply original query/fragment to absoluteUrl for output convenience.
        if ($absoluteUrl !== '' && ($query !== '' || $fragment !== '')) {
            $absoluteUrl .= $query !== '' ? ('?' . $query) : '';
            $absoluteUrl .= $fragment !== '' ? ('#' . $fragment) : '';
        }
        // For relative output include query/fragment.
        if ($relativeKey !== '' && ($query !== '' || $fragment !== '')) {
            $relativePath = $relativeKey . ($query !== '' ? ('?' . $query) : '') . ($fragment !== '' ? ('#' . $fragment) : '');
        } elseif ($relativeKey !== '') {
            $relativePath = $relativeKey;
        }

        return [
            'isAbsolute' => $isAbsolute,
            'isInternal' => $isInternal,
            'isFragmentOnly' => $isFragmentOnly,
            'absoluteUrl' => $absoluteUrl,
            'relativePath' => $relativePath,
            'absoluteKey' => $absoluteKey,
            'relativeKey' => $relativeKey,
        ];
    }

    /**
     * @param string $type
     * @param int $id
     * @param string $targetLangSlug
     * @param string $originalHref
     * @param bool $updateAnchorId
     * @return array{changed: bool, href: string, newId: int|null}
     */
    private static function rewriteUsingAnchorMetadata(
        string $type,
        int $id,
        string $targetLangSlug,
        string $originalHref,
        bool $updateAnchorId
    ): array {
        $type = strtolower(trim($type));
        $newId = null;
        $newHref = '';

        // Prefer post mapping for common types: page/post/cpt.
        $isTerm = in_array($type, ['term', 'category', 'tag', 'taxonomy', 'product_cat'], true);
        if ($isTerm) {
            if (function_exists('pll_get_term') && function_exists('get_term_link')) {
                $trId = (int) pll_get_term($id, $targetLangSlug);
                if ($trId > 0) {
                    $link = get_term_link($trId);
                    if (is_string($link) && $link !== '') {
                        $newHref = $link;
                        $newId = $updateAnchorId ? $trId : null;
                    }
                }
            }
        } else {
            if (function_exists('pll_get_post') && function_exists('get_permalink')) {
                $trId = (int) pll_get_post($id, $targetLangSlug);
                if ($trId > 0) {
                    $link = get_permalink($trId);
                    if (is_string($link) && $link !== '') {
                        $newHref = $link;
                        $newId = $updateAnchorId ? $trId : null;
                    }
                }
            }
        }

        if ($newHref === '') {
            return ['changed' => false, 'href' => $originalHref, 'newId' => null];
        }

        // Preserve style: if original was relative, return relative.
        $normOriginal = self::normalizeHref($originalHref);
        $normNew = self::normalizeHref($newHref);
        $out = $normOriginal['isAbsolute'] ? $normNew['absoluteUrl'] : $normNew['relativePath'];
        if ($out === '') {
            $out = $newHref;
        }

        return ['changed' => $out !== $originalHref, 'href' => $out, 'newId' => $newId];
    }

    /**
     * @param string $href
     * @param string $targetLangSlug
     * @return array{changed: bool, href: string}
     */
    /**
     * True when an internal URL resolves to a post/term that belongs to $lang (or has no lang).
     */
    private static function publishedObjectExistsForInternalUrl(string $url, string $lang): bool
    {
        $url = trim($url);
        $lang = trim($lang);
        if ($url === '' || $lang === '') {
            return false;
        }

        $norm = self::normalizeHref($url);
        $absolute = $norm['absoluteKey'] !== '' ? $norm['absoluteKey'] : ($norm['absoluteUrl'] !== '' ? $norm['absoluteUrl'] : $url);

        $postId = self::resolvePostIdFromInternalUrl($url, $absolute);
        if ($postId > 0) {
            if (!function_exists('pll_get_post_language')) {
                return true;
            }
            $postLang = (string) pll_get_post_language($postId);
            return $postLang === '' || $postLang === $lang;
        }

        if (function_exists('wp_url_to_termid')) {
            $termId = (int) wp_url_to_termid($absolute);
            if ($termId > 0) {
                if (!function_exists('pll_get_term_language')) {
                    return true;
                }
                $termLang = (string) pll_get_term_language($termId);
                return $termLang === '' || $termLang === $lang;
            }
        }

        return false;
    }

    private static function rewriteByDynamicResolution(string $href, string $targetLangSlug): array
    {
        $norm = self::normalizeHref($href);
        if (!$norm['isInternal']) {
            return ['changed' => false, 'href' => $href];
        }

        // url_to_postid/wp_url_to_termid are more reliable without query/fragment.
        $absolute = $norm['absoluteKey'] !== '' ? $norm['absoluteKey'] : ($norm['absoluteUrl'] !== '' ? $norm['absoluteUrl'] : $href);

        // Generic: if the first path segment matches a post type rewrite slug,
        // resolve via post_type + leaf slug (url_to_postid() can be wrong on custom rewrite stacks).
        $byType = self::rewriteByPostTypePath($href, $targetLangSlug);
        if ($byType['changed']) {
            return $byType;
        }

        // Post resolution.
        if (function_exists('url_to_postid') && function_exists('pll_get_post') && function_exists('get_permalink')) {
            $postId = self::resolvePostIdFromInternalUrl($href, $absolute);
            if ($postId > 0) {
                $trId = (int) pll_get_post($postId, $targetLangSlug);
                if ($trId > 0) {
                    $link = get_permalink($trId);
                    if (is_string($link) && $link !== '') {
                        $normNew = self::normalizeHref($link);
                        $out = $norm['isAbsolute'] ? $normNew['absoluteUrl'] : $normNew['relativePath'];
                        return ['changed' => $out !== '' && $out !== $href, 'href' => $out !== '' ? $out : $link];
                    }
                }
            }
        }

        // Term resolution.
        if (function_exists('wp_url_to_termid') && function_exists('pll_get_term') && function_exists('get_term_link')) {
            $termId = (int) wp_url_to_termid($absolute);
            if ($termId > 0) {
                $trId = (int) pll_get_term($termId, $targetLangSlug);
                if ($trId > 0) {
                    $link = get_term_link($trId);
                    if (is_string($link) && $link !== '') {
                        $normNew = self::normalizeHref($link);
                        $out = $norm['isAbsolute'] ? $normNew['absoluteUrl'] : $normNew['relativePath'];
                        return ['changed' => $out !== '' && $out !== $href, 'href' => $out !== '' ? $out : $link];
                    }
                }
            }
        }

        return ['changed' => false, 'href' => $href];
    }

    /**
     * Resolve a post ID from an internal URL, including stale paths that only resolve via redirects.
     */
    private static function resolvePostIdFromInternalUrl(string $href, string $absolute = ''): int
    {
        $norm = self::normalizeHref($href);
        if ($absolute === '') {
            $absolute = $norm['absoluteKey'] !== '' ? $norm['absoluteKey'] : ($norm['absoluteUrl'] !== '' ? $norm['absoluteUrl'] : $href);
        }

        $postId = 0;
        if (function_exists('url_to_postid')) {
            $postId = (int) url_to_postid($absolute);
        }
        if ($postId <= 0) {
            $postId = self::resolvePostIdByPathHeuristics($href);
        }

        // stale NL/EN paths often 301 to a canonical permalink; follow same-host redirects
        if ($postId <= 0) {
            $canonical = self::resolveCanonicalUrlViaRedirects($absolute);
            if ($canonical !== '' && $canonical !== $absolute) {
                if (function_exists('url_to_postid')) {
                    $postId = (int) url_to_postid($canonical);
                }
                if ($postId <= 0) {
                    $postId = self::resolvePostIdByPathHeuristics($canonical);
                }
            }
        }

        return $postId > 0 ? $postId : 0;
    }

    /**
     * Follow same-host internal redirects to a canonical absolute URL.
     * Prefer WP Redirection plugin matches; fall back to HTTP HEAD. Filter: nct_follow_internal_redirect.
     */
    private static function resolveCanonicalUrlViaRedirects(string $absoluteUrl): string
    {
        $absoluteUrl = trim($absoluteUrl);
        if ($absoluteUrl === '') {
            return '';
        }

        if (array_key_exists($absoluteUrl, self::$redirectResolveCache)) {
            return self::$redirectResolveCache[$absoluteUrl];
        }

        $current = $absoluteUrl;
        $seen = [];
        $maxHops = 5;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $key = self::untrailingslashitCompat($current);
            if (isset($seen[$key])) {
                break;
            }
            $seen[$key] = true;

            $next = '';
            if (function_exists('apply_filters')) {
                $filtered = apply_filters('nct_follow_internal_redirect', null, $current, $hop);
                if (is_string($filtered) && $filtered !== '') {
                    $next = $filtered;
                } elseif ($filtered === false) {
                    // filter explicitly says stop
                    break;
                }
            }

            if ($next === '') {
                $next = self::lookupRedirectionPluginTarget($current);
            }
            if ($next === '') {
                $next = self::lookupHttpRedirectTarget($current);
            }
            if ($next === '' || $next === $current) {
                break;
            }

            $nextNorm = self::normalizeHref($next);
            if (!$nextNorm['isInternal'] || $nextNorm['absoluteKey'] === '') {
                break;
            }
            $current = $nextNorm['absoluteKey'];
        }

        $resolved = $current !== $absoluteUrl ? $current : '';
        self::$redirectResolveCache[$absoluteUrl] = $resolved;
        return $resolved;
    }

    private static function lookupRedirectionPluginTarget(string $absoluteUrl): string
    {
        if (!class_exists('\Red_Item') || !method_exists('\Red_Item', 'get_for_url')) {
            return '';
        }

        $parts = wp_parse_url($absoluteUrl);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '') {
            return '';
        }

        try {
            $items = \Red_Item::get_for_url($path);
            if (!is_array($items) || $items === []) {
                $items = \Red_Item::get_for_url(self::untrailingslashitCompat($path));
            }
            if (!is_array($items) || $items === []) {
                return '';
            }

            foreach ($items as $item) {
                if (!is_object($item)) {
                    continue;
                }

                // Redirection's get_for_url always returns every enabled regex rule as a
                // candidate (match_url='regex'). Only use items that actually match $path.
                if (!self::redirectionItemMatchesPath($item, $path)) {
                    continue;
                }

                $actionData = '';
                if (method_exists($item, 'get_action_data')) {
                    $actionData = (string) $item->get_action_data();
                } elseif (isset($item->action_data)) {
                    $actionData = (string) $item->action_data;
                }
                $actionData = trim($actionData);
                if ($actionData === '') {
                    continue;
                }

                // only plain URL redirects (skip regex/error/pass-through)
                if (method_exists($item, 'get_action_type')) {
                    $type = (string) $item->get_action_type();
                    if ($type !== '' && $type !== 'url') {
                        continue;
                    }
                }

                if (str_starts_with($actionData, '/') || str_contains($actionData, '://')) {
                    $norm = self::normalizeHref($actionData);
                    if ($norm['isInternal'] && $norm['absoluteKey'] !== '') {
                        return $norm['absoluteKey'];
                    }
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    /**
     * Whether a Redirection item actually matches the request path.
     * Avoids Red_Item::get_match() (fires redirection_visit hit counters).
     */
    private static function redirectionItemMatchesPath(object $item, string $path): bool
    {
        $sourceUrl = '';
        if (method_exists($item, 'get_url')) {
            $sourceUrl = (string) $item->get_url();
        } elseif (isset($item->url)) {
            $sourceUrl = (string) $item->url;
        }
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            return false;
        }

        $isRegex = false;
        if (method_exists($item, 'is_regex')) {
            $isRegex = (bool) $item->is_regex();
        } elseif (isset($item->regex)) {
            $isRegex = (bool) $item->regex;
        } elseif (method_exists($item, 'get_match_url')) {
            $isRegex = (string) $item->get_match_url() === 'regex';
        }

        if ($isRegex) {
            if (class_exists('\Red_Regex')) {
                return (new \Red_Regex($sourceUrl, true))->is_match($path);
            }
            // same delimiter style as Redirection\Red_Regex::get_regex()
            $pattern = '@' . str_replace('@', '\\@', $sourceUrl) . '@si';
            return @preg_match($pattern, $path) === 1;
        }

        return self::untrailingslashitCompat($path) === self::untrailingslashitCompat($sourceUrl)
            || $path === $sourceUrl;
    }

    private static function lookupHttpRedirectTarget(string $absoluteUrl): string
    {
        if (!function_exists('wp_remote_head') || !function_exists('wp_remote_retrieve_response_code') || !function_exists('wp_remote_retrieve_header')) {
            return '';
        }

        $response = wp_remote_head($absoluteUrl, [
            'timeout' => 3,
            'redirection' => 0,
            'sslverify' => false,
        ]);
        if (is_wp_error($response)) {
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 300 || $code >= 400) {
            return '';
        }

        $location = wp_remote_retrieve_header($response, 'location');
        if (is_array($location)) {
            $location = (string) ($location[0] ?? '');
        }
        $location = trim((string) $location);
        if ($location === '') {
            return '';
        }

        // relative Location headers
        if (str_starts_with($location, '/')) {
            $home = function_exists('home_url') ? (string) home_url('/') : '';
            $homeParts = $home !== '' ? wp_parse_url($home) : [];
            $scheme = is_array($homeParts) ? (string) ($homeParts['scheme'] ?? 'https') : 'https';
            $host = is_array($homeParts) ? (string) ($homeParts['host'] ?? '') : '';
            if ($host === '') {
                return '';
            }
            $location = $scheme . '://' . $host . $location;
        }

        $norm = self::normalizeHref($location);
        if (!$norm['isInternal'] || $norm['absoluteKey'] === '') {
            return '';
        }

        return $norm['absoluteKey'];
    }

    private static function resolvePostIdByPathHeuristics(string $href): int
    {
        if (!function_exists('wp_parse_url') || !function_exists('get_page_by_path') || !function_exists('get_post_types')) {
            return 0;
        }

        $norm = self::normalizeHref($href);
        if (!$norm['isInternal'] || $norm['isFragmentOnly']) {
            return 0;
        }

        $parts = wp_parse_url($href);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '') {
            $path = $norm['relativeKey'] !== '' ? $norm['relativeKey'] : '/';
        }
        $pathTrim = trim($path, '/');
        if ($pathTrim === '') {
            return 0;
        }

        // Strip leading language segment if it matches Polylang languages.
        $segments = explode('/', $pathTrim);
        if ($segments !== []) {
            $first = (string) ($segments[0] ?? '');
            $langs = function_exists('pll_languages_list') ? (array) pll_languages_list() : [];
            $langs = array_values(array_filter(array_map('strval', $langs), static function ($v) {
                return $v !== '';
            }));
            if ($first !== '' && in_array($first, $langs, true)) {
                array_shift($segments);
            }
        }

        // legacy path aliases (e.g. /blog/ → /artikelen/)
        $segments = self::applyPathSegmentAliases($segments);

        $canonicalPath = trim(implode('/', $segments), '/');
        if ($canonicalPath === '') {
            return 0;
        }

        $publicTypes = (array) get_post_types(['public' => true], 'names');
        $publicTypes = array_values(array_filter(array_map('strval', $publicTypes), static function ($v) {
            return $v !== '';
        }));

        // 1) Try full path (handles nested pages like "about-us/faq").
        $p = get_page_by_path($canonicalPath, OBJECT, $publicTypes);
        if ($p instanceof \WP_Post) {
            return (int) $p->ID;
        }

        // 2) Try leaf name across public types (handles nested permalinks where the leaf is unique).
        $leaf = (string) ($segments[count($segments) - 1] ?? '');
        $leaf = trim($leaf);
        if ($leaf === '') {
            return 0;
        }

        if (class_exists('\WP_Query')) {
            $q = new \WP_Query([
                'post_type' => $publicTypes,
                'name' => $leaf,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'no_found_rows' => true,
                'fields' => 'ids',
            ]);
            $ids = isset($q->posts) && is_array($q->posts) ? $q->posts : [];
            $id = isset($ids[0]) ? (int) $ids[0] : 0;
            if ($id > 0) {
                return $id;
            }
        }

        // 3) Fuzzy repair: sometimes links already contain a translated slug variant that doesn't exist
        // (e.g. generated by a previous slug-translation pass). If we can uniquely identify the
        // intended object by token-matching within the same base segment post types, rewrite to the
        // canonical permalink.
        $fuzzy = self::resolvePostIdBySlugTokenMatch($leaf, $segments, $publicTypes);
        if ($fuzzy > 0) {
            return $fuzzy;
        }

        return 0;
    }

    /**
     * Attempt to resolve a non-existent slug to an existing post_name by token matching.
     * Prefers a unique high-overlap match so DeepL-invented variants (e.g. "indispensable"
     * vs "essential") still map to the canonical twin.
     *
     * @param string $leafSlug
     * @param array<int, string> $segments
     * @param array<int, string> $publicTypes
     */
    private static function resolvePostIdBySlugTokenMatch(string $leafSlug, array $segments, array $publicTypes): int
    {
        global $wpdb;
        if (!$wpdb || !is_object($wpdb) || !isset($wpdb->posts)) {
            return 0;
        }

        $leafSlug = trim($leafSlug);
        if ($leafSlug === '' || !str_contains($leafSlug, '-')) {
            return 0;
        }

        // Prefer narrowing by the first path segment (rewrite base) if possible.
        $base = isset($segments[0]) ? trim((string) $segments[0]) : '';
        $candidateTypes = $base !== '' ? self::getPostTypesForRewriteBase($base) : [];
        if ($candidateTypes === []) {
            $candidateTypes = $publicTypes;
        }

        // Use strong tokens from the slug to reduce collisions.
        $rawTokens = array_values(array_filter(explode('-', strtolower($leafSlug)), static function ($t) {
            $t = trim((string) $t);
            if ($t === '' || strlen($t) < 4) {
                return false;
            }
            return !in_array($t, ['with', 'from', 'this', 'that', 'voor', 'met', 'van', 'naar', 'door', 'de', 'het', 'een', 'and', 'the', 'into', 'onto'], true);
        }));
        if ($rawTokens === []) {
            return 0;
        }
        $tokens = array_slice(array_values(array_unique($rawTokens)), 0, 8);
        if (count($tokens) < 2) {
            return 0;
        }

        $typePlaceholders = implode(',', array_fill(0, count($candidateTypes), '%s'));
        // OR-match broad candidates, then score overlap so invented synonym tokens do not kill the match
        $likeParts = [];
        $params = [];
        foreach ($tokens as $t) {
            $likeParts[] = 'post_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($t) . '%';
        }
        $whereLike = implode(' OR ', $likeParts);
        $sql = "
            SELECT ID, post_name
            FROM {$wpdb->posts}
            WHERE post_type IN ($typePlaceholders)
              AND ($whereLike)
              AND post_status <> 'trash'
            LIMIT 25
        ";
        $params = array_merge(array_values($candidateTypes), $params);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (!is_array($rows) || $rows === []) {
            return 0;
        }

        $bestId = 0;
        $bestScore = 0;
        $bestCount = 0;
        $tokenCount = count($tokens);
        $minScore = max(2, (int) ceil($tokenCount * 0.6));

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['ID']) ? (int) $row['ID'] : 0;
            $name = isset($row['post_name']) ? strtolower((string) $row['post_name']) : '';
            if ($id <= 0 || $name === '') {
                continue;
            }
            $score = 0;
            foreach ($tokens as $t) {
                if (str_contains($name, $t)) {
                    $score++;
                }
            }
            if ($score < $minScore) {
                continue;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $id;
                $bestCount = 1;
            } elseif ($score === $bestScore) {
                $bestCount++;
            }
        }

        return ($bestCount === 1 && $bestId > 0) ? $bestId : 0;
    }

    /**
     * Resolve post permalinks by mapping the first path segment to a registered post type rewrite slug.
     * This is more reliable than url_to_postid() on sites with custom rewrite rules.
     *
     * @return array{changed: bool, href: string}
     */
    private static function rewriteByPostTypePath(string $href, string $targetLangSlug): array
    {
        $targetLangSlug = trim((string) $targetLangSlug);
        if ($targetLangSlug === '' || !function_exists('pll_get_post')) {
            return ['changed' => false, 'href' => $href];
        }

        $norm = self::normalizeHref($href);
        if (!$norm['isInternal'] || $norm['isFragmentOnly']) {
            return ['changed' => false, 'href' => $href];
        }

        $parts = wp_parse_url($href);
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
        $fragment = is_array($parts) ? (string) ($parts['fragment'] ?? '') : '';
        $path = $norm['relativeKey'] !== '' ? $norm['relativeKey'] : '/';
        $pathTrim = trim($path, '/');
        if ($pathTrim === '') {
            return ['changed' => false, 'href' => $href];
        }

        $segments = array_values(array_filter(explode('/', $pathTrim), static function ($v) {
            return (string) $v !== '';
        }));

        // Strip leading language segment if present.
        $langs = function_exists('pll_languages_list') ? (array) pll_languages_list() : [];
        $langs = array_values(array_filter(array_map('strval', $langs), static function ($v) {
            return $v !== '';
        }));
        if (isset($segments[0]) && in_array((string) $segments[0], $langs, true)) {
            array_shift($segments);
        }

        if (count($segments) < 2) {
            return ['changed' => false, 'href' => $href];
        }

        $base = (string) ($segments[0] ?? '');
        $leafSlug = (string) ($segments[count($segments) - 1] ?? '');
        if ($base === '' || $leafSlug === '') {
            return ['changed' => false, 'href' => $href];
        }

        if (!function_exists('get_page_by_path')) {
            return ['changed' => false, 'href' => $href];
        }

        $candidateTypes = self::getPostTypesForRewriteBase($base);
        if ($candidateTypes === []) {
            return ['changed' => false, 'href' => $href];
        }

        $source = null;
        foreach ($candidateTypes as $pt) {
            $source = get_page_by_path($leafSlug, OBJECT, (string) $pt);
            if ($source instanceof \WP_Post) {
                break;
            }
            // Repair already-saved links that point to wp_unique_post_slug variants (e.g. "-2").
            if (preg_match('/^(.*)-\\d+$/', $leafSlug, $m)) {
                $candidate = isset($m[1]) ? (string) $m[1] : '';
                if ($candidate !== '') {
                    $source = get_page_by_path($candidate, OBJECT, (string) $pt);
                    if ($source instanceof \WP_Post) {
                        break;
                    }
                }
            }
        }
        if (!($source instanceof \WP_Post) || (int) $source->ID <= 0) {
            return ['changed' => false, 'href' => $href];
        }

        $targetId = (int) pll_get_post((int) $source->ID, $targetLangSlug);
        if ($targetId <= 0) {
            return ['changed' => false, 'href' => $href];
        }

        $target = get_post($targetId);
        if (!($target instanceof \WP_Post) || (string) $target->post_name === '') {
            return ['changed' => false, 'href' => $href];
        }

        if (!function_exists('get_permalink')) {
            return ['changed' => false, 'href' => $href];
        }
        $link = get_permalink($targetId);
        if (!is_string($link) || $link === '') {
            return ['changed' => false, 'href' => $href];
        }

        $normNew = self::normalizeHref($link);
        $out = $norm['isAbsolute'] ? $normNew['absoluteUrl'] : $normNew['relativePath'];
        if ($out === '') {
            $out = $link;
        }

        if ($query !== '') {
            $out .= '?' . $query;
        }
        if ($fragment !== '') {
            $out .= '#' . $fragment;
        }

        return ['changed' => $out !== '' && $out !== $href, 'href' => $out !== '' ? $out : $href];
    }

    /**
     * @return array<int, string>
     */
    private static function getPostTypesForRewriteBase(string $base): array
    {
        $base = trim((string) $base, '/');
        if ($base === '' || !function_exists('get_post_types') || !function_exists('get_post_type_object')) {
            return [];
        }

        $types = (array) get_post_types(['public' => true], 'names');
        $candidates = [];
        foreach ($types as $pt) {
            $pt = (string) $pt;
            if ($pt === '') {
                continue;
            }
            $obj = get_post_type_object($pt);
            if (!is_object($obj)) {
                continue;
            }
            // Only types with rewrite rules are addressable as /<base>/<slug>/.
            $rewrite = isset($obj->rewrite) ? $obj->rewrite : null;
            if (!is_array($rewrite)) {
                continue;
            }
            $slug = isset($rewrite['slug']) ? (string) $rewrite['slug'] : '';
            $slug = trim($slug, '/');
            if ($slug === '' || $slug === '%postname%') {
                continue;
            }
            if ($slug === $base) {
                $candidates[] = $pt;
            }
        }

        // Some stacks build localized rewrite rules via custom filters, without a usable rewrite slug
        // on the post type object. As a fallback, inspect rewrite rules for post_type=... entries
        // whose regex contains the base segment.
        if ($candidates === [] && isset($GLOBALS['wp_rewrite']) && is_object($GLOBALS['wp_rewrite'])) {
            try {
                $rules = $GLOBALS['wp_rewrite']->wp_rewrite_rules();
                if (is_array($rules)) {
                    foreach ($rules as $pat => $q) {
                        if (!is_string($pat) || !is_string($q)) {
                            continue;
                        }
                        if (strpos($q, 'post_type=') === false) {
                            continue;
                        }
                        // Cheap filter: only consider rules that literally mention the base segment.
                        if (strpos($pat, $base) === false) {
                            continue;
                        }
                        if (preg_match('/(?:^|&)post_type=([^&]+)/', $q, $m)) {
                            $pt = isset($m[1]) ? (string) $m[1] : '';
                            if ($pt !== '') {
                                $candidates[] = $pt;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // If nothing matches, don't guess: we'd risk cross-type collisions.
        return array_values(array_unique(array_filter(array_map('strval', $candidates), static function ($v) {
            return $v !== '';
        })));
    }

    /**
     * Switch an internal URL to the target language base (subdir mode) while preserving
     * query/fragment and keeping absolute vs relative style.
     */
    private static function switchLanguageInUrl(string $url, string $targetLangSlug): string
    {
        $targetLangSlug = trim((string) $targetLangSlug);
        if ($targetLangSlug === '' || !function_exists('pll_home_url')) {
            return '';
        }

        $norm = self::normalizeHref($url);
        if (!$norm['isInternal'] || $norm['isFragmentOnly']) {
            return '';
        }

        $parts = wp_parse_url($url);
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
        $fragment = is_array($parts) ? (string) ($parts['fragment'] ?? '') : '';

        $path = $norm['relativeKey'] !== '' ? $norm['relativeKey'] : '/';

        // Strip leading language segment if it matches any configured Polylang language.
        $segments = explode('/', trim($path, '/'));
        if ($segments !== []) {
            $first = (string) ($segments[0] ?? '');
            $langs = function_exists('pll_languages_list') ? (array) pll_languages_list() : [];
            $langs = array_values(array_filter(array_map('strval', $langs), static function ($v) {
                return $v !== '';
            }));
            if ($first !== '' && in_array($first, $langs, true)) {
                array_shift($segments);
                $path = '/' . implode('/', $segments);
                if ($path === '/') {
                    $path = '/';
                }
            }
        }

        $targetBase = (string) pll_home_url($targetLangSlug);
        if ($targetBase === '') {
            return '';
        }

        $targetBase = rtrim($targetBase, '/');
        //never concat an absolute URL onto the language base (avoids https://hosthttps://host/…)
        if ($path !== '/' && (str_contains($path, '://') || str_starts_with($path, '//'))) {
            return '';
        }
        $newAbsolute = $targetBase . ($path !== '/' ? $path : '/');

        // Restore query/fragment.
        if ($query !== '') {
            $newAbsolute .= '?' . $query;
        }
        if ($fragment !== '') {
            $newAbsolute .= '#' . $fragment;
        }

        // Preserve input style.
        if ($norm['isAbsolute']) {
            return $newAbsolute;
        }

        // Build root-relative language-prefixed URL.
        $targetPath = wp_parse_url($targetBase . '/', PHP_URL_PATH);
        $targetPath = is_string($targetPath) ? $targetPath : '/' . $targetLangSlug . '/';
        $targetPath = '/' . trim($targetPath, '/') . '/';
        $targetPath = $targetPath === '//' ? '/' : $targetPath;

        $relativeNoLead = ltrim($path, '/');
        $relativeLang = rtrim($targetPath, '/') . '/' . $relativeNoLead;
        $relativeLang = '/' . ltrim($relativeLang, '/');
        $relativeLang = str_replace('//', '/', $relativeLang);

        if ($query !== '') {
            $relativeLang .= '?' . $query;
        }
        if ($fragment !== '') {
            $relativeLang .= '#' . $fragment;
        }

        return $relativeLang;
    }

    private static function translateSlugsInUrl(string $url, string $sourceLang, string $targetLangSlug): string
    {
        $sourceLang = trim((string) $sourceLang);
        if ($sourceLang === '') {
            $sourceLang = function_exists('pll_default_language') ? (string) pll_default_language('slug') : 'en';
        }
        $targetLangSlug = trim((string) $targetLangSlug);
        if ($targetLangSlug === '' || $sourceLang === $targetLangSlug) {
            return '';
        }

        // First let Polylang Translate Slugs adjust known bases (archives/search/etc) if available.
        $polylangSwitched = self::applyPolylangTranslateSlugs($url, $targetLangSlug);
        if ($polylangSwitched !== '') {
            $url = $polylangSwitched;
        }

        // Next: try Polylang translated slugs that map entire path strings (can be multi-segment),
        // e.g. "about-us/jobs" => "over-ons/vacatures".
        $fullPath = self::applyPolylangTranslatedSlugsFullPath($url, $sourceLang, $targetLangSlug);
        if ($fullPath !== '') {
            return $fullPath;
        }

        $norm = self::normalizeHref($url);
        if (!$norm['isInternal'] || $norm['isFragmentOnly']) {
            return '';
        }

        $parts = wp_parse_url($url);
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
        $fragment = is_array($parts) ? (string) ($parts['fragment'] ?? '') : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '') {
            $path = $norm['relativeKey'] !== '' ? $norm['relativeKey'] : '/';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($seg) {
            return $seg !== '';
        }));
        if ($segments === []) {
            return '';
        }

        // Strip leading target language segment if present.
        if (isset($segments[0]) && $segments[0] === $targetLangSlug) {
            array_shift($segments);
        }
        if ($segments === []) {
            return '';
        }

        $translated = [];
        $changedAny = false;
        foreach ($segments as $seg) {
            $translatedSeg = self::translateSlugSegment($seg, $sourceLang, $targetLangSlug);
            $translated[] = $translatedSeg;
            if ($translatedSeg !== $seg) {
                $changedAny = true;
            }
        }
        if (!$changedAny) {
            return '';
        }

        $newPath = '/' . $targetLangSlug . '/' . implode('/', $translated) . (str_ends_with($path, '/') ? '/' : '');

        $outAbsolute = $newPath;
        if ($norm['isAbsolute']) {
            $home = function_exists('home_url') ? (string) home_url('/') : '';
            $homeParts = $home !== '' ? wp_parse_url($home) : [];
            $scheme = is_array($homeParts) ? (string) ($homeParts['scheme'] ?? 'https') : 'https';
            $host = is_array($homeParts) ? (string) ($homeParts['host'] ?? '') : '';
            if ($host !== '') {
                $outAbsolute = $scheme . '://' . $host . $newPath;
            } else {
                return '';
            }
        }

        if ($query !== '') {
            $outAbsolute .= '?' . $query;
        }
        if ($fragment !== '') {
            $outAbsolute .= '#' . $fragment;
        }

        return $outAbsolute;
    }

    private static function applyPolylangTranslatedSlugsFullPath(string $url, string $sourceLangSlug, string $targetLangSlug): string
    {
        $sourceLangSlug = trim((string) $sourceLangSlug);
        $targetLangSlug = trim((string) $targetLangSlug);
        if ($sourceLangSlug === '' || $targetLangSlug === '' || $sourceLangSlug === $targetLangSlug || !function_exists('PLL')) {
            return '';
        }

        $norm = self::normalizeHref($url);
        if (!$norm['isInternal'] || $norm['isFragmentOnly']) {
            return '';
        }

        $parts = wp_parse_url($url);
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
        $fragment = is_array($parts) ? (string) ($parts['fragment'] ?? '') : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        if ($path === '') {
            $path = $norm['relativeKey'] !== '' ? $norm['relativeKey'] : '/';
        }

        $pathTrim = trim($path, '/');
        if ($pathTrim === '') {
            return '';
        }

        // If the path is already language-prefixed (common after switchLanguageInUrl),
        // drop the language segment before matching Polylang's stored "en" path strings.
        $segments = explode('/', $pathTrim);
        if (isset($segments[0]) && (string) $segments[0] === $targetLangSlug) {
            array_shift($segments);
        }
        if ($segments === []) {
            return '';
        }
        $matchPath = trim(implode('/', $segments), '/');
        if ($matchPath === '') {
            return '';
        }

        // Collect translated slug entries from the Polylang model.
        try {
            $pll = PLL();
            if (!is_object($pll) || !isset($pll->translate_slugs) || !isset($pll->translate_slugs->slugs_model)) {
                return '';
            }
            $model = $pll->translate_slugs->slugs_model;
            if (!is_object($model) || !isset($model->translated_slugs) || !is_array($model->translated_slugs)) {
                return '';
            }

            $entries = [];
            foreach ($model->translated_slugs as $k => $v) {
                // Some environments expose a numeric list; unit tests may use a map keyed by type.
                if (is_array($v) && isset($v['slug']) && isset($v['translations'])) {
                    $entries[] = $v;
                    continue;
                }
                if (is_array($v) && !isset($v['slug']) && !isset($v['translations'])) {
                    // ignore
                    continue;
                }
                if (is_string($k) && is_array($v)) {
                    $entries[] = $v;
                }
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $trs = $entry['translations'] ?? null;
                if (!is_array($trs)) {
                    continue;
                }
                $from = isset($trs[$sourceLangSlug]) ? (string) $trs[$sourceLangSlug] : '';
                $to = isset($trs[$targetLangSlug]) ? (string) $trs[$targetLangSlug] : '';
                $from = trim($from, '/');
                $to = trim($to, '/');
                if ($from === '' || $to === '') {
                    continue;
                }
                if ($from !== $matchPath) {
                    continue;
                }

                $newPath = '/' . $targetLangSlug . '/' . $to . (str_ends_with($path, '/') ? '/' : '');
                $out = $newPath;
                if ($norm['isAbsolute']) {
                    $home = function_exists('home_url') ? (string) home_url('/') : '';
                    $homeParts = $home !== '' ? wp_parse_url($home) : [];
                    $scheme = is_array($homeParts) ? (string) ($homeParts['scheme'] ?? 'https') : 'https';
                    $host = is_array($homeParts) ? (string) ($homeParts['host'] ?? '') : '';
                    if ($host === '') {
                        return '';
                    }
                    $out = $scheme . '://' . $host . $newPath;
                }

                if ($query !== '') {
                    $out .= '?' . $query;
                }
                if ($fragment !== '') {
                    $out .= '#' . $fragment;
                }

                return $out;
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    private static function translateSlugSegment(string $segment, string $sourceLang, string $targetLang): string
    {
        $segment = trim($segment);
        if ($segment === '' || ctype_digit($segment) || str_contains($segment, '.')) {
            return $segment;
        }

        // Prefer Polylang translate-slugs string translations for known bases (e.g. "resources").
        $pllTranslated = self::getPolylangTranslatedSlugForSegment($segment, $targetLang);
        if ($pllTranslated !== '') {
            return $pllTranslated;
        }

        $cacheKey = strtolower(trim($sourceLang)) . '|' . strtolower(trim($targetLang));
        $segKey = $cacheKey . '|' . strtolower($segment);
        if (isset(self::$slugTranslationCache[$cacheKey][$segKey])) {
            return self::$slugTranslationCache[$cacheKey][$segKey];
        }

        $text = str_replace(['-', '_'], ' ', $segment);
        $translated = $text;
        if (class_exists('\NoviOnline\ContentTranslator\Core\DeepLTranslator')) {
            $translated = (string) DeepLTranslator::translateText($text, $sourceLang, $targetLang, ['context' => 'plain']);
        }

        $slug = self::slugify($translated);
        if ($slug === '') {
            $slug = $segment;
        }

        self::$slugTranslationCache[$cacheKey][$segKey] = $slug;
        return $slug;
    }

    private static function slugify(string $text): string
    {
        $lower = function_exists('mb_strtolower') ? (string) mb_strtolower($text) : strtolower($text);
        $text = trim($lower);
        if ($text === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $text = (string) remove_accents($text);
        } elseif (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        if (function_exists('sanitize_title')) {
            return (string) sanitize_title($text);
        }

        $text = preg_replace('/[^a-z0-9\\s\\-]/', '', $text);
        $text = preg_replace('/[\\s\\-]+/', '-', (string) $text);
        $text = trim((string) $text, '-');
        return $text;
    }

    private static function applyPolylangTranslateSlugs(string $url, string $targetLangSlug): string
    {
        if (!function_exists('PLL')) {
            return '';
        }

        try {
            $pll = PLL();
            if (!is_object($pll) || !isset($pll->translate_slugs) || !isset($pll->translate_slugs->slugs_model)) {
                return '';
            }
            $model = $pll->translate_slugs->slugs_model;
            if (!is_object($model) || !method_exists($model, 'switch_translated_slug')) {
                return '';
            }

            $lang = null;
            if (isset($pll->model) && is_object($pll->model) && method_exists($pll->model, 'get_language')) {
                $lang = $pll->model->get_language($targetLangSlug);
            }
            if (!is_object($lang)) {
                return '';
            }

            // Try a few common base types. This is conservative; DeepL will handle the rest.
            $candidates = [
                'front',
                'search',
                'author',
                'paged',
            ];
            $out = $url;
            foreach ($candidates as $type) {
                $sw = $model->switch_translated_slug($out, $lang, $type);
                if (is_string($sw) && $sw !== '') {
                    $out = $sw;
                }
            }
            return $out !== $url ? $out : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function getPolylangTranslatedSlugForSegment(string $segment, string $targetLangSlug): string
    {
        $segment = trim((string) $segment);
        $targetLangSlug = trim((string) $targetLangSlug);
        if ($segment === '' || $targetLangSlug === '' || !function_exists('PLL')) {
            return '';
        }

        try {
            $pll = PLL();
            if (!is_object($pll) || !isset($pll->translate_slugs) || !isset($pll->translate_slugs->slugs_model)) {
                return '';
            }
            $model = $pll->translate_slugs->slugs_model;
            if (!is_object($model) || !isset($model->translated_slugs) || !is_array($model->translated_slugs)) {
                return '';
            }

            foreach ($model->translated_slugs as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
                if ($slug !== '' && $slug === $segment) {
                    $tr = isset($entry['translations'][$targetLangSlug]) ? (string) $entry['translations'][$targetLangSlug] : '';
                    return $tr !== '' ? $tr : '';
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    private static function rewriteHrefsByRegex(string $html, string $targetLangSlug, array &$stats): string
    {
        $pattern = '/<a\\b([^>]*?)\\shref=(["\'])(.*?)\\2([^>]*)>/i';
        return (string) preg_replace_callback($pattern, function ($m) use ($targetLangSlug, &$stats) {
            $href = (string) ($m[3] ?? '');
            $stats['encountered']++;
            if (self::shouldSkipHref($href)) {
                $stats['skipped']++;
                return $m[0];
            }
            $norm = self::normalizeHref($href);
            if ($norm['isFragmentOnly']) {
                $stats['fragment_only']++;
                return $m[0];
            }
            if (!$norm['isInternal']) {
                $stats['external']++;
                return $m[0];
            }
            $stats['internal']++;

            $resolved = self::rewriteByDynamicResolution($href, $targetLangSlug);
            if ($resolved['changed']) {
                $stats['rewritten_by_object_translation']++;
                $quote = $m[2];
                return '<a' . $m[1] . ' href=' . $quote . $resolved['href'] . $quote . $m[4] . '>';
            }

            $fallback = self::switchLanguageInUrl($href, $targetLangSlug);
            if ($fallback !== '' && $fallback !== $href) {
                $stats['rewritten_by_language_switch']++;
                $quote = $m[2];
                $final = $fallback;
                $slugged = self::translateSlugsInUrl($fallback, '', $targetLangSlug);
                if ($slugged !== '' && $slugged !== $fallback) {
                    $final = $slugged;
                    $stats['rewritten_by_slug_translation']++;
                }
                return '<a' . $m[1] . ' href=' . $quote . $final . $quote . $m[4] . '>';
            }

            $stats['not_rewritten']++;
            return $m[0];
        }, $html);
    }
}

