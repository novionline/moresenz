<?php

namespace Nectar\Dynamic_Data\Sources;

use Nectar\Utilities\Log;

/**
 * Other Posts
 * @since 2.0.0
 * @version 2.0.0
 */
class Other_Posts {
  private static $instance;

  function __construct() {
    $this->hooks();
  }

  public static function get_instance() {
    if (! self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function hooks() {
    add_filter( 'nectar_blocks_dynamic_data/otherPosts/content', [ $this, 'get_content' ], 10, 3 );
  }

  /**
   * Parses the block content for dynamic content and returns the modified content.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $args [post_id, field]
   * @return mixed Array or String
   */
  public function get_content($content, $args, $is_editor) {

    $field_map = [
      'post_title' => 'render_post_title',
      'post_url' => 'render_post_url',
      'post_id' => 'render_post_id',
      'post_slug' => 'render_post_slug',
      'post_taxonomy' => 'render_post_taxonomy',
      'post_excerpt' => 'render_post_excerpt',
      'post_date' => 'render_post_date',
      'post_date_modified' => 'render_post_date_modified',
      'post_type' => 'render_post_type',
      'post_status' => 'render_post_status',

      'author_name' => 'render_author_name',
      'author_id' => 'render_author_id',
      'author_url' => 'render_author_url',
      'author_first_name' => 'render_author_first_name',
      'author_last_name' => 'render_author_last_name',
      'author_bio' => 'render_author_bio',

      'comment_number' => 'render_comment_number',
      'comment_status' => 'render_comment_status',

      '_nectar_portfolio_video' => 'render_video_url',
    ];

    if ( $args['replacement_type'] === 'image' ) {
      // full img replacement
      $field_map['featured_image'] = 'render_featured_image_in_content';
      $field_map['author_profile_picture'] = 'render_author_profile_picture_in_content';
    } else {
      // attribute replacement
      $field_map['featured_image'] = 'render_featured_image';
      $field_map['author_profile_picture'] = 'render_author_profile_picture';
    }

    if (array_key_exists($args['field'], $field_map)) {
      return $this->{$field_map[$args['field']]}($args);
    }

    // Fallback to custom meta fields.
    $custom_meta_render = $this->render_custom_meta_field($args);
    if ($custom_meta_render && is_string($custom_meta_render)) {
      return $custom_meta_render;
    }

    Log::info('The field type provided is not valid', [
      'field' => $args['field']
    ]);
    return '';
  }

  protected function get_post_data($post_id, $field = null) {
    $post = get_post($post_id);
    if (! $post) {
      return null;
    }

    if ($field && property_exists($post, $field)) {
      return $post->$field;
    }

    return $post;
  }

  public function render_post_title($args) {
    return $this->get_post_data($args['post_id'], 'post_title');
  }

  public function render_post_url($args) {
    return get_permalink($args['post_id']);
  }

  public function render_post_id($args) {
    return $args['post_id'];
  }

  public function render_post_slug($args) {
    return $this->get_post_data($args['post_id'], 'post_name');
  }

  public function render_post_taxonomy($args) {
    return get_the_terms($args['post_id'], 'category');
  }

  public function render_post_excerpt($args) {
    if (post_password_required()) {
      return;
    }

    // Ensure 'attributes' key exists before accessing its values
    $attributes = [];

    if (isset($args['attributes']) && is_array($args['attributes'])) {
      $attributes = $args['attributes'];
    }

    $limit = 30;
    if (isset($attributes['length'])) {
      $limit = (int) $attributes['length'];
    }

    $strip_html = true;
    if (isset($attributes['stripHtml'])) {
      $strip_html = (bool) $attributes['stripHtml'];
    }

    if (has_excerpt($args['post_id'])) {
      $the_excerpt = get_the_excerpt($args['post_id']);
    } else {
      $the_excerpt = get_post_field('post_content', $args['post_id']);
    }

    // Strip all shortcodes
    $the_excerpt = preg_replace('/\[[^\]]+\]/', '', $the_excerpt);

    // Strip HTML if enabled
    if ($strip_html) {
      $the_excerpt = wp_strip_all_tags($the_excerpt);
    }

    // Normalize spaces to ensure only one space between words
    $the_excerpt = preg_replace('/\s+/', ' ', trim($the_excerpt));

    return wp_trim_words($the_excerpt, $limit);
  }

  public function render_post_date($args) {
    return get_the_date('', $args['post_id']);
  }

  public function render_post_date_modified($args) {
    $modified = get_the_modified_date('', $args['post_id']);
    if ( empty( $modified ) ) {
      return $this->render_post_date($args);
    }
    return $modified;
  }

  public function render_post_type($args) {
    return $this->get_post_data($args['post_id'], 'post_type');
  }

  public function render_post_status($args) {
    return $this->get_post_data($args['post_id'], 'post_status');
  }

  public function render_author_name($args) {
    $post = $this->get_post_data($args['post_id']);
    return $post ? get_the_author_meta('display_name', $post->post_author) : null;
  }

  public function render_author_first_name($args) {
    $post = $this->get_post_data($args['post_id']);
    return $post ? get_the_author_meta('first_name', $post->post_author) : null;
  }

  public function render_author_last_name($args) {
    $post = $this->get_post_data($args['post_id']);
    return $post ? get_the_author_meta('last_name', $post->post_author) : null;
  }

  public function render_author_id($args) {
    return $this->get_post_data($args['post_id'], 'post_author');
  }

  public function render_author_url($args) {
    $post = $this->get_post_data($args['post_id']);
    return $post ? get_author_posts_url($post->post_author) : null;
  }

  public function render_author_bio($args) {
    $post = $this->get_post_data($args['post_id']);
    return $post ? get_the_author_meta('description', $post->post_author) : null;
  }

  public function render_author_profile_picture_in_content($args) {
    $post = $this->get_post_data($args['post_id']);
    if (! $post) {
      return '';
    }

    $author_id = $post->post_author;
    $author = get_user_by('ID', $author_id);
    if (! $author) {
      return '';
    }

    $image_size = 'full';
    $image_width = 150;
    $attributes = [];
    if (isset($args['attributes']) && is_array($args['attributes'])) {
      $attributes = $args['attributes'];
    }
    if (isset($attributes['size'])) {
      $image_size = $attributes['size'];
      $image_width_result = self::get_wp_image_size_width($image_size);
      if ( $image_width_result )  {
        $image_width = $image_width_result;
      }
    }

    $avatar_url = get_avatar_url($author_id, ['size' => $image_width]);
    return '<img src="' . esc_attr($avatar_url) . '" alt="' . esc_attr($author->display_name) . '" />';
  }

  public function render_author_profile_picture($args) {
    $post = $this->get_post_data($args['post_id']);
    if (! $post) {
      return '';
    }

    $author_id = $post->post_author;
    $author = get_user_by('ID', $author_id);
    if (! $author) {
      return '';
    }

    $image_size = 'full';
    $image_width = 150;
    $attributes = [];
    if (isset($args['attributes']) && is_array($args['attributes'])) {
      $attributes = $args['attributes'];
    }
    if (isset($attributes['size'])) {
      $image_size = $attributes['size'];
      $image_width_result = self::get_wp_image_size_width($image_size);
      if ( $image_width_result )  {
        $image_width = $image_width_result;
      }
    }

    $avatar_url = get_avatar_url($author_id, ['size' => $image_width]);
    return $avatar_url;
  }

  public function render_comment_number($args) {
    return get_comments_number($args['post_id']);
  }

  public function render_comment_status($args) {
    $comments = get_comments([
        'post_id' => $args['post_id'],
        'status' => 'approve',
    ]);
    return count($comments) > 0 ? __('Open', 'nectar-blocks') : __('Closed', 'nectar-blocks');
  }

  public static function get_wp_image_size_width($size) {
    global $_wp_additional_image_sizes;

    if (isset($_wp_additional_image_sizes[$size])) {
      return $_wp_additional_image_sizes[$size]['width'];
    } else {
      return get_option($size . '_size_w');
    }
  }

  public function get_thumbnail_data( $args ) {
    $post_id = $args['post_id'] ?? 0;
    if ( ! $post_id ) {
      return '';
    }

    $thumbnail_id = get_post_thumbnail_id( $post_id );
    if ( ! $thumbnail_id ) {
      return '';
    }

    $attachment = get_post( $thumbnail_id );
    if ( ! $attachment ) {
      return '';
    }

    $size = $args['attributes']['size'] ?? 'full';

    $value = wp_get_attachment_image_src( $thumbnail_id, $size );

    return [
      'id' => $thumbnail_id,
      'attachment' => $attachment,
      'size' => $size,
      'value' => $value,
    ];
  }

  public function render_featured_image( $args ) {

    $thumbnail_data = $this->get_thumbnail_data($args);
    if (empty($thumbnail_data)) {
      return '';
    }
    return $thumbnail_data['value'][0];

  }

  public function render_featured_image_in_content($args) {
    $image_id = $args['post_id'] ?? 0;

    if (! $image_id) {
      return '';
    }

    $thumbnail_id = get_post_thumbnail_id( $image_id );
    if ( ! $thumbnail_id ) {
      return '';
    }

    $image_size = 'full';
    $attributes = [];
    if (isset($args['attributes']) && is_array($args['attributes'])) {
      $attributes = $args['attributes'];
    }
    if (isset($attributes['size'])) {
      $image_size = $attributes['size'];
    }

    $image_attributes = [
      'class' => 'wp-image-' . $thumbnail_id
    ];

    // Add WordPress's automatic loading optimization attributes
    $loading_attrs = wp_get_loading_optimization_attributes('img', $image_attributes, 'wp_get_attachment_image');
    $image_attributes = array_merge($image_attributes, $loading_attrs);

    // class name
    if (isset($args['class']) && ! empty($args['class'])) {
      $image_attributes['class'] = $args['class'];
    }

    return wp_get_attachment_image($thumbnail_id, $image_size, false, $image_attributes);
  }

  public function render_custom_meta_field($args) {
    $post_id = $args['post_id'];
    $field = $args['field'];
    $custom_field = get_post_meta($post_id, $field, true);

    if (! is_string($custom_field)) {
      return '';
    }

    $custom_field = trim($custom_field);
    if (strlen($custom_field) <= 1) {
      return '';
    }

    return $custom_field;
  }

  public function render_video_url($args) {
    $video_url = get_post_meta($args['post_id'], $args['field'], true);
    if (is_array($video_url) && isset($video_url['source'])) {
      return esc_attr($video_url['source']['url']);
    }
    return '';
  }
}
