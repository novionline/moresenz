<?php

namespace Nectar\Render\Blocks\Tabs;

use Nectar\Render\Blocks\Shared\IconSlotRenderer;

/**
 * Tabs block server-side renderer.
 *
 * Each tab item in the nav can have its own library-SVG icon, so save()
 * emits one <span data-nectar-icon-slot> per tabItem that has an icon
 * (custom-image and no-icon tabs emit no slot). This renderer walks
 * $attributes['tabItems'] in the same order save() did, builds an svgs
 * array for the items that emitted a slot, and asks IconSlotRenderer to
 * fill the slots in order.
 *
 * Inner tab-section blocks have already been rendered into $content by the
 * time this runs; their markup is left alone because the slot regex only
 * matches empty <span> bodies.
 */
class Tabs {
  private $block_attributes;

  private $content;

  function __construct($block_attributes, $content) {
    $this->block_attributes = is_array($block_attributes) ? $block_attributes : [];
    $this->content = is_string($content) ? $content : '';
  }

  function render() {
    $tab_items = $this->block_attributes['tabItems'] ?? [];
    if (! is_array($tab_items) || count($tab_items) === 0) {
      return $this->content;
    }

    $svgs = [];
    foreach ($tab_items as $tab_item) {
      $icon_attr = is_array($tab_item) ? ($tab_item['icon'] ?? null) : null;
      // Mirror renderIconSlot's "emits a slot?" check exactly:
      // source != 'custom' AND icon is set. Whether the SVG payload is
      // present or empty doesn't change slot emission — so the svgs array
      // must include a (possibly empty) entry per slot to keep alignment
      // with the slots in $content. An empty entry does NOT force an empty
      // slot: fill_slots falls back to slot i's own self-describing identity
      // (data-icon-library / data-icon-name) and resolves the SVG server-side,
      // so a default-omitted icon still renders. A slot only stays empty when
      // neither a payload nor a resolvable identity yields an SVG.
      $would_emit_slot = is_array($icon_attr)
        && ($icon_attr['source'] ?? '') !== 'custom'
        && isset($icon_attr['icon'])
        && $icon_attr['icon'] !== null;
      if ($would_emit_slot) {
        $svgs[] = IconSlotRenderer::get_svg_from_icon_attr($icon_attr);
      }
    }

    return IconSlotRenderer::fill_slots($this->content, $svgs);
  }
}
