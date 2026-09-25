<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * class LoaderComponent
 * @package NoviOnline\Core
 */
class LoaderComponent extends Singleton
{

    /**
     * Init loader assets
     * @return void
     */
    public function initLoaderAssets(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueLoaderAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueLoaderAssets']);
    }

    /**
     * Get loader HTML
     * @param int $size
     * @param string $theme
     * @param bool $withText
     * @return string
     */
    public function getLoaderHtml(int $size = 40, string $theme = 'dark', bool $withText = true, $loaderText = ''): string
    {
        if (!$loaderText) $loaderText = __("Loading..", Core::TEXT_DOMAIN);
        $html = Partial::render('components/loader', [
            'size' => $size,
            'theme' => $theme,
            'withText' => $withText,
            'loaderText' => $loaderText
        ], false, WCP_PARTIAL_PATH);

        return apply_filters('novi_loader_html', $html);
    }

    /**
     * Enqueue loader assets
     * @return void
     */
    public static function enqueueLoaderAssets(): void
    {
        $loaderCss = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'component-loader.scss');
        if ($loaderCss) wp_enqueue_style('loader_styles', $loaderCss, [], null);
    }
}