<?php

/**
 * Preload Background Images
 *
 * Scans top-level row/column blocks (and their nested children) for
 * background images and emits <link rel="preload"> tags in wp_head to
 * improve LCP scores in Lighthouse.
 *
 * Responsive handling uses media queries on the preload links so the
 * browser itself decides which image to fetch — no server-side UA
 * detection needed, fully compatible with page caching.
 *
 * - Desktop-only image: no media attribute (preloaded on all viewports).
 * - Desktop + mobile images: two links with complementary media queries
 *   so each viewport fetches only its own image.
 * - Mobile breakpoint matches Block_Dynamic_CSS::MEDIA_QUERIES (767px).
 *
 * @package Nectar\Render
 * @since 3.0.0
 */

namespace Nectar\Render;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class Preload_BG_Images {
  private const MOBILE_BREAKPOINT = 767;

  function __construct() {
    add_action('wp_head', [$this, 'output_preload_links'], 4);
  }

  /**
   * Outputs preload link tags for background images found in
   * top-level row/column blocks and their nested children.
   */
  function output_preload_links(): void {
    global $post;

    if ( ! $post || empty($post->post_content) ) {
      return;
    }

    $blocks = parse_blocks($post->post_content);
    $preloaded = [];

    foreach ($blocks as $block) {
      $block_name = $block['blockName'] ?? '';

      // Only preload from the first top-level row — this is the
      // above-the-fold hero content that determines LCP.
      if ($block_name === 'nectar-blocks/row') {
        $this->collect_preloads($block, $preloaded);
        break;
      }
    }
  }

  /**
   * Recursively walks a block tree and outputs preload links
   * for any block with a bgImage attribute.
   */
  private function collect_preloads(array $block, array &$preloaded): void {
    $attrs = $block['attrs'] ?? [];

    if ( ! empty($attrs['bgImage']) ) {
      $bg_image = $attrs['bgImage'];
      $desktop_url = $this->resolve_device_url($bg_image['desktop'] ?? []);
      $mobile_data = $bg_image['mobile'] ?? [];
      // Only resolve mobile URL if the mobile slot has its own image
      // (its own url or id). Slots that only override focal point / size
      // etc. inherit the desktop image and shouldn't split the preload.
      $mobile_url = $this->has_own_image($mobile_data) ? $this->resolve_device_url($mobile_data) : '';

      $bp = self::MOBILE_BREAKPOINT;

      if ( ! empty($desktop_url) && ! empty($mobile_url) && $desktop_url !== $mobile_url ) {
        // Different images per viewport — use media queries so the browser
        // only fetches the one matching the current viewport.
        $this->output_link($mobile_url, "(max-width: {$bp}px)", $preloaded);
        $this->output_link($desktop_url, "(min-width: " . ($bp + 1) . "px)", $preloaded);
      } elseif ( ! empty($desktop_url) ) {
        // Same image (or mobile not set) — preload unconditionally.
        $this->output_link($desktop_url, '', $preloaded);
      } elseif ( ! empty($mobile_url) ) {
        // Only mobile image set (edge case) — scope to mobile viewport.
        $this->output_link($mobile_url, "(max-width: {$bp}px)", $preloaded);
      }
    }

    // Recurse into inner blocks.
    if ( ! empty($block['innerBlocks']) ) {
      foreach ($block['innerBlocks'] as $inner_block) {
        $this->collect_preloads($inner_block, $preloaded);
      }
    }
  }

  /**
   * Outputs a single preload link tag, deduplicating by URL + media pair.
   */
  private function output_link(string $url, string $media, array &$preloaded): void {
    // esc_url() returns '' for disallowed protocols (data:, javascript:) and
    // values left empty after protocol stripping. Never emit a preload tag
    // without a real href — an empty <link rel="preload" href=""> is invalid
    // markup and is flagged by Google Search Console.
    $safe_url = esc_url($url);
    if ( empty($safe_url) ) {
      return;
    }

    $key = $safe_url . '|' . $media;
    if ( isset($preloaded[$key]) ) {
      return;
    }
    $preloaded[$key] = true;

    $media_attr = ! empty($media) ? ' media="' . esc_attr($media) . '"' : '';
    echo '<link rel="preload" fetchpriority="high" as="image" href="' . $safe_url . '"' . $media_attr . '>';
  }

  /**
   * Checks whether a device slot defines its own image (url or id that
   * differs from an empty/inherited state). Slots that only override
   * display properties (focal point, size, opacity) without setting a
   * distinct image source should not be treated as a separate preload.
   */
  private function has_own_image(array|string $device_data): bool {
    if ( empty($device_data) || ! is_array($device_data) ) {
      return false;
    }

    $url = $device_data['url'] ?? '';
    $id = $device_data['id'] ?? null;

    return ! empty($url) || ( ! empty($id) && is_numeric($id) && (int) $id > 0 );
  }

  /**
   * Resolves a per-device bgImage entry to a preloadable URL.
   *
   * Returns empty string when:
   * - The device slot is empty (inherits from desktop).
   * - The URL is a dynamic data template (not a real image URL).
   * - No URL or attachment ID is available.
   */
  private function resolve_device_url(array|string $device_data): string {
    // Empty device slot — tablet/mobile can be {} or [] (WP serialization).
    if ( empty($device_data) || ! is_array($device_data) ) {
      return '';
    }

    $url = $device_data['url'] ?? '';

    // Skip dynamic data templates — they resolve at render time, not preloadable.
    if ( ! empty($url) && str_contains($url, '{{!!nb_dynamic/') ) {
      return '';
    }

    if ( ! empty($url) ) {
      return $url;
    }

    // Fallback: resolve from attachment ID if present.
    $id = $device_data['id'] ?? null;
    if ( ! empty($id) && is_numeric($id) && (int) $id > 0 ) {
      $attachment_url = wp_get_attachment_url((int) $id);
      return $attachment_url ? $attachment_url : '';
    }

    return '';
  }
}
