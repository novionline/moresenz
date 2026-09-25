<?php

namespace Nectar\Render;

use Nectar\Dynamic_Data\Frontend_Render;

/**
 * Third Party Compatibility
 * @version 0.0.1
 * @since 0.1.1
 */
class Compatibility {
  /**
   * Scope for rules that must apply in the real editor canvas (both
   * edit-post and edit-site) but NOT in BlockPreview iframes (template
   * library, block-inserter previews, etc.). WordPress stamps
   * `block-editor-block-preview__content-iframe` onto the iframe `<html>`
   * only inside BlockPreview — excluding it pins us to the real canvas
   * while letting these rules through in both post and site editors.
   */
  private const EDITOR_SCOPE = 'html:not(.block-editor-block-preview__content-iframe) .editor-styles-wrapper';

  function __construct() {
    $this->initialize_hooks();
  }

  private function initialize_hooks() {
    add_action( 'wp_enqueue_scripts', [$this, 'enqueue_css'], 100 );
    add_action( 'enqueue_block_assets', [$this, 'enqueue_editor_css'], 100 );

    add_action( 'wp', [$this, 'compatibility_filters'] );

    // Polylang: Prevent CSS meta from syncing across translations
    // High priority ensures this runs after all other filters have added metas
    add_filter( 'pll_copy_post_metas', [$this, 'polylang_exclude_css_meta'], 90, 2 );
  }

  function enqueue_css() {
    $css = $this->get_third_party_frontend_css();
    // Append dynamic CSS coming from third-party plugin posts
    $css .= $this->get_third_party_dynamic_css();
    if ( $css ) {
      wp_add_inline_style( 'nectar-frontend-global', $css);
    }
  }

  function enqueue_editor_css() {
    $css = $this->get_third_party_editor_css();
    if ( $css ) {
      wp_add_inline_style( 'nectar-editor-global', $css);
    }
  }

  function get_third_party_editor_css() {
    $css = '';
    $editor = self::EDITOR_SCOPE;

    // General classic themes which trigger a classic stylesheet load. ----------------------
    // https://github.com/WordPress/gutenberg/blob/b5a31264e7c614534eb021ca272e9dca30676b70/lib/client-assets.php#L357
    // check if the stylesheet "wp-editor-classic-layout-styles" is enqueued.
    // Skip when the theme already provides layout settings via theme.json (e.g. Kadence)
    // because WordPress + the theme handle the editor layout correctly on their own.
    $has_theme_layout = false;
    if ( function_exists('wp_get_global_settings') ) {
      $content_size = wp_get_global_settings( ['layout', 'contentSize'] );
      $has_theme_layout = ! empty($content_size);
    }

    // The classic layout wraps each root block in a .wp-block div with data-align.
    // It needs position:relative so our block outline pseudo-element is contained.
    if ( wp_style_is('wp-editor-classic-layout-styles', 'enqueued') ) {
      $css .= "
      {$editor} .wp-block[data-align]:not(.block-editor-block-list__block) {
        position: relative;
      }";
    }

    if ( wp_style_is('wp-editor-classic-layout-styles', 'enqueued') && ! $has_theme_layout ) {
      $resolved_width = $this->get_resolved_content_width();
      $css .= "
      {$editor} .wp-block {
        margin: 0;
        max-width: none;
      }
      {$editor} .block-editor-block-list__layout.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)),
      .edit-post-visual-editor__post-title-wrapper {
        max-width: " . esc_attr($resolved_width) . ";
        padding-right: var(--wp--style--root--padding-right, 50px);
        padding-left: var(--wp--style--root--padding-left, 50px);
        margin-left: auto !important;
        margin-right: auto !important;
      }
      ";
    }

    // Set the contained content width for third party themes --------------------------------

    // Blocksy.
    if ( class_exists('Blocksy_Manager') ) {
      // Blocksy uses negative margins to pull rows flush;
      // the global overflow-x: clip prevents this.
      $css .= "
      {$editor} {
        overflow-x: visible !important;
      }
      {$editor} .nectar-blocks-row__inner.is-contained-content {
        padding-left: 0;
        padding-right: 0;
        width: calc(100% - 40px);
      }
      {$editor} .block-editor-block-list__layout .wp-block.wp-block-nectar-blocks-row .nectar-blocks-row__inner.is-contained-content:before {
        left: 0;
        right: 0;
      }";
    }

    // Kadence.
    if ( defined('KADENCE_VERSION') ) {
      // Edit-post's classic.css centers `.wp-block` children of the root
      // container with `margin: 0 auto`, which combined with Kadence's
      // theme.json contentSize cap on `.wp-block` compresses contained rows
      // to a narrow centered column. Free contained rows so the row's own
      // __wrapper / __inner chain governs width. Exclude alignfull/wide —
      // those need to keep classic.css's `margin: 0 -8px` so they can
      // cancel the root container's side padding and extend edge-to-edge.
      $css .= "
      {$editor} .wp-block.wp-block-nectar-blocks-row:not([data-align=\"full\"]):not([data-align=\"wide\"]) {
        margin-left: 0;
        margin-right: 0;
        max-width: none;
      }
      {$editor} .nectar-blocks-row__inner.is-contained-content {
        max-width: var(--global-content-width, var(--wp--style--global--content-size, 1200px));
        width: 100%;
        padding-left: var(--global-content-edge-padding, 1.5rem);
        padding-right: var(--global-content-edge-padding, 1.5rem);
      }
      .nectar-blocks-flex-box .nectar-blocks-text {
        margin-top: 0;
      }";
    }

    // GeneratePress.
    if ( defined('GENERATE_VERSION') ) {
      $gp_width = intval( get_theme_mod('container_width', 1200) );
      $css .= "
      {$editor} .nectar-blocks-row__inner.is-contained-content {
        max-width: " . esc_attr($gp_width + 40) . "px;
        width: 100%;
        padding-left: 20px;
        padding-right: 20px;
      }";
    }

    // Default WP themes.
    if ( function_exists('twentytwentyfour_block_styles') ) {
          $css .= "
          {$editor} .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--style--root--padding-right) * 2));
            width: 100%;
            padding-right: var(--wp--style--root--padding-right);
            padding-left: var(--wp--style--root--padding-left);
          }";
    }
    if ( function_exists('twentytwentytwo_styles') ) {
          $css .= "
          {$editor} .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--custom--spacing--outer) * 2));
            width: 100%;
            padding-right: var(--wp--custom--spacing--outer);
            padding-left: var(--wp--custom--spacing--outer);
          }";
    }

    // Enfold Theme.
    // Enfold sets global styling targeting the colorpicker class, and will completely hide our color picker.
    if ( defined('AV_FRAMEWORK_VERSION') ) {
      $css .= '
      .nectar-component__color-picker .colorpicker,
      .nectar-component__gradient-color-picker .colorpicker {
        display: block;
        position: relative;
        width: auto;
        height: auto;
        background: transparent;
        overflow: auto;
      }
      .nectar-component__color-picker .colorpicker input,
      .nectar-component__gradient-color-picker .colorpicker input {
        position: relative;
        height: auto;
        right: auto;
        top: auto;
        text-align: left;
        width: auto;
      }';
    }

    return $css;
  }

  function wp_forms_frontend_css() {
    $css = '';
    // Wpforms doesn't set a width, and will collapse when used inside of a flex column.
    if (function_exists( 'wpforms' )) {
      $css = 'div.wpforms-container-full:not(:empty) {
        width: 100%;
      }';
    }

    return $css;
  }

  function get_pinned_section_css() {
    $css = ' .pin-spacer:has( > .alignfull) {
        max-width: none!important;
      }
      .pin-spacer > .alignfull {
          margin-right: 0!important;
          margin-left:-50vw !important;
          left: 50%!important;
          max-width: 100vw!important;
          width: 100vw!important;
      }';

    return $css;
  }

  /**
   * Resolve the content width for the current theme.
   * @since 3.0.0
   * @return string CSS value with unit, e.g. "1200px"
   */
  private function get_resolved_content_width(): string {
    if ( function_exists('wp_get_global_settings') ) {
      $content_size = wp_get_global_settings( ['layout', 'contentSize'] );
      if ( ! empty($content_size) && is_string($content_size) ) {
        return $content_size;
      }
    }

    if ( ! empty($GLOBALS['content_width']) ) {
      return intval($GLOBALS['content_width']) . 'px';
    }

    return '1200px';
  }

  function get_third_party_frontend_css() {

    $css = '';

    // general compatibility when Nectarblocks theme is not active.
    if ( ! defined('NB_THEME_VERSION') ) {
      // full width alignment.
      $css .= '
        .nectar-blocks-row__wrapper.alignfull {
          width: auto;
        }';
    }

    // For classic themes without theme.json, set the content-size CSS variable
    // so the row base style (.is-contained-content) gets the correct width.
    if ( function_exists('wp_theme_has_theme_json') && ! wp_theme_has_theme_json() && ! defined('NB_THEME_VERSION') ) {
      $resolved_width = $this->get_resolved_content_width();
      $css .= '
      html body {
        --wp--style--global--content-size: ' . esc_attr($resolved_width) . ';
      }';
    }

    // Set the contained content width for third party themes --------------------------------

    // Salient.
    if ( defined('NECTAR_THEME_NAME') && NECTAR_THEME_NAME === 'salient' ) {
      $css .= '
      html body {
        overflow-y: visible;
        overflow-x: clip;
      }';
    }

    // Blocksy.
    if ( class_exists('Blocksy_Manager') ) {
      $css .= '
      body .nectar-blocks-row__inner.is-contained-content {
         max-width: var(--theme-normal-container-max-width);
         width: var(--theme-container-width);
         padding-left: 0;
         padding-right: 0;
      }
      article>.entry-content .nectar-blocks-row__inner.is-contained-content {
        max-width: var(--theme-default-editor, var(--theme-block-max-width));
       }

      .entry-content > *.alignfull {
        margin-bottom: 0;
      }';
      $css .= $this->get_pinned_section_css();
    }

    // Astra.
    if ( defined('ASTRA_THEME_VERSION') ) {

      $css .= '
      body .nectar-blocks-row__inner.is-contained-content {
         max-width: calc(var(--wp--custom--ast-content-width-size) + 40px);
         width: 100%;
         padding-left: 20px;
         padding-right: 20px;
      }';
      $css .= $this->get_pinned_section_css();
    }

    // Kadence.
    if ( defined('KADENCE_VERSION') ) {
      $css .= '
      body .nectar-blocks-row__inner.is-contained-content {
        max-width: var(--global-content-width, var(--wp--style--global--content-size, 1200px));
        width: 100%;
        padding-left: var(--global-content-edge-padding, 1.5rem);
        padding-right: var(--global-content-edge-padding, 1.5rem);
      }
      .nectar-blocks-flex-box .nectar-blocks-text {
        margin-top: 0;
      }';
      $css .= $this->get_pinned_section_css();
    }

    // GeneratePress.
    if ( defined('GENERATE_VERSION') ) {
      $gp_width = intval( get_theme_mod('container_width', 1200) );
      $css .= '
      body .nectar-blocks-row__inner.is-contained-content {
        max-width: ' . esc_attr($gp_width + 40) . 'px;
        width: 100%;
        padding-left: 20px;
        padding-right: 20px;
      }';
      $css .= $this->get_pinned_section_css();
    }

    // Default WP themes.
    if ( function_exists('twentytwentyfour_block_styles') ) {
          $css .= '
          body .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--style--root--padding-right) * 2));
            width: 100%;
            padding-right: var(--wp--style--root--padding-right);
            padding-left: var(--wp--style--root--padding-left);
          }
          .pin-spacer:has( > .alignfull) {
              max-width: none!important;
              margin-left: calc(var(--wp--style--root--padding-left)* -1)!important;
           }
          .pin-spacer > .alignfull {
              margin-right: 0!important;
              margin-left:-50vw !important;
              left: 50%!important;
              max-width: 100vw!important;
              width: 100vw!important;
          }';
    }
    if (  function_exists('twentytwentytwo_styles') ) {
      $css .= '
          body .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--custom--spacing--outer) * 2));
            width: 100%;
            padding-right: var(--wp--custom--spacing--outer);
            padding-left: var(--wp--custom--spacing--outer);
          }';
    }

    $css .= $this->wp_forms_frontend_css();

    return $css;
  }

  /**
   * Aggregate dynamic CSS stored on third-party plugin content (e.g., popups)
   * @since 2.4.0
   */
  function get_third_party_dynamic_css() {

    $css = '';

    // Popup Maker
    $css .= $this->get_popup_maker_css();

    return $css;
  }

  /**
   * Collect dynamic CSS for Popup Maker popups present on the page
   * @since 2.4.0
   */
  function get_popup_maker_css() {
    $css = '';
    if ( function_exists('pum_get_all_popups') && function_exists('pum_is_popup_loadable') && ! is_admin() ) {
      $popups = pum_get_all_popups();
      if ( ! empty($popups) ) {
        foreach ( $popups as $popup ) {
          if ( isset($popup->ID) && pum_is_popup_loadable( $popup->ID ) ) {
            $css_for_popup = $this->render_css_for_post_id( $popup->ID );
            if ( $css_for_popup ) {
              $css .= $css_for_popup;
            }
          }
        }
      }
    }
    return $css;
  }

  /**
   * Resolve and render dynamic CSS saved on a given post ID
   * Mirrors the logic used in Render::get_dynamic_block_css without re-instantiating Render
   * @since 2.4.0
   */
  private function render_css_for_post_id($id, bool $is_preview = false) {
    if ( $is_preview ) {
      $css = get_post_meta( $id, '_nectar_blocks_css_preview', true );
    } else {
      $css = get_post_meta( $id, '_nectar_blocks_css', true );
    }
    if ( ! $css ) {
      return '';
    }

    $FE_RENDER = new Frontend_Render();
    return $FE_RENDER->render_dynamic_content([], $css);
  }

  function compatibility_filters() {
    // Slim SEO.
    if ( defined('SLIM_SEO_VER') ) {
      add_filter( 'slim_seo_allowed_blocks', function( $blocks ) {
        return array_filter( $blocks, function( $block ) {
            return ! str_starts_with( $block, 'nectar' );
        } );
      } );
    }
  }

  /**
   * Polylang: Exclude CSS meta keys from being synchronized across translations.
   * Each translation should maintain its own unique block CSS.
   *
   * @since 2.5.4
   * @param array $metas List of meta keys to copy.
   * @param bool  $sync  Whether synchronizing or copying.
   * @return array Filtered list of meta keys.
   */
  function polylang_exclude_css_meta( $metas, $sync ) {
    $exclude_keys = [
      '_nectar_blocks_css',
      '_nectar_blocks_css_preview',
    ];

    return array_diff( $metas, $exclude_keys );
  }
}