<?php
/**
 * Audit NB content after programmatic alt/title fills.
 *
 * Checks:
 * - nectar-blocks/image attrs.image.alt|title vs first <img> markup
 * - serialize roundtrip stability
 * - corruption markers (literal u003c, lost unicode escapes)
 * - thin / missing _nectar_blocks_css vs content blockIds
 *
 * Usage:
 *   wp eval-file tmp/alt-inventory/audit-nb-after-alt.php
 */

if (!defined('ABSPATH')) {
    exit("Run via wp eval-file\n");
}

require_once ABSPATH . 'wp-includes/blocks.php';

function auditDecodeAttr(string $value): string
{
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function auditExtractImgAttr(string $html, string $attr): ?string
{
    if (!preg_match('/<img\b[^>]*>/i', $html, $m)) {
        return null;
    }
    $tag = $m[0];
    if (preg_match('/\b' . preg_quote($attr, '/') . '=("|\')(.*?)\1/is', $tag, $am)) {
        return auditDecodeAttr($am[2]);
    }
    return null;
}

/**
 * @return array<int,array<string,mixed>>
 */
function auditWalk(array $blocks, int $depth = 0): array
{
    $issues = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $name = $block['blockName'] ?? null;
        $innerHtml = (string) ($block['innerHTML'] ?? '');
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        if ($name === null && trim(strip_tags($innerHtml)) !== '') {
            $issues[] = [
                'type' => 'freeform_html',
                'depth' => $depth,
                'snippet' => mb_substr(trim(strip_tags($innerHtml)), 0, 80),
            ];
        }

        if ($name === 'nectar-blocks/image') {
            $blockId = (string) ($attrs['blockId'] ?? '');
            $img = is_array($attrs['image'] ?? null) ? $attrs['image'] : [];
            $attrAlt = (string) ($img['alt'] ?? '');
            $attrTitle = (string) ($img['title'] ?? '');
            $htmlAlt = auditExtractImgAttr($innerHtml, 'alt');
            $htmlTitle = auditExtractImgAttr($innerHtml, 'title');

            if ($innerHtml !== '' && str_contains($innerHtml, '<img') === false && !empty($img['url'])) {
                $issues[] = [
                    'type' => 'image_missing_img_tag',
                    'blockId' => $blockId,
                    'depth' => $depth,
                ];
            }

            if ($htmlAlt !== null && $htmlAlt !== $attrAlt) {
                $issues[] = [
                    'type' => 'alt_mismatch',
                    'blockId' => $blockId,
                    'depth' => $depth,
                    'attr' => mb_substr($attrAlt, 0, 80),
                    'html' => mb_substr($htmlAlt, 0, 80),
                ];
            }
            if ($htmlTitle !== null && $htmlTitle !== $attrTitle) {
                $issues[] = [
                    'type' => 'title_mismatch',
                    'blockId' => $blockId,
                    'depth' => $depth,
                    'attr' => mb_substr($attrTitle, 0, 80),
                    'html' => mb_substr($htmlTitle, 0, 80),
                ];
            }
            //attrs filled but markup still empty (or missing attr)
            if ($attrAlt !== '' && ($htmlAlt === null || $htmlAlt === '')) {
                $issues[] = [
                    'type' => 'alt_attr_not_in_html',
                    'blockId' => $blockId,
                    'depth' => $depth,
                    'attr' => mb_substr($attrAlt, 0, 80),
                ];
            }
            if ($attrTitle !== '' && ($htmlTitle === null || $htmlTitle === '')) {
                $issues[] = [
                    'type' => 'title_attr_not_in_html',
                    'blockId' => $blockId,
                    'depth' => $depth,
                    'attr' => mb_substr($attrTitle, 0, 80),
                ];
            }
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $issues = array_merge($issues, auditWalk($block['innerBlocks'], $depth + 1));
        }
    }
    return $issues;
}

function auditExtractBlockIds(string $content): array
{
    preg_match_all('/"blockId"\s*:\s*"([^"]+)"/', $content, $m);
    return array_values(array_unique($m[1] ?? []));
}

function auditHasCorruption(string $content): array
{
    $flags = [];
    if (str_contains($content, 'u003c') && !str_contains($content, '\\u003c')) {
        $flags[] = 'literal_u003c';
    }
    if (preg_match('/[^\\\\]u00[0-9a-fA-F]{2}/', $content)) {
        //heuristic only; may false-positive on dutch words — keep as soft
        if (str_contains($content, 'u0022') || str_contains($content, 'u003c') || str_contains($content, 'u0026')) {
            $flags[] = 'possible_lost_unicode_escape';
        }
    }
    if (str_contains($content, '\\\\\\"') && substr_count($content, '\\\\\\"') > 20) {
        $flags[] = 'heavy_backslash_noise';
    }
    return $flags;
}

$postTypes = ['page', 'post', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'];
$postIds = get_posts([
    'post_type' => $postTypes,
    'post_status' => ['publish', 'private', 'draft'],
    'posts_per_page' => -1,
    'fields' => 'ids',
    'orderby' => 'ID',
    'order' => 'ASC',
]);

$rows = [];
$summary = [
    'checked' => 0,
    'with_nb_image' => 0,
    'issue_posts' => 0,
    'thin_css' => 0,
    'missing_css_ids' => 0,
    'roundtrip_fail' => 0,
    'corruption' => 0,
    'issue_type_counts' => [],
];

foreach ($postIds as $postId) {
    $post = get_post((int) $postId);
    if (!$post) {
        continue;
    }
    $content = (string) $post->post_content;
    if ($content === '' || !has_blocks($content)) {
        continue;
    }
    $summary['checked']++;

    $blocks = parse_blocks($content);
    $issues = auditWalk($blocks);
    $serialized = serialize_blocks($blocks);
    $roundtripOk = $serialized === serialize_blocks(parse_blocks($serialized));
    $corruption = auditHasCorruption($content);

    $hasNbImage = str_contains($content, 'nectar-blocks/image');
    if ($hasNbImage) {
        $summary['with_nb_image']++;
    }

    $css = get_post_meta($postId, '_nectar_blocks_css', true);
    if (!is_string($css)) {
        $css = '';
    }
    $cssBytes = strlen($css);
    $contentIds = auditExtractBlockIds($content);
    $missingCssIds = [];
    if ($contentIds !== [] && $cssBytes > 0) {
        foreach ($contentIds as $id) {
            if (!str_contains($css, $id)) {
                $missingCssIds[] = $id;
            }
        }
    }
    $thinCss = ($contentIds !== [] && $cssBytes < 200);

    $rowIssues = $issues;
    if (!$roundtripOk) {
        $rowIssues[] = ['type' => 'roundtrip_fail'];
        $summary['roundtrip_fail']++;
    }
    foreach ($corruption as $flag) {
        $rowIssues[] = ['type' => $flag];
        $summary['corruption']++;
    }
    if ($thinCss) {
        $rowIssues[] = ['type' => 'thin_css', 'css_bytes' => $cssBytes, 'block_ids' => count($contentIds)];
        $summary['thin_css']++;
    }
    if ($missingCssIds !== [] && count($missingCssIds) > 3) {
        //many synced patterns intentionally omit some ids in consumer CSS; flag heavy misses
        $rowIssues[] = [
            'type' => 'many_missing_css_ids',
            'missing_count' => count($missingCssIds),
            'sample' => array_slice($missingCssIds, 0, 5),
            'css_bytes' => $cssBytes,
        ];
        $summary['missing_css_ids']++;
    }

    if ($rowIssues === []) {
        continue;
    }

    $summary['issue_posts']++;
    foreach ($rowIssues as $issue) {
        $t = (string) ($issue['type'] ?? 'unknown');
        $summary['issue_type_counts'][$t] = ($summary['issue_type_counts'][$t] ?? 0) + 1;
    }

    $rows[] = [
        'post_id' => (int) $postId,
        'type' => $post->post_type,
        'slug' => $post->post_name,
        'title' => get_the_title($post),
        'css_bytes' => $cssBytes,
        'content_block_ids' => count($contentIds),
        'issue_count' => count($rowIssues),
        'issues' => array_slice($rowIssues, 0, 25),
    ];
}

$out = [
    'ok' => $summary['issue_posts'] === 0,
    'summary' => $summary,
    'failures' => $rows,
];

$outPath = dirname(__FILE__) . '/audit-nb-after-alt.json';
file_put_contents($outPath, wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode([
    'ok' => $out['ok'],
    'summary' => $summary,
    'failure_count' => count($rows),
    'out' => $outPath,
    'top_failures' => array_slice($rows, 0, 15),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

exit($out['ok'] ? 0 : 1);
