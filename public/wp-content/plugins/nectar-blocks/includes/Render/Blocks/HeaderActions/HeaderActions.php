<?php

namespace Nectar\Render\Blocks\HeaderActions;

/**
 * Header Actions (Theme-dependent)
 *
 * Wraps header action items in the theme's expected `<ul class="buttons">`.
 */
class HeaderActions {
  private $content;

  private $attrs;

  public function __construct( $block_attributes, $content ) {
    $this->attrs = is_array( $block_attributes ) ? $block_attributes : [];
    $this->content = is_string( $content ) ? $content : '';
  }

  private function is_nectar_blocks_theme_active(): bool {
    if ( defined( 'NECTAR_BLOCKS_FORCE_THEME_ACTIVE' ) && true === NECTAR_BLOCKS_FORCE_THEME_ACTIVE ) {
      return true;
    }
    return class_exists( '\NectarThemeManager' );
  }

  public function render(): string {
    if ( ! $this->is_nectar_blocks_theme_active() ) {
      return '';
    }

    $inner = trim( $this->content );
    if ( '' === $inner ) {
      return '';
    }

    $block_id = isset( $this->attrs['blockId'] ) ? (string) $this->attrs['blockId'] : '';
    $id_attr = '';
    $data_attr = '';
    if ( '' !== $block_id ) {
      $safe_id = esc_attr( $block_id );
      $id_attr = ' id="' . $safe_id . '"';
      $data_attr = ' data-render-block-id="' . $safe_id . '"';
    }

    // Modern, plugin-owned markup. (No legacy theme `buttons` class dependency.)
    return '<ul class="nectar-header-actions wp-block-nectar-blocks-header-actions"' . $id_attr . $data_attr . '>' . $inner . '</ul>';
  }
}

