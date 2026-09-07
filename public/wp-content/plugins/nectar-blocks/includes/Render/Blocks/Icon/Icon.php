<?php

namespace Nectar\Render\Blocks\Icon;

use Nectar\Render\Blocks\Shared\IconSlotRenderer;

/**
 * Icon block server-side renderer.
 *
 * save() emits the deterministic wrapper plus an empty
 * <span data-nectar-icon-slot> marker for library SVGs (custom-uploaded
 * images stay as static <img> in save() and skip this path). This class
 * pulls the SVG out of attributes and asks IconSlotRenderer to fill the
 * marker. Keeping SVG out of save() output is what protects the block from
 * @wordpress/blocks normaliser drift between WP releases.
 */
class Icon {
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
    return IconSlotRenderer::fill_slot($this->content, $svg, $this->aria_label());
  }

  /**
   * ariaLabel is present in the delimiter only when non-default (non-empty);
   * absent -> '' -> decorative. Mirrors iconA11yLabel() on the JS side,
   * including the linked-icon carve-out: a link whose only content is the
   * icon must not go decorative, or the <a> is left with no accessible name.
   * Returning null there suppresses the a11y emission entirely, leaving the
   * SVG's own <title> to name the link as it did before these attrs landed.
   */
  private function aria_label() {
    $aria_label = $this->block_attributes['ariaLabel'] ?? '';
    $aria_label = is_string($aria_label) ? $aria_label : '';
    if (trim($aria_label) === '' && ! empty($this->block_attributes['link']['href'])) {
      return null;
    }
    return $aria_label;
  }
}
