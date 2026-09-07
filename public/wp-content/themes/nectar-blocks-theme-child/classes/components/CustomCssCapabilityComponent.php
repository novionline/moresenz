<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class CustomCssCapabilityComponent
 *
 * Restricts Customizer / Site Editor Additional CSS editing to administrators.
 * Existing custom CSS continues to load on the frontend.
 *
 * @package NoviOnline
 */
class CustomCssCapabilityComponent extends Singleton {

    /**
     * CustomCssCapabilityComponent constructor.
     */
    protected function __construct() {
        add_filter('map_meta_cap', [$this, 'restrictEditCssToAdministrators'], 10, 3);
    }

    /**
     * Deny edit_css for every user that is not an administrator.
     *
     * @param array $caps Capabilities for the meta capability.
     * @param string $cap Capability name.
     * @param int $userId The user ID.
     * @return array
     */
    public function restrictEditCssToAdministrators(array $caps, string $cap, int $userId): array {
        if ($cap !== 'edit_css') {
            return $caps;
        }

        $user = get_userdata($userId);
        if (!$user || !in_array('administrator', (array) $user->roles, true)) {
            $caps[] = 'do_not_allow';
        }

        return $caps;
    }
}
