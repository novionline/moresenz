<?php

namespace Nectar\Dynamic_Data;

/**
 * Dynamic Helpers
 * @since 2.0.0
 * @version 2.0.0
 */
class Dynamic_Helpers {
  public static $NB_DYNAMIC_PATTERN = '/\{\{!!nb_dynamic\/([\w]+)\/(\d+)\/([\w_]+)\/?(?:\?(.*?))?!!\}\}/';

  public static $NB_DYNAMIC_TEXT_PATTERN = '/<span[^>]*\bdata-nectarblocks-dynamic-text="\{\{!!nb_dynamic\/([\w]+)\/(\d+)\/([\w_]+)\/?(?:\?(.*?))?!!\}\}"[^>]*>(.*?)\s*<\/span>?/';

  public static $NB_DYNAMIC_IMAGE_PATTERN = '/<img[^>]*\bdata-nectar-dynamic-img="\{\{!!nb_dynamic\/([\w]+)\/(\d+)\/([\w_]+)\/?(?:\?(.*?))?!!\}\}"[^>]*>/';

  public static function post_fiends() {
    $fields = [
      // Post Group
      'post-title' => [
        'title' => __( 'Post Title', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-url' => [
        'title' => __( 'Post URL', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
        'type' => 'link',
      ],
      'post-id' => [
        'title' => __( 'Post ID', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-slug' => [
        'title' => __( 'Post Slug', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-excerpt' => [
        'title' => __( 'Post Excerpt', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-date' => [
        'title' => __( 'Post Date', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-modified' => [
        'title' => __( 'Post Modified', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-type' => [
        'title' => __( 'Post Type', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],
      'post-status' => [
        'title' => __( 'Post Status', 'nectar-blocks' ),
        'group' => __( 'Post', 'nectar-blocks' ),
      ],

      // Author Group
      'author-name' => [
        'title' => __( 'Author Name', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
      ],
      'author-id' => [
        'title' => __( 'Author ID', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
      ],
      'author-posts-url' => [
        'title' => __( 'Author URL', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
        'type' => 'link',
      ],
      'author-profile-picture' => [
        'title' => __( 'Author Profile Picture URL', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
        'type' => 'image-url',
      ],
      'author-first-name' => [
        'title' => __( 'Author First Name', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
      ],
      'author-last-name' => [
        'title' => __( 'Author Last Name', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
      ],
      'author-bio' => [
        'title' => __( 'Author Bio', 'nectar-blocks' ),
        'group' => __( 'Author', 'nectar-blocks' ),
      ],
      // Comment Group
      'comment-number' => [
        'title' => __( 'Comment Number', 'nectar-blocks' ),
        'group' => __( 'Comment', 'nectar-blocks' ),
      ],
      // 'comment-status' => [
      //   'title' => __( 'Comment Status', 'nectar-blocks' ),
      //   'group' => __( 'Comment', 'nectar-blocks' ),
      // ],
      // Media Group
      'featured-image-data' => [
        'title' => __( 'Featured Image URL', 'nectar-blocks' ),
        'group' => __( 'Media', 'nectar-blocks' ),
        'type' => 'image-url'
      ],
    ];

    return $fields;
  }

  /**
   * Get the field title.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $key The key of the field.
   * @return string The title of the field.
   */
  public static function get_field_title($key) {
    return ucwords(str_replace('_', ' ', $key));
  }

  /**
   * Matches a single occurrence of the NB Dynamic Pattern in a string.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $dynamic_string The string to parse.
   * @return array Returns a single DynamicDataFields.
   */
  public static function parse_dynamic_field($dynamic_string) {
    if (preg_match(Dynamic_Helpers::$NB_DYNAMIC_PATTERN, $dynamic_string, $match)) {

      if (count($match) < 4) {
        // Log::error('parse_dynamic_field: Bad to match NB Dynamic Pattern: ' . json_encode($match));
        throw new \Exception('Unable to match count NB Dynamic Pattern: ' . $dynamic_string . ' ::' . $match);
      }

      return [
        'source' => $match[1], // e.g., "currentPost"
        'post_id' => $match[2], // e.g., "0"
        'field' => $match[3],   // e.g., "post_slug"
        'attributes' => isset($match[4]) ? self::parse_attributes($match[4]) : [], // e.g., "param1=value1&param2=value2"
      ];
    }

    throw new \Exception('Unable to parse NB Dynamic Pattern: ' . $dynamic_string);
  }

  /**
   * Parses a string of attributes into an associative array.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $attributes A string of attributes in query string format.
   * @return array An associative array of parsed attributes.
   */
  public static function parse_attributes($attributes) {

    $parsed_attributes = [];
    if (empty($attributes)) {
      return $parsed_attributes;
    }

    $attributes = html_entity_decode($attributes, ENT_QUOTES, 'UTF-8');
    parse_str($attributes, $parsed_attributes);
    return $parsed_attributes;
  }

  /**
   * Match all occurrences of the NB Dynamic Pattern in a string.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $text The text to parse.
   * @return array Returns an array of match parsed DynamicDataFields.
   */
  public static function parse_dynamic_fields($text) {
    if (preg_match_all(Dynamic_Helpers::$NB_DYNAMIC_PATTERN, $text, $matches, PREG_SET_ORDER)) {
      $results = [];

      foreach ( $matches as $match ) {
        array_push($results, [
          'source' => $match[1], // e.g., "currentPost"
          'post_id' => $match[2], // e.g., "0"
          'field' => $match[3],   // e.g., "post_slug",
          'attributes' => isset($match[4]) ? self::parse_attributes($match[4]) : [], // e.g., "param1=value1&param2=value2"
        ]);
      }

      return $results;
    }

    throw new \Exception('Bad match NB Dynamic Pattern.');
  }
}