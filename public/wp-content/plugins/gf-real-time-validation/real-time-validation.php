<?php
/*
* Plugin Name: Real Time Validation For Gravity Forms
* Plugin Url: https://pluginscafe.com/plugin/real-time-validation-for-gravity-forms
* Version: 1.0.4
* Description: This plugin adds an awesome feature that provides instant feedback and guidance in each field, helps prevent errors.
* Author: PluginsCafe
* Author URI: https://pluginscafe.com
* License: GPLv2 or later
* Text Domain: gfrtv
* Domain Path: /languages/
*/

if (!defined('ABSPATH')) {
    exit;
}

define('REAL_TIME_VALIDATION_ADDON_VERSION', '1.0.4');

add_action('gform_loaded', array('GF_Real_Time_Validation_AddOn_Bootstrap', 'load'), 5);

class GF_Real_Time_Validation_AddOn_Bootstrap {

    public static function load() {

        if (!method_exists('GFForms', 'include_addon_framework')) {
            return;
        }
        // are we on GF 2.5+
        define('RTV_GF_MIN_2_5', version_compare(GFCommon::$version, '2.5-dev-1', '>='));

        require_once('class-gfrtv.php');

        GFAddOn::register('GFRTVAddOn');
    }
}

function gf_real_time_validation() {
    return GFRTVAddOn::get_instance();
}
