<?php

namespace Nectar\Render\Blocks\IconListItem;

use Nectar\Render\Blocks\Shared\IconSlotRenderer;

/**
 * Icon List Item block server-side renderer.
 *
 * Same pattern as the Icon block: save() emits an empty
 * <span data-nectar-icon-slot> for library SVGs alongside the title /
 * description content. The SVG payload lives in $attributes['icon']; the
 * shared renderer fills the marker.
 */
class IconListItem {
  private $block_attributes;

  private $content;

  function __construct($block_attributes, $content) {
    $this->block_attributes = is_array($block_attributes) ? $block_attributes : [];
    $this->content = is_string($content) ? $content : '';
  }

  function render() {
    $svg = IconSlotRenderer::get_svg_from_icon_attr(
        $this->block_attributes['icon'] ?? null
    );
    return IconSlotRenderer::fill_slot($this->content, $svg);
  }
}
