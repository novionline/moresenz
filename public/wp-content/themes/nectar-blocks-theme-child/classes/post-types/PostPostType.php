<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class PostPostType
 * @package NoviOnline
 */
class PostPostType extends Singleton {

    /**
     * PostPostType constructor.
     */
    protected function __construct() {
        //disable default admin pages
        add_action('admin_menu', [$this, 'disableDefaultAdminPages']);
    }

    /**
     * Disable default admin pages (posts / comments)
     * @return void
     */
    public static function disableDefaultAdminPages(): void {
        remove_menu_page('edit.php');
        remove_menu_page('edit-comments.php');
    }
}
