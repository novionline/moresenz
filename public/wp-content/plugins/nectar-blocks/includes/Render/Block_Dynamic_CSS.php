<?php

namespace Nectar\Render;

/**
 * Server-side dynamic CSS generation for Nectar blocks.
 *
 * Normally, dynamic block CSS is generated client-side in the editor's save hook
 * (processRawToCSS in save-hook/utils.ts) and persisted to _nectar_blocks_css
 * post meta. However, theme-file template parts have no DB post and therefore
 * no post meta. This class fills that gap by parsing block attributes directly
 * from the template content and producing the equivalent layout CSS in PHP.
 *
 * To add support for a new block, add an entry to BLOCK_GENERATORS and a
 * corresponding private method that accepts the block's attribute array and
 * returns a CSS string.
 *
 * @since 3.0
 */
class Block_Dynamic_CSS {
  private const DEVICES = [ 'desktop', 'tablet', 'mobile' ];

  private const MEDIA_QUERIES = [
    'desktop' => '@media all',
    'tablet' => '@media (max-width: 1024px)',
    'mobile' => '@media (max-width: 767px)',
  ];

  private const FLEX_MAP = [
    'beginning' => 'flex-start',
    'center' => 'center',
    'end' => 'flex-end',
    'stretch' => 'stretch',
    'spacebetween' => 'space-between',
  ];

  private const VALID_DISPLAY_TYPES = [ 'flex', 'block', 'grid', 'inline', 'inline-flex', 'inline-block', 'hidden' ];

  private const VALID_DIRECTIONS = [ 'row', 'column' ];

  private const VALID_WRAPS = [ 'wrap', 'nowrap', 'wrap-reverse' ];

  private const VALID_UNITS = [ 'px', 'em', 'rem', 'vw', 'vh', '%', 'ch' ];

  /**
   * Block name → generator method mapping.
   * Add entries here as more blocks need server-side CSS support.
   */
  private const BLOCK_GENERATORS = [
    'nectar-blocks/row' => 'row_css',
    'nectar-blocks/column' => 'column_css',
  ];

  /**
   * Generates CSS for a template part, with transient caching.
   *
   * @param string $slug    Template part slug.
   * @param string $content Raw block content from the template part.
   * @return string Generated CSS string.
   */
  public function generate( string $slug, string $content ): string {
    $theme_dir = get_stylesheet_directory();
    $part_file = $theme_dir . '/parts/' . $slug . '.html';
    $file_mtime = file_exists( $part_file ) ? (string) filemtime( $part_file ) : '0';
    $cache_key = 'nb_fse_css_' . md5( $slug . $file_mtime );

    $cached = get_transient( $cache_key );
    if ( false !== $cached ) {
      return $cached;
    }

    $blocks = parse_blocks( $content );
    $css = '';
    $this->collect_block_css( $blocks, $css );

    set_transient( $cache_key, $css, DAY_IN_SECONDS );
    return $css;
  }

  /**
   * Recursively walks parsed blocks and generates CSS for supported Nectar blocks.
   */
  private function collect_block_css( array $blocks, string &$css ): void {
    foreach ( $blocks as $block ) {
      $name = $block['blockName'] ?? '';
      $attrs = $block['attrs'] ?? [];

      if ( isset( self::BLOCK_GENERATORS[$name] ) && ! empty( $attrs['blockId'] ) ) {
        $method = self::BLOCK_GENERATORS[$name];
        $css .= $this->$method( $attrs );
      }

      if ( ! empty( $block['innerBlocks'] ) ) {
        $this->collect_block_css( $block['innerBlocks'], $css );
      }
    }
  }

  // ------------------------------------------------------------------
  //  Block-specific generators
  // ------------------------------------------------------------------

  /**
   * Row: column gap, display/flex per device.
   */
  private function row_css( array $attrs ): string {
    $block_id = self::sanitize_block_id( $attrs['blockId'] );
    // Targets the row's flex container at every markup level:
    //   - new L1 saves: root is the flex container, identified by .nectar-l1
    //   - new L2/L3 saves: __inner.scope-X
    //   - deprecated saves (legacy markup): __inner.parent-block-X
    $selector =
        "#{$block_id}.nectar-l1, " .
        ".nectar-blocks-row__inner.scope-{$block_id}, " .
        ".nectar-blocks-row__inner.parent-block-{$block_id}";
    $css = '';

    // Column gap per device.
    foreach ( self::DEVICES as $device ) {
      $settings = $attrs['rowSettings'][$device] ?? [];
      if ( ! self::is_assoc( $settings ) ) {
        continue;
      }

      $gap = $settings['columnGap'] ?? null;
      if ( $gap && isset( $gap['value'] ) ) {
        $css .= self::media_query(
            $device,
            $selector,
            'gap: ' . self::sizing_to_string( $gap ) . ';'
        );
      }
    }

    // Display / flex per device.
    $css .= $this->display_css_for_devices( $attrs, $selector );

    return $css;
  }

  /**
   * Column: width (flex), child content gap, display/flex per device.
   */
  private function column_css( array $attrs ): string {
    $block_id = self::sanitize_block_id( $attrs['blockId'] );
    $id_selector = "#{$block_id}";
    // Targets the column's flex container at every markup level:
    //   - new L1 saves: root is the flex container, identified by .nectar-l1
    //   - new L2/L3 saves: __content-wrap.scope-X (same element as __inner at saved frontend)
    //   - deprecated saves (legacy markup): __content-wrap.parent-block-X
    $wrap_selector =
        "#{$block_id}.nectar-l1, " .
        ".nectar-blocks-column__content-wrap.scope-{$block_id}, " .
        ".nectar-blocks-column__content-wrap.parent-block-{$block_id}";
    $css = '';

    foreach ( self::DEVICES as $device ) {
      $settings = $attrs['columnSettings'][$device] ?? [];
      if ( ! self::is_assoc( $settings ) ) {
        continue;
      }

      // Column width.
      $width = $settings['width'] ?? null;
      if ( $width && self::is_safe_css_value( $width ) ) {
        $css .= self::media_query( $device, $id_selector, "flex: {$width};" );
      }

      // Block gap (child content gap).
      $block_gap = $settings['blockGap'] ?? null;
      if ( $block_gap && isset( $block_gap['value'] ) ) {
        $css .= self::media_query(
            $device,
            $wrap_selector,
            'gap: ' . self::sizing_to_string( $block_gap ) . ';'
        );
      }
    }

    // Display / flex per device.
    $css .= $this->display_css_for_devices( $attrs, $wrap_selector );

    return $css;
  }

  // ------------------------------------------------------------------
  //  Shared CSS helpers
  // ------------------------------------------------------------------

  /**
   * Generates display/flex declarations for each device where explicitly set.
   *
   * Mirrors display-control/dynamic-styles.ts → displayControlCSS.
   */
  private function display_css_for_devices( array $attrs, string $selector ): string {
    $css = '';

    foreach ( self::DEVICES as $device ) {
      $display = $attrs['display'][$device] ?? [];
      if ( ! self::is_assoc( $display ) ) {
        continue;
      }

      $decl = self::display_declarations( $display );
      if ( $decl ) {
        $css .= self::media_query( $device, $selector, $decl );
      }
    }

    return $css;
  }

  /**
   * Converts a single device's display settings into CSS declarations.
   */
  private static function display_declarations( array $display ): string {
    $type = $display['displayType'] ?? 'auto';
    if ( 'auto' === $type || ! in_array( $type, self::VALID_DISPLAY_TYPES, true ) ) {
      return '';
    }

    $css = '';

    if ( 'hidden' === $type ) {
      $css .= 'display: none;';
    } else {
      $css .= "display: {$type};";
    }

    if ( 'flex' === $type || 'hidden' === $type ) {
      $dir = $display['flexDirection'] ?? [];
      if ( ! empty( $dir['direction'] ) && 'auto' !== $dir['direction']
        && in_array( $dir['direction'], self::VALID_DIRECTIONS, true ) ) {
        $direction = $dir['direction'];
        if ( ! empty( $dir['isReversed'] ) ) {
          $direction .= '-reverse';
        }
        $css .= "flex-direction: {$direction};";
      }

      $wrap = $display['flexWrap'] ?? 'auto';
      if ( 'auto' !== $wrap && in_array( $wrap, self::VALID_WRAPS, true ) ) {
        $css .= "flex-wrap: {$wrap};";
      }

      $align = $display['flexAlign'] ?? 'auto';
      if ( 'auto' !== $align && isset( self::FLEX_MAP[$align] ) ) {
        $css .= 'align-items: ' . self::FLEX_MAP[$align] . ';';
      }

      $justify = $display['justifyContent'] ?? 'auto';
      if ( 'auto' !== $justify && isset( self::FLEX_MAP[$justify] ) ) {
        $css .= 'justify-content: ' . self::FLEX_MAP[$justify] . ';';
      }
    }

    return $css;
  }

  /**
   * Wraps CSS declarations in a media query for the given device type.
   */
  private static function media_query( string $device, string $selector, string $declarations ): string {
    $mq = self::MEDIA_QUERIES[$device] ?? '@media all';
    return "{$mq} { {$selector} { {$declarations} } } ";
  }

  /**
   * Converts a { value, unit } sizing field to a CSS string (e.g. "3rem").
   * Mirrors shared/utils sizingFieldToString with the % → vw replacement.
   */
  private static function sizing_to_string( array $field ): string {
    $value = $field['value'];
    if ( ! is_numeric( $value ) ) {
      return '0px';
    }
    $unit = $field['unit'] ?? 'px';
    if ( '%' === $unit ) {
      $unit = 'vw';
    }
    if ( ! in_array( $unit, self::VALID_UNITS, true ) ) {
      $unit = 'px';
    }
    return "{$value}{$unit}";
  }

  /**
   * Sanitizes a block ID to only allow alphanumeric characters and hyphens.
   */
  private static function sanitize_block_id( string $id ): string {
    return preg_replace( '/[^a-zA-Z0-9\-]/', '', $id );
  }

  /**
   * Checks if a string is a safe CSS value (no special characters that could break out of declarations).
   */
  private static function is_safe_css_value( string $value ): bool {
    return 1 === preg_match( '/^[a-zA-Z0-9\s\.\-%\/]+$/', $value );
  }

  /**
   * Checks if an array is a non-empty associative array (JSON object, not []).
   */
  private static function is_assoc( $value ): bool {
    return is_array( $value ) && ! empty( $value ) && ! isset( $value[0] );
  }
}
