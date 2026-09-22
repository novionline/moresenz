<?php
if (!defined('ABSPATH')) exit;
$p = get_post(2236); // Apex
$html = do_blocks($p->post_content);
// find robert/scene photo imgs
if (preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
  foreach ($m[0] as $tag) {
    if (!preg_match('/Robert|Scene-|Aspen|scaled\.(jpg|png)/i', $tag)) continue;
    echo $tag . "\n\n";
  }
}
// also dump attrs.image for first photo block
$walk = function($blocks) use (&$walk) {
  foreach ($blocks as $b) {
    if (($b['blockName'] ?? '') === 'nectar-blocks/image' && !empty($b['attrs']['image']['url'])) {
      $url = $b['attrs']['image']['url'];
      if (preg_match('/\.(jpe?g|png)($|\?)/i', $url)) {
        echo "ATTR: alt=" . json_encode($b['attrs']['image']['alt'] ?? null) . " title=" . json_encode($b['attrs']['image']['title'] ?? null) . " id=" . ($b['attrs']['image']['id'] ?? '') . "\n";
        echo "URL: $url\n";
        $id = (int)($b['attrs']['image']['id'] ?? 0);
        if ($id) {
          echo "META alt=" . json_encode(get_post_meta($id, '_wp_attachment_image_alt', true)) . " title=" . json_encode(get_the_title($id)) . "\n";
        }
        echo "---\n";
      }
    }
    if (!empty($b['innerBlocks'])) $walk($b['innerBlocks']);
  }
};
$walk(parse_blocks($p->post_content));
