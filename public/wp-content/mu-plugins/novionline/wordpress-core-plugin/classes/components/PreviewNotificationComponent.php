<?php

namespace NoviOnline\Core;



/**
 * class PreviewNotificationComponent
 * @package NoviOnline\BlockLibrary
 */
class PreviewNotificationComponent extends Singleton
{
    /**
     * PreviewNotificationComponent constructor
     */
    protected function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueNotificationAssets']);
    }

    /**
     * Get Notification HTML
     * @param string $notificationTitle
     * @param string $notificationText
     * @param string $notificationType 'default' | 'informational' | 'success' | 'warning' | 'error'
     * @return string
     */
    public static function getNotificationHtml(string $notificationTitle, string $notificationText, string $notificationType = 'default'): string
    {
        $html = Partial::render('components/preview-notification', [
            'notificationTitle' => $notificationTitle,
            'notificationText' => $notificationText,
            'notificationType' => $notificationType,
        ], false, WCP_PARTIAL_PATH);

        return apply_filters('novi_notification_html', $html);
    }

    /**
     * Enqueue notification assets
     * @return void
     */
    public static function enqueueNotificationAssets(): void
    {
        $notificationCss = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'component-notification.scss');
        if ($notificationCss) wp_enqueue_style('notification_styles', $notificationCss, [], null);
    }
}
