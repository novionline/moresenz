<?php

namespace Nectar\Notifications;

class NotificationManager {
    private static $instance;

    private $slug;

    private $message;

    public function __construct($slug, $message) {
        $this->slug = 'nectarblocks_notice_' . $slug;
        $this->message = $message;

        add_action('admin_notices', [ $this, 'display_notification' ]);
        add_action("wp_ajax_{$this->slug}_dismissed", [ $this, 'handle_dismiss' ]);
    }

    public function display_notification() {
        $dismissed = get_option("{$this->slug}_dismissed");
        if (! $dismissed) {
            echo '<div class="notice notice-warning is-dismissible ' . esc_attr($this->slug) . '"><p>' .
                $this->message .
                '</p></div>
                <script>
                    (function() {
                        document.body.addEventListener("click", function(e) {
                            if (e.target.matches(".notice.' . esc_js($this->slug) . ' button.notice-dismiss")) {
                                wp.ajax.post("' . esc_js($this->slug) . '_dismissed");
                            }
                        });
                    })();
                </script>';
        }
    }

    public function handle_dismiss() {
        update_option("{$this->slug}_dismissed", true);
    }

    public static function create_notification($slug, $message) {
        if (! isset(self::$instance[$slug])) {
            self::$instance[$slug] = new self($slug, $message);
        }

        return self::$instance[$slug];
    }
}
