<?php

if ( ! class_exists( 'NectarBlocks_Kirki_Compatibility' ) ) {

  class NectarBlocks_Kirki_Compatibility {
    public function __construct() {
      add_filter( 'kirki/config', [ $this, 'config' ], 999 );
      add_filter( 'kirki_settings_page', '__return_false' );
    }

    public function config( $config ) {
      if ( isset( $config['compiler'] ) ) {
        unset( $config['compiler'] );
      }
      return $config;
    }
  }

  new NectarBlocks_Kirki_Compatibility();
}
