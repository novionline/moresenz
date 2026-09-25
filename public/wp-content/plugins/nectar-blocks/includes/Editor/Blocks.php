<?php

namespace Nectar\Editor;

use Nectar\Global_Settings\{
  Global_Colors,
  Global_Typography,
  Nectar_Blocks_Options,
  Nectar_Plugin_Options
};
use Nectar\Render\Blocks\AccordionSection\AccordionSection;
use Nectar\Render\Blocks\Button\Button;
use Nectar\Render\Blocks\Icon\Icon;
use Nectar\Render\Blocks\IconListItem\IconListItem;
use Nectar\Render\Blocks\PostContent\PostContent;
use Nectar\Render\Blocks\Tabs\Tabs;
use Nectar\Render\Blocks\PostGrid\PostGrid;
use Nectar\Render\Blocks\TaxonomyGrid\TaxonomyGrid;
use Nectar\Render\Blocks\TaxonomyTerms\TaxonomyTerms;
use Nectar\Render\Blocks\HeaderActions\HeaderActions;
use Nectar\Render\Blocks\HeaderActionAccount\HeaderActionAccount;
use Nectar\Render\Blocks\WorldTime\WorldTime;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Global_Sections\Global_Sections;
use Nectar\Licensing\Token_Service;

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
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new Button($block_attributes, $content);
          return $block->render();
        },
        'attributes' => []
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
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new Icon($block_attributes, $content);
          return $block->render();
        },
        'attributes' => []
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
      'ticker' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'world-time' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new WorldTime($block_attributes, $content);
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
          'city' => [
            'type' => 'string',
            'default' => 'America/New_York'
          ],
          'timeFormat' => [
            'type' => 'string',
            'default' => '12h'
          ],
          'dateFormat' => [
            'type' => 'string',
            'default' => 'medium'
          ],
          'datePosition' => [
            'type' => 'string',
            'default' => 'after'
          ],
          'dateSize' => [
            'type' => 'string',
            'default' => 'same'
          ],
          'showSeconds' => [
            'type' => 'boolean',
            'default' => false
          ],
          'showDate' => [
            'type' => 'boolean',
            'default' => true
          ],
          'showTimezone' => [
            'type' => 'boolean',
            'default' => true
          ],
          'typography' => [
            'type' => 'string',
            'default' => ''
          ],
          'fontColor' => [
            'type' => 'object',
            'default' => []
          ],
          'fontSettings' => [
            'type' => 'object',
            'default' => []
          ],
          'spacing' => [
            'type' => 'object',
            'default' => []
          ],
          'displaySimple' => [
            'type' => 'object',
            'default' => [
              'desktop' => [
                'displayType' => 'auto'
              ],
              'tablet' => [],
              'mobile' => []
            ]
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
          'size' => [
            'type' => 'object',
            'default' => []
          ],
          'animation' => [
            'type' => 'object',
            'default' => []
          ]
        ]
      ],
      'ticker-item' => [
        'deps' => [],
        'frontend_style' => false
      ],
      'tabs' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new Tabs($block_attributes, $content);
          return $block->render();
        },
        'attributes' => []
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
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new IconListItem($block_attributes, $content);
          return $block->render();
        },
        'attributes' => []
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
      'table-of-contents' => [
        'deps' => [],
        'frontend_style' => true
      ],
      'accordion-section' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new AccordionSection($block_attributes, $content);
          return $block->render();
        },
        'attributes' => []
      ],
      'navigation' => [
        'deps' => [],
        'frontend_style' => false
      ],
      'megamenu' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        // Megamenu lives inside core/navigation-submenu, whose render rebuilds inner
        // HTML and drops siblings of the megamenu div — a prepended inline <style>
        // would be stripped. Deliver as a <link> in <head> instead.
        'frontend_style_delivery' => 'link',
        'render_callback' => function($block_attributes, $content) {
          $block = new \Nectar\Render\Blocks\Megamenu\Megamenu($block_attributes);
          return $block->render();
        },
        'attributes' => [
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'sourceType' => [
            'type' => 'string',
            'default' => 'global-section',
          ],
          'sourceId' => [
            'type' => ['number', 'string'],
            'default' => 0,
          ],
          'sourceTitle' => [
            'type' => 'string',
            'default' => '',
          ],
          'size' => [
            'type' => 'string',
            'default' => 'fullwidth',
          ],
          'containedWidth' => [
            'type' => 'object',
            'properties' => [
              'value' => [
                'type' => 'number',
              ],
              'unit' => [
                'type' => 'string',
              ],
            ],
            'default' => [
              'value' => 600,
              'unit' => 'px'
            ]
          ]
        ]
      ],
      'header-actions' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new HeaderActions($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'blockId' => [
            'type' => 'string',
            'default' => '',
          ],
          'iconSize' => [
            'type' => 'object',
            'default' => [
              'desktop' => [ 'value' => 22 ],
              'tablet' => [],
              'mobile' => [],
            ],
          ],
          'itemGap' => [
            'type' => 'object',
            'default' => [
              'desktop' => [ 'value' => 10 ],
              'tablet' => [],
              'mobile' => [],
            ],
          ],
          'iconColor' => [
            'type' => 'object',
            'default' => [
              'desktop' => [
                'type' => 'solid',
                'solidValue' => '',
                'gradientValue' => '',
                'solidGlobalColorData' => null,
                'gradientGlobalColorData' => null,
              ],
              'tablet' => [],
              'mobile' => [],
              'hover' => [],
            ],
          ]
        ]
      ],
      'search' => [
        'deps' => [],
        'frontend_style' => true,
        'attributes' => [
          'blockId' => [
            'type' => 'string',
            'default' => ''
          ]
        ]
      ],
      'header-action-account' => [
        'deps' => [],
        'isDynamic' => true,
        'frontend_style' => true,
        'render_callback' => function($block_attributes, $content) {
          $block = new HeaderActionAccount($block_attributes, $content);
          return $block->render();
        },
        'attributes' => [
          'isPreview' => [
            'type' => 'boolean',
            'default' => false
          ],
          'iconStyle' => [
            'type' => 'string',
            'default' => 'default'
          ],
          'link' => [
            'type' => 'object',
            'default' => [
              'href' => null,
              'openInNewTab' => false,
              'clickEvent' => 'regular',
              'customAttributes' => []
            ]
          ]
        ]
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
          // Switches the render path to use bundled demo posts instead of
          // querying the user's site. Set by the template library so cards
          // look populated even on fresh installs with no posts. MUST stay
          // registered: ServerSideRender sends every attribute over REST
          // and WP rejects requests containing unregistered parameters with
          // "Invalid parameter(s): attributes", which would break every
          // post-grid render (preview AND inserted).
          'useDemoPosts' => [
            'type' => 'boolean',
            'default' => false
          ],
          'demoFixtureMode' => [
            'type' => 'string',
            'default' => 'shuffled'
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
          'dynamicLink' => [
            'type' => 'object',
            'default' => [
              'enabled' => false,
              'source' => ''
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

      // Single GSAP bundle — gsap core + ScrollTrigger + CustomEase in one file.
      wp_enqueue_script( 'gsap-js', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/gsap.bundle.min.js', [], '3.12.7', true );

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
        'canAssignGlobalSections' => current_user_can( Global_Sections::assign_capability() ),
      ]);

      // Per-device fluid root font-size clamp formulas — only when the
      // root value actually contains `vw`. JS applies these as inline
      // style on the canvas `<html>` and strips them during a resize drag
      // (see editor/fluid-root-font-size.ts). Empty array when the root
      // config is non-fluid, in which case the stylesheet emits normally
      // and JS does nothing.
      wp_localize_script(
          'nectar-editor-global',
          'nectarblocksFluidRoot',
          Global_Typography::get_editor_fluid_root_data()
      );

      // Localize the script with translations.
      wp_set_script_translations( 'nectar-editor-global', 'nectar-blocks', NECTAR_BLOCKS_ROOT_DIR_PATH . '/languages'  );
    }

    // --------- LOADED IN EDITOR AND IFRAME -------------

    // Google fonts.
    $google_fonts = Global_Typography::create_google_fonts_link('editor');
    if ( $google_fonts ) {
      wp_enqueue_style( 'nectar-blocks-google-fonts', esc_url( (string) $google_fonts ), [], null );
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

    // PERF: fluid `:root { font-size }` boot stylesheet for the editor.
    //
    // Why this exists: a `:root { font-size: clamp(... + Nvw, ...) }` rule
    // living in any stylesheet makes the browser re-evaluate it on every
    // viewport-width tick (window resize, sidebar slide, device preview
    // switch). Because every `rem` value depends on root font-size, that
    // recalc cascades through every rem-using element in the canvas —
    // measured at ~3000ms per drag on heavy docs. The cost holds even if
    // a higher-specificity static override wins, because the browser keeps
    // the fluid rule live for invalidation tracking.
    //
    // Fix: the main editor stylesheet OMITS the `:root` rule (when fluid).
    // We emit it HERE, in its own <style> element, for first-paint only.
    // editor/fluid-root-font-size.ts removes this element at module init
    // and owns the value as inline style on the canvas <html>. With no
    // `vw` rule live in any stylesheet, the cascade is gone. During a
    // drag JS replaces the inline value with the current computed pixel
    // (still no `vw`), then restores the clamp on settle.
    //
    // Defensive guards below: every value is type-checked before being
    // interpolated into CSS, so a malformed option or a future shape
    // change can't emit broken CSS or trigger PHP warnings.
    $fluid_root_boot_data = method_exists('Nectar\\Global_Settings\\Global_Typography', 'get_editor_fluid_root_data')
      ? Global_Typography::get_editor_fluid_root_data()
      : [];
    if ( is_array($fluid_root_boot_data) && ! empty($fluid_root_boot_data) ) {
      $boot_css_rules = '';
      foreach ( $fluid_root_boot_data as $device => $clamp ) {
        if ( ! is_string($device) || ! is_string($clamp) || $clamp === '' ) {
          continue;
        }
        if ( ! isset(Global_Typography::$devices[$device]) ) {
          continue;
        }
        $media = Global_Typography::$devices[$device];
        if ( ! is_string($media) || $media === '' ) {
          continue;
        }
        $boot_css_rules .= $media . ' { :root { font-size: ' . $clamp . '; } }';
      }
      if ( $boot_css_rules !== '' ) {
        wp_register_style( 'nectar-blocks-fluid-root-boot', false );
        wp_enqueue_style( 'nectar-blocks-fluid-root-boot' );
        wp_add_inline_style( 'nectar-blocks-fluid-root-boot', $boot_css_rules );
      }
    }

    // Uploaded fonts.
    $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('editor');
    if ( $uploaded_fonts ) {
      wp_add_inline_style( 'nectar-front-end-render', $uploaded_fonts);
    }

    // OCM template editor background.
    // Uses its own handle so the editor's global-styles-canvas-watcher doesn't
    // overwrite it when it replaces nectar-front-end-render-inline-css contents.
    $ocm_editor_css = $this->get_ocm_template_editor_css();
    if ( $ocm_editor_css ) {
      wp_register_style( 'nectar-ocm-editor-bg', false );
      wp_enqueue_style( 'nectar-ocm-editor-bg' );
      wp_add_inline_style( 'nectar-ocm-editor-bg', $ocm_editor_css );
    }

    // Responsive toolbar
    $nectar_i18n_vars = [
      'desktop' => esc_html__('Desktop', 'nectar-blocks'),
      'tablet' => esc_html__('Tablet', 'nectar-blocks'),
      'phone' => esc_html__('Phone', 'nectar-blocks')
    ];
    wp_localize_script('nectar-editor-global', 'nectar_i18n', $nectar_i18n_vars);

    $options = Nectar_Blocks_Options::get_options();
    $nectar_security = [
      'token' => is_array($options) && isset($options['token']) ? $options['token'] : '',
      // Reported on the Template Library fetch for the JWT V2 `aud` binding.
      // Resolved via Token_Service::current_hostname() rather than
      // window.location in the editor: same source and canonical form as
      // /license/register records, and wp-admin can legitimately sit on a
      // different host than the site itself (WP_SITEURL vs WP_HOME).
      // '' when unresolvable — the client then omits the field entirely.
      'hostname' => Token_Service::current_hostname()
    ];
    wp_localize_script('nectar-editor-global', 'nectar_security', $nectar_security);

    // OCM (off-canvas menu) colors from the theme customizer. Exposed so the
    // template library can paint navigation-category preview cards with the
    // same background — independent of which post is being edited. Empty
    // strings when the theme isn't active or the customizer hasn't set them;
    // the JS side treats that as "skip the inline style".
    wp_localize_script('nectar-editor-global', 'nectarOcmColors', $this->get_ocm_colors());
 }

  /**
   * Read OCM bg/text colors from the customizer. Theme-active gated.
   * Returns empty strings (not nulls) so the localized object shape is stable
   * and the JS consumer can just truthy-check before applying.
   */
  private function get_ocm_colors(): array {
    $empty = [ 'background' => '', 'text' => '' ];

    $theme_active = class_exists( '\NectarThemeManager' )
      || ( defined( 'NECTAR_BLOCKS_FORCE_THEME_ACTIVE' ) && NECTAR_BLOCKS_FORCE_THEME_ACTIVE );
    if ( ! $theme_active || ! function_exists( 'get_nectar_theme_options' ) ) {
      return $empty;
    }

    $opts = get_nectar_theme_options();
    $bg = $opts['header-slide-out-widget-area-background-color'] ?? '';
    $text = $opts['header-slide-out-widget-area-color'] ?? '';

    return [
      'background' => self::is_safe_css_color( $bg ) ? $bg : '',
      'text' => self::is_safe_css_color( $text ) ? $text : '',
    ];
  }

  /**
   * Generate editor background CSS when editing an Off Canvas Menu template.
   *
   * Reads the OCM background color from the customizer. Only applies when the
   * Nectar Blocks theme is active (the customizer option lives in the theme).
   *
   * @since 3.0.0
   * @return string CSS rules or empty string.
   */
  private function get_ocm_template_editor_css(): string {
    global $pagenow;

    // Theme must be active for the customizer value to exist.
    $theme_active = class_exists( '\NectarThemeManager' )
      || ( defined( 'NECTAR_BLOCKS_FORCE_THEME_ACTIVE' ) && NECTAR_BLOCKS_FORCE_THEME_ACTIVE );

    if ( ! $theme_active || ! function_exists( 'get_nectar_theme_options' ) ) {
      return '';
    }

    if ( $pagenow !== 'post.php' || empty( $_GET['post'] ) ) {
      return '';
    }

    $post_id = absint( $_GET['post'] );
    if ( get_post_type( $post_id ) !== Nectar_Templates::POST_TYPE ) {
      return '';
    }

    $meta = get_post_meta( $post_id, Nectar_Templates::META_KEY, true );
    if ( ! is_array( $meta ) || ( $meta['templatePart'] ?? '' ) !== 'nectar_template__ocm' ) {
      return '';
    }

    $opts = get_nectar_theme_options();
    $bg = $opts['header-slide-out-widget-area-background-color'] ?? '';
    $text = $opts['header-slide-out-widget-area-color'] ?? '';

    if ( empty( $bg ) || ! self::is_safe_css_color( $bg ) ) {
      return '';
    }

    // Scope to the actual post-editor canvas iframe only. Block previews
    // (template library, inserter previews) share the same body classes,
    // so without :has() the OCM background bleeds into every preview iframe
    // on the page. The post-title wrapper is unique to the canvas. Template
    // library navigation-card backgrounds are applied separately, per-card,
    // by Template.tsx using the localized color (see `nectarOcmColors`).
    $body = '.editor-styles-wrapper.block-editor-iframe__body:has(.edit-post-visual-editor__post-title-wrapper)';
    $title = $body . ' .edit-post-visual-editor__post-title-wrapper';
    $layout = $body . ' .block-editor-block-list__layout';
    $css = "{$body} { --nectar-overall-bg-color: {$bg}; background-color: var(--nectar-overall-bg-color); } ";
    $css .= "{$layout} { min-height: calc(100vh - 56px); } ";

    // Text / title color — only override when explicitly set in customizer.
    if ( ! empty( $text ) && self::is_safe_css_color( $text ) ) {
      $css .= "{$body} { --nectar-overall-font-color: {$text}; color: var(--nectar-overall-font-color); } ";
      $css .= "{$body} .block-editor-block-list__layout { color: var(--nectar-overall-font-color); } ";
      $css .= "{$title}, {$title} h1 { color: var(--nectar-overall-font-color); } ";
    }

    return $css;
  }

  /**
   * Check if a value is a hex color or a CSS variable reference.
   */
  private static function is_safe_css_color( string $value ): bool {
    return sanitize_hex_color( $value ) || preg_match( '/^var\(--[\w-]+\)$/', $value );
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
    // Empty slot marker emitted by icon-bearing blocks' save() (icon, button,
    // tabs, accordion-section, icon-list-item); the SVG is injected
    // server-side at render. Must be allowlisted or wp_kses_post() strips it on
    // save for users without `unfiltered_html` (e.g. multisite Editors),
    // which kills icon render and trips block validation.
    $tags[$tag]['data-nectar-icon-slot'] = true;
    // Self-describing identity on the slot marker (library + icon name) so the
    // render_callback can resolve the SVG server-side even when the `icon`
    // attribute is dropped from the delimiter for equalling its default. Must
    // survive wp_kses_post() for restricted-role saves alongside the slot attr.
    $tags[$tag]['data-icon-library'] = true;
    $tags[$tag]['data-icon-name'] = true;
    $tags[$tag]['aria-controls'] = true;
    $tags[$tag]['aria-selected'] = true;
    $tags[$tag]['aria-hidden'] = true;
    $tags[$tag]['aria-expanded'] = true;
    $tags[$tag]['aria-level'] = true;
    $tags[$tag]['role'] = true;
    $tags[$tag]['tabindex'] = true;
  }
}
