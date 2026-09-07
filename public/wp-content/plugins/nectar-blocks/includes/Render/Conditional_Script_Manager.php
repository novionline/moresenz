<?php

namespace Nectar\Render;

/**
 * Handles registering and conditionally enqueueing frontend scripts.
 */
class Conditional_Script_Manager {
  protected Conditional_Scripts $detector;

  /**
   * @var array<string, array{
   *   asset: string,
   *   src: string,
   *   extra_dependencies?: string[],
   *   requirements?: array{
   *     blocks?: string[],
   *     strings?: string[],
   *     filter?: string
   *   }
   * }>
   */
  protected array $definitions;

  public function __construct( Conditional_Scripts $detector, array $definitions = [] ) {
    $this->detector = $detector;
    if ( $definitions && is_array( $definitions ) ) {
      $this->definitions = $definitions;
    } else {
      $this->definitions = $this->get_default_definitions();
    }
  }

  /**
   * Registers each conditional script and enqueues it if required.
   */
  public function register_scripts(): void {
    foreach ( $this->definitions as $handle => $definition ) {
      $this->register_single_script( $handle, $definition );
    }
  }

  public function get_requirements_for_handle( string $handle ): array {
    if ( isset( $this->definitions[$handle]['requirements'] ) ) {
      return $this->definitions[$handle]['requirements'];
    }
    return [];
  }

  protected function register_single_script( string $handle, array $definition ): void {
    $asset_path = $definition['asset'] ?? '';
    $script_src = $definition['src'] ?? '';

    if ( ! $asset_path || ! $script_src ) {
      return;
    }

    if ( ! file_exists( $asset_path ) ) {
      return;
    }

    $asset_args = include $asset_path;
    $dependencies = isset( $asset_args['dependencies'] ) ? $asset_args['dependencies'] : [];
    $extra_dependencies = isset( $definition['extra_dependencies'] ) && is_array( $definition['extra_dependencies'] ) ? $definition['extra_dependencies'] : [];

    $dependencies = array_values(
        array_unique(
            array_filter(
                array_merge( $dependencies, $extra_dependencies )
            )
        )
    );

    $version = isset( $asset_args['version'] ) ? $asset_args['version'] : false;

    wp_register_script(
        $handle,
        $script_src,
        $dependencies,
        $version,
        true
    );

    $requirements = isset( $definition['requirements'] ) ? $definition['requirements'] : [];

    if ( $this->detector->should_enqueue( $requirements ) ) {
      wp_enqueue_script( $handle );
    }
  }

  protected function get_default_definitions(): array {
    $definitions = [
      'nectar-blocks-lightbox' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-lightbox.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-lightbox.js',
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_lightbox',
          'blocks' => [
            'image-grid',
            'video-lightbox'
          ],
          'strings' => [
            'nectar-blocks-lightbox-group',
            'nectar__link--lightbox',
            'nectar-blocks-video-lightbox'
          ],
          'attributes' => [
            [
              'block' => 'image-gallery',
              'attributes' => [
                'imageClick' => 'lightbox'
              ]
            ]
          ]
        ]
      ],
      'nectar-blocks-mouse-attract' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-mouse-attract.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-mouse-attract.js',
        'extra_dependencies' => [ 'nectar-blocks-frontend' ],
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_mouse_attract',
          'strings' => [
            'data-nectar-mouse-attract'
          ],
          'attributes' => [
            [
              'block' => 'video-lightbox',
              'attributes' => [
                'mouseAttract' => [
                  'enabled' => true
                ]
              ]
            ]
          ]
        ]
      ],
      'nectar-blocks-text-animations' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-text-animations.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-text-animations.js',
        'extra_dependencies' => [ 'nectar-blocks-frontend' ],
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_text_animations',
          'strings' => [
            'text-animation--'
          ]
        ]
      ],
      'nectar-blocks-fit-text' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-fit-text.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-fit-text.js',
        'extra_dependencies' => [ 'nectar-blocks-frontend' ],
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_fit_text',
          'strings' => [
            'has-fit-text'
          ],
          'attributes' => [
            [
              'block' => 'text',
              'attributes' => [
                'isFit' => true
              ]
            ]
          ]
        ]
      ],
      'nectar-blocks-header-variant-observer' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-header-variant-observer.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-header-variant-observer.js',
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_header_variant_observer',
          'strings' => [
            'data-header-variant='
          ]
        ]
      ],
      'nectar-blocks-button-wave' => [
        'asset' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-button-wave.asset.php',
        'src' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-button-wave.js',
        'extra_dependencies' => [ 'split-type' ],
        'requirements' => [
          'filter' => 'nectar_blocks/conditional_scripts/needs_button_wave',
          'strings' => [
            'nectar-blocks-button--Wave'
          ]
        ]
      ]
    ];

    /**
     * Filter the default conditional script definitions.
     *
     * @param array $definitions The conditional script definitions.
     */
    return apply_filters( 'nectar_blocks/conditional_scripts/definitions', $definitions );
  }
}

