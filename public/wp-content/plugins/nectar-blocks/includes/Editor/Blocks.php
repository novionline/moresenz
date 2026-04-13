<?php

namespace Nectar\Editor;

use Nectar\Global_Settings\{
  Global_Colors,
  Global_Typography,
  Nectar_Blocks_Options,
  Nectar_Plugin_Options
};
use Nectar\Render\Blocks\PostContent\PostContent;
use Nectar\Render\Blocks\PostGrid\PostGrid;
use Nectar\Render\Blocks\TaxonomyGrid\TaxonomyGrid;
use Nectar\Render\Blocks\TaxonomyTerms\TaxonomyTerms;

/**
 * Blocks Editor configuration
 * @version 1.3.0
 * @since 0.0.2
 */
class Blocks {
  static $block_list = [];

  function __construct() {
    $this->initialize();
  }

  function initialize() {
    self::$block_list = [
      'button' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'row' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'column' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'text' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'milestone' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'image' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'icon' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'divider' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'star-rating' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'video-player' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'video-lightbox' => [
        'deps' => ['nectar-blocks-lightgallery'],
        'frontend_style' => true
      ],
      'image-gallery' => [
        'deps' => ['nectar-blocks-swiper'],
        'frontend_style' => true
      ],
      'image-grid' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'scrolling-marquee' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'tabs' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'tab-section' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'icon-list' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'icon-list-item' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'testimonial' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'carousel' => [
        'deps' => ['nectar-blocks-swiper'],
        'frontend_style' => true
      ],
      'carousel-item' => [
        'deps' => ['nectar-blocks-swiper'],
        'frontend_style' => true
      ],
      'accordion' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'accordion-section' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'flex-box' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'post-content' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => false,
        'render_callback' => function($block_attributes, $content) {
          $block = new PostContent($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'shouldRender' => [
            'type' => 'boolean',
            'default' => false
          ]
        ]
      ],
      'post-grid' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new PostGrid($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'isPreview' => [
            'type' => 'boolean',
            'default' => false
          ],
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'postType' => [
            'type' => 'string',
            'default' => 'post'
          ],
          'taxonomies' => [
            'type' => 'array',
            'default' => []
          ],
          'postsPerPage' => [
            'type' => 'number',
            'default' => 12
          ],
          'postOrder' => [
            'type' => 'string',
            'default' => 'DESC'
          ],
          'postOffset' => [
            'type' => 'number',
            'default' => 0
          ],
          'excludeCurrentPost' => [
            'type' => 'boolean',
            'default' => false
          ],
          'orderBy' => [
            'type' => 'string',
            'default' => 'date'
          ],
          'pagination' => [
            'type' => 'object',
            'default' => [
              'enabled' => false,
              'ajax' => false
            ]
          ],
          'postGridStyle' => [
            'type' => 'object',
            'default' => []
          ],
          'responsiveStyle' => [
            'type' => 'object',
            'default' => []
          ],
          'layout' => [
            'type' => 'string',
            'default' => 'grid'
          ],
          'itemLayout' => [
            'type' => 'string',
            'default' => 'content-under'
          ],
          'linkType' => [
            'type' => 'string',
            'default' => 'default'
          ],
          'layoutMasonryVariant' => [
            'type' => 'string',
            'default' => 'default'
          ],
          'contentOverlaidLayout' => [
            'type' => 'object',
            'default' => []
          ],
          'contentUnderLayout' => [
            'type' => 'object',
            'default' => []
          ],
          'contentSideLayout' => [
            'type' => 'object',
            'default' => []
          ],
          'imageRatio' => [
            'type' => 'string',
            'default' => '4:3'
          ],
          'imageSize' => [
            'type' => 'string',
            'default' => 'large'
          ],
          'inheritQuery' => [
            'type' => 'object',
            'default' => [
              'enable' => false,
              'postType' => ''
            ]
          ],
          'dynamicMedia' => [
            'type' => 'object',
            'default' => [
              'enabled' => false,
              'image' => [
                'enabled' => true,
                'source' => ''
              ],
              'video' => [
                'enabled' => false,
                'source' => '',
                'playback' => 'autoplay',
                'visibility' => 'always'
              ]
            ]
          ],
          'animation' => [
            'type' => 'object',
            'default' => []
          ],
          'responsiveGrid' => [
            'type' => 'object',
            // TODO: Might need to fill this in fully, not sure
            'default' => [
            //   'desktop' => [
            //     'columnNumber' => 4,
            //     'gridSpacing' => [
            //       'value' => 10,
            //       'unit' => 'px'
            //     ]
            //   ],
            //   'tablet' => [],
            //   'mobile' => []
            ]
          ],
          'displayMeta' => [
            'type' => 'array',
            'default' => [
              [
                'type' => 'taxonomies',
                'taxonomy' => '',
                'typography' => 'label',
                'display' => 'parent-only',
                'position' => 'top-corner',
                'style' => 'button',
                'link' => true
              ],
              [
                'type' => 'title',
                'headingLevel' => 'h3',
                'typography' => ''
              ],
              [ 'type' => 'excerpt',
                'length' => 30,
                'typography' => 'body'
            ],
            [ 'type' => 'author',
              'link' => 'default',
              'style' => 'with-by-text',
              'typography' => 'label'
            ]
            ]
          ],
          'mediaDisplayMeta' => [
            'type' => 'array',
            'default' => [
              [
                'type' => 'featured-media'
              ]
            ]
          ],
          // Unused but defined attributes from the frontend
          'displaySimple' => [
            'type' => 'object',
            'default' => []
          ],
          'bgColor' => [
            'type' => 'object',
            'default' => []
          ],
          'size' => [
            'type' => 'object',
            'default' => []
          ],
          'position' => [
            'type' => 'object',
            'default' => []
          ],
          'effects' => [
            'type' => 'object',
            'default' => []
          ],
          'transform' => [
            'type' => 'object',
            'default' => []
          ],
          'spacing' => [
            'type' => 'object',
            'default' => []
          ],
          'borderRadius' => [
            'type' => 'object',
            'default' => []
          ]
        ],
      ],
      'taxonomy-terms' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new TaxonomyTerms($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'isPreview' => [
            'type' => 'boolean',
            'default' => false
          ],
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'taxonomy' => [
            'type' => 'string',
            'default' => ''
          ],
          'responsiveSettings' => [
            'type' => 'object',
            'default' => []
          ],
          'enableLink' => [
            'type' => 'boolean',
            'default' => false
          ],
          'linkHoverEffect' => [
            'type' => 'string',
            'default' => 'None'
          ],
          'enableAllLink' => [
            'type' => 'boolean',
            'default' => false
          ],
          'enableDelimiter' => [
            'type' => 'boolean',
            'default' => false
          ],
          'displayType' => [
            'type' => 'string',
            'default' => ''
          ],
          'activeState' => [
            'type' => 'object',
            'default' => []
          ],
          // Unused but defined attributes from the frontend
          'displaySimple' => [
            'type' => 'object',
            'default' => []
          ],
          'bgColor' => [
            'type' => 'object',
            'default' => []
          ],
          'size' => [
            'type' => 'object',
            'default' => []
          ],
          'position' => [
            'type' => 'object',
            'default' => []
          ],
          'effects' => [
            'type' => 'object',
            'default' => []
          ],
          'transform' => [
            'type' => 'object',
            'default' => []
          ],
          'animation' => [
            'type' => 'object',
            'default' => []
          ],
          'alignment' => [
            'type' => 'object',
            'default' => []
          ],
          'spacing' => [
            'type' => 'object',
            'default' => []
          ],
          'borderRadius' => [
            'type' => 'object',
            'default' => []
          ],
          'border' => [
            'type' => 'object',
            'default' => []
          ],
          'typography' => [
            'type' => 'string',
            'default' => ''
          ],
          'fontColor' => [
            'type' => 'object',
            'default' => []
          ],
        ],
      ],
      'taxonomy-grid' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new TaxonomyGrid($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'isPreview' => [
            'type' => 'boolean',
            'default' => false
          ],
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'postType' => [
            'type' => 'string',
            'default' => 'post'
          ],
          'taxonomies' => [
            'type' => 'array',
            'default' => []
          ],
          'postOrder' => [
            'type' => 'string',
            'default' => 'DESC'
          ],
          'orderBy' => [
            'type' => 'string',
            'default' => 'date'
          ],
          'postGridStyle' => [
            'type' => 'object',
            'default' => []
          ],
          'responsiveStyle' => [
            'type' => 'object',
            'default' => []
          ],
          'layout' => [
            'type' => 'string',
            'default' => 'grid'
          ],
          'itemLayout' => [
            'type' => 'string',
            'default' => 'content-under'
          ],
          'contentOverlaidLayout' => [
            'type' => 'object',
            'default' => []
          ],
          'contentUnderLayout' => [
            'type' => 'object',
            'default' => []
          ],

          'imageRatio' => [
            'type' => 'string',
            'default' => '4:3'
          ],
          'imageSize' => [
            'type' => 'string',
            'default' => 'large'
          ],
          'responsiveGrid' => [
            'type' => 'object',
            // TODO: Might need to fill this in fully, not sure
            'default' => [
            //   'desktop' => [
            //     'columnNumber' => 4,
            //     'gridSpacing' => [
            //       'value' => 10,
            //       'unit' => 'px'
            //     ]
            //   ],
            //   'tablet' => [],
            //   'mobile' => []
            ]
          ],
          'displayMeta' => [
            'type' => 'array',
            'default' => [
              [
                'type' => 'title',
                'headingLevel' => 'h3',
                'typography' => ''
              ]
            ]
          ],
          // Unused but defined attributes from the frontend
          'displaySimple' => [
            'type' => 'object',
            'default' => []
          ],
          'bgColor' => [
            'type' => 'object',
            'default' => []
          ],
          'size' => [
            'type' => 'object',
            'default' => []
          ],
          'position' => [
            'type' => 'object',
            'default' => []
          ],
          'effects' => [
            'type' => 'object',
            'default' => []
          ],
          'transform' => [
            'type' => 'object',
            'default' => []
          ],
          'spacing' => [
            'type' => 'object',
            'default' => []
          ],
          'borderRadius' => [
            'type' => 'object',
            'default' => []
          ]
        ],
      ]
    ];
    $this->initialize_hooks();
  }

  function initialize_hooks() {
    add_action( 'init', [$this, 'create_block_nectar_blocks_block_init'] );
    add_action( 'enqueue_block_editor_assets', [$this, 'nectar_block_editor_assets' ], 9999999 );
    add_action( 'enqueue_block_assets', [$this, 'nectar_editor_assets' ] );
    add_filter( 'block_categories_all', [$this, 'nectar_block_category'], 9999999, 2 );
    add_action( 'admin_footer', [$this, 'nectar_blocks_icon_gradient'] );
    add_action( 'init', [$this, 'filters']);
  }

  /**
   * Adds block via metadata file.
   */
  function create_block_nectar_blocks_block_init() {
    // WARNING: NECTAR_BLOCKS_BUILD_PATH is some http path during this hook. Cannot use it for
    // whatever reason.
    foreach (self::$block_list as $block => $args) {

      if ($args['isDynamic'] ?? false) {
        register_block_type_from_metadata( NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block, [
          'render_callback' => $args['render_callback'],
          'attributes' => $args['attributes'],
        ]);
      } else {
        register_block_type_from_metadata( NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block );
      }

    }
  }

  function nectar_block_editor_assets() {
    // Deregistering conflicting scripts from wordpress.com which are enqueued in a MU plugin (Jetpack).
    // They are used for tracking and override our save hook, which will prevent dynamic CSS from saving.
    wp_dequeue_script('wpcom-block-editor-wpcom-editor-script');
    wp_dequeue_script('wpcom-block-editor-default-editor-script');
  }

  /**
   * Adds editor asset css and js
   */
  function nectar_editor_assets() {
    // https://github.com/WordPress/gutenberg/pull/49655
    // Using is_admin() + enqueue_block_assets instead of enqueue_block_editor_assets
    // to ensure these get in the iframe and only in the block editor
    if ( ! is_admin() ) {
      return;
    }

    if ( is_customize_preview() ) {
      return;
    }

    global $wp_scripts;

    // NB Plugin Options
    $nb_plugin_options = Nectar_Plugin_Options::get_options();

    // Custom body class.
    add_filter('admin_body_class', function($classes) {
      $classes .= ' nectar-blocks-theme ';
      return $classes;
    });

    // We don't want to load EDITOR scripts in the iframe, only enqueue
    // front-end assets for the content.
    // Any iframe component in the editor will automatically load the assets in this hook
    // so we need to gate it with the should_load_block_editor_scripts_and_styles filter
    // https://github.com/WordPress/gutenberg/blob/5bcb30933846450ed25b3c9d0da39ccc95307b54/lib/compat/wordpress-6.4/script-loader.php#L143
    $should_load_assets = apply_filters('should_load_block_editor_scripts_and_styles', true);

    // --------- LOADED IN EDITOR ONLY -------------
    if( $should_load_assets ) {

      $asset_file = include NECTAR_BLOCKS_ROOT_DIR_PATH . 'build/editor.asset.php';
      // Remove wp-edit-post script from our deps when on widget editor.
      // https://developer.wordpress.org/reference/functions/wp_check_widget_editor_deps/
      if ( $wp_scripts->query( 'wp-edit-widgets', 'enqueued' ) ) {
        $asset_file['dependencies'] = \array_diff($asset_file['dependencies'], ['wp-edit-post', 'wp-editor']);
      }

      wp_enqueue_script( 'gsap-js', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/gsap.min.js', [], '3.12.7', true );
      wp_enqueue_script( 'gsap-scroll-trigger-js', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/ScrollTrigger.min.js', ['gsap-js'], '3.12.7', true );
      wp_enqueue_script( 'gsap-custom-ease-js', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/CustomEase.min.js', ['gsap-js'], '3.12.7', true );

      $editor_file_version = $asset_file['version'];
      if (NECTAR_BUILD_MODE === 'production') {
        $editor_file_version = NECTAR_BLOCKS_VERSION;
      }
      wp_enqueue_script(
          'nectar-editor-global',
          NECTAR_BLOCKS_BUILD_PATH . '/editor.js',
          [ ...$asset_file['dependencies'] ],
          $editor_file_version,
          true
      );

      // Pass user capability to JavaScript
      wp_localize_script('nectar-editor-global', 'nectarblocksEnv', [
        'canUnfilteredHtml' => current_user_can('unfiltered_html'),
      ]);

      // Localize the script with translations.
      wp_set_script_translations( 'nectar-editor-global', 'nectar-blocks', NECTAR_BLOCKS_ROOT_DIR_PATH . '/languages'  );
    }

    // --------- LOADED IN EDITOR AND IFRAME -------------

    // Google fonts.
    $google_fonts = Global_Typography::create_google_fonts_link('editor');
    if ( $google_fonts ) {
      wp_enqueue_style( 'nectar-blocks-google-fonts', $google_fonts, [], null );
    }
    // Main.
    wp_enqueue_style( 'nectar-editor-global', NECTAR_BLOCKS_BUILD_PATH . '/editor.css', [], NECTAR_BLOCKS_VERSION);
    wp_enqueue_style( 'nectar-front-end-render', NECTAR_BLOCKS_BUILD_PATH . '/frontend-styles.css', [], NECTAR_BLOCKS_VERSION);

    // Global styles.
    $global_css = Global_Colors::css_output();
    $global_css .= Global_Typography::css_output( 'editor', $nb_plugin_options['shouldDisableNectarGlobalTypography'] );

    if( $global_css ) {
      wp_add_inline_style( 'nectar-front-end-render', $global_css );
    }

    // Uploaded fonts.
    $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('editor');
    if ( $uploaded_fonts ) {
      wp_add_inline_style( 'nectar-front-end-render', $uploaded_fonts);
    }

    // Responsive toolbar
    $nectar_i18n_vars = [
      'desktop' => esc_html__('Desktop', 'nectar-blocks'),
      'tablet' => esc_html__('Tablet', 'nectar-blocks'),
      'phone' => esc_html__('Phone', 'nectar-blocks')
    ];
    wp_localize_script('nectar-editor-global', 'nectar_i18n', $nectar_i18n_vars);

    $nectar_security = [
      'token' => Nectar_Blocks_Options::get_options()['token']
    ];
    wp_localize_script('nectar-editor-global', 'nectar_security', $nectar_security);
 }

  /**
   * Add Nectar block category.
   */
  function nectar_block_category( $categories ) {
    $nectar_cat = [
      'slug' => 'nectar',
      'title' => __( 'Nectarblocks', 'nectar-blocks' ),
    ];

    $new_cat_list = [];
    $new_cat_list[0] = $nectar_cat;

    foreach ($categories as $category) {
      $new_cat_list[] = $category;
    }

    return $new_cat_list;
  }

  /**
   * Add Nectar gradient for block icons.
   */
  function nectar_blocks_icon_gradient() {
    echo '<svg style="visibility: hidden; pointer-events: none; position: absolute; z-index: -999;">
        <linearGradient id="nectar-blocks-icon-gradient" gradientTransform="rotate(45)">
          <stop offset="15%" stop-color="#1099ff" />
          <stop offset="100%" stop-color="#3452ff" />
        </linearGradient>
        <linearGradient id="nectar-blocks-icon-gradient-alt" gradientTransform="rotate(45)">
          <stop offset="15%" stop-color="#ff6114" />
          <stop offset="100%" stop-color="#a335fe" />
        </linearGradient>
    </svg>';
  }

  function filters() {
    add_filter( 'wp_kses_allowed_html', [$this, 'nectar_wp_kses_allowed_html'], 10, 2 );
    add_filter( 'safe_style_css', [$this, 'nectar_wp_kses_allowed_styles'] );
  }

  function nectar_wp_kses_allowed_styles( $styles ) {
    $styles[] = 'opacity';
    return $styles;
  }

  function nectar_wp_kses_allowed_html( $tags, $context ) {
    $tags['style'] = [];

    // text highlights.
    $tags['nectar-blocks-text-highlight'] = [
      'style' => true
    ];
    // videos.
    $tags['source'] = [
      'type' => true,
      'src' => true
    ];

    // svgs
    $tags['svg'] = [
      'xmlns' => true,
      'viewbox' => true,
      'width' => true,
      'height' => true,
      'class' => true,
      'style' => true,
      'preserveaspectratio' => true,
      'aria-hidden' => true,
      'role' => true,
      'focusable' => true,
      'data-*' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true
    ];
    $tags['g'] = [
      'id' => true,
      'class' => true,
      'style' => true,
      'transform' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true
    ];
    $tags['path'] = [
      'd' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true,
      'pathlength' => true,
      'class' => true,
      'transform' => true,
      'style' => true,
    ];
    $tags['polygon'] = [
      'style' => true,
      'points' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'class' => true
    ];
    $tags['rect'] = [
      'style' => true,
      'x' => true,
      'y' => true,
      'width' => true,
      'height' => true,
      'rx' => true,
      'ry' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'class' => true,
    ];
    $tags['line'] = [
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-miterlimit' => true,
      'class' => true,
      'style' => true,
    ];
    $tags['circle'] = [
      'cx' => true,
      'cy' => true,
      'r' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'class' => true,
      'style' => true,
    ];
    $tags['filter'] = [
      'id' => true,
      'x' => true,
      'y' => true,
      'width' => true,
      'height' => true,
      'filterunits' => true,
      'primitiveunits' => true,
    ];

    // SVG gradients.
    $tags['defs'] = [];
    $tags['stop'] = [
      'offset' => true,
      'style' => true,
      'stop-color' => true,
      'stop-opacity' => true,
    ];
    $tags['lineargradient'] = [
      'id' => true,
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
      'gradientunits' => true,
    ];

    $this->shared_attributes( $tags, 'div' );
    $this->shared_attributes( $tags, 'span' );
    $this->shared_attributes( $tags, 'a' );
    $this->shared_attributes( $tags, 'button' );
    $this->shared_attributes( $tags, 'section' );
    $this->shared_attributes( $tags, 'aside' );
    $this->shared_attributes( $tags, 'nav' );
    $this->shared_attributes( $tags, 'main' );
    $this->shared_attributes( $tags, 'article' );
    $this->shared_attributes( $tags, 'header' );
    $this->shared_attributes( $tags, 'footer' );
    return $tags;
  }

  function shared_attributes( &$tags, $tag ) {
    $tags[$tag]['data-nectar-block-animation'] = true;
    $tags[$tag]['aria-hidden'] = true;
    $tags[$tag]['aria-expanded'] = true;
    $tags[$tag]['aria-level'] = true;
    $tags[$tag]['role'] = true;
    $tags[$tag]['tabindex'] = true;
  }
}
