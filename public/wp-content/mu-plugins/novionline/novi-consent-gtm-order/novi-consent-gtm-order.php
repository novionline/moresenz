<?php

/**
 * Plugin Name:     Novi Consent GTM Order
 * Plugin URI:      https://novionline.nl
 * Description:     Loads CookieConfirm before GTM and prevents Metronet Tag Manager from wiping Consent Mode defaults via dataLayer assignment.
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep CookieConfirm Consent Mode defaults intact and print CMP before Metronet GTM.
 */
final class NoviConsentGtmOrder
{
    private const COOKIE_CONFIRM_HANDLE = 'cookie-confirm-cmp';
    private const DEFAULT_CONSENT_SCRIPT = 'https://assets.cookieconfirm.com/js/consent.js';

    /**
     * Register front-end hooks when both plugins are active.
     */
    public static function init(): void
    {
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', [self::class, 'dequeueCookieConfirmScript'], 20);
        //print cmp first, then buffer only metronet's priority-1 output for the dataLayer rewrite
        add_action('wp_head', [self::class, 'printCookieConfirmScript'], 0);
        add_action('wp_head', [self::class, 'startMetronetBuffer'], 0);
        add_action('wp_head', [self::class, 'endMetronetBuffer'], 2);
    }

    /**
     * Whether CookieConfirm and Metronet Tag Manager are both active.
     */
    private static function bothPluginsActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('cookie-confirm-cmp/cookie-confirm-cmp.php')
            && is_plugin_active('metronet-tag-manager/metronet-tag-manager.php');
    }

    /**
     * Remove CookieConfirm's late async enqueue so we can print it before GTM.
     */
    public static function dequeueCookieConfirmScript(): void
    {
        if (!self::bothPluginsActive()) {
            return;
        }

        //skip when CookieConfirm is configured to load CMP via GTM instead
        $settings = get_option('cookie_confirm_settings', []);
        if (!empty($settings['tagManager'])) {
            return;
        }

        wp_dequeue_script(self::COOKIE_CONFIRM_HANDLE);
        wp_deregister_script(self::COOKIE_CONFIRM_HANDLE);
    }

    /**
     * Print CookieConfirm CMP sync in head before Metronet (priority 1).
     */
    public static function printCookieConfirmScript(): void
    {
        if (!self::bothPluginsActive()) {
            return;
        }

        $settings = get_option('cookie_confirm_settings', []);
        if (!empty($settings['tagManager'])) {
            return;
        }

        $scriptUrl = !empty($settings['script']) && is_string($settings['script'])
            ? $settings['script']
            : self::DEFAULT_CONSENT_SCRIPT;

        //data-noptimize keeps Autoptimize from adding defer (CMP must execute before GTM boots)
        printf(
            "<script src=\"%s\" id=\"cookie-confirm-cmp-js\" data-noptimize=\"1\" data-cfasync=\"false\"></script>\n",
            esc_url($scriptUrl)
        );
    }

    /**
     * Start buffering Metronet's wp_head output so dataLayer assignment can be rewritten.
     */
    public static function startMetronetBuffer(): void
    {
        if (!self::bothPluginsActive()) {
            return;
        }

        ob_start();
    }

    /**
     * Flush Metronet buffer after rewriting destructive dataLayer assignment to push.
     */
    public static function endMetronetBuffer(): void
    {
        if (!self::bothPluginsActive()) {
            return;
        }

        $html = ob_get_clean();
        if ($html === false || $html === '') {
            return;
        }

        //metronet emits: dataLayer = [{...vars...}]; — push the object so consent defaults survive
        $html = preg_replace(
            '/dataLayer\s*=\s*\[\s*(\{.*?\})\s*\]\s*;/s',
            'window.dataLayer = window.dataLayer || []; dataLayer.push($1);',
            $html,
            1
        );

        echo $html;
    }
}

NoviConsentGtmOrder::init();
