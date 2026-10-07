<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('cookie_confirm_settings');
