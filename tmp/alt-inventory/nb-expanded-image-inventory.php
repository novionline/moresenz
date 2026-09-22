<?php
/**
 * Expanded read-only inventory: alt/title/description across NB + logo/carousel/gallery/ACF.
 * Usage: wp eval-file /tmp/nb-expanded-image-inventory.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$postTypes = ['page', 'post', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'];

function nbXEmpty($v)
{
    if ($v === null) {
        return true;
    }
    if (is_string($v)) {
        return trim($v) === '';
    }
    return false;
}

function nbXWalk($blocks, $callback)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $callback($block);
        if (!empty($block['innerBlocks'])) {
            nbXWalk($block['innerBlocks'], $callback);
        }
    }
}

function nbXAtt($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['alt' => null, 'title' => null, 'caption' => null, 'description' => null, 'url' => null, 'mime' => null];
    }
    $post = get_post($id);
    if (!$post || $post->post_type !== 'attachment') {
        return ['alt' => null, 'title' => null, 'caption' => null, 'description' => null, 'url' => null, 'mime' => null];
    }
    return [
        'alt' => get_post_meta($id, '_wp_attachment_image_alt', true),
        'title' => $post->post_title,
        'caption' => $post->post_excerpt,
        'description' => $post->post_content,
        'url' => wp_get_attachment_url($id),
        'mime' => $post->post_mime_type,
    ];
}

function nbXIsRasterUrl($url)
{
    $path = strtolower(parse_url((string) $url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.(jpe?g|png|webp|gif|avif)$/', $path);
}

function nbXIsSvgUrl($url)
{
    $path = strtolower(parse_url((string) $url, PHP_URL_PATH) ?: '');
    return (bool) preg_match('/\.svg$/', $path);
}

function nbXClassify($url, $block, $source)
{
    $u = strtolower((string) $url);
    $b = strtolower((string) $block);
    $s = strtolower((string) $source);
    if (strpos($b, 'logo') !== false || strpos($s, 'logo') !== false || strpos($u, 'logo') !== false) {
        return 'logo';
    }
    if (strpos($b, 'carousel') !== false || strpos($b, 'slider') !== false || strpos($b, 'marquee') !== false) {
        return 'slider_carousel';
    }
    if (strpos($b, 'gallery') !== false || strpos($b, 'image-grid') !== false) {
        return 'gallery_grid';
    }
    if (strpos($b, 'icon') !== false || strpos($u, 'icon') !== false) {
        return 'icon';
    }
    if ($b === '(featured-image)') {
        return 'featured';
    }
    if (strpos($s, 'bg') !== false || strpos($s, 'background') !== false) {
        return 'background';
    }
    if (nbXIsSvgUrl($url)) {
        return 'svg';
    }
    if (nbXIsRasterUrl($url)) {
        return 'photo';
    }
    return 'other';
}

function nbXPush(&$bag, $item)
{
    $bag[] = $item;
}

function nbXExtractMediaMap($media, $path)
{
    $out = [];
    if (!is_array($media)) {
        return $out;
    }
    // single media object
    $looksMedia = isset($media['url']) || isset($media['id']) || isset($media['ID']) || isset($media['alt']) || isset($media['title']);
    $isList = array_keys($media) === range(0, count($media) - 1);
    if ($looksMedia && !$isList) {
        $out[] = ['media' => $media, 'path' => $path];
        // nested desktop/tablet/mobile variants
        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            if (!empty($media[$bp]) && is_array($media[$bp])) {
                foreach (nbXExtractMediaMap($media[$bp], $path . '.' . $bp) as $row) {
                    $out[] = $row;
                }
            }
        }
        return $out;
    }
    if ($isList) {
        foreach ($media as $i => $row) {
            foreach (nbXExtractMediaMap($row, $path . '[' . $i . ']') as $r) {
                $out[] = $r;
            }
        }
        return $out;
    }
    // keyed map of possible media bags
    foreach ($media as $k => $v) {
        if (!is_array($v)) {
            continue;
        }
        $kl = strtolower((string) $k);
        if (preg_match('/(image|media|logo|icon|poster|gallery|slide|brand|bg)/', $kl)) {
            foreach (nbXExtractMediaMap($v, $path . '.' . $k) as $r) {
                $out[] = $r;
            }
        }
    }
    return $out;
}

function nbXFromBlock($block)
{
    $name = $block['blockName'] ?? '';
    $attrs = $block['attrs'] ?? [];
    $found = [];
    if (!$name) {
        return $found;
    }

    // prioritized known keys + recursive media-ish keys
    $keys = [
        'image', 'media', 'logo', 'logos', 'images', 'gallery', 'slides', 'items',
        'iconImage', 'poster', 'backgroundImage', 'bgImage', 'background',
        'desktop', 'tablet', 'mobile', 'brand', 'brands', 'partners',
    ];
    foreach ($keys as $key) {
        if (!empty($attrs[$key]) && is_array($attrs[$key])) {
            foreach (nbXExtractMediaMap($attrs[$key], 'attrs.' . $key) as $row) {
                $m = $row['media'];
                $found[] = [
                    'block' => $name,
                    'id' => (int) ($m['id'] ?? $m['ID'] ?? 0),
                    'url' => (string) ($m['url'] ?? ''),
                    'altAttr' => array_key_exists('alt', $m) ? $m['alt'] : null,
                    'titleAttr' => array_key_exists('title', $m) ? $m['title'] : null,
                    'descAttr' => array_key_exists('description', $m) ? $m['description'] : (array_key_exists('caption', $m) ? $m['caption'] : null),
                    'source' => $row['path'],
                ];
            }
        }
    }

    // flat image attrs on nectar-blocks/image
    if ($name === 'nectar-blocks/image') {
        $id = (int) ($attrs['id'] ?? $attrs['mediaId'] ?? 0);
        $url = (string) ($attrs['url'] ?? $attrs['mediaUrl'] ?? '');
        if ($id || $url || array_key_exists('alt', $attrs) || array_key_exists('title', $attrs)) {
            $found[] = [
                'block' => $name,
                'id' => $id,
                'url' => $url,
                'altAttr' => array_key_exists('alt', $attrs) ? $attrs['alt'] : null,
                'titleAttr' => array_key_exists('title', $attrs) ? $attrs['title'] : null,
                'descAttr' => array_key_exists('description', $attrs) ? $attrs['description'] : null,
                'source' => 'attrs.flat',
            ];
        }
    }

    // ACF blocks often store image arrays under data
    if (strpos($name, 'acf/') === 0 && !empty($attrs['data']) && is_array($attrs['data'])) {
        foreach ($attrs['data'] as $k => $v) {
            if (!is_array($v) && !is_numeric($v)) {
                continue;
            }
            $kl = strtolower((string) $k);
            if (!preg_match('/(image|logo|gallery|slide|media|icon|brand)/', $kl)) {
                continue;
            }
            if (is_numeric($v)) {
                $found[] = [
                    'block' => $name,
                    'id' => (int) $v,
                    'url' => '',
                    'altAttr' => null,
                    'titleAttr' => null,
                    'descAttr' => null,
                    'source' => 'attrs.data.' . $k,
                ];
            } elseif (is_array($v)) {
                foreach (nbXExtractMediaMap($v, 'attrs.data.' . $k) as $row) {
                    $m = $row['media'];
                    $found[] = [
                        'block' => $name,
                        'id' => (int) ($m['id'] ?? $m['ID'] ?? $m['ID'] ?? 0),
                        'url' => (string) ($m['url'] ?? ''),
                        'altAttr' => array_key_exists('alt', $m) ? $m['alt'] : null,
                        'titleAttr' => array_key_exists('title', $m) ? $m['title'] : null,
                        'descAttr' => array_key_exists('description', $m) ? $m['description'] : (array_key_exists('caption', $m) ? $m['caption'] : null),
                        'source' => $row['path'],
                    ];
                }
            }
        }
    }

    return $found;
}

$query = new WP_Query([
    'post_type' => $postTypes,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'type title',
    'order' => 'ASC',
    'lang' => 'nl',
]);

$blockNamesSeen = [];
$items = [];
$logoLikePosts = [];

foreach ($query->posts as $post) {
    $blocks = parse_blocks($post->post_content);
    $postItems = [];

    nbXWalk($blocks, function ($block) use (&$postItems, &$blockNamesSeen, $post, &$logoLikePosts) {
        $name = $block['blockName'] ?? '';
        if ($name) {
            $blockNamesSeen[$name] = ($blockNamesSeen[$name] ?? 0) + 1;
        }
        $nl = strtolower($name);
        if (preg_match('/(logo|carousel|slider|marquee|gallery|brand|partner)/', $nl)) {
            $logoLikePosts[$post->ID] = [
                'id' => $post->ID,
                'type' => $post->post_type,
                'title' => $post->post_title,
                'block' => $name,
                'url' => get_permalink($post->ID),
            ];
        }
        foreach (nbXFromBlock($block) as $img) {
            $postItems[] = $img;
        }
    });

    $thumbId = (int) get_post_thumbnail_id($post->ID);
    if ($thumbId) {
        $meta = nbXAtt($thumbId);
        $postItems[] = [
            'block' => '(featured-image)',
            'id' => $thumbId,
            'url' => $meta['url'] ?: '',
            'altAttr' => $meta['alt'],
            'titleAttr' => $meta['title'],
            'descAttr' => $meta['description'],
            'source' => 'featured_image',
        ];
    }

    $seen = [];
    foreach ($postItems as $img) {
        $key = ($img['id'] ?: 0) . '|' . ($img['url'] ?: '') . '|' . ($img['block'] ?: '') . '|' . ($img['source'] ?: '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $att = nbXAtt($img['id']);
        if (!$img['url'] && !empty($att['url'])) {
            $img['url'] = $att['url'];
        }

        $kind = nbXClassify($img['url'], $img['block'], $img['source']);
        $altEmpty = nbXEmpty($img['altAttr']) && nbXEmpty($att['alt']);
        // for block attrs: prefer block alt; if both empty count missing
        $blockAltEmpty = array_key_exists('altAttr', $img) ? nbXEmpty($img['altAttr']) : true;
        $titleEmpty = nbXEmpty($img['titleAttr']) && nbXEmpty($att['title']);
        $descEmpty = nbXEmpty($img['descAttr']) && nbXEmpty($att['description']) && nbXEmpty($att['caption']);

        $items[] = [
            'postId' => $post->ID,
            'postType' => $post->post_type,
            'postTitle' => $post->post_title,
            'permalink' => get_permalink($post->ID),
            'block' => $img['block'],
            'source' => $img['source'],
            'attachmentId' => $img['id'],
            'url' => $img['url'],
            'kind' => $kind,
            'blockAlt' => $img['altAttr'],
            'blockTitle' => $img['titleAttr'],
            'blockDesc' => $img['descAttr'],
            'attAlt' => $att['alt'],
            'attTitle' => $att['title'],
            'attCaption' => $att['caption'],
            'attDescription' => $att['description'],
            'missingAlt' => $blockAltEmpty,
            'missingAttAlt' => nbXEmpty($att['alt']),
            'missingTitle' => nbXEmpty($img['titleAttr']),
            'missingAttTitle' => nbXEmpty($att['title']),
            'missingDescription' => $descEmpty,
            'isRaster' => nbXIsRasterUrl($img['url']) || (isset($att['mime']) && strpos((string) $att['mime'], 'image/') === 0 && strpos((string) $att['mime'], 'svg') === false),
            'isSvg' => nbXIsSvgUrl($img['url']) || (isset($att['mime']) && strpos((string) $att['mime'], 'svg') !== false),
        ];
    }
}

// media library sweep
$mediaMissing = ['alt' => 0, 'title' => 0, 'description' => 0, 'caption' => 0, 'total' => 0];
$mediaQ = new WP_Query([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'post_mime_type' => 'image',
    'fields' => 'ids',
]);
foreach ($mediaQ->posts as $aid) {
    $mediaMissing['total']++;
    $a = nbXAtt($aid);
    if (nbXEmpty($a['alt'])) {
        $mediaMissing['alt']++;
    }
    if (nbXEmpty($a['title'])) {
        $mediaMissing['title']++;
    }
    if (nbXEmpty($a['description'])) {
        $mediaMissing['description']++;
    }
    if (nbXEmpty($a['caption'])) {
        $mediaMissing['caption']++;
    }
}

function nbXCount($items, $fn)
{
    $n = 0;
    foreach ($items as $it) {
        if ($fn($it)) {
            $n++;
        }
    }
    return $n;
}

$summary = [
    'postsScanned' => count($query->posts),
    'imagesFound' => count($items),
    'missingAltBlock' => nbXCount($items, fn($i) => $i['missingAlt']),
    'missingAltBlockRaster' => nbXCount($items, fn($i) => $i['missingAlt'] && $i['isRaster']),
    'missingAltBlockSvg' => nbXCount($items, fn($i) => $i['missingAlt'] && $i['isSvg']),
    'missingTitleBlock' => nbXCount($items, fn($i) => $i['missingTitle']),
    'missingDescription' => nbXCount($items, fn($i) => $i['missingDescription']),
    'byKind' => [],
    'byPostType' => [],
    'byBlock' => [],
    'blockNamesSeen' => $blockNamesSeen,
    'logoLikePosts' => array_values($logoLikePosts),
    'mediaLibrary' => $mediaMissing,
];

foreach ($items as $it) {
    $k = $it['kind'];
    if (!isset($summary['byKind'][$k])) {
        $summary['byKind'][$k] = ['total' => 0, 'missingAlt' => 0, 'missingTitle' => 0, 'missingDesc' => 0, 'raster' => 0];
    }
    $summary['byKind'][$k]['total']++;
    if ($it['missingAlt']) {
        $summary['byKind'][$k]['missingAlt']++;
    }
    if ($it['missingTitle']) {
        $summary['byKind'][$k]['missingTitle']++;
    }
    if ($it['missingDescription']) {
        $summary['byKind'][$k]['missingDesc']++;
    }
    if ($it['isRaster']) {
        $summary['byKind'][$k]['raster']++;
    }

    $pt = $it['postType'];
    if (!isset($summary['byPostType'][$pt])) {
        $summary['byPostType'][$pt] = ['total' => 0, 'missingAlt' => 0, 'missingTitle' => 0, 'missingDesc' => 0];
    }
    $summary['byPostType'][$pt]['total']++;
    if ($it['missingAlt']) {
        $summary['byPostType'][$pt]['missingAlt']++;
    }
    if ($it['missingTitle']) {
        $summary['byPostType'][$pt]['missingTitle']++;
    }
    if ($it['missingDescription']) {
        $summary['byPostType'][$pt]['missingDesc']++;
    }

    $b = $it['block'];
    if (!isset($summary['byBlock'][$b])) {
        $summary['byBlock'][$b] = ['total' => 0, 'missingAlt' => 0, 'missingTitle' => 0, 'missingDesc' => 0];
    }
    $summary['byBlock'][$b]['total']++;
    if ($it['missingAlt']) {
        $summary['byBlock'][$b]['missingAlt']++;
    }
    if ($it['missingTitle']) {
        $summary['byBlock'][$b]['missingTitle']++;
    }
    if ($it['missingDescription']) {
        $summary['byBlock'][$b]['missingDesc']++;
    }
}

// unique attachment gaps for photos with empty block alt
$uniquePhotoGaps = [];
foreach ($items as $it) {
    if (!$it['missingAlt'] || !$it['isRaster']) {
        continue;
    }
    $aid = (int) $it['attachmentId'];
    $uk = $aid ?: $it['url'];
    if (!$uk || isset($uniquePhotoGaps[$uk])) {
        continue;
    }
    $uniquePhotoGaps[$uk] = [
        'attachmentId' => $aid,
        'url' => $it['url'],
        'kind' => $it['kind'],
        'block' => $it['block'],
        'postId' => $it['postId'],
        'postType' => $it['postType'],
        'postTitle' => $it['postTitle'],
        'permalink' => $it['permalink'],
        'blockTitle' => $it['blockTitle'],
        'attTitle' => $it['attTitle'],
        'attAlt' => $it['attAlt'],
        'attDescription' => $it['attDescription'],
    ];
}

$logoGaps = array_values(array_filter($items, function ($it) {
    return ($it['kind'] === 'logo' || $it['kind'] === 'slider_carousel') && $it['missingAlt'];
}));

$out = [
    'generatedAt' => gmdate('c'),
    'summary' => $summary,
    'uniquePhotoGaps' => array_values($uniquePhotoGaps),
    'logoSliderGapsSample' => array_slice($logoGaps, 0, 80),
    'examplesMissingAltRaster' => array_slice(array_values(array_filter($items, fn($i) => $i['missingAlt'] && $i['isRaster'])), 0, 60),
];

echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
