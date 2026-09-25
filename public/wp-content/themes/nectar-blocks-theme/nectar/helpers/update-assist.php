<?php

/**
 * Deprecated function names upgrade assist.
 *
 * Will prevent fatal errors from occurring if user
 * updates and has outdated templates/function overrides
 * which contain commonly used functions which have been renamed
 * to have a unique prefix. These fallback helpers are
 * slated to be removed on November 30th, 2019.
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 1.0
 */

 // Exit if accessed directly
 if ( ! defined( 'ABSPATH' ) ) {
    exit;
 }

 /**
  * Alias for nectar_using_page_header
  */
 if( ! function_exists('using_page_header') ) {
   function using_page_header($post_id) {

     return nectar_using_page_header($post_id);

   }
 }

 /**
  * Alias for nectar_grab_ids_from_gallery
  */
 if( ! function_exists('grab_ids_from_gallery') ) {
   function grab_ids_from_gallery($post_id) {

     return nectar_grab_ids_from_gallery($post_id);

   }
 }

 /**
  * @deprecated since 10.5
  */
 if ( ! function_exists( 'nectar_current_page_url' ) ) {
   function nectar_current_page_url() {
     return '';
   }
 }

 /**
  * Alias for nectar_current_page_url
  */
 if( ! function_exists('current_page_url') ) {
   function current_page_url() {

     return nectar_current_page_url();

   }
 }

 /**
  * Alias for nectar_shortcode_empty_paragraph_fix
  */
 if( ! function_exists('shortcode_empty_paragraph_fix') ) {
   function shortcode_empty_paragraph_fix($content) {

     return nectar_shortcode_empty_paragraph_fix($content);

   }
 }

 /**
  * Portfolio
  *
  * Alias for nectar_project_single_controls
  * Alias for nectar_get_page_by_title_search
  */
  if( ! function_exists('project_single_controls') && function_exists('nectar_project_single_controls') ) {
    function project_single_controls() {

      return nectar_project_single_controls();

    }
  }

  if( ! function_exists('get_page_by_title_search') && function_exists('nectar_get_page_by_title_search') ) {
    function get_page_by_title_search() {

      return nectar_get_page_by_title_search();

    }
  }

 /**
  * WooCommerce
  *
  * Alias for nectar_product_thumbnail_with_cart
  * Alias for nectar_product_thumbnail_with_cart_alt
  * Alias for nectar_product_thumbnail_material
  * Alias for nectar_product_thumbnail_minimal
  */
 if( ! function_exists('product_thumbnail_with_cart') ) {
   function product_thumbnail_with_cart() {

     return nectar_product_thumbnail_with_cart();

   }
 }

 if( ! function_exists('product_thumbnail_with_cart_alt') ) {
   function product_thumbnail_with_cart_alt() {

     return nectar_product_thumbnail_with_cart_alt();

   }
 }

 if( ! function_exists('product_thumbnail_material') ) {
   function product_thumbnail_material() {

     return nectar_product_thumbnail_material();

   }
 }

 if( ! function_exists('product_thumbnail_minimal') ) {
   function product_thumbnail_minimal() {

     return nectar_product_thumbnail_minimal();

   }
 }

 /**
  * Page full screen rows removal aliases.
  *
  * The full screen rows feature was removed along with support for
  * leftover Salient page metabox settings. These fallbacks keep child
  * themes with outdated template copies or hook references from fataling.
  */
 if( ! function_exists('nectar_get_full_page_options') ) {
   function nectar_get_full_page_options() {

     return [
       'page_full_screen_rows' => '',
       'page_full_screen_rows_animation' => '',
       'page_full_screen_rows_animation_speed' => '',
       'page_full_screen_rows_anchors' => '',
       'page_full_screen_rows_dot_navigation' => '',
       'page_full_screen_rows_footer' => '',
       'page_full_screen_rows_content_overflow' => '',
       'page_full_screen_rows_bg_img_animation' => '',
       'page_full_screen_rows_mobile_disable' => '',
     ];

   }
 }

 if( ! function_exists('nectar_using_pfsr_class') ) {
   function nectar_using_pfsr_class( $classes ) {

     return $classes;

   }
 }

 if( ! function_exists('nectar_using_pfsr_editor_class') ) {
   function nectar_using_pfsr_editor_class( $classes ) {

     return $classes;

   }
 }

 if( ! function_exists('nectar_add_pfsr_bodyclass') ) {
   function nectar_add_pfsr_bodyclass() {}
 }

 if( ! function_exists('nectar_fullpage_markup_open') ) {
   function nectar_fullpage_markup_open() {}
 }

 if( ! function_exists('nectar_fullpage_markup_close') ) {
   function nectar_fullpage_markup_close() {}
 }
