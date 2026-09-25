<?php

namespace Nectar\Utilities;

class FlatMap {
  public static function flatMap(callable $fn, array $array): array {
    return array_merge([], ...array_map($fn, $array));
  }
}