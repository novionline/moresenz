<?php
/**
 * Rule-based brand-name alts for Client logo grid scrolling-marquee.
 * No Gemini. Derive from filename/slug → proper brand name.
 *
 * Usage: wp eval-file fix-logo-marquee-alts.php
 * Env: LOGO_ALT_DRY_RUN=1 to preview only
 */

if (!defined('ABSPATH')) {
    exit;
}

$dryRun = (getenv('LOGO_ALT_DRY_RUN') ?: '') === '1';
$postId = (int) (getenv('LOGO_ALT_POST_ID') ?: 1215);

//known brand spellings (slug without leading logo-)
$brandMap = [
    'bowers-and-wilkins' => 'Bowers & Wilkins',
    'sonance' => 'Sonance',
    'kef' => 'KEF',
    'james' => 'James',
    'krix' => 'Krix',
    'trinnov-audio' => 'Trinnov Audio',
    'pmc' => 'PMC',
    'marantz' => 'Marantz',
    'audiocontrol' => 'AudioControl',
    'jbl' => 'JBL',
    'jvc' => 'JVC',
    'sony' => 'Sony',
    'moovia' => 'Moovia',
    'gfc' => 'GFC',
    'wisdom' => 'Wisdom',
    'bluesound' => 'Bluesound',
    'denon' => 'Denon',
    'stewart' => 'Stewart',
    'jl-audio' => 'JL Audio',
    'screen-research' => 'Screen Research',
];

/**
 * @param string $slugOrFilename
 */
$slugToBrand = static function (string $slugOrFilename) use ($brandMap): string {
    $base = strtolower(basename($slugOrFilename));
    $base = preg_replace('/\.(svg|png|jpe?g|webp|gif)$/i', '', $base) ?: $base;
    $base = preg_replace('/^logo-/', '', $base) ?: $base;
    $base = trim($base, "-_ \t\n\r");

    if (isset($brandMap[$base])) {
        return $brandMap[$base];
    }

    //fallback: title-case hyphen/underscore segments
    $parts = preg_split('/[-_]+/', $base) ?: [];
    $out = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if ($part === 'and') {
            $out[] = '&';
            continue;
        }
        if (strlen($part) <= 3 && preg_match('/^[a-z]+$/', $part)) {
            $out[] = strtoupper($part);
            continue;
        }
        $out[] = ucfirst($part);
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $out)) ?: $base);
};

$isSlugStyle = static function (string $value): bool {
    $v = trim($value);
    if ($v === '') {
        return true;
    }
    if (preg_match('/^logo[-_]/i', $v)) {
        return true;
    }
    //all-lowercase hyphenated slug (no spaces, no &)
    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', $v)) {
        return true;
    }
    if (preg_match('/^[a-z0-9]+$/', $v) && $v === strtolower($v)) {
        return true;
    }
    return false;
};

$syncImgAttrs = static function (string $html, string $brand, int $attachmentId): string {
    if ($html === '') {
        return $html;
    }

    return (string) preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $matches) use ($brand, $attachmentId): string {
            $tag = $matches[0];
            //only touch matching attachment when class/wp-image present
            if ($attachmentId > 0) {
                $hasId = (bool) preg_match('/\bwp-image-' . preg_quote((string) $attachmentId, '/') . '\b/', $tag);
                if (!$hasId) {
                    return $tag;
                }
            }
            $escaped = esc_attr($brand);
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
        $html
    );
};

$post = get_post($postId);
if (!$post) {
    fwrite(STDERR, "post {$postId} not found\n");
    exit(1);
}

$blocks = parse_blocks($post->post_content);
$changed = false;
$updates = [];
$metaUpdates = [];

$walker = function (&$blocks) use (
    &$walker,
    &$changed,
    &$updates,
    &$metaUpdates,
    $slugToBrand,
    $isSlugStyle,
    $syncImgAttrs,
    $dryRun
) {
    foreach ($blocks as &$block) {
        if (!is_array($block)) {
            continue;
        }

        if (($block['blockName'] ?? '') === 'nectar-blocks/scrolling-marquee') {
            $blockId = (string) ($block['attrs']['blockId'] ?? '');
            $items = $block['attrs']['repeaterContent'] ?? null;
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => &$item) {
                if (!is_array($item)) {
                    continue;
                }
                $img = $item['image']['image'] ?? null;
                if (!is_array($img)) {
                    continue;
                }

                $attachmentId = (int) ($img['id'] ?? 0);
                $url = (string) ($img['url'] ?? '');
                $beforeAlt = (string) ($img['alt'] ?? '');
                $beforeTitle = (string) ($img['title'] ?? '');

                $slugSource = $beforeAlt !== '' ? $beforeAlt : ($beforeTitle !== '' ? $beforeTitle : basename(parse_url($url, PHP_URL_PATH) ?: ''));
                $brand = $slugToBrand($slugSource);

                $needsAlt = $isSlugStyle($beforeAlt) || $beforeAlt !== $brand;
                $needsTitle = $isSlugStyle($beforeTitle) || $beforeTitle !== $brand;

                if (!$needsAlt && !$needsTitle) {
                    continue;
                }

                $item['image']['image']['alt'] = $brand;
                $item['image']['image']['title'] = $brand;
                $changed = true;

                $updates[] = [
                    'blockId' => $blockId,
                    'index' => $index,
                    'attachmentId' => $attachmentId,
                    'file' => basename(parse_url($url, PHP_URL_PATH) ?: ''),
                    'beforeAlt' => $beforeAlt,
                    'beforeTitle' => $beforeTitle,
                    'after' => $brand,
                ];

                //sync saved markup for this attachment id
                if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                    $block['innerHTML'] = $syncImgAttrs($block['innerHTML'], $brand, $attachmentId);
                }
                if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                    foreach ($block['innerContent'] as $ci => $piece) {
                        if (is_string($piece)) {
                            $block['innerContent'][$ci] = $syncImgAttrs($piece, $brand, $attachmentId);
                        }
                    }
                }

                //optional attachment meta when empty or slug-style
                if ($attachmentId > 0) {
                    $existingMeta = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
                    if ($isSlugStyle($existingMeta) || $existingMeta !== $brand) {
                        if (!$dryRun) {
                            update_post_meta($attachmentId, '_wp_attachment_image_alt', $brand);
                        }
                        $metaUpdates[] = [
                            'attachmentId' => $attachmentId,
                            'before' => $existingMeta,
                            'after' => $brand,
                        ];
                    }
                }
            }
            unset($item);

            $block['attrs']['repeaterContent'] = $items;
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $walker($block['innerBlocks']);
        }
    }
    unset($block);
};

$walker($blocks);

$out = [
    'ok' => true,
    'dryRun' => $dryRun,
    'postId' => $postId,
    'postTitle' => $post->post_title,
    'home' => home_url('/'),
    'logosChanged' => count($updates),
    'metaChanged' => count($metaUpdates),
    'updates' => $updates,
    'metaUpdates' => $metaUpdates,
];

if (!$changed) {
    $out['message'] = 'nothing to update';
    echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($dryRun) {
    $out['message'] = 'dry-run only — no write';
    echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

$result = wp_update_post([
    'ID' => $postId,
    'post_content' => wp_slash(serialize_blocks($blocks)),
], true);

if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}

//verify roundtrip for target block + sample brands
$verify = [];
$verifyWalk = function ($blocks) use (&$verifyWalk, &$verify) {
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['blockName'] ?? '') === 'nectar-blocks/scrolling-marquee') {
            $blockId = (string) ($block['attrs']['blockId'] ?? '');
            $items = $block['attrs']['repeaterContent'] ?? [];
            $row = [
                'blockId' => $blockId,
                'count' => is_array($items) ? count($items) : 0,
                'logos' => [],
            ];
            $sampleImg = null;
            if (isset($block['innerHTML']) && is_string($block['innerHTML']) && preg_match('/<img\b[^>]*>/i', $block['innerHTML'], $m)) {
                $sampleImg = $m[0];
            }
            if (is_array($items)) {
                foreach ($items as $item) {
                    $img = $item['image']['image'] ?? [];
                    $row['logos'][] = [
                        'id' => (int) ($img['id'] ?? 0),
                        'alt' => (string) ($img['alt'] ?? ''),
                        'title' => (string) ($img['title'] ?? ''),
                        'file' => basename(parse_url((string) ($img['url'] ?? ''), PHP_URL_PATH) ?: ''),
                    ];
                }
            }
            $row['sampleInnerImg'] = $sampleImg;
            $verify[] = $row;
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $verifyWalk($block['innerBlocks']);
        }
    }
};
$saved = get_post($postId);
$verifyWalk(parse_blocks($saved->post_content));

$lostUnicode = (bool) preg_match('/(?<!\\\\)u003c|(?<!\\\\)u0022/', $saved->post_content);

$out['verify'] = $verify;
$out['lostUnicodeEscapes'] = $lostUnicode;
$out['nbSafeWrite'] = 'parse_blocks → mutate repeaterContent[].image.image.alt/title → sync innerHTML → serialize_blocks → wp_slash → wp_update_post';

$logPath = dirname(__FILE__) . '/fix-logo-marquee-alts-result.json';
file_put_contents($logPath, wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
