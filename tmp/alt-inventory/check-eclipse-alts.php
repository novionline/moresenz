<?php
if (!defined('ABSPATH')) {
    exit;
}

$post = get_post(27);
$blocks = parse_blocks((string) $post->post_content);
$mismatches = 0;
$checked = 0;

$walk = static function (array $blocks) use (&$walk, &$mismatches, &$checked): void {
    foreach ($blocks as $b) {
        if (($b['blockName'] ?? '') === 'nectar-blocks/image') {
            $checked++;
            $alt = (string) ($b['attrs']['image']['alt'] ?? '');
            $title = (string) ($b['attrs']['image']['title'] ?? '');
            $html = (string) ($b['innerHTML'] ?? '');
            preg_match('/<img\b[^>]*>/i', $html, $m);
            $tag = $m[0] ?? '';
            preg_match('/\balt=("|\')(.*?)\1/is', $tag, $am);
            preg_match('/\btitle=("|\')(.*?)\1/is', $tag, $tm);
            $ha = isset($am[2]) ? html_entity_decode($am[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
            $ht = isset($tm[2]) ? html_entity_decode($tm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
            if ($ha !== $alt || $ht !== $title) {
                $mismatches++;
                echo 'MISMATCH ' . ($b['attrs']['blockId'] ?? '') . "\n";
            }
        }
        if (!empty($b['innerBlocks']) && is_array($b['innerBlocks'])) {
            $walk($b['innerBlocks']);
        }
    }
};
$walk($blocks);
echo 'eclipse images checked=' . $checked . ' mismatches=' . $mismatches
    . ' css=' . strlen((string) get_post_meta(27, '_nectar_blocks_css', true)) . "\n";
