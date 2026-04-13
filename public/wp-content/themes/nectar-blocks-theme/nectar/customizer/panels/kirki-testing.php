<?php

function kirki_testing() {
  Kirki::add_section( 'general_settings_style_section', [
    'priority' => 2,
    'title' => esc_html__( 'Style', 'nectar-blocks-theme' ),
  ] );

  Kirki::add_field( 'nectar-blocks-theme', [
    'type' => 'typography',
    'settings' => 'typography_setting',
    'label' => esc_html__( 'Automatic Google Fonts control', 'text-domain' ),
    'section' => 'general_settings_style_section',
    'default' => [
      'font-family' => 'Noto Serif',
      'variant' => '400',
      'font-size' => '14px',
      'line-height' => '1.5',
      'letter-spacing' => '0',
      'color' => '#333333',
      'text-transform' => 'none',
      'text-align' => 'left',
    ],
    'priority' => 10,
    'transport' => 'auto',
    'output' => [
      [
        'element' => 'body',
      ],
      ],
  ] );

}
