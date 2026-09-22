<?php
/**
 * Retry Gemini alt generation for attachments that failed as too_large,
 * using a temporary downscaled JPEG (does not replace originals).
 * Then apply remaining empty block alts from cache (NB-safe).
 */

if (!defined('ABSPATH')) {
    exit;
}

$apiKey = getenv('GEMINI_API_KEY') ?: '';
$model = getenv('GEMINI_MODEL') ?: 'gemini-3.1-flash-lite';
$locale = getenv('ALT_FILL_LOCALE') ?: 'nl_NL';
$inventoryDir = dirname(__FILE__);
$cachePath = $inventoryDir . '/gemini-alt-cache.json';
$apply = getenv('ALT_FILL_APPLY') !== '0';

if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY missing\n");
    exit(1);
}

$cache = file_exists($cachePath) ? (json_decode(file_get_contents($cachePath), true) ?: []) : [];

function retryEmpty($v): bool
{
    return $v === null || (is_string($v) && trim($v) === '');
}

function retryIsRaster(string $url): bool
{
    $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.(jpe?g|png|webp|gif|avif)$/', $p);
}

function retryIsSvg(string $url): bool
{
    $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.svg$/', $p);
}

function retryToolbeltPrompt(string $locale, string $imageTitle, string $mimeType): string
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
 * Build a temp JPEG <= maxEdge / ~maxBytes for Gemini only.
 *
 * @return array{ok:bool,path:?string,mime:?string,error:?string,bytes:?int,method:?string}
 */
function retryMakeTempDerivative(int $attachmentId, int $maxEdge = 1600, int $maxBytes = 3500000): array
{
    $upload = wp_get_upload_dir();
    $basedir = $upload['basedir'] ?? '';

    //prefer existing intermediates under size limit
    foreach (['medium_large', 'large', 'medium', 'thumbnail'] as $size) {
        $mid = image_get_intermediate_size($attachmentId, $size);
        if (!$mid || empty($mid['path'])) {
            continue;
        }
        $path = $basedir . '/' . ltrim((string) $mid['path'], '/');
        if (file_exists($path) && is_readable($path) && filesize($path) <= $maxBytes) {
            return [
                'ok' => true,
                'path' => $path,
                'mime' => mime_content_type($path) ?: 'image/jpeg',
                'error' => null,
                'bytes' => filesize($path),
                'method' => 'intermediate:' . $size,
                'cleanup' => false,
            ];
        }
    }

    $full = get_attached_file($attachmentId);
    if (!$full || !is_readable($full)) {
        return ['ok' => false, 'path' => null, 'mime' => null, 'error' => 'file_missing', 'bytes' => null, 'method' => null, 'cleanup' => false];
    }

    $editor = wp_get_image_editor($full);
    if (is_wp_error($editor)) {
        return ['ok' => false, 'path' => null, 'mime' => null, 'error' => $editor->get_error_message(), 'bytes' => null, 'method' => null, 'cleanup' => false];
    }

    $editor->resize($maxEdge, $maxEdge, false);
    $tmp = trailingslashit(sys_get_temp_dir()) . 'moresenz-alt-' . $attachmentId . '-' . wp_generate_password(6, false) . '.jpg';
    $saved = $editor->save($tmp, 'image/jpeg');
    if (is_wp_error($saved)) {
        return ['ok' => false, 'path' => null, 'mime' => null, 'error' => $saved->get_error_message(), 'bytes' => null, 'method' => null, 'cleanup' => false];
    }

    $path = is_array($saved) ? (string) ($saved['path'] ?? $tmp) : $tmp;
    if (!file_exists($path)) {
        return ['ok' => false, 'path' => null, 'mime' => null, 'error' => 'temp_missing', 'bytes' => null, 'method' => null, 'cleanup' => false];
    }

    //if still huge, try quality reduction via second pass
    if (filesize($path) > $maxBytes && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($path);
        if ($img) {
            $path2 = preg_replace('/\.jpg$/', '-q70.jpg', $path) ?: ($path . '.q70.jpg');
            imagejpeg($img, $path2, 70);
            imagedestroy($img);
            if (file_exists($path2)) {
                @unlink($path);
                $path = $path2;
            }
        }
    }

    return [
        'ok' => true,
        'path' => $path,
        'mime' => 'image/jpeg',
        'error' => null,
        'bytes' => filesize($path),
        'method' => 'temp_resize_' . $maxEdge,
        'cleanup' => true,
    ];
}

/**
 * @return array{ok:bool,alt:?string,error:?string}
 */
function retryGemini(string $apiKey, string $model, string $imagePath, string $mime, string $locale, string $imageTitle): array
{
    $bytes = file_get_contents($imagePath);
    if ($bytes === false) {
        return ['ok' => false, 'alt' => null, 'error' => 'read_failed'];
    }

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => retryToolbeltPrompt($locale, $imageTitle, $mime)],
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
        'timeout' => 90,
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

function retrySyncImgHtml(string $html, string $alt): string
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

function retryFillImageNodes(&$node, array $altByAttachment): int
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
            && retryIsRaster($url)
            && !retryIsSvg($url)
            && retryEmpty($node['alt'] ?? '')
        ) {
            $alt = $altByAttachment[$id];
            $node['alt'] = $alt;
            $node['title'] = $alt;
            $filled++;
        }
    }
    foreach ($node as &$v) {
        if (is_array($v)) {
            $filled += retryFillImageNodes($v, $altByAttachment);
        }
    }
    unset($v);
    return $filled;
}

//ids that failed as too_large (+ any cache entries with that error)
$retryIds = [];
foreach ($cache as $id => $row) {
    $err = (string) ($row['error'] ?? '');
    if ($err === 'too_large' || str_contains($err, 'too_large') || str_contains(strtolower($err), 'too large')) {
        $retryIds[] = (int) $id;
    }
}
foreach ([2233, 2391] as $id) {
    if (!in_array($id, $retryIds, true)) {
        $retryIds[] = $id;
    }
}
$retryIds = array_values(array_unique(array_filter($retryIds)));

$retryReport = [];
$tempsToClean = [];

foreach ($retryIds as $attachmentId) {
    //skip if already have usable alt
    if (!empty($cache[(string) $attachmentId]['alt'])) {
        $retryReport[] = ['attachmentId' => $attachmentId, 'status' => 'already_ok'];
        continue;
    }

    $deriv = retryMakeTempDerivative($attachmentId, 1600, 3500000);
    if (!$deriv['ok']) {
        //try smaller edge
        $deriv = retryMakeTempDerivative($attachmentId, 1280, 3500000);
    }
    if (!$deriv['ok']) {
        $retryReport[] = ['attachmentId' => $attachmentId, 'status' => 'deriv_failed', 'error' => $deriv['error']];
        continue;
    }
    if (!empty($deriv['cleanup']) && !empty($deriv['path'])) {
        $tempsToClean[] = $deriv['path'];
    }

    $title = get_the_title($attachmentId) ?: basename((string) (wp_get_attachment_url($attachmentId) ?: ''));
    $result = retryGemini($apiKey, $model, $deriv['path'], (string) $deriv['mime'], $locale, $title);
    if (!$result['ok']) {
        $retryReport[] = [
            'attachmentId' => $attachmentId,
            'status' => 'gemini_failed',
            'error' => $result['error'],
            'method' => $deriv['method'],
            'bytes' => $deriv['bytes'],
        ];
        continue;
    }

    $cache[(string) $attachmentId] = [
        'alt' => $result['alt'],
        'error' => null,
        'source' => 'resize-retry',
        'resizeMethod' => $deriv['method'],
        'resizeBytes' => $deriv['bytes'],
    ];
    $retryReport[] = [
        'attachmentId' => $attachmentId,
        'status' => 'ok',
        'alt' => $result['alt'],
        'method' => $deriv['method'],
        'bytes' => $deriv['bytes'],
        'file' => basename((string) (wp_get_attachment_url($attachmentId) ?: (string) $attachmentId)),
    ];
}

file_put_contents($cachePath, wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

foreach ($tempsToClean as $tmp) {
    if (is_string($tmp) && str_starts_with($tmp, trailingslashit(sys_get_temp_dir())) && file_exists($tmp)) {
        @unlink($tmp);
    }
}

$altByAttachment = [];
foreach ($cache as $id => $row) {
    if (!empty($row['alt']) && is_string($row['alt'])) {
        $altByAttachment[(int) $id] = trim($row['alt']);
    }
}

$stats = [
    'blockAltsFilled' => 0,
    'postsUpdated' => 0,
    'metaUpdated' => 0,
    'byBlock' => [],
    'failures' => [],
];

if ($apply) {
    foreach ($altByAttachment as $attachmentId => $alt) {
        if (retryEmpty(get_post_meta($attachmentId, '_wp_attachment_image_alt', true))) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
            $stats['metaUpdated']++;
        }
    }

    $q = new WP_Query([
        'post_type' => ['page', 'post', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'],
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
    ]);

    foreach ($q->posts as $post) {
        $blocks = parse_blocks($post->post_content);
        $changed = false;

        $walker = function (&$blocks) use (&$walker, &$changed, $altByAttachment, &$stats) {
            foreach ($blocks as &$block) {
                if (!is_array($block)) {
                    continue;
                }
                $blockName = (string) ($block['blockName'] ?? '');
                $attrs = $block['attrs'] ?? null;
                if (is_array($attrs) && $blockName !== '') {
                    $n = retryFillImageNodes($attrs, $altByAttachment);
                    if ($n > 0) {
                        $block['attrs'] = $attrs;
                        $changed = true;
                        $stats['blockAltsFilled'] += $n;
                        $stats['byBlock'][$blockName] = ($stats['byBlock'][$blockName] ?? 0) + $n;

                        if ($blockName === 'nectar-blocks/image') {
                            $id = (int) ($block['attrs']['image']['id'] ?? 0);
                            $alt = (string) ($block['attrs']['image']['alt'] ?? '');
                            if ($id > 0 && $alt !== '') {
                                if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                                    $block['innerHTML'] = retrySyncImgHtml($block['innerHTML'], $alt);
                                }
                                if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                                    foreach ($block['innerContent'] as $i => $piece) {
                                        if (is_string($piece)) {
                                            $block['innerContent'][$i] = retrySyncImgHtml($piece, $alt);
                                        }
                                    }
                                }
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

$out = [
    'retryIds' => $retryIds,
    'retryReport' => $retryReport,
    'applyStats' => $stats,
    'usableAlts' => count($altByAttachment),
    'tempsCleaned' => count($tempsToClean),
];
file_put_contents($inventoryDir . '/retry-large-alts-result.json', wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
