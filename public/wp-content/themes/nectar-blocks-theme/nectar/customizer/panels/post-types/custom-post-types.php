<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Post Types - auto-generated per custom post type options.
 *
 * One panel + Single section per public CPT (excluding the post types that have
 * dedicated panels). Currently exposes the single-page post navigation options,
 * mirroring the Blog panel.
 */
class NectarBlocks_Customizer_Post_Types_CPT {
  public static function get_kirki_partials() {

    $partials = [];

    if ( ! function_exists( 'nectar_get_single_option_post_types' ) ) {
      return $partials;
    }

    // Slot CPT panels after Blog (25), WooCommerce (26) and Portfolio (25).
    $priority = 27;

    foreach ( nectar_get_single_option_post_types() as $post_type => $object ) {

      $label = ( isset( $object->labels->name ) && '' !== $object->labels->name )
        ? $object->labels->name
        : ( ! empty( $object->label ) ? $object->label : $post_type );
      $panel_id = 'cpt-' . $post_type . '-panel';
      $prefix = nectar_cpt_option_prefix( $post_type );

      $partials[] = [
        'panel_id' => $panel_id,
        'settings' => [
          'title' => $label,
          'priority' => $priority,
        ],
      ];

      $partials[] = [
        'section_id' => 'cpt-' . $post_type . '-single-section',
        'settings' => [
          'panel' => $panel_id,
          'title' => esc_html__( 'Single', 'nectar-blocks-theme' ),
          'priority' => 1,
        ],
        'controls' => self::get_navigation_controls( $post_type, $prefix, $label ),
      ];

      $priority++;
    }

    return $partials;
  }

  private static function get_navigation_controls( $post_type, $prefix, $label ) {

    $controls = [
      [
        'id' => $prefix . 'next_post_link',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__( 'Post Navigation Links', 'nectar-blocks-theme' ),
        'subtitle' => sprintf(
          /* translators: %s: post type name. */
          esc_html__( 'Adds navigation link(s) at the bottom of every single %s page.', 'nectar-blocks-theme' ),
            $label
        ),
        'desc' => '',
        'default' => '0',
      ],
      [
        'id' => $prefix . 'next_post_link_style',
        'type' => 'select',
        'title' => esc_html__( 'Post Navigation Style', 'nectar-blocks-theme' ),
        'desc' => '',
        'required' => [ [ $prefix . 'next_post_link', '=', '1' ] ],
        'options' => [
          'fullwidth_next_only' => esc_html__( 'Fullwidth Next Link Only', 'nectar-blocks-theme' ),
          'fullwidth_next_prev' => esc_html__( 'Fullwidth Next & Prev Links', 'nectar-blocks-theme' ),
          'contained_next_prev' => esc_html__( 'Contained Next & Prev Links', 'nectar-blocks-theme' ),
          'parallax_next_only' => esc_html__( 'Parallax Contained Next Link Only', 'nectar-blocks-theme' ),
        ],
        'default' => 'fullwidth_next_prev',
      ],
      [
        'id' => $prefix . 'next_post_link_order',
        'type' => 'select',
        'title' => esc_html__( 'Post Navigation Ordering', 'nectar-blocks-theme' ),
        'desc' => '',
        'required' => [ [ $prefix . 'next_post_link', '=', '1' ] ],
        'options' => [
          'default' => esc_html_x( 'Default', 'dropdown option: use the default value', 'nectar-blocks-theme' ),
          'reverse' => esc_html__( 'Reverse Order', 'nectar-blocks-theme' ),
        ],
        'default' => 'default',
      ],
    ];

    // Taxonomies aren't registered yet when the customizer builds panels, so the
    // limit is exposed generically; nectar_single_post_nav_settings() resolves the
    // post type's taxonomy at render time and no-ops it when there is none.
    $controls[] = [
      'id' => $prefix . 'next_post_limit_cat',
      'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__( 'Limit Post Navigation To Same Term', 'nectar-blocks-theme' ),
      'subtitle' => esc_html__( 'Only show next/prev links for posts sharing the current term. Requires the post type to have a taxonomy.', 'nectar-blocks-theme' ),
      'desc' => '',
      'required' => [ [ $prefix . 'next_post_link', '=', '1' ] ],
      'default' => '0',
    ];

    return $controls;
  }
}
