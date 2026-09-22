<?php
/**
 * Sync one nectar-blocks/image sample: attrs + innerHTML/innerContent img alt/title.
 * NB-safe: parse_blocks → mutate → sync first <img> attrs → serialize_blocks → wp_slash → wp_update_post.
 */

if (!defined('ABSPATH')) {
    exit;
}

$postId = 27;
$targetBlockId = 'block-jngokjt8be9q';
$attachmentId = 2003;

/**
 * @param string $html
 */
function nbSampleSyncImgTag(string $html, string $alt, string $title): string
{
    if ($html === '') {
        return $html;
    }

    return (string) preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $matches) use ($alt, $title): string {
            $tag = $matches[0];
            $escapedAlt = esc_attr($alt);
            $escapedTitle = esc_attr($title);

            if (preg_match('/\balt=("|\')/i', $tag)) {
                $tag = (string) preg_replace('/\balt=("|\').*?\1/i', 'alt="' . $escapedAlt . '"', $tag, 1);
            } else {
                $tag = (string) preg_replace('/<img\b/i', '<img alt="' . $escapedAlt . '"', $tag, 1);
            }

            if (preg_match('/\btitle=("|\')/i', $tag)) {
                $tag = (string) preg_replace('/\btitle=("|\').*?\1/i', 'title="' . $escapedTitle . '"', $tag, 1);
            } else {
                $tag = (string) preg_replace('/<img\b/i', '<img title="' . $escapedTitle . '"', $tag, 1);
            }

            return $tag;
        },
        $html,
        1
    );
}

$post = get_post($postId);
$blocks = parse_blocks($post->post_content);
$changed = false;
$altText = '';
$titleText = '';

$walker = function (&$blocks) use (
    &$walker,
    &$changed,
    &$altText,
    &$titleText,
    $targetBlockId,
    $attachmentId
): void {
    foreach ($blocks as &$block) {
        if (!is_array($block)) {
            continue;
        }

        if (
            ($block['blockName'] ?? '') === 'nectar-blocks/image'
            && ($block['attrs']['blockId'] ?? '') === $targetBlockId
            && (int) ($block['attrs']['image']['id'] ?? 0) === $attachmentId
        ) {
            $altText = trim((string) ($block['attrs']['image']['alt'] ?? ''));
            if ($altText === '') {
                return;
            }

            //title matches descriptive alt
            $block['attrs']['image']['title'] = $altText;
            $titleText = $altText;

            if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                $block['innerHTML'] = nbSampleSyncImgTag($block['innerHTML'], $altText, $titleText);
            }

            if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                foreach ($block['innerContent'] as $index => $piece) {
                    if (is_string($piece)) {
                        $block['innerContent'][$index] = nbSampleSyncImgTag($piece, $altText, $titleText);
                    }
                }
            }

            $changed = true;
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $walker($block['innerBlocks']);
        }
    }
    unset($block);
};

$walker($blocks);

if (!$changed || $altText === '') {
    echo wp_json_encode(['ok' => false, 'reason' => 'not_changed_or_empty_alt'], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$result = wp_update_post([
    'ID' => $postId,
    'post_content' => wp_slash(serialize_blocks($blocks)),
], true);

if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}

$saved = get_post($postId);
$innerImg = '';
$verifyAlt = '';
$verifyTitle = '';
$verifyWalk = function ($blocks) use (&$verifyWalk, &$innerImg, &$verifyAlt, &$verifyTitle, $targetBlockId, $attachmentId): void {
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (
            ($block['blockName'] ?? '') === 'nectar-blocks/image'
            && ($block['attrs']['blockId'] ?? '') === $targetBlockId
            && (int) ($block['attrs']['image']['id'] ?? 0) === $attachmentId
        ) {
            $verifyAlt = (string) ($block['attrs']['image']['alt'] ?? '');
            $verifyTitle = (string) ($block['attrs']['image']['title'] ?? '');
            if (preg_match('/<img\b[^>]*>/i', (string) ($block['innerHTML'] ?? ''), $matches)) {
                $innerImg = $matches[0];
            }
            return;
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $verifyWalk($block['innerBlocks']);
        }
    }
};
$verifyWalk(parse_blocks($saved->post_content));

$out = [
    'ok' => true,
    'nbSafeWrite' => 'parse_blocks → attrs.image.alt/title + sync first <img> in innerHTML/innerContent → serialize_blocks → wp_slash → wp_update_post',
    'postId' => $postId,
    'postTitle' => $saved->post_title,
    'permalink' => get_permalink($postId),
    'edit' => admin_url("post.php?post={$postId}&action=edit"),
    'blockType' => 'nectar-blocks/image',
    'blockId' => $targetBlockId,
    'attachmentId' => $attachmentId,
    'file' => 'about-this-project.png',
    'alt' => $verifyAlt,
    'title' => $verifyTitle,
    'altEqualsTitle' => $verifyAlt === $verifyTitle,
    'innerImg' => $innerImg,
    'lostUnicodeEscapes' => (bool) preg_match('/(?<!\\\\)u003c|(?<!\\\\)u0022/', $saved->post_content),
];

file_put_contents(dirname(__FILE__) . '/fill-one-sample-result.json', wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
