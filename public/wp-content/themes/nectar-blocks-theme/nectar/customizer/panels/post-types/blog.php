<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Post Types - Portfolio customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Post_Types_Blog {
  public static $using_post_grid_archive = false;

  public static $post_grid_special_id = '';

  public static function get_kirki_partials() {

    // if ( NectarThemeManager::is_special_location_active('nectar_special_location__blog_loop') ) {
    //   self::$using_post_grid_archive = true;
    //   self::$post_grid_special_id = NectarThemeManager::is_special_location_active('nectar_special_location__blog_loop');
    // }

    return [
      [
        'panel_id' => 'blog-panel',
        'settings' => [
          'title' => esc_html__( 'Blog', 'nectar-blocks-theme' ),
          'priority' => 25
        ]
      ],
      self::get_styling(),
      self::get_functionality(),
      self::get_single_post(),
      self::get_single_post_header(),
      self::get_archive_header(),
      self::get_post_meta()
    ];
  }

  public static function get_styling() {

    $controls = [

      // TODO: future feature
      // array(
      //   'id' => 'blog_type_post_grid',
      //   'class' => self::$using_post_grid_archive ? '' : 'hidden-theme-option',
      //   'type' => 'select',
      //   'title' => esc_html__('Blog Format', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('Please select your blog format here.', 'nectar-blocks-theme') . '<br/><br/><strong>' . esc_html__('Note: Your blog archive design is currently being handled through a', 'nectar-blocks-theme') .' <a target="_blank" href="'.esc_url( admin_url( 'post.php?post='.self::$post_grid_special_id.'&action=edit' ) ).'">'.esc_html__('global section.', 'nectar-blocks-theme').'</a></strong>',
      //   'desc' => '',
      //   'options' => array(
      //     'contained' => esc_html__('Contained', 'nectar-blocks-theme'),
      //     'contained-sidebar' => esc_html__('Contained + sidebar', 'nectar-blocks-theme'),
      //     'fullwidth' => esc_html__('Fullwidth', 'nectar-blocks-theme')
      //   ),
      //   'default' => 'contained'
      // ),

      [
        'id' => 'blog_type',
        'class' => self::$using_post_grid_archive ? 'hidden-theme-option' : '',
        'type' => 'select',
        'title' => esc_html__('Blog Layout', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'std-blog-sidebar' => esc_html__('Standard Blog W/ Sidebar', 'nectar-blocks-theme'),
          'std-blog-fullwidth' => esc_html__('Standard Blog No Sidebar', 'nectar-blocks-theme'),
          'masonry-blog-sidebar' => esc_html__('Masonry Blog W/ Sidebar', 'nectar-blocks-theme'),
          'masonry-blog-fullwidth' => esc_html__('Masonry Blog No Sidebar', 'nectar-blocks-theme'),
          'masonry-blog-full-screen-width' => esc_html__('Masonry Blog Fullwidth', 'nectar-blocks-theme')
        ],
        'default' => 'masonry-blog-fullwidth'
      ],

      [
        'id' => 'blog_auto_masonry_spacing',
        'type' => 'select',
        'class' => self::$using_post_grid_archive ? 'hidden-theme-option' : '',
        'title' => esc_html__('Blog Spacing', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          '4px' => '4px',
          '8px' => '8px',
          '12px' => '12px',
          '16px' => '16px',
          '20px' => '20px',
        ],
        'default' => '8px',
        'required' => [
          'or' => [
            [
              'setting' => 'blog_type',
              'operator' => '=',
              'value' => 'masonry-blog-sidebar'
            ],
            [
              'setting' => 'blog_type',
              'operator' => '=',
              'value' => 'masonry-blog-fullwidth'
            ],
            [
              'setting' => 'blog_type',
              'operator' => '=',
              'value' => 'masonry-blog-full-screen-width'
            ]
          ]
        ]
      ],

      [
        'id' => 'blog_enable_ss',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Enable Sticky Sidebar', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0',
      ],

      [
        'id' => 'blog_auto_excerpt',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Post Excerpt', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],

      [
        'id' => 'blog_excerpt_length',
        'type' => 'text',
        'required' => [ [ 'blog_auto_excerpt', '=', '1' ] ],
        'title' => esc_html__('Excerpt Length', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('How many words would you like to display for your post excerpts?', 'nectar-blocks-theme'),
        'desc' => ''
      ],

    ];

    return [
      'section_id' => 'blog-styling-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Styling', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_single_post() {
    $controls = [

      [
        'id' => 'blog_hide_sidebar',
         'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Hide Sidebar on Single Post', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'blog_width',
        'type' => 'select',
        'title' => esc_html__('Blog Content Width', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          '1000px' => esc_html__('1000px', 'nectar-blocks-theme'),
          '900px' => esc_html__('900px', 'nectar-blocks-theme'),
          '800px' => esc_html__('800px', 'nectar-blocks-theme'),
          '700px' => esc_html__('700px', 'nectar-blocks-theme')
        ],
        'required' => [ [ 'blog_hide_sidebar', '=', '1' ] ],
        'default' => 'default'
      ],

    [
      'id' => 'blog_header_author_link',
      'type' => 'select',
      'title' => esc_html__('Author Link', 'nectar-blocks-theme'),
      'subtitle' => '',
      'desc' => '',
      'options' => [
        'default' => esc_html__('Post Author Archives', 'nectar-blocks-theme'),
        'website_url' => esc_html__('Post Author Website URL ', 'nectar-blocks-theme'),
        'none' => esc_html__('None', 'nectar-blocks-theme'),
      ],
      'default' => 'default'
    ],
    [
      'id' => 'author_bio',
       'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Author Bio', 'nectar-blocks-theme'),
      'subtitle' => esc_html__('Display the author\'s bio at the bottom of posts?', 'nectar-blocks-theme'),
      'desc' => '',
      'default' => '1'
    ],

    [
      'id' => 'blog_next_post_link',
       'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Post Navigation Links', 'nectar-blocks-theme'),
      'subtitle' => esc_html__('Using this will add navigation link(s) at the bottom of every single post page.', 'nectar-blocks-theme'),
      'desc' => '',
      'type' => 'nectar_blocks_switch_legacy',
      'default' => '1'
    ],
    [
      'id' => 'blog_next_post_link_style',
      'type' => 'select',
      'title' => esc_html__('Post Navigation Style', 'nectar-blocks-theme'),
      'desc' => '',
      'required' => [ [ 'blog_next_post_link', '=', '1' ] ],
      'options' => [
        "fullwidth_next_only" => esc_html__("Fullwidth Next Link Only", 'nectar-blocks-theme'),
        "fullwidth_next_prev" => esc_html__("Fullwidth Next & Prev Links", 'nectar-blocks-theme'),
        "contained_next_prev" => esc_html__("Contained Next & Prev Links", 'nectar-blocks-theme'),
        "parallax_next_only" => esc_html__("Parallax Contained Next Link Only", 'nectar-blocks-theme'),
      ],
      'default' => 'fullwidth_next_prev'
    ],
    [
     'id' => 'blog_next_post_limit_cat',
     'type' => 'nectar_blocks_switch_legacy',
     'title' => esc_html__('Limit Post Navigation To Same Category', 'nectar-blocks-theme'),
     'subtitle' => esc_html__('This will ensure that the next/prev links will only show posts that are set in the same category of the post being viewed.', 'nectar-blocks-theme'),
     'desc' => '',
     'type' => 'nectar_blocks_switch_legacy',
     'required' => [ [ 'blog_next_post_link', '=', '1' ] ],
     'default' => '0'
   ],
    [
      'id' => 'blog_next_post_link_order',
      'type' => 'select',
      'title' => esc_html__('Post Navigation Ordering', 'nectar-blocks-theme'),
      'desc' => '',
      'hint' => ['content' => '<strong>' . esc_html__('Default:', 'nectar-blocks-theme') . '</strong> ' . esc_html__('the next post link will be the next', 'nectar-blocks-theme') . ' <i>' . esc_html__('oldest', 'nectar-blocks-theme') . '</i> ' . esc_html__('post.', 'nectar-blocks-theme') . '<br/> <strong>' . esc_html__('Reverse Order:', 'nectar-blocks-theme') . '</strong> ' . esc_html__('the next post link will be the next', 'nectar-blocks-theme') . ' <i>' . esc_html__('newest', 'nectar-blocks-theme') . '</i> ' . esc_html__('post.', 'nectar-blocks-theme'), 'title' => ''],
      'required' => [[ 'blog_next_post_link', '=', '1' ]],
      'options' => [
        "default" => esc_html__("Default", 'nectar-blocks-theme'),
        "reverse" => esc_html__("Reverse Order", 'nectar-blocks-theme')
      ],
      'default' => 'default'
    ],
    [
      'id' => 'blog_related_posts',
      'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Related Posts', 'nectar-blocks-theme'),
      'subtitle' => esc_html__('Adds related posts after the post content on single post pages.', 'nectar-blocks-theme'),
      'desc' => '',
      'default' => '0'
    ],
    [
      'id' => 'blog_related_posts_functionality',
      'type' => 'select',
      'title' => esc_html__('Related Posts Functionality', 'nectar-blocks-theme'),
      'desc' => '',
      'required' => [[ 'blog_related_posts', '=', '1' ]],
      'options' => [
        "default" => esc_html__("Recent in same category", 'nectar-blocks-theme'),
        "random_same_cat" => esc_html__("Random in same category", 'nectar-blocks-theme'),
        "random" => esc_html__("Random in any category", 'nectar-blocks-theme'),

      ],
      'default' => 'default'
    ],

    [
      'id' => 'blog_related_posts_style',
      'type' => 'select',
      'class' => self::$using_post_grid_archive ? 'hidden-theme-option' : '',
      'title' => esc_html__('Related Posts Style', 'nectar-blocks-theme'),
      'desc' => '',
      'required' => [[ 'blog_related_posts', '=', '1' ]],
      'options' => [
        "material" => esc_html__("Material", 'nectar-blocks-theme'),
        "classic_enhanced" => esc_html__("Classic", 'nectar-blocks-theme'),
      ],
      'default' => 'material'
    ],
    [
      'id' => 'blog_related_posts_excerpt',
      'type' => 'nectar_blocks_switch_legacy',
      'class' => self::$using_post_grid_archive ? 'hidden-theme-option' : '',
      'title' => esc_html__('Display Excerpt In Related Posts', 'nectar-blocks-theme'),
      'subtitle' => '',
      'desc' => '',
      'required' => [[ 'blog_related_posts', '=', '1' ]],
      'default' => '0'
    ],

    [
      'id' => 'blog_related_posts_title_text',
      'type' => 'select',
      'title' => esc_html__('Related Posts Title Text', 'nectar-blocks-theme'),
      'desc' => '',
      'required' => [[ 'blog_related_posts', '=', '1' ]],
      'options' => [
        "related_posts" => esc_html__("Related Posts", 'nectar-blocks-theme'),
        "similar_posts" => esc_html__("Similar Posts", 'nectar-blocks-theme'),
        "you_may_also_like" => esc_html__("You May Also Like", 'nectar-blocks-theme'),
        "recommended_for_you" => esc_html__("Recommended For You", 'nectar-blocks-theme'),
        "hidden" => esc_html__("None (Hidden)", 'nectar-blocks-theme')
      ],
      'default' => 'related_posts'
    ],
    [
      'id' => 'blog_section_title',
      'type' => 'select',
      'title' => esc_html__('Blog Section Title Typography', 'nectar-blocks-theme'),
      'desc' => '',
      'options' => [
        "default" => "Default",
        "h2" => "Heading 2",
        "h3" => "Heading 3",
        "h4" => "Heading 4",
        "h5" => "Heading 5",
      ],
      'default' => 'default',
    ],
    [
      'id' => 'blog_comment_author_style',
      'type' => 'select',
      'title' => esc_html__('Author Style in Comments', 'nectar-blocks-theme'),
      'desc' => '',
      'options' => [
        "default" => esc_html__("Default", 'nectar-blocks-theme'),
        "author_badge" => esc_html__("\"Author\" Badge Next to Name", 'nectar-blocks-theme'),
      ],
      'default' => 'default'
    ],
    [
      'id' => 'display_tags',
      'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Display Tags', 'nectar-blocks-theme'),
      'subtitle' => esc_html__('Display tags at the bottom of posts?', 'nectar-blocks-theme'),
      'desc' => '',
      'default' => '0'
    ]
    ];

    return [
      'section_id' => 'blog-single-post-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Single Post', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_single_post_header() {
    $controls = [
      [
        'id' => 'blog_header_type',
        'type' => 'select',
        'title' => esc_html__('Blog Header Type', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default_minimal' => esc_html__('Variable Height', 'nectar-blocks-theme'),
          'image_under' => esc_html__('Featured Media Under Heading', 'nectar-blocks-theme')
        ],
        'default' => 'image_under'
      ],

      [
       'id' => 'blog_header_aspect_ratio',
       'type' => 'select',
       'title' => esc_html__('Blog Header Image Sizing', 'nectar-blocks-theme'),
       'subtitle' => '',
       'desc' => '',
       'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
       'options' => [
         '40' => esc_html__('Slim (2.5:1)', 'nectar-blocks-theme'),
         '50' => esc_html__('Narrow (2:1)', 'nectar-blocks-theme'),
         '56.25' => esc_html__('Regular (16:9)', 'nectar-blocks-theme'),
         '66.66' => esc_html__('Tall (3:2)', 'nectar-blocks-theme'),
         '100' => esc_html__('Square (1:1)', 'nectar-blocks-theme'),
       ],
       'default' => '40'
     ],

     [
      'id' => 'blog_header_image_under_border_radius',
      'type' => 'slider',
      'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
      'title' => esc_html__('Blog Header Roundness', 'nectar-blocks-theme'),
      'desc' => '',
      "default" => 0,
      "min" => 0,
      "step" => 1,
      "max" => 50,
      'display_value' => 'label'
    ],

     [
       'id' => 'blog_header_image_under_align',
       'type' => 'select',
       'title' => esc_html__('Blog Header Alignment', 'nectar-blocks-theme'),
       'subtitle' => '',
       'desc' => '',
       'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
       'options' => [
         'default' => esc_html__('Left', 'nectar-blocks-theme'),
         'center' => esc_html__('Center', 'nectar-blocks-theme'),
       ],
       'default' => 'center'
     ],

     [
       'id' => 'blog_header_image_under_author_style',
       'type' => 'select',
       'title' => esc_html__('Blog Header Author Style', 'nectar-blocks-theme'),
       'subtitle' => '',
       'desc' => '',
       'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
       'options' => [
         'default' => esc_html__('Small Image Inline', 'nectar-blocks-theme'),
         'large' => esc_html__('Large Image Multiline ', 'nectar-blocks-theme'),
       ],
       'default' => 'default'
     ],

     [
       'id' => 'blog_header_image_under_excerpt',
       'type' => 'nectar_blocks_switch_legacy',
       'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
       'title' => esc_html__('Blog Header Display Excerpt', 'nectar-blocks-theme'),
       'desc' => '',
       'default' => '0'
     ],

      [
       'id' => 'std_blog_header_overlay_color',
       'type' => 'color',
       'title' => esc_html__('Blog Header Overlay Color', 'nectar-blocks-theme'),
       'subtitle' => '',
       'desc' => '',
       'default' => '',
       'required' => [ [ 'blog_header_type', '!=', 'default_minimal' ], [ 'blog_header_type', '!=', 'image_under' ] ],
       'transparent' => false
     ],
     [
       'id' => 'std_blog_header_overlay_opacity',
       'type' => 'slider',
       'required' => [ [ 'blog_header_type', '!=', 'default_minimal' ], [ 'blog_header_type', '!=', 'image_under' ] ],
       'title' => esc_html__('Blog Header Overlay Opacity', 'nectar-blocks-theme'),
       'desc' => '',
       "default" => 0.0,
       "min" => 0,
       "step" => 0.1,
       "max" => 1,
       'resolution' => 0.1,
       'display_value' => 'text'
     ],

      [
        'id' => 'default_minimal_overlay_color',
        'type' => 'color',
        'title' => esc_html__('Blog Header Overlay Color', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '#2d2d2d',
        'required' => [[ 'blog_header_type', '=', 'default_minimal' ]],
        'transparent' => false,
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('default_minimal_overlay_color')
      ],
      [
        'id' => 'default_minimal_overlay_opacity',
        'type' => 'slider',
        'required' => [[ 'blog_header_type', '=', 'default_minimal' ]],
        'title' => esc_html__('Blog Header Overlay Opacity', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0.4,
        "min" => 0,
        "step" => 0.1,
        "max" => 1,
        'resolution' => 0.1,
        'display_value' => 'text',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('default_minimal_overlay_opacity')
      ],
      [
        'id' => 'default_minimal_text_color',
        'type' => 'color',
        'title' => esc_html__('Blog Header Text Color', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '#ffffff',
        'required' => [[ 'blog_header_type', '=', 'default_minimal' ]],
        'transparent' => false
      ],
      [
        'id' => 'blog_header_scroll_effect',
        'type' => 'select',
        'title' => esc_html__('Blog Header Scroll Effect', 'nectar-blocks-theme'),
        'desc' => esc_html__('Globally define a scroll effect for your blog header.', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('None', 'nectar-blocks-theme'),
          'parallax' => esc_html__('Parallax', 'nectar-blocks-theme')
        ],
        'default' => 'parallax'
      ],
      [
       'id' => 'blog_header_load_in_animation',
       'type' => 'select',
       'title' => esc_html__('Blog Header Load In Animation', 'nectar-blocks-theme'),
       'desc' => '',
       'required' => [[ 'blog_header_type', '=', 'image_under'  ]],
       'options' => [
         "none" => esc_html__("None", 'nectar-blocks-theme'),
         "fade_in" => esc_html__("Fade In Staggered", 'nectar-blocks-theme'),
       ],
       'default' => 'none'
     ],

     [
      'id' => 'blog_hide_featured_image',
      'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Hide Featured Media', 'nectar-blocks-theme'),
      'tooltip' => esc_html__('Using this will remove the featured media (determined by the selected post format) from appearing in the top of your single blog posts.', 'nectar-blocks-theme'),
      'desc' => '',
      'default' => '0'
    ],

    [
      'id' => 'blog_post_header_inherit_featured_image',
      'type' => 'nectar_blocks_switch_legacy',
      'title' => esc_html__('Single Post Header Inherits Featured Image', 'nectar-blocks-theme'),
      'subtitle' => esc_html__('Using this will cause the default background of your post header to use your featured image when no other post header image is supplied.', 'nectar-blocks-theme'),
      'desc' => '',
      'required' => [ [ 'blog_header_type', '!=', 'image_under' ] ],
      'default' => '1'
    ],

    [
      'id' => 'blog_header_category_display',
      'type' => 'select',
      'title' => esc_html__('Blog Header Category Display', 'nectar-blocks-theme'),
      'desc' => '',
      'options' => [
        "default" => esc_html__("Display All", 'nectar-blocks-theme'),
        "parent_only" => esc_html__("Parent Categories Only", 'nectar-blocks-theme'),
        "none" => esc_html__("None", 'nectar-blocks-theme'),
      ],
      'default' => 'default'
    ]
    ];

    return [
      'section_id' => 'blog-single-post-header-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Single Post Header', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_archive_header() {
    $controls = [
      [
        'id' => 'blog_archive_bg_functionality',
        'type' => 'select',
        'title' => esc_html__('Blog Archive Header Type', 'nectar-blocks-theme'),
        'options' => [
          'image' => esc_html__('Image Background', 'nectar-blocks-theme'),
          'color' => esc_html__('Color Background', 'nectar-blocks-theme'),
        ],
        'default' => 'color'
      ],

      [
        'id' => 'blog_archive_bg_color',
        'type' => 'color',
        'title' => esc_html__('Blog Archive Header BG Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#b6c1ff',
        'required' => [[ 'blog_archive_bg_functionality', '=', 'color' ]],
        'transparent' => false
      ],
      [
        'id' => 'blog_archive_bg_text_color',
        'type' => 'color',
        'title' => esc_html__('Blog Archive Header Text Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#000000',
        'required' => [[ 'blog_archive_bg_functionality', '=', 'color' ]],
        'transparent' => false
      ],

      [
        'id' => 'blog_archive_bg_image',
        'type' => 'media',
        'title' => esc_html__('Blog Archive Header Background Image', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload an optional background that will be used as the default on all blog archive pages.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [[ 'blog_archive_bg_functionality', '=', 'image' ]],
      ],

      [
        'id' => 'blog_archive_bg_color_layout',
        'type' => 'select',
        'title' => esc_html__('Blog Archive Background Color Layout', 'nectar-blocks-theme'),
        'required' => [[ 'blog_archive_bg_functionality', '=', 'color' ]],
        'options' => [
          'default' => esc_html__('Default (Solid)', 'nectar-blocks-theme'),
          'gradient' => esc_html__('Gradient', 'nectar-blocks-theme'),
        ],
        'default' => 'gradient'
      ],

      [
        'id' => 'blog_archive_text_alignment',
        'type' => 'select',
        'title' => esc_html__('Blog Archive Text Alignment', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'left' => esc_html__('Left', 'nectar-blocks-theme'),
          'center' => esc_html__('Center', 'nectar-blocks-theme'),
          'right' => esc_html__('Right', 'nectar-blocks-theme'),
        ],
        'default' => 'center'
      ],

      [
        'id' => 'blog_archive_format',
        'type' => 'select',
        'title' => esc_html__('Blog Archive Format', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'minimal' => esc_html__('Minimal', 'nectar-blocks-theme'),
        ],
        'default' => 'minimal'
      ],

      [
        'id' => 'blog_archive_author_gravatar',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Blog Archive Author Gravatar', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ]
    ];

    return [
      'section_id' => 'blog-archive-header-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Archive Header', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_functionality() {
    $controls = [
      // array(
      //   'id' => 'blog_lazy_load',
      //   'type' => 'nectar_blocks_switch_legacy',
      //   'title' => esc_html__('Lazy Load Blog Images', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('Enabling this will load all featured images within the blog element via a lazy load method for higher performance.', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'default' => '0'
      // ),
       [
         'id' => 'post_date_functionality',
         'type' => 'select',
         'title' => esc_html__('Post Date Functionality', 'nectar-blocks-theme'),
         'desc' => '',
         'options' => [
           "published_date" => esc_html__("Show Published Date", 'nectar-blocks-theme'),
           "last_edited_date" => esc_html__("Show Last Edited Date", 'nectar-blocks-theme'),
         ],
         'default' => 'published_date'
       ],
       [
         'id' => 'blog_pagination_type',
         'type' => 'select',
         'title' => esc_html__('Pagination Type', 'nectar-blocks-theme'),
         'subtitle' => esc_html__('Please select your pagination type here.', 'nectar-blocks-theme'),
         'desc' => '',
         'options' => [
           'default' => esc_html__('Default', 'nectar-blocks-theme'),
           // 'infinite_scroll' => esc_html__('Infinite Scroll', 'nectar-blocks-theme')
         ],
         'default' => 'default'
       ]
    ];

    return [
      'section_id' => 'blog-functionality-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Functionality', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_post_meta() {
    $controls = [
      [
        'id' => 'blog_single_meta_info',
        'type' => 'info',
        'style' => 'success',
        'title' => esc_html__('Single Post Template', 'nectar-blocks-theme'),
        'icon' => 'el-icon-info-sign',
        'desc' => esc_html__( 'Use the following options to control what meta information will be shown on your single post template.', 'nectar-blocks-theme')
      ],
      [
        'id' => 'blog_remove_single_date',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Single Post Date', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'blog_remove_single_author',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Single Post Author', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'blog_remove_single_comment_number',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Single Post Comment Number', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
       'id' => 'blog_remove_single_reading_dur',
       'type' => 'nectar_blocks_switch_legacy',
       'title' => esc_html__('Remove Single Estimated Reading Time', 'nectar-blocks-theme'),
       'subtitle' => '',
       'desc' => '',
       'default' => '1'
     ],
      [
        'id' => 'blog_archive_meta_info',
        'type' => 'info',
        'style' => 'success',
        'title' => esc_html__('Blog Archive (Post Grid/List) Template', 'nectar-blocks-theme'),
        'icon' => 'el-icon-info-sign',
        'desc' => esc_html__( 'Use the following options to control what meta information will be shown on your posts in the main post query.', 'nectar-blocks-theme')
      ],
      [
        'id' => 'blog_remove_post_date',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Post Date', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'blog_remove_post_author',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Post Author', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'blog_remove_post_categories',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Categories', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ]
    ];

    return [
      'section_id' => 'blog-post-meta-section',
      'settings' => [
        'panel' => 'blog-panel',
        'title' => esc_html__( 'Post Meta', 'nectar-blocks-theme' ),
        'priority' => 3
      ],
      'controls' => $controls
    ];
  }
}
