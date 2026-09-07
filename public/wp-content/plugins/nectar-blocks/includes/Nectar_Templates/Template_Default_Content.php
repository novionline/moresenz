<?php

namespace Nectar\Nectar_Templates;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Default Content for Theme Builder Templates.
 *
 * Maps template keys to HTML files in the theme's
 * template-defaults directory. These files provide pre-populated
 * starting content when creating new Theme Builder templates.
 *
 * @since 3.0
 */
class Template_Default_Content {
  private const TEMPLATE_FILES = [
    'nectar_template_wc__single_product' => 'single-product.html',
    'nectar_template_wc__archive_product' => 'archive-product.html',
    'nectar_template_wc__cart' => 'page-cart.html',
    'nectar_template_wc__checkout' => 'page-checkout.html',
    'nectar_template_wc__my_account' => 'page-my-account.html',
    'nectar_template_wc__order_confirmation' => 'order-confirmation.html',
    'nectar_template__404' => '404.html',
    'nectar_template_single__post' => 'single-post.html',
    'nectar_template_archive__post' => 'archive-post.html',
    'nectar_template__ocm' => 'off-canvas-menu.html',
    'nectar_template__header_navigation' => 'header-navigation.html',
    'nectar_hook_global_section_footer' => 'footer.html',
    'nectar_hook_global_section_parallax_footer' => 'footer-parallax.html',
  ];

  /**
   * Get default content for a template key.
   *
   * @param string $template_key Template hook name (e.g. 'nectar_template_wc__checkout').
   * @return string Block markup content or empty string.
   */
  public static function get_default_content( string $template_key ): string {
    $file = self::TEMPLATE_FILES[$template_key] ?? null;
    if ( ! $file ) {
      return '';
    }

    $path = get_stylesheet_directory() . '/nectar/template-defaults/' . $file;
    if ( ! file_exists( $path ) ) {
      return '';
    }

    return file_get_contents( $path );
  }

  /**
   * Check if a template key has default content available.
   *
   * @param string $template_key Template hook name.
   * @return bool
   */
  public static function has_default_content( string $template_key ): bool {
    return isset( self::TEMPLATE_FILES[$template_key] );
  }
}
