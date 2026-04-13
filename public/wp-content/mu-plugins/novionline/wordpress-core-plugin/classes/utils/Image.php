<?php

namespace NoviOnline\Core;

/**
 * class Image
 * @package NoviOnline\Core
 */
class Image
{

    /**
     * Get url from given attachment id
     * @param int|string $attachmentId
     * @param string $size
     * @return string|false
     */
    public static function urlFromId(int|string $attachmentId, string $size = 'full'): string|false
    {
        if (!$attachmentId) return false;
        $attachment = wp_get_attachment_image_src($attachmentId, $size);
        return $attachment ? $attachment[0] : false;
    }

    /**
     * Get alt for given attachment ID
     * @param $attachmentId
     * @return string
     */
    public static function altFromId($attachmentId): string
    {
        if (!$attachmentId) return '';
        $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        return !empty($alt) ? $alt : get_the_title($attachmentId);
    }

    /**
     * Get image dimensions by ID / size
     * @param int|string $attachmentId
     * @param string $size
     * @return \stdClass|false
     */
    public static function dimensionsFromId(int|string $attachmentId, string $size = 'full'): \stdClass|false
    {
        if (!$attachmentId) return false;
        $attachment = wp_get_attachment_image_src($attachmentId, $size);

        if ($attachment) {
            $dimensions = new \stdClass();
            $dimensions->width = $attachment[1];
            $dimensions->height = $attachment[2];
            return $dimensions;
        }

        return false;
    }

    /**
     * Get image dimensions by image size
     * @param $size
     * @return \stdClass|false
     */
    public static function dimensionsFromImageSize($size = 'full'): \stdClass|false
    {
        global $_wp_additional_image_sizes;

        if ($_wp_additional_image_sizes && isset($_wp_additional_image_sizes[$size])) {
            $dimensions = new \stdClass();
            $dimensions->width = $_wp_additional_image_sizes[$size]['width'] ?? 0;
            $dimensions->height = $_wp_additional_image_sizes[$size]['height'] ?? 0;
            return $dimensions;
        }

        return false;
    }

    /**
     * Get image sizes
     * @return array
     */
    public static function getImageSizes(): array
    {
        global $_wp_additional_image_sizes;

        $imageSizes = [];
        $defaultImageSizes = get_intermediate_image_sizes();

        foreach ($defaultImageSizes as $imageSize) {
            $imageSizes[$imageSize]['width'] = intval(get_option("{$imageSize}_size_w"));
            $imageSizes[$imageSize]['height'] = intval(get_option("{$imageSize}_size_h"));
            $imageSizes[$imageSize]['crop'] = get_option("{$imageSize}_crop") ? get_option("{$imageSize}_crop") : false;
        }

        if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
            $imageSizes = array_merge($imageSizes, $_wp_additional_image_sizes);
        }

        return $imageSizes;
    }
}