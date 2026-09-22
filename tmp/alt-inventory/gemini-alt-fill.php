<?php
/**
 * Fill empty NL nectar-blocks/image attrs.image.alt via Gemini Vision.
 *
 * Prompt + generation settings adapted from wordpress-ai-toolbelt-plugin
 * (partials/prompts/generate-alt-tag-by-image.php + AltTagEndpoints).
 * Primary write: NectarBlocks block attrs (Polylang). Optional: empty
 * `_wp_attachment_image_alt` only.
 *
 * Usage (from public/):
 *   ALT_FILL_APPLY=0 wp eval-file ../tmp/alt-inventory/gemini-alt-fill.php
 *   ALT_FILL_APPLY=1 wp eval-file ../tmp/alt-inventory/gemini-alt-fill.php
 *
 * Requires GEMINI_API_KEY in env. Never overwrites non-empty alts. Skips SVGs.
 */

if (!defined('ABSPATH')) {
    exit;
}

$apply = getenv('ALT_FILL_APPLY') === '1';
$limit = (int) (getenv('ALT_FILL_LIMIT') ?: 0);
$inventoryDir = dirname(__FILE__);
$inventoryPath = $inventoryDir . '/nb-expanded-image-inventory.json';
$cachePath = $inventoryDir . '/gemini-alt-cache.json';
$logPath = $inventoryDir . '/gemini-alt-fill-log.json';
//toolbelt UI recommends Gemini 2.0 Flash; that id is retired for new keys.
//gemini-3.1-flash-lite accepts the same toolbelt prompt + maxOutputTokens:125 without thinking truncation.
$model = getenv('GEMINI_MODEL') ?: 'gemini-3.1-flash-lite';
$apiKey = getenv('GEMINI_API_KEY') ?: '';
$locale = getenv('ALT_FILL_LOCALE') ?: (get_locale() ?: 'nl_NL');

if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY missing\n");
    exit(1);
}

if (!file_exists($inventoryPath)) {
    fwrite(STDERR, "inventory missing: {$inventoryPath}\n");
    exit(1);
}

$inventory = json_decode(file_get_contents($inventoryPath), true);
if (!is_array($inventory)) {
    fwrite(STDERR, "invalid inventory json\n");
    exit(1);
}

$cache = [];
if (file_exists($cachePath)) {
    $cache = json_decode(file_get_contents($cachePath), true) ?: [];
}

/**
 * @param mixed $value
 */
function altFillIsEmpty($value): bool
{
    if ($value === null) {
        return true;
    }
    if (is_string($value)) {
        return trim($value) === '';
    }
    return false;
}

function altFillIsRasterUrl(string $url): bool
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.(jpe?g|png|webp|gif|avif)$/', $path);
}

function altFillIsSvgUrl(string $url): bool
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.svg$/', $path);
}

/**
 * @param array $blocks
 * @param callable $callback
 */
function altFillWalk(array $blocks, callable $callback): void
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $callback($block);
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            altFillWalk($block['innerBlocks'], $callback);
        }
    }
}

/**
 * @return array{path:?string,mime:?string}
 */
function altFillPickImageFile(int $attachmentId): array
{
    $full = get_attached_file($attachmentId);
    $mime = get_post_mime_type($attachmentId) ?: null;
    $candidates = [];

    $mid = image_get_intermediate_size($attachmentId, 'medium_large');
    if (is_array($mid) && !empty($mid['path'])) {
        $uploadDir = wp_get_upload_dir();
        $basedir = $uploadDir['basedir'] ?? '';
        $candidates[] = $basedir . '/' . ltrim((string) $mid['path'], '/');
    }

    $large = image_get_intermediate_size($attachmentId, 'large');
    if (is_array($large) && !empty($large['path'])) {
        $uploadDir = wp_get_upload_dir();
        $basedir = $uploadDir['basedir'] ?? '';
        $candidates[] = $basedir . '/' . ltrim((string) $large['path'], '/');
    }

    if ($full) {
        $candidates[] = $full;
    }

    foreach ($candidates as $path) {
        if ($path && file_exists($path) && is_readable($path)) {
            return ['path' => $path, 'mime' => $mime];
        }
    }

    return ['path' => null, 'mime' => $mime];
}

/**
 * Build prompt matching wordpress-ai-toolbelt-plugin generate-alt-tag-by-image.
 */
function altFillBuildToolbeltPrompt(string $locale, string $imageTitle, string $mimeType): string
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
 * @return array{ok:bool,alt:?string,error:?string}
 */
function altFillGemini(string $apiKey, string $model, string $imagePath, string $mime, string $locale, string $imageTitle): array
{
    $bytes = file_get_contents($imagePath);
    if ($bytes === false) {
        return ['ok' => false, 'alt' => null, 'error' => 'read_failed'];
    }

    //keep payloads reasonable
    if (strlen($bytes) > 4 * 1024 * 1024) {
        return ['ok' => false, 'alt' => null, 'error' => 'too_large'];
    }

    $mimeType = $mime ?: (mime_content_type($imagePath) ?: 'image/jpeg');
    $prompt = altFillBuildToolbeltPrompt($locale, $imageTitle, $mimeType);

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($bytes),
                    ],
                ],
            ],
        ]],
        //match AltTagEndpoints generationConfig
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
    $body = (string) wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? $body) : $body;
        return ['ok' => false, 'alt' => null, 'error' => "http_{$code}: " . substr($msg, 0, 200)];
    }

    $text = '';
    $parts = $json['candidates'][0]['content']['parts'] ?? [];
    if (is_array($parts)) {
        foreach ($parts as $part) {
            if (!empty($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }
    }
    $text = trim((string) $text);
    $text = trim($text, " \t\n\r\0\x0B\"'");
    $text = preg_replace('/\s+/', ' ', $text) ?: '';
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 125);
    } else {
        $text = substr($text, 0, 125);
    }
    $text = trim($text);

    if ($text === '') {
        $finish = $json['candidates'][0]['finishReason'] ?? '';
        return ['ok' => false, 'alt' => null, 'error' => 'empty_response:' . $finish];
    }

    return ['ok' => true, 'alt' => $text, 'error' => null];
}

//build unique targets: inventory photos + popups + featured
$targets = [];

foreach (($inventory['uniquePhotoGaps'] ?? []) as $gap) {
    $attachmentId = (int) ($gap['attachmentId'] ?? 0);
    $url = (string) ($gap['url'] ?? '');
    if ($attachmentId <= 0 || altFillIsSvgUrl($url) || !altFillIsRasterUrl($url)) {
        continue;
    }
    $targets[$attachmentId] = [
        'attachmentId' => $attachmentId,
        'url' => $url,
        'contextTitle' => (string) ($gap['postTitle'] ?? ''),
        'blockTitle' => (string) ($gap['blockTitle'] ?? $gap['attTitle'] ?? ''),
        'source' => 'inventory:' . (string) ($gap['postType'] ?? '') . ':' . (string) ($gap['block'] ?? ''),
    ];
}

//popup content photos
foreach ([823, 703] as $popupId) {
    $post = get_post($popupId);
    if (!$post) {
        continue;
    }
    altFillWalk(parse_blocks($post->post_content), function ($block) use (&$targets, $post) {
        if (($block['blockName'] ?? '') !== 'nectar-blocks/image') {
            return;
        }
        $image = $block['attrs']['image'] ?? [];
        $url = (string) ($image['url'] ?? '');
        $attachmentId = (int) ($image['id'] ?? 0);
        if ($attachmentId <= 0 || !altFillIsRasterUrl($url) || altFillIsSvgUrl($url)) {
            return;
        }
        if (!altFillIsEmpty($image['alt'] ?? '')) {
            return;
        }
        if (!isset($targets[$attachmentId])) {
            $targets[$attachmentId] = [
                'attachmentId' => $attachmentId,
                'url' => $url,
                'contextTitle' => (string) $post->post_title,
                'blockTitle' => (string) ($image['title'] ?? ''),
                'source' => 'popup',
            ];
        }
    });
}

//featured empty alts
$featuredQuery = new WP_Query([
    'post_type' => ['novi-project', 'page', 'popup'],
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
]);
foreach ($featuredQuery->posts as $postId) {
    $thumbId = (int) get_post_thumbnail_id($postId);
    if ($thumbId <= 0) {
        continue;
    }
    $url = (string) (wp_get_attachment_url($thumbId) ?: '');
    if (!altFillIsRasterUrl($url) || altFillIsSvgUrl($url)) {
        continue;
    }
    $attAlt = get_post_meta($thumbId, '_wp_attachment_image_alt', true);
    if (!altFillIsEmpty($attAlt)) {
        continue;
    }
    if (!isset($targets[$thumbId])) {
        $targets[$thumbId] = [
            'attachmentId' => $thumbId,
            'url' => $url,
            'contextTitle' => get_the_title($postId),
            'blockTitle' => get_the_title($thumbId),
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
    'metaUpdated' => 0,
    'metaSkippedNonEmpty' => 0,
    'metaSkippedMissing' => 0,
    'postsUpdated' => 0,
    'blockAltsFilled' => 0,
    'blockAltsSkippedNonEmpty' => 0,
    'apply' => $apply,
    'model' => $model,
    'locale' => $locale,
    'promptSource' => 'wordpress-ai-toolbelt-plugin/partials/prompts/generate-alt-tag-by-image.php',
    'failures' => [],
];

foreach ($targets as $attachmentId => $target) {
    $cacheKey = (string) $attachmentId;
    if (!empty($cache[$cacheKey]['alt']) && is_string($cache[$cacheKey]['alt'])) {
        $stats['cacheHits']++;
        continue;
    }

    $picked = altFillPickImageFile($attachmentId);
    if (!$picked['path']) {
        $stats['failedGenerate']++;
        $stats['failures'][] = ['attachmentId' => $attachmentId, 'error' => 'file_missing'];
        $cache[$cacheKey] = ['alt' => null, 'error' => 'file_missing', 'source' => $target['source']];
        file_put_contents($cachePath, wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        continue;
    }

    $result = altFillGemini(
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
            'contextTitle' => $target['contextTitle'],
            'blockTitle' => $target['blockTitle'],
        ];
    }

    file_put_contents($cachePath, wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    usleep(150000);
}

//build usable alt map
$altByAttachment = [];
foreach ($cache as $id => $row) {
    if (!empty($row['alt']) && is_string($row['alt'])) {
        $altByAttachment[(int) $id] = trim($row['alt']);
    }
}

if ($apply) {
    //attachment meta
    foreach ($altByAttachment as $attachmentId => $alt) {
        $existing = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        if (!altFillIsEmpty($existing)) {
            $stats['metaSkippedNonEmpty']++;
            continue;
        }
        if (!get_post($attachmentId)) {
            $stats['metaSkippedMissing']++;
            continue;
        }
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        $stats['metaUpdated']++;
    }

    //block attrs on relevant posts
    $contentQuery = new WP_Query([
        'post_type' => ['page', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'],
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
    ]);

    foreach ($contentQuery->posts as $post) {
        $blocks = parse_blocks($post->post_content);
        $changed = false;
        $filledHere = 0;

        $walker = function (&$blocks) use (&$walker, &$changed, &$filledHere, $altByAttachment, &$stats) {
            foreach ($blocks as &$block) {
                if (!is_array($block)) {
                    continue;
                }
                if (($block['blockName'] ?? '') === 'nectar-blocks/image') {
                    $image = $block['attrs']['image'] ?? null;
                    if (is_array($image)) {
                        $attachmentId = (int) ($image['id'] ?? 0);
                        $url = (string) ($image['url'] ?? '');
                        if (
                            $attachmentId > 0
                            && isset($altByAttachment[$attachmentId])
                            && altFillIsRasterUrl($url)
                            && !altFillIsSvgUrl($url)
                        ) {
                            if (altFillIsEmpty($image['alt'] ?? '')) {
                                $block['attrs']['image']['alt'] = $altByAttachment[$attachmentId];
                                $changed = true;
                                $filledHere++;
                                $stats['blockAltsFilled']++;
                            } else {
                                $stats['blockAltsSkippedNonEmpty']++;
                            }
                        }
                    }
                }
                if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $walker($block['innerBlocks']);
                }
            }
            unset($block);
        };

        $walker($blocks);

        if ($changed) {
            $newContent = serialize_blocks($blocks);
            $result = wp_update_post([
                'ID' => $post->ID,
                'post_content' => $newContent,
            ], true);
            if (is_wp_error($result)) {
                $stats['failures'][] = [
                    'postId' => $post->ID,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $stats['postsUpdated']++;
            }
        }
    }
}

$usable = count($altByAttachment);
$failed = 0;
foreach ($cache as $row) {
    if (empty($row['alt'])) {
        $failed++;
    }
}

$summary = [
    'stats' => $stats,
    'usableAlts' => $usable,
    'failedOrMissingInCache' => $failed,
    'cachePath' => $cachePath,
    'sampleAlts' => array_slice($altByAttachment, 0, 8, true),
];

file_put_contents($logPath, wp_json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
