<?php

if ( ! class_exists( 'NectarBlocks_Kirki_Compatibility' ) ) {

  class NectarBlocks_Kirki_Compatibility {
    public function __construct() {
      add_filter( 'kirki/config', [ $this, 'config' ], 999 );
      add_filter( 'kirki_settings_page', '__return_false' );
      add_action( 'customize_controls_enqueue_scripts', [ $this, 'stub_typography_data' ], 6 );
      add_action( 'admin_enqueue_scripts', [ $this, 'dequeue_webfont_loader' ], 20 );
      // Dequeue on wp_enqueue_scripts (which fires on wp_head at priority 1), not on
      // wp_head at priority 20: head scripts are printed by wp_print_head_scripts on
      // wp_head at priority 9, so a priority-20 wp_head dequeue would run after
      // webfont-loader was already output and be a no-op on the frontend.
      add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_webfont_loader' ], 20 );
    }

    public function config( $config ) {
      if ( isset( $config['compiler'] ) ) {
        unset( $config['compiler'] );
      }
      return $config;
    }

    // Kirki 5.2.3's bundle reads kirkiTypographyControls even when no typography field registers it.
    public function stub_typography_data() {
      wp_add_inline_script( 'kirki-customizer', 'window.kirkiTypographyControls = window.kirkiTypographyControls || [];', 'before' );
    }

    // The wp.org 5.2.3 zip ships no webfontloader.js, so Kirki's enqueue 404s; nothing here consumes it.
    public function dequeue_webfont_loader() {
      wp_dequeue_script( 'webfont-loader' );
    }
  }

  new NectarBlocks_Kirki_Compatibility();
}
