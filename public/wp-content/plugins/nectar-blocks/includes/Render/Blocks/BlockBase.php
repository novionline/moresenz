<?php

namespace Nectar\Render\Blocks;

abstract class BlockBase {
  public $attributes;

  public function __construct($attributes) {
    $this->attributes = $attributes;
  }

  abstract public function get_conditional_js(): array;
}
