<?php

if (is_blog_installed()) {

    //load third party MU plugins
    require WPMU_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php';

    //autoload Novi Online core plugin
    require WPMU_PLUGIN_DIR . '/novionline/wordpress-core-plugin/wordpress-core-plugin.php';

    //load Novi Login
    require WPMU_PLUGIN_DIR . '/novionline/novi-login/novi-login.php';            

    //load Novi Code Snippets (requires Core + ACF)
    if (class_exists('NoviOnline\Core') && function_exists('acf_add_local_field_group')) {
        require WPMU_PLUGIN_DIR . '/novionline/novi-code-snippets/novi-code-snippets.php';
    }
}