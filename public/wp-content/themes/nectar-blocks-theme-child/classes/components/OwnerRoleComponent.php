<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class OwnerRoleComponent
 * @package NoviOnline
 */
class OwnerRoleComponent extends Singleton {

    /**
     * The role name for the owner role.
     * @var string
     */
    const ROLE_OWNER = 'owner';

    /**
     * The transient key to check if the owner role has been checked.
     * @var string
     */
    const TRANSIENT_KEY_OWNER_ROLE_CHECKED = 'owner_role_checked';

    /**
     * Nectar Blocks Menu_Options Modal class name (must match Nectar\Menu_Options\Modal; used to detect caller in user_has_cap backtrace).
     * @var string
     */
    private const NECTAR_MENU_OPTIONS_MODAL_CLASS = 'Nectar\Menu_Options\Modal';

    /**
     * The capabilities that should be removed from the owner role compared to the administrator role.
     * @var array
     */
    public array $capabilitiesToRemove = [

        //don't allow any plugin-related actions
        'activate_plugins',
        'delete_plugins',
        'install_plugins',
        'update_plugins',

        //don't allow updating WP core
        'update_core',

        //don't allow deleting users
        'delete_users',
        'remove_users',

        //don't allow any theme-related actions
        'switch_themes',
        'edit_themes',
        'delete_themes',
        'install_themes',
        'update_themes',
    ];

    /**
     * OwnerRoleComponent constructor.
     */
    protected function __construct() {

        //Register the owner role
        add_action('admin_init', [$this, 'checkAndPossiblyUpdateOwnerRole']);

        //hide admin pages for the owner role
        add_action('admin_menu', [$this, 'hideAdminPagesForOwner'], 100);

        //don't allow the owner to edit, demote or create administrator users
        add_filter('editable_roles', [$this, 'disableCreateEditAdminUsers']);
        add_filter('map_meta_cap', [$this, 'disableEditRemoveAdminUsers'], 10, 4);

        //allow owner to use Nectar Blocks Menu Item Options on Appearance > Menus (no plugin edit required)
        add_filter('user_has_cap', [$this, 'allowOwnerNectarMenuOptions'], 10, 4);
    }

    /**
     * Ensures the owner role exists and is up-to-date.
     * @return void
     */
    public function checkAndPossiblyUpdateOwnerRole(): void {

        //validate if owner role exists
        $ownerRole = get_role(self::ROLE_OWNER);
        $hasOwnerRole = is_a($ownerRole, '\WP_Role');

        //scenario 1 - role does not exit yet -> register the owner role
        if (!$hasOwnerRole) $this->registerOrUpdateOwnerRole();

        //scenario 2 - role exists -> update the owner role capabilities (max every X minutes)
        if ($hasOwnerRole && !get_transient(self::TRANSIENT_KEY_OWNER_ROLE_CHECKED)) {
            $this->updateExistingRoleCapabilities();
        }
    }

    /**
     * Registers the owner role with capabilities based on the administrator's, minus exclusions.
     * @return void
     */
    public function registerOrUpdateOwnerRole(): void {
        $adminRole = get_role('administrator');
        if ($adminRole) {
            $capabilities = array_diff_key($adminRole->capabilities, array_flip($this->capabilitiesToRemove));
            add_role(self::ROLE_OWNER, __('Owner', Theme::TEXT_DOMAIN), $capabilities);
        }
    }

    /**
     * Updates the capabilities of the existing owner role.
     * @return void
     */
    public function updateExistingRoleCapabilities(): void {
        $adminRole = get_role('administrator');
        $ownerRole = get_role(self::ROLE_OWNER);

        //add Gravity Forms capabilities
        $ownerRole->add_cap('gform_full_access');

        if ($adminRole && $ownerRole) {

            //remove unwanted capabilities
            foreach ($this->capabilitiesToRemove as $cap) {
                if ($ownerRole->has_cap($cap)) {
                    $ownerRole->remove_cap($cap);
                }
            }

            //add new capabilities from the admin role
            foreach ($adminRole->capabilities as $cap => $grant) {
                if (!in_array($cap, $this->capabilitiesToRemove)) {
                    if (!$ownerRole->has_cap($cap)) {
                        $ownerRole->add_cap($cap);
                    }
                }
            }

            //prevent checking the owner role capabilities too often
            set_transient(self::TRANSIENT_KEY_OWNER_ROLE_CHECKED, true, (5 * MINUTE_IN_SECONDS));
        }
    }

    /**
     * Filters out roles that should not be editable by the owner.
     * @param array $roles The array of roles.
     * @return array
     */
    public function disableCreateEditAdminUsers(array $roles): array {
        if (self::isOwner() && isset($roles['administrator'])) unset($roles['administrator']);
        return $roles;
    }

    /**
     * Restricts the capabilities of the owner when managing users, particularly with respect to administrator role management.
     * @param array $caps Capabilities for meta capabilities.
     * @param string $cap Capability name.
     * @param int $userId The user ID.
     * @param array $args Additional arguments.
     * @return array Modified capabilities.
     */
    public function disableEditRemoveAdminUsers(array $caps, string $cap, int $userId, array $args): array {
        if (is_admin() && in_array($cap, ['edit_user', 'delete_user']) && isset($args[0]) && self::isOwner()) {
            $otherUser = get_userdata($args[0]);
            if (is_a($otherUser, '\WP_User') && in_array('administrator', $otherUser->roles)) $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    /**
     * Grants administrator capability to owner only on Menus screen and Nectar menu-options AJAX,
     * so Menu Item Options button and load/save work without editing Nectar Blocks plugin.
     * @param array $allcaps All capabilities for the user.
     * @param array $caps Requested capabilities.
     * @param array $args Additional context (e.g. requested cap in $args[0]).
     * @param \WP_User $user The user object.
     * @return array Modified capabilities.
     */
    public function allowOwnerNectarMenuOptions(array $allcaps, array $caps, array $args, $user): array {
        //only for owner role (and not already administrator)
        if (empty($user) || !isset($user->roles) || in_array('administrator', $user->roles)) {
            return $allcaps;
        }
        if (!in_array(self::ROLE_OWNER, $user->roles)) {
            return $allcaps;
        }

        $requested = isset($args[0]) ? $args[0] : '';
        $screen = function_exists('get_current_screen') && get_current_screen() ? get_current_screen()->id : null;
        $pagenow = isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '';
        //screen can be null when Nectar checks administrator early (e.g. before current_screen is set), so use pagenow as fallback
        $isNavMenusScreen = is_admin() && ($screen === 'nav-menus' || $pagenow === 'nav-menus.php');
        $isMenuOptionsAjax = wp_doing_ajax() && isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['nectar_menu_item_settings', 'nectar_menu_item_settings_save'], true);

        //only when the requested cap is administrator
        if ($requested !== 'administrator') {
            return $allcaps;
        }
        //AJAX: grant (action name uniquely identifies Nectar menu-options)
        if ($isMenuOptionsAjax) {
            $allcaps['administrator'] = true;
            return $allcaps;
        }
        //nav-menus page: grant only when this check originates from Nectar Menu_Options
        if ($isNavMenusScreen) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            $namespacePrefix = substr(self::NECTAR_MENU_OPTIONS_MODAL_CLASS, 0, strrpos(self::NECTAR_MENU_OPTIONS_MODAL_CLASS, '\\') + 1);
            $classParts = explode('\\', self::NECTAR_MENU_OPTIONS_MODAL_CLASS);
            $pathModuleName = isset($classParts[1]) ? $classParts[1] : 'Menu_Options';
            $pathFileName = isset($classParts[2]) ? $classParts[2] . '.php' : 'Modal.php';
            $pluginSlug = defined('NECTAR_BLOCKS_FOLDER_NAME') ? NECTAR_BLOCKS_FOLDER_NAME : 'nectar-blocks';
            foreach ($trace as $frame) {
                if (isset($frame['class']) && ($frame['class'] === self::NECTAR_MENU_OPTIONS_MODAL_CLASS || strpos($frame['class'], $namespacePrefix) === 0)) {
                    $allcaps['administrator'] = true;
                    break;
                }
                if (isset($frame['file'])) {
                    $path = str_replace('\\', '/', $frame['file']);
                    if (strpos($path, $pluginSlug) !== false && (strpos($path, $pathModuleName) !== false || strpos($path, $pathFileName) !== false)) {
                        $allcaps['administrator'] = true;
                        break;
                    }
                }
            }
        }
        return $allcaps;
    }

    /**
     * Hides specific admin pages from the owner role.
     * @return void
     */
    public function hideAdminPagesForOwner(): void {
        if (self::isOwner()) {

            //hide ACF related CMS pages
            remove_menu_page('edit.php?post_type=acf-field-group');

            //Hide Polylang related CMS pages
            remove_submenu_page('mlang', 'mlang');
            remove_submenu_page('mlang', 'mlang_wizard');

            //hide Nectar Blocks related CMS pages
            remove_menu_page('nectar-blocks');
        }
    }

    /**
     * Checks if the current user has the 'owner' role.
     * @return bool
     */
    public static function isOwner(): bool {
        return current_user_can(self::ROLE_OWNER) && !current_user_can('administrator');
    }
}