<?php

namespace Nectar\Render;

use Nectar\Dynamic_Data\Frontend_Render;

/**
 * Third Party Compatibility
 * @version 0.0.1
 * @since 0.1.1
 */
class Compatibility {
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

    // General classic themes which trigger a classic stylesheet load. ----------------------
    // https://github.com/WordPress/gutenberg/blob/b5a31264e7c614534eb021ca272e9dca30676b70/lib/client-assets.php#L357
    // check if the stylesheet "wp-editor-classic-layout-styles" is enqueued.
    if ( wp_style_is('wp-editor-classic-layout-styles', 'enqueued') ) {
      $css .= '
      html .editor-styles-wrapper .wp-block {
        margin: 0;
      }
      html .editor-styles-wrapper .wp-block {
        max-width: none;
      }
      .editor-styles-wrapper .block-editor-block-list__layout.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)),
      .edit-post-visual-editor__post-title-wrapper {
        max-width: var(--theme-block-max-width, 1300px);
        padding-right: var(--wp--style--root--padding-right, 50px);
        padding-left: var(--wp--style--root--padding-right, 50px);
        margin-left: auto !important;
        margin-right: auto !important;
      }
      ';
    }

    // Set the contained content width for third party themes --------------------------------

    // Blocksy.
    if ( class_exists('Blocksy_Manager') ) {
      $css .= '
      body .nectar-blocks-row__inner.is-contained-content {
        padding-left: 0;
        padding-right: 0;
        width: calc(100% - 40px);
      }';
    }

    // Default WP themes.
    if ( function_exists('twentytwentyfour_block_styles') ) {
          $css .= '
          body .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--style--root--padding-right) * 2));
            width: 100%;
            padding-right: var(--wp--style--root--padding-right);
            padding-left: var(--wp--style--root--padding-left);
          }';
    }
    if ( function_exists('twentytwentytwo_styles') ) {
          $css .= '
          body .nectar-blocks-row__inner.is-contained-content {
            max-width: calc(var(--wp--style--global--content-size) + (var(--wp--custom--spacing--outer) * 2));
            width: 100%;
            padding-right: var(--wp--custom--spacing--outer);
            padding-left: var(--wp--custom--spacing--outer);
          }';
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

    // GeneratePress.

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