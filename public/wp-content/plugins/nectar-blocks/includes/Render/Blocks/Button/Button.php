<?php

namespace Nectar\Render\Blocks\Button;

use Nectar\Render\Blocks\Shared\IconSlotRenderer;

/**
 * Button block server-side renderer.
 *
 * Single-slot pattern: save() emits an empty <span data-nectar-icon-slot>
 * for the non-Arrow icon path (custom uploads stay as static <img>; Arrow
 * style emits a static <span><Arrow/></span> that doesn't need PHP). The
 * shared renderer fills the marker when present.
 */
class Button {
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
