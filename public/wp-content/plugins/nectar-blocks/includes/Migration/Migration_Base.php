<?php

namespace Nectar\Migration;

interface Migration_Base {
  public function get_version(): string;

  public function migrate(): bool;
}
