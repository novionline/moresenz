<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * Class MetaBox
 * @package NoviOnline\Core
 */
class MetaBox
{
    /**
     * Add featured image description in the admin
     * @param string $postType
     * @param string $imageSize
     * @return void
     */
    public static function addFeaturedImageDescription(string $postType = 'post', string $imageSize = 'full'): void
    {
        if (is_admin() && Admin::getCurrentPostType() === $postType) {
            $width = 0;
            $height = 0;
            $sizes = acf_get_image_size($imageSize);
            if ($sizes && isset($sizes['width']) && isset($sizes['height'])) {
                $width = $sizes['width'];
                $height = $sizes['height'];
            }

            if ($width && $height) {
                add_filter('admin_post_thumbnail_html', function ($content) use ($width, $height) {
                    ob_start(); ?>
                    <?php echo $content; ?>
                    <p class="howto">
                        <?php echo sprintf(__("The ideal size for this image is %1s wide and %2s high.", Core::TEXT_DOMAIN), '<code>' . $width . 'px</code>', '<code>' . $height . 'px</code>'); ?>
                    </p>
                    <?php return ob_get_clean();
                }, 1, 100);
            }
        }
    }
}