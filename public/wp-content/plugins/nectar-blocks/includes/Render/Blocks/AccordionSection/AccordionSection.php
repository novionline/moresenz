<?php

namespace Nectar\Render\Blocks\AccordionSection;

use Nectar\Render\Blocks\Shared\IconSlotRenderer;

/**
 * Accordion Section block server-side renderer.
 *
 * Same single-slot pattern as Icon / IconListItem: save() emits an empty
 * <span data-nectar-icon-slot> for the trigger's library SVG (custom
 * uploads stay as static <img>; no-icon triggers emit nothing). The shared
 * renderer fills the marker.
 *
 * Inner accordion content has already been rendered into $content by the
 * time this runs; its markup is left alone because the slot regex only
 * matches empty <span> bodies.
 */
class AccordionSection {
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
