<?php
/**
 * Fill exactly one nectar-blocks/image alt+title sample for user review.
 */

if (!defined('ABSPATH')) {
    exit;
}

$apiKey = getenv('GEMINI_API_KEY') ?: '';
$model = getenv('GEMINI_MODEL') ?: 'gemini-3.1-flash-lite';
$locale = getenv('ALT_FILL_LOCALE') ?: 'nl_NL';
$postId = 27;
$attachmentId = 2003;
$targetBlockId = 'block-jngokjt8be9q';

if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY missing\n");
    exit(1);
}

$mid = image_get_intermediate_size($attachmentId, 'medium_large');
$base = wp_get_upload_dir()['basedir'];
$path = ($mid && !empty($mid['path'])) ? ($base . '/' . $mid['path']) : get_attached_file($attachmentId);
if (!$path || !is_readable($path)) {
    fwrite(STDERR, "file missing\n");
    exit(1);
}

$mime = mime_content_type($path) ?: 'image/png';
$imageTitle = get_the_title($attachmentId) ?: 'about-this-project';

//toolbelt prompt: partials/prompts/generate-alt-tag-by-image.php
$lines = ['Generate an alt text for the given image (<125 chars).'];
$lines[] = sprintf('Use locale: "%s".', $locale);
$lines[] = sprintf('The file name of the image is "%s".', $imageTitle);
if ($mime === 'image/png') {
    $lines[] = 'The given image is a PNG. Do not describe the background color.';
}
$lines[] = 'Describe visible subject(s) first, then any text/logo.';
$lines[] = 'Return one concise sentence, no "Image of".';
$prompt = implode("\n", $lines);

$payload = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            [
                'inline_data' => [
                    'mime_type' => $mime,
                    'data' => base64_encode(file_get_contents($path)),
                ],
            ],
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
    fwrite(STDERR, $response->get_error_message() . "\n");
    exit(1);
}

$code = (int) wp_remote_retrieve_response_code($response);
$body = json_decode((string) wp_remote_retrieve_body($response), true);
if ($code < 200 || $code >= 300) {
    $msg = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';
    fwrite(STDERR, "http_{$code}: {$msg}\n");
    exit(1);
}

$alt = '';
foreach (($body['candidates'][0]['content']['parts'] ?? []) as $part) {
    if (!empty($part['text']) && is_string($part['text'])) {
        $alt .= $part['text'];
    }
}
$alt = trim(preg_replace('/\s+/', ' ', trim($alt, " \t\n\r\0\x0B\"'")) ?: '');
if (function_exists('mb_substr')) {
    $alt = mb_substr($alt, 0, 125);
}
$alt = trim($alt);
if ($alt === '') {
    fwrite(STDERR, "empty alt response\n");
    exit(1);
}

$post = get_post($postId);
$blocks = parse_blocks($post->post_content);
$changed = false;
$updated = null;

$walker = function (&$blocks) use (&$walker, &$changed, &$updated, $targetBlockId, $attachmentId, $alt) {
    foreach ($blocks as &$block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['blockName'] ?? '') === 'nectar-blocks/image') {
            $blockId = (string) ($block['attrs']['blockId'] ?? '');
            $image = $block['attrs']['image'] ?? [];
            $id = (int) ($image['id'] ?? 0);
            if ($blockId === $targetBlockId && $id === $attachmentId) {
                $beforeAlt = (string) ($image['alt'] ?? '');
                $beforeTitle = (string) ($image['title'] ?? '');
                if (trim($beforeAlt) !== '') {
                    $updated = [
                        'skipped' => true,
                        'reason' => 'alt_already_set',
                        'beforeAlt' => $beforeAlt,
                    ];
                    return;
                }
                $block['attrs']['image']['alt'] = $alt;
                $block['attrs']['image']['title'] = $alt;

                //sync saved markup (NB serialize uses innerContent) — same lesson as novi-nb-content-agent / 2bhonest
                $syncImg = static function (string $html) use ($alt): string {
                    if ($html === '') {
                        return $html;
                    }
                    return (string) preg_replace_callback(
                        '/<img\b[^>]*>/i',
                        static function (array $matches) use ($alt): string {
                            $tag = $matches[0];
                            $escapedAlt = esc_attr($alt);
                            if (preg_match('/\balt=("|\')/i', $tag)) {
                                $tag = (string) preg_replace('/\balt=("|\').*?\1/i', 'alt="' . $escapedAlt . '"', $tag, 1);
                            } else {
                                $tag = (string) preg_replace('/<img\b/i', '<img alt="' . $escapedAlt . '"', $tag, 1);
                            }
                            if (preg_match('/\btitle=("|\')/i', $tag)) {
                                $tag = (string) preg_replace('/\btitle=("|\').*?\1/i', 'title="' . $escapedAlt . '"', $tag, 1);
                            } else {
                                $tag = (string) preg_replace('/<img\b/i', '<img title="' . $escapedAlt . '"', $tag, 1);
                            }
                            return $tag;
                        },
                        $html,
                        1
                    );
                };
                if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                    $block['innerHTML'] = $syncImg($block['innerHTML']);
                }
                if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                    foreach ($block['innerContent'] as $index => $piece) {
                        if (is_string($piece)) {
                            $block['innerContent'][$index] = $syncImg($piece);
                        }
                    }
                }

                $changed = true;
                $updated = [
                    'beforeAlt' => $beforeAlt,
                    'beforeTitle' => $beforeTitle,
                    'afterAlt' => $alt,
                    'afterTitle' => $alt,
                    'url' => (string) ($image['url'] ?? ''),
                    'file' => basename((string) ($image['url'] ?? '')),
                    'attachmentId' => $id,
                    'blockId' => $blockId,
                ];
            }
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $walker($block['innerBlocks']);
        }
    }
    unset($block);
};
$walker($blocks);

if (!$changed) {
    echo wp_json_encode(['ok' => false, 'updated' => $updated], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}

$result = wp_update_post([
    'ID' => $postId,
    //critical: wp_update_post unslashes — without wp_slash, serialize_blocks \u003c becomes bare u003c and breaks NB validation
    'post_content' => wp_slash(serialize_blocks($blocks)),
], true);

if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}

//roundtrip check: re-parse saved content and confirm alt/title on target block
$saved = get_post($postId);
$verifyAlt = null;
$verifyTitle = null;
$verifyWalk = function ($blocks) use (&$verifyWalk, &$verifyAlt, &$verifyTitle, $targetBlockId, $attachmentId) {
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['blockName'] ?? '') === 'nectar-blocks/image') {
            $blockId = (string) ($block['attrs']['blockId'] ?? '');
            $image = $block['attrs']['image'] ?? [];
            if ($blockId === $targetBlockId && (int) ($image['id'] ?? 0) === $attachmentId) {
                $verifyAlt = (string) ($image['alt'] ?? '');
                $verifyTitle = (string) ($image['title'] ?? '');
            }
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $verifyWalk($block['innerBlocks']);
        }
    }
};
$verifyWalk(parse_blocks($saved->post_content));

//flag lost-backslash corruption in whole post content
$lostUnicode = (bool) preg_match('/(?<!\\\\)u003c|(?<!\\\\)u0022/', $saved->post_content);

$metaUpdated = false;
$existingMeta = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
if ($existingMeta === null || trim((string) $existingMeta) === '') {
    update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
    $metaUpdated = true;
}

$out = [
    'ok' => true,
    'model' => $model,
    'locale' => $locale,
    'promptSource' => 'wordpress-ai-toolbelt-plugin/partials/prompts/generate-alt-tag-by-image.php',
    'nbSafeWrite' => 'parse_blocks → mutate attrs.image.alt/title → serialize_blocks → wp_slash → wp_update_post',
    'postId' => $postId,
    'postTitle' => $post->post_title,
    'permalink' => get_permalink($postId),
    'edit' => admin_url("post.php?post={$postId}&action=edit"),
    'blockType' => 'nectar-blocks/image',
    'metaUpdated' => $metaUpdated,
    'updated' => $updated,
    'verify' => [
        'alt' => $verifyAlt,
        'title' => $verifyTitle,
        'altEqualsTitle' => $verifyAlt !== null && $verifyAlt === $verifyTitle,
        'lostUnicodeEscapes' => $lostUnicode,
    ],
];

file_put_contents(dirname(__FILE__) . '/fill-one-sample-result.json', wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
