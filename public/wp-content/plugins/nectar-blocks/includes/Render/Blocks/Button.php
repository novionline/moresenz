<?php

namespace Nectar\Render\Blocks;

use Nectar\Render\BlockControls\AnimationControl;

class Button implements BlockBase {
  public function get_conditional_js(): array {
    $animationJS = AnimationControl::get_conditional_js($this->attributes);

    return [
      ...$animationJS
    ];
  }
}