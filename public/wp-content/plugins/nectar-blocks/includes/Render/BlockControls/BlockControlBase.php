<?php

namespace Nectar\Render\BlockControls;

/**
 * Block Base
 * @version 0.0.9
 * @since 0.0.9
 */
interface BlockControlBase {
  public static function get_conditional_js($attributes): array;
}
