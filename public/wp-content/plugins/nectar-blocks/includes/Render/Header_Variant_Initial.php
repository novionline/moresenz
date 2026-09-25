<?php

namespace Nectar\Render;

/**
 * Server-side first-paint class for header variants.
 *
 * Walks the active page's blocks at render time, finds the first top-level
 * `nectar-blocks/row` with a non-empty `headerVariant` attribute, and
 * contributes `is-variant-<key>` through the theme's generic
 * `nectar_header_nav_classes` filter.
 *
 * Per-request cache keyed by post id avoids re-parsing if the filter fires
 * multiple times in one render. parse_blocks is ms-scale on typical posts;
 * full-page caches absorb the cost on cache miss.
 */
class Header_Variant_Initial {
  /** Mirrors the SSOT slug regex in `Nectar_Templates_Register::header_variant_reserved()`. */
  private const KEY_PATTERN = '/^[a-z][a-z0-9-]{0,63}$/';

  /** @var array<int, string> */
  private array $cache = [];

  public function __construct() {
    add_filter( 'nectar_header_nav_classes', [ $this, 'add_initial_variant_class' ], 10, 2 );
  }

  /**
   * @param string[] $classes  Existing class list.
   * @param int|null $post_id  Post to look up. Falls back to the queried object.
   * @return string[]
   */
  public function add_initial_variant_class( $classes, $post_id = null ): array {
    if ( ! is_array( $classes ) ) {
      $classes = [];
    }
    $resolved_id = is_int( $post_id ) && $post_id > 0 ? $post_id : (int) get_queried_object_id();
    if ( ! $resolved_id ) {
      return $classes;
    }

    if ( ! isset( $this->cache[$resolved_id] ) ) {
      $this->cache[$resolved_id] = $this->compute_for_post( $resolved_id );
    }
    $variant = $this->cache[$resolved_id];
    if ( '' === $variant ) {
      return $classes;
    }
    $classes[] = 'is-variant-' . $variant;
    return $classes;
  }

  private function compute_for_post( int $post_id ): string {
    $content = (string) get_post_field( 'post_content', $post_id );
    // Cheap pre-check: skip parsing when no row carries the attribute.
    if ( false === strpos( $content, '"headerVariant"' ) ) {
      return '';
    }
    return $this->find_first_top_level_variant( $content );
  }

  private function find_first_top_level_variant( string $content ): string {
    $blocks = parse_blocks( $content );
    foreach ( $blocks as $block ) {
      if ( ! is_array( $block ) ) {
        continue;
      }
      $name = isset( $block['blockName'] ) ? $block['blockName'] : null;
      if ( 'nectar-blocks/row' !== $name ) {
        continue;
      }
      $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
      $variant = isset( $attrs['headerVariant'] ) && is_string( $attrs['headerVariant'] ) ? $attrs['headerVariant'] : '';
      if ( '' === $variant ) {
        continue;
      }
      if ( ! preg_match( self::KEY_PATTERN, $variant ) ) {
        continue;
      }
      return $variant;
    }
    return '';
  }
}
