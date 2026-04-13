<?php

namespace Nectar\API;

/**
 * Access_Utils
 * @version 0.0.4
 * @since 0.0.4
 */
class Access_Utils {
  function __construct() {}

  /**
   * has_access
   * Checks if a user has access.
   * @since 0.0.4
   */
  public static function has_access() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    if ( is_user_logged_in() ) {
      return true;
    }
    return false;
  }

  /**
   * can_edit_others_posts
   * Checks if a user can edit others posts.
   * @since 2.1.0
   */
  public static function can_edit_others_posts() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    return current_user_can('edit_others_posts');
  }

  /**
   * can_edit_posts
   * Checks if a user has access.
   * @since 0.0.4
   */
  public static function can_edit_posts() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    if ( is_user_logged_in() ) {
      return current_user_can('edit_posts');
    }
    return false;
  }

  /**
   * can_upload_files
   * Checks if a user can upload files.
   * @since 0.0.7
   */
  public static function can_upload_files() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    if ( is_user_logged_in() ) {
      return current_user_can('upload_files');
    }
    return false;
  }

   /**
   * can_manage_options
   * Checks if a user can manage options.
   * @since 0.0.7
   */
  public static function can_manage_options() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    if ( is_user_logged_in() ) {
      return current_user_can('manage_options');
    }
    return false;
  }

  /**
   * is_super_admin
   * Checks if a user can manage options.
   * @since 1.1.0
   */
  public static function is_super_admin() {
    if (NECTAR_BUILD_MODE === 'development') {
      return true;
    }

    if ( is_user_logged_in() ) {
      $permission_cap = ( is_multisite() ) ? 'manage_sites' : 'manage_options';
      return current_user_can($permission_cap);
    }
    return false;
  }
}
