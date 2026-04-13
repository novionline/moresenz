<?php

namespace NoviOnline\Core;

/**
 * Class Enqueue
 * @package NoviOnline\Core
 */
class Enqueue
{

    /**
     * Get webpack file name by key from manifest
     * @param string $manifestPath
     * @param string $assetKey
     * @return string
     */
    public static function getWebpackAssetUrlByKey(string $manifestPath, string $assetKey): string
    {
        $assetUrl = '';

        //fetch manifest JSON file
        $manifest = JsonFetchComponent::getInstance()->fetch($manifestPath);

        if ($assetKey && $manifest) {
            if (property_exists($manifest, $assetKey)) $assetUrl = untrailingslashit(get_site_url()) . $manifest->$assetKey;
        }

        return $assetUrl;
    }
}
