<?php
/**
 * Read-only inventory: images missing alt and/or title in published NL content.
 * Usage: wp eval-file /tmp/nb-image-alt-scan.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$postTypes = ['page', 'post', 'novi-project', 'popup', 'wp_block', 'nectar_sections', 'nectar_templates'];
$summary = [
    'postsScanned' => 0,
    'imagesFound' => 0,
    'missingAlt' => 0,
    'missingTitle' => 0,
    'missingBoth' => 0,
    'byPostType' => [],
    'byBlockType' => [],
    'attachmentMetaMissingAlt' => 0,
    'attachmentMetaMissingTitle' => 0,
];

function nbInvEmpty($v)
{
    if ($v === null) {
        return true;
    }
    if (is_string($v)) {
        return trim($v) === '';
    }
    return false;
}

function nbInvWalkBlocks($blocks, $callback, $parent = null)
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $callback($block, $parent);
        if (!empty($block['innerBlocks'])) {
            nbInvWalkBlocks($block['innerBlocks'], $callback, $block);
        }
    }
}

function nbInvAttachmentMeta($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['alt' => null, 'title' => null, 'url' => null, 'exists' => false];
    }
    $post = get_post($id);
    if (!$post || $post->post_type !== 'attachment') {
        return ['alt' => null, 'title' => null, 'url' => null, 'exists' => false];
    }
    return [
        'alt' => get_post_meta($id, '_wp_attachment_image_alt', true),
        'title' => $post->post_title,
        'url' => wp_get_attachment_url($id),
        'exists' => true,
    ];
}

function nbInvExtractImagesFromBlock($block)
{
    $name = $block['blockName'] ?? '';
    $attrs = $block['attrs'] ?? [];
    $found = [];

    $pushMedia = function ($blockName, $media, $source) use (&$found) {
        if (!is_array($media)) {
            return;
        }
        $id = (int) ($media['id'] ?? $media['ID'] ?? 0);
        $url = (string) ($media['url'] ?? '');
        $hasSignal = $id || $url || array_key_exists('alt', $media) || array_key_exists('title', $media);
        if (!$hasSignal) {
            return;
        }
        $found[] = [
            'block' => $blockName,
            'id' => $id,
            'url' => $url,
            'altAttr' => array_key_exists('alt', $media) ? $media['alt'] : null,
            'titleAttr' => array_key_exists('title', $media) ? $media['title'] : null,
            'source' => $source,
        ];
    };

    if ($name === 'nectar-blocks/image') {
        foreach (['media', 'image', 'desktop', 'tablet', 'mobile', 'imageDesktop', 'imageTablet', 'imageMobile'] as $key) {
            if (!empty($attrs[$key]) && is_array($attrs[$key])) {
                $pushMedia($name, $attrs[$key], 'attrs.' . $key);
            }
        }
        // flat attrs fallback
        $id = (int) ($attrs['id'] ?? $attrs['mediaId'] ?? 0);
        $url = (string) ($attrs['url'] ?? $attrs['mediaUrl'] ?? '');
        if ($id || $url || array_key_exists('alt', $attrs) || array_key_exists('title', $attrs)) {
            $found[] = [
                'block' => $name,
                'id' => $id,
                'url' => $url,
                'altAttr' => array_key_exists('alt', $attrs) ? $attrs['alt'] : null,
                'titleAttr' => array_key_exists('title', $attrs) ? $attrs['title'] : null,
                'source' => 'attrs.flat',
            ];
        }
    }

    if (in_array($name, ['nectar-blocks/image-grid', 'nectar-blocks/image-gallery'], true)) {
        $images = $attrs['images'] ?? ($attrs['gallery'] ?? ($attrs['media'] ?? []));
        if (is_array($images)) {
            $i = 0;
            foreach ($images as $img) {
                $pushMedia($name, $img, 'attrs.images[' . $i . ']');
                $i++;
            }
        }
    }

    if (in_array($name, [
        'nectar-blocks/carousel-item',
        'nectar-blocks/video-lightbox',
        'nectar-blocks/testimonial',
        'nectar-blocks/icon',
        'nectar-blocks/button',
        'nectar-blocks/post-grid',
        'nectar-blocks/row',
        'nectar-blocks/column',
        'nectar-blocks/flex-box',
    ], true)) {
        foreach (['media', 'image', 'poster', 'iconImage', 'backgroundImage', 'bgImage'] as $key) {
            if (!empty($attrs[$key]) && is_array($attrs[$key])) {
                $pushMedia($name, $attrs[$key], 'attrs.' . $key);
            }
        }
        // nested background shapes common in NB
        if (!empty($attrs['background']) && is_array($attrs['background'])) {
            foreach (['image', 'desktop', 'tablet', 'mobile', 'media'] as $key) {
                if (!empty($attrs['background'][$key]) && is_array($attrs['background'][$key])) {
                    $pushMedia($name, $attrs['background'][$key], 'attrs.background.' . $key);
                }
            }
        }
    }

    if ($name === 'core/image') {
        $found[] = [
            'block' => $name,
            'id' => (int) ($attrs['id'] ?? 0),
            'url' => (string) ($attrs['url'] ?? ''),
            'altAttr' => array_key_exists('alt', $attrs) ? $attrs['alt'] : null,
            'titleAttr' => array_key_exists('title', $attrs) ? $attrs['title'] : null,
            'source' => 'core attrs',
        ];
    }

    if ($name === 'core/gallery' && !empty($attrs['ids']) && is_array($attrs['ids'])) {
        foreach ($attrs['ids'] as $gid) {
            $found[] = [
                'block' => $name,
                'id' => (int) $gid,
                'url' => '',
                'altAttr' => null,
                'titleAttr' => null,
                'source' => 'core gallery ids',
            ];
        }
    }

    $html = $block['innerHTML'] ?? '';
    if ($html && preg_match_all('/<img\b[^>]*>/i', $html, $imgs)) {
        foreach ($imgs[0] as $tag) {
            preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $sm);
            preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $am);
            preg_match('/\btitle=["\']([^"\']*)["\']/i', $tag, $tm);
            $hasAltAttr = (bool) preg_match('/\balt=/i', $tag);
            $hasTitleAttr = (bool) preg_match('/\btitle=/i', $tag);
            $found[] = [
                'block' => $name ?: '(html-fragment)',
                'id' => 0,
                'url' => $sm[1] ?? '',
                'altAttr' => $hasAltAttr ? ($am[1] ?? '') : null,
                'titleAttr' => $hasTitleAttr ? ($tm[1] ?? '') : null,
                'source' => 'innerHTML img',
            ];
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

$examples = [];
$attrKeySamples = [];
$blockNamesSeen = [];

foreach ($query->posts as $post) {
    $summary['postsScanned']++;
    $pt = $post->post_type;
    if (!isset($summary['byPostType'][$pt])) {
        $summary['byPostType'][$pt] = [
            'posts' => 0,
            'images' => 0,
            'missingAlt' => 0,
            'missingTitle' => 0,
            'missingBoth' => 0,
        ];
    }
    $summary['byPostType'][$pt]['posts']++;

    $blocks = parse_blocks($post->post_content);
    $postImages = [];

    nbInvWalkBlocks($blocks, function ($block) use (&$postImages, &$blockNamesSeen, &$attrKeySamples) {
        $name = $block['blockName'] ?? '';
        if ($name) {
            $blockNamesSeen[$name] = ($blockNamesSeen[$name] ?? 0) + 1;
        }
        $isImageLike = $name && (
            strpos($name, 'image') !== false
            || in_array($name, [
                'nectar-blocks/carousel-item',
                'nectar-blocks/video-lightbox',
                'nectar-blocks/testimonial',
                'nectar-blocks/icon',
                'nectar-blocks/row',
                'nectar-blocks/column',
                'nectar-blocks/flex-box',
            ], true)
        );
        if ($isImageLike && !isset($attrKeySamples[$name])) {
            $attrKeySamples[$name] = array_keys($block['attrs'] ?? []);
            $attrKeySamples[$name . '__sample'] = substr(wp_json_encode($block['attrs'] ?? []), 0, 1500);
        }
        foreach (nbInvExtractImagesFromBlock($block) as $img) {
            $postImages[] = $img;
        }
    });

    $thumbId = (int) get_post_thumbnail_id($post->ID);
    if ($thumbId) {
        $meta = nbInvAttachmentMeta($thumbId);
        $postImages[] = [
            'block' => '(featured-image)',
            'id' => $thumbId,
            'url' => $meta['url'] ?: '',
            'altAttr' => $meta['alt'],
            'titleAttr' => $meta['title'],
            'source' => 'featured_image',
        ];
    }

    $seen = [];
    foreach ($postImages as $img) {
        $key = ($img['block'] ?? '') . '|' . ($img['id'] ?? 0) . '|' . ($img['url'] ?? '') . '|' . ($img['source'] ?? '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $meta = nbInvAttachmentMeta($img['id'] ?? 0);
        $altBlock = $img['altAttr'];
        $titleBlock = $img['titleAttr'];

        $altEffective = null;
        $titleEffective = null;
        $altSource = 'none';
        $titleSource = 'none';

        if ($altBlock !== null) {
            $altEffective = $altBlock;
            $altSource = 'block';
        } elseif ($meta['exists']) {
            $altEffective = $meta['alt'];
            $altSource = 'attachment';
        }

        if ($titleBlock !== null) {
            $titleEffective = $titleBlock;
            $titleSource = 'block';
        } elseif ($meta['exists']) {
            $titleEffective = $meta['title'];
            $titleSource = 'attachment';
        }

        $missingAlt = nbInvEmpty($altEffective);
        $missingTitle = nbInvEmpty($titleEffective);

        $summary['imagesFound']++;
        $summary['byPostType'][$pt]['images']++;
        $bt = $img['block'] ?: '(unknown)';
        if (!isset($summary['byBlockType'][$bt])) {
            $summary['byBlockType'][$bt] = [
                'images' => 0,
                'missingAlt' => 0,
                'missingTitle' => 0,
                'missingBoth' => 0,
            ];
        }
        $summary['byBlockType'][$bt]['images']++;

        if ($meta['exists'] && nbInvEmpty($meta['alt'])) {
            $summary['attachmentMetaMissingAlt']++;
        }
        if ($meta['exists'] && nbInvEmpty($meta['title'])) {
            $summary['attachmentMetaMissingTitle']++;
        }

        if ($missingAlt) {
            $summary['missingAlt']++;
            $summary['byPostType'][$pt]['missingAlt']++;
            $summary['byBlockType'][$bt]['missingAlt']++;
        }
        if ($missingTitle) {
            $summary['missingTitle']++;
            $summary['byPostType'][$pt]['missingTitle']++;
            $summary['byBlockType'][$bt]['missingTitle']++;
        }
        if ($missingAlt && $missingTitle) {
            $summary['missingBoth']++;
            $summary['byPostType'][$pt]['missingBoth']++;
            $summary['byBlockType'][$bt]['missingBoth']++;
        }

        if (($missingAlt || $missingTitle) && count($examples) < 100) {
            $examples[] = [
                'postId' => $post->ID,
                'postType' => $pt,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'edit' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                'block' => $bt,
                'imageId' => (int) ($img['id'] ?? 0),
                'imageUrl' => $img['url'] ?: ($meta['url'] ?: ''),
                'missingAlt' => $missingAlt,
                'missingTitle' => $missingTitle,
                'altSource' => $altSource,
                'titleSource' => $titleSource,
                'altBlock' => $altBlock,
                'titleBlock' => $titleBlock,
                'altAttachment' => $meta['exists'] ? $meta['alt'] : null,
                'titleAttachment' => $meta['exists'] ? $meta['title'] : null,
                'source' => $img['source'] ?? '',
            ];
        }
    }
}

$attachQ = new WP_Query([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'post_mime_type' => 'image',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);
$lib = ['total' => 0, 'missingAlt' => 0, 'missingTitle' => 0, 'missingBoth' => 0];
foreach ($attachQ->posts as $aid) {
    $lib['total']++;
    $alt = get_post_meta($aid, '_wp_attachment_image_alt', true);
    $title = get_the_title($aid);
    $ma = nbInvEmpty($alt);
    $mt = nbInvEmpty($title);
    if ($ma) {
        $lib['missingAlt']++;
    }
    if ($mt) {
        $lib['missingTitle']++;
    }
    if ($ma && $mt) {
        $lib['missingBoth']++;
    }
}

$out = [
    'scope' => [
        'site' => get_option('siteurl'),
        'blogname' => get_option('blogname'),
        'language' => 'nl (Polylang)',
        'locale' => get_locale(),
        'postTypes' => $postTypes,
        'postsScanned' => $summary['postsScanned'],
        'builder' => 'NectarBlocks (+ core blocks if present)',
        'draftsSkipped' => true,
        'englishSkipped' => true,
    ],
    'summary' => $summary,
    'mediaLibraryImages' => $lib,
    'blockNamesSeen' => $blockNamesSeen,
    'attrKeySamples' => $attrKeySamples,
    'examples' => $examples,
];

echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
