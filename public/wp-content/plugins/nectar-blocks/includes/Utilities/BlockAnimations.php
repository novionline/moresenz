<?php

namespace Nectar\Utilities;

class BlockAnimations {
  public static function get_animation_attrs($animation_attributes) {
    $attributes = [];
    $output = '';
    if ( isset($animation_attributes['selector']) && ! empty($animation_attributes['selector']) ) {
      $attributes['selector'] = $animation_attributes['selector'];
    }

    if ( isset($animation_attributes['selectorMode']) && ! empty($animation_attributes['selectorMode']) ) {
      $attributes['selectorMode'] = $animation_attributes['selectorMode'];
    }

    // Handle click animations
    if (isset($animation_attributes['click']) && ! empty($animation_attributes['click'])) {
      $attributes['click'] = $animation_attributes['click'];
    }

    // Handle scroll position animations
    if (isset($animation_attributes['scrollPosition']) && ! empty($animation_attributes['scrollPosition'])) {
      $attributes['scrollPosition'] = $animation_attributes['scrollPosition'];

      // Clean up empty responsive values
      if (isset($attributes['scrollPosition']['scrollValues'])) {
        $scrollValues = &$attributes['scrollPosition']['scrollValues'];
        if (empty($scrollValues['tablet'])) {
          unset($scrollValues['tablet']);
        }
        if (empty($scrollValues['mobile'])) {
          unset($scrollValues['mobile']);
        }
      }
    }

    // Handle scroll into view animations
    if (isset($animation_attributes['scrollIntoView'])) {
      $attributes['scrollIntoView'] = $animation_attributes['scrollIntoView'];
    }

    if (empty($attributes)) {
      return '';
    }

    $animation_attrs = [
      'data-nectar-block-animation' => esc_attr(wp_json_encode($attributes))
    ];

    if (isset($animation_attributes['scrollIntoView'])) {
      $attributes['scrollIntoView'] = $animation_attributes['scrollIntoView'];

      // Add data attributes for device types
      $deviceTypes = ['desktop', 'tablet', 'mobile'];
      foreach ($deviceTypes as $deviceType) {
        if (isset($attributes['scrollIntoView']['triggerDevices']) &&
            in_array($deviceType, $attributes['scrollIntoView']['triggerDevices'])) {
          $animation_attrs["data-await-in-view-{$deviceType}"] = '';
        }
      }

      if (! empty($animation_attrs)) {
        foreach ($animation_attrs as $key => $value) {
          $output .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
      }
    }

    return $output;
  }
}