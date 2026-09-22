<?php
if (!defined('ABSPATH')) {
    exit;
}

$q = new WP_Query([
    'post_type' => ['page', 'novi-project'],
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'lang' => 'nl',
]);

$total = 0;
$empty = 0;
$emptySvg = 0;
$emptyPhoto = 0;
$emptyByExt = [];
$photoExamples = [];

foreach ($q->posts as $p) {
    $html = do_blocks($p->post_content);
    if (!preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
        continue;
    }
    foreach ($m[0] as $tag) {
        $total++;
        preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $sm);
        preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $am);
        $hasAlt = (bool) preg_match('/\balt=/i', $tag);
        $alt = $hasAlt ? ($am[1] ?? '') : null;
        if ($hasAlt && trim((string) $alt) !== '') {
            continue;
        }
        $empty++;
        $src = $sm[1] ?? '';
        $ext = 'other';
        if (preg_match('/\.(svg)($|\?)/i', $src)) {
            $ext = 'svg';
        } elseif (preg_match('/\.(jpe?g|png|webp|gif)($|\?)/i', $src)) {
            $ext = 'photo';
        }
        $emptyByExt[$ext] = ($emptyByExt[$ext] ?? 0) + 1;
        if ($ext === 'svg') {
            $emptySvg++;
        } else {
            $emptyPhoto++;
            if (count($photoExamples) < 12) {
                $photoExamples[] = [
                    'title' => $p->post_title,
                    'url' => get_permalink($p->ID),
                    'src' => $src,
                    'id' => $p->ID,
                ];
            }
        }
    }
}

echo wp_json_encode([
    'totalImgs' => $total,
    'emptyAlt' => $empty,
    'emptySvg' => $emptySvg,
    'emptyPhoto' => $emptyPhoto,
    'emptyByExt' => $emptyByExt,
    'photoExamples' => $photoExamples,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
