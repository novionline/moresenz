<?php
/**
 * Read-only: scan do_blocks() rendered HTML for img alt/title gaps.
 * Bypasses SeedProd Coming Soon (no HTTP front-end).
 */
if (!defined('ABSPATH')) {
    exit;
}

function nbRenEmpty($v)
{
    return $v === null || trim((string) $v) === '';
}

$postTypes = ['page', 'novi-project'];
$q = new WP_Query([
    'post_type' => $postTypes,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'lang' => 'nl',
    'orderby' => 'title',
    'order' => 'ASC',
]);

$summary = [
    'pages' => 0,
    'imgTags' => 0,
    'missingAltAttr' => 0,
    'emptyAlt' => 0,
    'missingOrEmptyAlt' => 0,
    'hasTitleAttr' => 0,
    'emptyOrMissingTitle' => 0,
    'byPost' => [],
];
$examples = [];

foreach ($q->posts as $post) {
    $summary['pages']++;
    $html = do_blocks($post->post_content);
    if (!preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
        $summary['byPost'][] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'type' => $post->post_type,
            'url' => get_permalink($post->ID),
            'imgs' => 0,
            'missingOrEmptyAlt' => 0,
        ];
        continue;
    }
    $pageMissing = 0;
    foreach ($m[0] as $tag) {
        $summary['imgTags']++;
        preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $sm);
        $hasAlt = (bool) preg_match('/\balt=/i', $tag);
        preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $am);
        $hasTitle = (bool) preg_match('/\btitle=/i', $tag);
        preg_match('/\btitle=["\']([^"\']*)["\']/i', $tag, $tm);
        $altVal = $hasAlt ? ($am[1] ?? '') : null;
        $titleVal = $hasTitle ? ($tm[1] ?? '') : null;
        if ($hasTitle) {
            $summary['hasTitleAttr']++;
        }
        if (!$hasTitle || nbRenEmpty($titleVal)) {
            $summary['emptyOrMissingTitle']++;
        }
        $bad = false;
        if (!$hasAlt) {
            $summary['missingAltAttr']++;
            $bad = true;
        } elseif (nbRenEmpty($altVal)) {
            $summary['emptyAlt']++;
            $bad = true;
        }
        if ($bad) {
            $summary['missingOrEmptyAlt']++;
            $pageMissing++;
            if (count($examples) < 80) {
                $examples[] = [
                    'postId' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'url' => get_permalink($post->ID),
                    'src' => $sm[1] ?? '',
                    'alt' => $altVal,
                    'titleAttr' => $titleVal,
                    'hasAltAttr' => $hasAlt,
                    'hasTitleAttr' => $hasTitle,
                ];
            }
        }
    }
    $summary['byPost'][] = [
        'id' => $post->ID,
        'title' => $post->post_title,
        'type' => $post->post_type,
        'url' => get_permalink($post->ID),
        'imgs' => count($m[0]),
        'missingOrEmptyAlt' => $pageMissing,
    ];
}

usort($summary['byPost'], function ($a, $b) {
    return $b['missingOrEmptyAlt'] <=> $a['missingOrEmptyAlt'];
});

echo wp_json_encode([
    'method' => 'do_blocks rendered HTML (SeedProd bypass; content only, not full theme chrome)',
    'summary' => $summary,
    'examples' => $examples,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
