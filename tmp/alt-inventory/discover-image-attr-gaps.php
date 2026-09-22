<?php
if (!defined('ABSPATH')) exit;

$postTypes = ['page','post','novi-project','popup','wp_block','nectar_sections','nectar_templates'];
$q = new WP_Query([
  'post_type' => $postTypes,
  'post_status' => ['publish','private'],
  'posts_per_page' => -1,
  'no_found_rows' => true,
]);

function discWalk(array $blocks, callable $cb): void {
  foreach ($blocks as $block) {
    if (!is_array($block)) continue;
    $cb($block);
    if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
      discWalk($block['innerBlocks'], $cb);
    }
  }
}

function discIsRaster(string $url): bool {
  $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
  return (bool) preg_match('/\.(jpe?g|png|webp|gif|avif)$/', $p);
}
function discIsSvg(string $url): bool {
  $p = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
  return (bool) preg_match('/\.svg$/', $p);
}
function discEmpty($v): bool {
  return $v === null || (is_string($v) && trim($v) === '');
}

$paths = []; // block|path => stats
$gaps = [];  // unique attachment gaps for fill

$scan = function ($node, string $path, string $blockName, $post) use (&$scan, &$paths, &$gaps) {
  if (!is_array($node)) return;
  $hasUrl = isset($node['url']) && is_string($node['url']);
  $hasAlt = array_key_exists('alt', $node);
  $hasTitle = array_key_exists('title', $node);
  if ($hasUrl && ($hasAlt || $hasTitle)) {
    $url = (string) $node['url'];
    $key = $blockName . '|' . $path;
    if (!isset($paths[$key])) {
      $paths[$key] = [
        'block' => $blockName,
        'path' => $path,
        'total' => 0,
        'emptyAlt' => 0,
        'rasterEmptyAlt' => 0,
        'svgEmptyAlt' => 0,
      ];
    }
    $paths[$key]['total']++;
    $altEmpty = !$hasAlt || discEmpty($node['alt'] ?? '');
    if ($altEmpty) $paths[$key]['emptyAlt']++;
    if ($altEmpty && discIsRaster($url)) $paths[$key]['rasterEmptyAlt']++;
    if ($altEmpty && discIsSvg($url)) $paths[$key]['svgEmptyAlt']++;

    if ($altEmpty && discIsRaster($url) && !discIsSvg($url)) {
      $attId = (int) ($node['id'] ?? 0);
      $gaps[] = [
        'postId' => (int) $post->ID,
        'postType' => $post->post_type,
        'postTitle' => $post->post_title,
        'permalink' => get_permalink($post),
        'block' => $blockName,
        'blockId' => (string) (($GLOBALS['disc_current_block_id'] ?? '')),
        'attrPath' => $path,
        'attachmentId' => $attId,
        'url' => $url,
        'file' => basename($url),
        'title' => (string) ($node['title'] ?? ''),
        'mime' => $attId ? (string) get_post_mime_type($attId) : '',
      ];
    }
  }
  foreach ($node as $k => $v) {
    if (!is_array($v)) continue;
    $child = $path === '' ? (string) $k : $path . '.' . $k;
    if (is_int($k) || ctype_digit((string) $k)) {
      $child = $path . '[]';
    }
    $scan($v, $child, $blockName, $post);
  }
};

foreach ($q->posts as $post) {
  discWalk(parse_blocks($post->post_content), function ($b) use ($post, $scan) {
    $name = $b['blockName'] ?? '';
    if (!$name) return;
    $GLOBALS['disc_current_block_id'] = (string) ($b['attrs']['blockId'] ?? '');
    $attrs = $b['attrs'] ?? [];
    if (is_array($attrs)) $scan($attrs, '', $name, $post);
  });
}

uasort($paths, fn($a, $b) => $b['rasterEmptyAlt'] <=> $a['rasterEmptyAlt']);

// unique by attachment for generation, keep all gap instances for apply
$byAtt = [];
foreach ($gaps as $g) {
  $id = (int) $g['attachmentId'];
  if ($id <= 0) continue;
  if (!isset($byAtt[$id])) $byAtt[$id] = $g;
}

$out = [
  'generatedAt' => gmdate('c'),
  'gapInstances' => count($gaps),
  'uniqueAttachments' => count($byAtt),
  'paths' => array_values($paths),
  'gaps' => $gaps,
  'uniqueTargets' => array_values($byAtt),
];
file_put_contents(dirname(__FILE__) . '/discover-image-attr-gaps.json', wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo wp_json_encode([
  'gapInstances' => count($gaps),
  'uniqueAttachments' => count($byAtt),
  'pathsWithRasterEmptyAlt' => array_values(array_filter($paths, fn($p) => $p['rasterEmptyAlt'] > 0)),
  'byPostType' => array_count_values(array_column($gaps, 'postType')),
  'byBlock' => array_count_values(array_column($gaps, 'block')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
