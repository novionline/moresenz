<?php
/**
 * Bulk-fill empty NL image alts/titles on NectarBlocks attrs (local).
 *
 * Prompt/model from wordpress-ai-toolbelt-plugin.
 * NB-safe write: parse_blocks → attrs + sync <img> innerHTML/innerContent
 * → serialize_blocks → wp_slash → wp_update_post.
 *
 * Usage:
 *   ALT_FILL_APPLY=1 wp eval-file ../tmp/alt-inventory/gemini-alt-fill-bulk.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$apply = getenv('ALT_FILL_APPLY') === '1';
$limit = (int) (getenv('ALT_FILL_LIMIT') ?: 0);
$model = getenv('GEMINI_MODEL') ?: 'gemini-3.1-flash-lite';
$locale = getenv('ALT_FILL_LOCALE') ?: (get_locale() ?: 'nl_NL');
$apiKey = getenv('GEMINI_API_KEY') ?: '';
$inventoryDir = dirname(__FILE__);
$cachePath = $inventoryDir . '/gemini-alt-cache.json';
$logPath = $inventoryDir . '/gemini-alt-fill-bulk-log.json';
$discoverPath = $inventoryDir . '/discover-image-attr-gaps.json';

if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY missing\n");
    exit(1);
}

$cache = file_exists($cachePath) ? (json_decode(file_get_contents($cachePath), true) ?: []) : [];

function bulkEmpty($v): bool
{
    return $v === null || (is_string($v) && trim($v) === '');
}

function bulkIsRaster(string $url): bool
{
    $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.(jpe?g|png|webp|gif|avif)$/', $p);
}

function bulkIsSvg(string $url): bool
{
    $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.svg$/', $p);
}

function bulkWalk(array $blocks, callable $cb): void
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $cb($block);
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            bulkWalk($block['innerBlocks'], $cb);
        }
    }
}

function bulkToolbeltPrompt(string $locale, string $imageTitle, string $mimeType): string
{
    $lines = ['Generate an alt text for the given image (<125 chars).'];
    if ($locale !== '') {
        $lines[] = sprintf('Use locale: "%s".', $locale);
    }
    if ($imageTitle !== '') {
        $lines[] = sprintf('The file name of the image is "%s".', $imageTitle);
    }
    if ($mimeType === 'image/png') {
        $lines[] = 'The given image is a PNG. Do not describe the background color.';
    }
    $lines[] = 'Describe visible subject(s) first, then any text/logo.';
    $lines[] = 'Return one concise sentence, no "Image of".';
    return implode("\n", $lines);
}

/**
 * @return array{path:?string,mime:?string}
 */
function bulkPickFile(int $attachmentId): array
{
    $full = get_attached_file($attachmentId);
    $mime = get_post_mime_type($attachmentId) ?: null;
    $uploadDir = wp_get_upload_dir();
    $basedir = $uploadDir['basedir'] ?? '';
    $candidates = [];
    foreach (['medium_large', 'large'] as $size) {
        $mid = image_get_intermediate_size($attachmentId, $size);
        if (is_array($mid) && !empty($mid['path'])) {
            $candidates[] = $basedir . '/' . ltrim((string) $mid['path'], '/');
        }
    }
    if ($full) {
        $candidates[] = $full;
    }
    foreach ($candidates as $path) {
        if ($path && file_exists($path) && is_readable($path)) {
            return ['path' => $path, 'mime' => $mime ?: (mime_content_type($path) ?: 'image/jpeg')];
        }
    }
    return ['path' => null, 'mime' => $mime];
}

/**
 * @return array{ok:bool,alt:?string,error:?string}
 */
function bulkGemini(string $apiKey, string $model, string $imagePath, string $mime, string $locale, string $imageTitle): array
{
    $bytes = file_get_contents($imagePath);
    if ($bytes === false) {
        return ['ok' => false, 'alt' => null, 'error' => 'read_failed'];
    }
    if (strlen($bytes) > 4 * 1024 * 1024) {
        return ['ok' => false, 'alt' => null, 'error' => 'too_large'];
    }

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => bulkToolbeltPrompt($locale, $imageTitle, $mime)],
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
            ],
        ]],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 125,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $response = wp_remote_post($url, [
        'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($payload),
    ]);
    if (is_wp_error($response)) {
        return ['ok' => false, 'alt' => null, 'error' => $response->get_error_message()];
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
        return ['ok' => false, 'alt' => null, 'error' => "http_{$code}: " . substr($msg, 0, 200)];
    }

    $text = '';
    foreach (($json['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (!empty($part['text']) && is_string($part['text'])) {
            $text .= $part['text'];
        }
    }
    $text = trim(preg_replace('/\s+/', ' ', trim($text, " \t\n\r\0\x0B\"'")) ?: '');
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 125);
    } else {
        $text = substr($text, 0, 125);
    }
    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'alt' => null, 'error' => 'empty_response'];
    }
    return ['ok' => true, 'alt' => $text, 'error' => null];
}

function bulkSyncImgHtml(string $html, string $alt): string
{
    if ($html === '') {
        return $html;
    }
    return (string) preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $matches) use ($alt): string {
            $tag = $matches[0];
            $escaped = esc_attr($alt);
            if (preg_match('/\balt=("|\')/i', $tag)) {
                $tag = (string) preg_replace('/\balt=("|\').*?\1/i', 'alt="' . $escaped . '"', $tag, 1);
            } else {
                $tag = (string) preg_replace('/<img\b/i', '<img alt="' . $escaped . '"', $tag, 1);
            }
            if (preg_match('/\btitle=("|\')/i', $tag)) {
                $tag = (string) preg_replace('/\btitle=("|\').*?\1/i', 'title="' . $escaped . '"', $tag, 1);
            } else {
                $tag = (string) preg_replace('/<img\b/i', '<img title="' . $escaped . '"', $tag, 1);
            }
            return $tag;
        },
        $html,
        1
    );
}

/**
 * Recursively fill empty alt/title on image-like nodes that match alt map by attachment id.
 *
 * @param array<int,string> $altByAttachment
 * @return int filled count
 */
function bulkFillImageNodes(&$node, array $altByAttachment, string $path = ''): int
{
    $filled = 0;
    if (!is_array($node)) {
        return 0;
    }

    $hasUrl = isset($node['url']) && is_string($node['url']);
    $hasAlt = array_key_exists('alt', $node);
    if ($hasUrl && $hasAlt) {
        $url = (string) $node['url'];
        $id = (int) ($node['id'] ?? 0);
        if (
            $id > 0
            && isset($altByAttachment[$id])
            && bulkIsRaster($url)
            && !bulkIsSvg($url)
            && bulkEmpty($node['alt'] ?? '')
        ) {
            $alt = $altByAttachment[$id];
            $node['alt'] = $alt;
            $node['title'] = $alt;
            $filled++;
        }
    }

    foreach ($node as $k => &$v) {
        if (is_array($v)) {
            $filled += bulkFillImageNodes($v, $altByAttachment, $path);
        }
    }
    unset($v);

    return $filled;
}

//collect unique targets from discover file or live scan
$targets = [];
if (file_exists($discoverPath)) {
    $disc = json_decode(file_get_contents($discoverPath), true) ?: [];
    foreach (($disc['uniqueTargets'] ?? []) as $t) {
        $id = (int) ($t['attachmentId'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $targets[$id] = [
            'attachmentId' => $id,
            'blockTitle' => (string) ($t['title'] ?: get_the_title($id)),
            'contextTitle' => (string) ($t['postTitle'] ?? ''),
            'source' => (string) ($t['block'] ?? 'nectar-blocks/image'),
        ];
    }
}

//also include featured empty rasters (meta-only secondary)
$featQ = new WP_Query([
    'post_type' => ['novi-project', 'page', 'popup'],
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
]);
foreach ($featQ->posts as $postId) {
    $thumbId = (int) get_post_thumbnail_id($postId);
    if ($thumbId <= 0) {
        continue;
    }
    $url = (string) (wp_get_attachment_url($thumbId) ?: '');
    if (!bulkIsRaster($url) || bulkIsSvg($url)) {
        continue;
    }
    if (!bulkEmpty(get_post_meta($thumbId, '_wp_attachment_image_alt', true))) {
        continue;
    }
    if (!isset($targets[$thumbId])) {
        $targets[$thumbId] = [
            'attachmentId' => $thumbId,
            'blockTitle' => get_the_title($thumbId),
            'contextTitle' => get_the_title($postId),
            'source' => 'featured',
        ];
    }
}

ksort($targets);
if ($limit > 0) {
    $targets = array_slice($targets, 0, $limit, true);
}

$stats = [
    'targets' => count($targets),
    'generated' => 0,
    'cacheHits' => 0,
    'failedGenerate' => 0,
    'blockAltsFilled' => 0,
    'blockAltsSkippedNonEmpty' => 0,
    'postsUpdated' => 0,
    'metaUpdated' => 0,
    'metaSkippedNonEmpty' => 0,
    'byBlock' => [],
    'apply' => $apply,
    'model' => $model,
    'locale' => $locale,
    'failures' => [],
];

foreach ($targets as $attachmentId => $target) {
    $cacheKey = (string) $attachmentId;
    if (!empty($cache[$cacheKey]['alt']) && is_string($cache[$cacheKey]['alt'])) {
        $stats['cacheHits']++;
        continue;
    }

    $picked = bulkPickFile($attachmentId);
    if (!$picked['path']) {
        $stats['failedGenerate']++;
        $stats['failures'][] = ['attachmentId' => $attachmentId, 'error' => 'file_missing'];
        $cache[$cacheKey] = ['alt' => null, 'error' => 'file_missing'];
        file_put_contents($cachePath, wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        continue;
    }

    $result = bulkGemini(
        $apiKey,
        $model,
        $picked['path'],
        (string) $picked['mime'],
        $locale,
        (string) ($target['blockTitle'] ?: $target['contextTitle'])
    );

    if (!$result['ok']) {
        $stats['failedGenerate']++;
        $stats['failures'][] = ['attachmentId' => $attachmentId, 'error' => $result['error']];
        $cache[$cacheKey] = ['alt' => null, 'error' => $result['error'], 'source' => $target['source']];
    } else {
        $stats['generated']++;
        $cache[$cacheKey] = [
            'alt' => $result['alt'],
            'error' => null,
            'source' => $target['source'],
            'blockTitle' => $target['blockTitle'],
        ];
    }
    file_put_contents($cachePath, wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    usleep(120000);
}

$altByAttachment = [];
foreach ($cache as $id => $row) {
    if (!empty($row['alt']) && is_string($row['alt'])) {
        $altByAttachment[(int) $id] = trim($row['alt']);
    }
}

if ($apply) {
    //optional attachment meta when empty
    foreach ($altByAttachment as $attachmentId => $alt) {
        $existing = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        if (!bulkEmpty($existing)) {
            $stats['metaSkippedNonEmpty']++;
            continue;
        }
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        $stats['metaUpdated']++;
    }

    $contentQ = new WP_Query([
        'post_type' => ['page', 'post', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'],
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
    ]);

    foreach ($contentQ->posts as $post) {
        $blocks = parse_blocks($post->post_content);
        $changed = false;
        $filledHere = 0;

        $walker = function (&$blocks) use (&$walker, &$changed, &$filledHere, $altByAttachment, &$stats) {
            foreach ($blocks as &$block) {
                if (!is_array($block)) {
                    continue;
                }
                $blockName = (string) ($block['blockName'] ?? '');
                $attrs = $block['attrs'] ?? null;
                if (is_array($attrs) && $blockName !== '') {
                    $before = $filledHere;
                    $n = bulkFillImageNodes($attrs, $altByAttachment);
                    if ($n > 0) {
                        $block['attrs'] = $attrs;
                        $filledHere += $n;
                        $stats['blockAltsFilled'] += $n;
                        $stats['byBlock'][$blockName] = ($stats['byBlock'][$blockName] ?? 0) + $n;
                        $changed = true;

                        //sync first img tag(s) in saved markup for image blocks
                        if ($blockName === 'nectar-blocks/image') {
                            $id = (int) ($block['attrs']['image']['id'] ?? 0);
                            if ($id > 0 && isset($altByAttachment[$id]) && !bulkEmpty($block['attrs']['image']['alt'] ?? '')) {
                                $alt = (string) $block['attrs']['image']['alt'];
                                if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                                    $block['innerHTML'] = bulkSyncImgHtml($block['innerHTML'], $alt);
                                }
                                if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                                    foreach ($block['innerContent'] as $i => $piece) {
                                        if (is_string($piece)) {
                                            $block['innerContent'][$i] = bulkSyncImgHtml($piece, $alt);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        //count skipped non-empty image nodes for nectar-blocks/image
                        if ($blockName === 'nectar-blocks/image') {
                            $img = $attrs['image'] ?? [];
                            $url = (string) ($img['url'] ?? '');
                            $id = (int) ($img['id'] ?? 0);
                            if ($id > 0 && isset($altByAttachment[$id]) && bulkIsRaster($url) && !bulkEmpty($img['alt'] ?? '')) {
                                $stats['blockAltsSkippedNonEmpty']++;
                            }
                        }
                    }
                    //even if attrs already filled, ensure title matches alt when we own the attachment and alt equals generated?
                    //skip: never overwrite non-empty alts; title sync only when we fill alt
                }

                if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $walker($block['innerBlocks']);
                }
            }
            unset($block);
        };

        $walker($blocks);

        if ($changed) {
            $result = wp_update_post([
                'ID' => $post->ID,
                'post_content' => wp_slash(serialize_blocks($blocks)),
            ], true);
            if (is_wp_error($result)) {
                $stats['failures'][] = ['postId' => $post->ID, 'error' => $result->get_error_message()];
            } else {
                $stats['postsUpdated']++;
            }
        }
    }
}

$usable = count($altByAttachment);
$failedCache = 0;
foreach ($cache as $row) {
    if (empty($row['alt'])) {
        $failedCache++;
    }
}

$summary = [
    'stats' => $stats,
    'usableAlts' => $usable,
    'failedOrMissingInCache' => $failedCache,
    'cachePath' => $cachePath,
    'sampleAlts' => array_slice($altByAttachment, 0, 5, true),
    'nbSafeWrite' => 'parse_blocks → attrs.alt/title (+ img html sync) → serialize_blocks → wp_slash → wp_update_post',
    'promptSource' => 'wordpress-ai-toolbelt-plugin/partials/prompts/generate-alt-tag-by-image.php',
    'eclipseSample' => 'already filled (post 27 / att 2003) — skipped when non-empty',
];

file_put_contents($logPath, wp_json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
