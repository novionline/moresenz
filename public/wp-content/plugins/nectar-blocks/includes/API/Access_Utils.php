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
   * @since 0.0.4
   */
  public static function has_access() {
    return is_user_logged_in();
  }

  /**
   * can_edit_others_posts
   * Pass the explicit $user_id (e.g. from a meta auth_callback) to authorize that user rather
   * than the ambient current one; called with no argument it keeps the original behavior.
   * @since 2.1.0
   */
  public static function can_edit_others_posts( $user_id = null ) {
    if ( null !== $user_id ) {
      return user_can( $user_id, 'edit_others_posts' );
    }
    return is_user_logged_in() && current_user_can('edit_others_posts');
  }

  /**
   * can_edit_posts
   * Pass the explicit $user_id (e.g. from a meta auth_callback) to authorize that user rather
   * than the ambient current one; called with no argument it keeps the original behavior.
   * @since 0.0.4
   */
  public static function can_edit_posts( $user_id = null ) {
    if ( null !== $user_id ) {
      return user_can( $user_id, 'edit_posts' );
    }
    return is_user_logged_in() && current_user_can('edit_posts');
  }

  /**
   * can_edit_post
   * When called from a meta auth_callback, pass the explicit $user_id from the
   * filter args so the check honors the user being authorized rather than the
   * current one.
   * @since 3.0.0
   */
  public static function can_edit_post( $post_id, $user_id = null ) {
    // Strict null check, not falsy: a meta auth_callback always passes $user_id (0 for a guest),
    // so route that through user_can( 0, … ) (correctly false) rather than falling back to the
    // ambient current_user_can(). Only a direct call with no $user_id ( null ) uses the fallback.
    if ( null !== $user_id ) {
      return user_can( $user_id, 'edit_post', $post_id );
    }
    return is_user_logged_in() && current_user_can( 'edit_post', $post_id );
  }

  /**
   * can_upload_files
   * Pass the explicit $user_id (e.g. from a meta auth_callback) to authorize that user rather
   * than the ambient current one; called with no argument it keeps the original behavior.
   * @since 0.0.7
   */
  public static function can_upload_files( $user_id = null ) {
    if ( null !== $user_id ) {
      return user_can( $user_id, 'upload_files' );
    }
    return is_user_logged_in() && current_user_can('upload_files');
  }

  /**
   * can_manage_options
   * Pass the explicit $user_id (e.g. from a meta auth_callback) to authorize that user rather
   * than the ambient current one; called with no argument it keeps the original behavior.
   * @since 0.0.7
   */
  public static function can_manage_options( $user_id = null ) {
    if ( null !== $user_id ) {
      return user_can( $user_id, 'manage_options' );
    }
    return is_user_logged_in() && current_user_can('manage_options');
  }

  /**
   * can_edit_theme_options
   * Pass the explicit $user_id (e.g. from a meta auth_callback) to authorize that user rather
   * than the ambient current one; called with no argument it keeps the original behavior.
   * @since 3.0.0
   */
  public static function can_edit_theme_options( $user_id = null ) {
    if ( null !== $user_id ) {
      return user_can( $user_id, 'edit_theme_options' );
    }
    return is_user_logged_in() && current_user_can('edit_theme_options');
  }

  /**
   * is_super_admin
   *
   * Delegates to core \is_super_admin(), which consults the network
   * super-admin list directly. The previous manage_sites proxy could grant a
   * non-super-admin holding that network cap (custom role plugins, direct
   * grants). Note core's single-site branch checks delete_users rather than
   * manage_options — both admin-level, a deliberate contract change.
   *
   * @since 1.1.0
   * @version 3.1.1
   */
  public static function is_super_admin() {
    if ( ! is_user_logged_in() ) {
      return false;
    }
    return \is_super_admin();
  }
}
