<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * Class AdminBarLightComponent
 * Provides an alternative light admin bar within the WP frontend.
 *
 * Themes may apply the `novi-has-light-admin-bar` class via the `novi_html_classes` filter.
 */
class AdminBarLightComponent extends Singleton
{
    /**
     * Meta key for use light admin bar setting field on user level
     */
    public const META_KEY_USE_LIGHT_ADMIN_BAR = 'novi_use_admin_bar_light';

    /**
     * Query parameter used to toggle the light admin bar (?novi-enable-admin-bar-light=true|false).
     */
    public const QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR = 'novi-enable-admin-bar-light';

    /**
     * AdminBarLightComponent constructor.
     */
    public function __construct()
    {
        //bail if user is not logged in
        if (!is_user_logged_in()) return;

        //bail if current WP user is not allowed to use the light admin bar
        $currentUserId = get_current_user_id();
        if (!$currentUserId || !$this->userIsAllowedToUseAdminBar($currentUserId)) return;

        //admin hooks - register profile field and saving hooks
        if (is_admin()) add_action('admin_init', [$this, 'addUseLightAdminBarSetting']);

        //front-end hooks
        if (!is_admin() && is_admin_bar_showing()) {

            //handle toggle query param
            add_action('template_redirect', [$this, 'handleToggleQueryParam'], 0);

            //check if user had admin bar enabled
            $hasAdminBarLightEnabled = $this->userHasLightAdminBarEnabled($currentUserId);

            //handle default admin bar enabled
            if (!$hasAdminBarLightEnabled) {

                //add toggle link to the default admin bar
                add_action('admin_bar_menu', [$this, 'addLightAdminBarToggle'], 100);

                //enqueue front-end assets
                add_action('wp_enqueue_scripts', [$this, 'initFrontendScriptsAdminBar']);
            }

            //handle light admin bar enabled
            if ($hasAdminBarLightEnabled) {

                //enqueue front-end assets
                add_action('wp_enqueue_scripts', [$this, 'initFrontendScriptsAdminBarLight']);

                //inject NB global color variables for the light admin bar
                add_action('wp_head', [$this, 'printColorVariables'], 100);

                //add query var for potential usage in theme
                set_query_var('novi_has_light_admin_bar', true);

                //add a CSS class to the HTML tag via theme filter
                add_filter('novi_html_classes', static function (array $classes): array {
                    if (!in_array('novi-has-light-admin-bar', $classes, true)) $classes[] = 'novi-has-light-admin-bar';
                    return $classes;
                });

                //disable the default admin bar
                add_filter('show_admin_bar', '__return_false');

                //render the light admin bar at the footer, in the admin user's preferred locale
                add_action('wp_footer', static function (): void {
                    self::withUserLocale(static function (): void {
                        Partial::render('components/admin-bar-light', [], true, WCP_PARTIAL_PATH);
                    });
                });
            }
        }
    }

    /**
     * Checks for the toggle query param, updates user meta, and redirects.
     * @return void
     */
    public function handleToggleQueryParam(): void
    {
        if (isset($_GET[self::QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR])) {

            $userId = get_current_user_id();

            if (!$userId || !$this->userIsAllowedToUseAdminBar($userId)) return;

            $enableParam = $_GET[self::QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR] ?? '';
            $enableValue = ($enableParam === 'true') ? 1 : 0;

            update_user_meta($userId, self::META_KEY_USE_LIGHT_ADMIN_BAR, $enableValue);

            $redirectUrl = remove_query_arg(self::QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR);
            wp_safe_redirect($redirectUrl);
            exit;
        }
    }

    /**
     * Adds hooks to display and save the light admin bar user setting.
     * @return void
     */
    public function addUseLightAdminBarSetting(): void
    {
        add_action('personal_options', [$this, 'renderUseLightAdminBarSetting']);
        add_action('personal_options_update', [$this, 'saveLightAdminBarSetting']);
        add_action('edit_user_profile_update', [$this, 'saveLightAdminBarSetting']);
        add_action('user_register', [$this, 'setAdminBarFrontDefault'], 10, 1);
    }

    /**
     * Renders the user profile checkbox field for enabling the light admin bar
     * @param \WP_User $user The WP_User object being edited.
     * @return void
     */
    public function renderUseLightAdminBarSetting(\WP_User $user): void
    {
        if (!$this->userIsAllowedToUseAdminBar($user->ID)) return;

        $checked = $this->userHasLightAdminBarEnabled($user->ID);
        ?>
        <tr class="user-admin-bar-light-option">
            <th scope="row">
                <label for="<?php echo esc_attr(self::META_KEY_USE_LIGHT_ADMIN_BAR); ?>">
                    <?php esc_html_e('Admin bar light', Core::TEXT_DOMAIN); ?>
                </label>
            </th>
            <td>
                <input name="<?php echo esc_attr(self::META_KEY_USE_LIGHT_ADMIN_BAR); ?>"
                       id="<?php echo esc_attr(self::META_KEY_USE_LIGHT_ADMIN_BAR); ?>"
                       type="checkbox"
                       value="1"
                    <?php checked($checked); ?>/>
                <label for="<?php echo esc_attr(self::META_KEY_USE_LIGHT_ADMIN_BAR); ?>">
                    <?php esc_html_e('Enable admin bar light', Core::TEXT_DOMAIN); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Enable the option below to use the light version of the admin bar. This simpler version of the default admin bar only features the buttons you actually use.', Core::TEXT_DOMAIN); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Saves the user choice to enable or disable the light admin bar.
     * @param int $userId The ID of the user being edited.
     * @return void
     */
    public function saveLightAdminBarSetting(int $userId): void
    {
        if (!$this->userIsAllowedToUseAdminBar($userId)) return;
        $useLightValue = isset($_POST[self::META_KEY_USE_LIGHT_ADMIN_BAR]) ? (int) sanitize_text_field($_POST[self::META_KEY_USE_LIGHT_ADMIN_BAR]) : 0;
        update_user_meta($userId, self::META_KEY_USE_LIGHT_ADMIN_BAR, $useLightValue);
    }

    /**
     * Enable the light admin bar by default for new users.
     * @param int $userId The ID of the newly created user.
     * @return void
     */
    public function setAdminBarFrontDefault(int $userId): void
    {
        if (!$this->userIsAllowedToUseAdminBar($userId)) return;
        update_user_meta($userId, self::META_KEY_USE_LIGHT_ADMIN_BAR, 1);
    }

    /**
     * Checks if a user currently has the light admin bar enabled.
     * @param int $userId The user ID.
     * @return bool
     */
    public function userHasLightAdminBarEnabled(int $userId): bool
    {
        return (bool) get_user_meta($userId, self::META_KEY_USE_LIGHT_ADMIN_BAR, true);
    }

    /**
     * Checks if the user is allowed (by role) to use the light admin bar.
     * @param int $userId The user ID.
     * @return bool
     */
    public function userIsAllowedToUseAdminBar(int $userId): bool
    {
        $currentUser = get_user_by('ID', $userId);
        if (!$currentUser instanceof \WP_User) return false;

        $allowedRoles = ['administrator'];
        $allowedRoles = apply_filters('novi_admin_bar_light_allowed_roles', $allowedRoles);

        foreach ($currentUser->roles as $role) {
            if (in_array($role, $allowedRoles, true)) return true;
        }

        return false;
    }

    /**
     * Adds a toggle link to the default WordPress Admin Bar.
     * @param \WP_Admin_Bar $wpAdminBar The WP_Admin_Bar object for the current user.
     * @return void
     */
    public function addLightAdminBarToggle(\WP_Admin_Bar $wpAdminBar): void
    {
        $userId = get_current_user_id();
        if (!$userId || !defined('WCP_ICON_PATH')) return;

        $toggleUrl = add_query_arg(self::QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR, 'true');

        //render the label in the admin user's preferred locale (e.g. English) instead of the site locale
        $title = self::withUserLocale(static function (): string {
            return '<svg><use xlink:href="' . WCP_ICON_PATH . 'icon-switch-on"/></svg><span>' . esc_html__('Use admin bar light', Core::TEXT_DOMAIN) . '</span>';
        });

        $wpAdminBar->add_node([
            'id' => 'toggle-light-admin-bar',
            'title' => $title,
            'href' => esc_url($toggleUrl),
            'meta' => ['class' => 'toggle-light-admin-bar'],
        ]);
    }

    /**
     * Runs the given callback under the current user's preferred admin locale.
     * Falls back to a no-op switch when WP is older than 6.2 or no user is logged in.
     *
     * Used so the light admin bar renders in the admin's chosen language (set in their
     * WP profile), regardless of the front-end site locale that is otherwise active.
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withUserLocale(callable $callback)
    {
        $userId = get_current_user_id();
        $switched = false;

        if ($userId && function_exists('switch_to_user_locale')) {
            $switched = switch_to_user_locale($userId) !== false;
        }

        try {
            return $callback();
        } finally {
            if ($switched) restore_previous_locale();
        }
    }

    /**
     * Returns the singular name of a post type translated using the currently active locale.
     *
     * Built-in WP post types (`post`, `page`, `attachment`) store their labels at registration
     * time. Switching locale later does not re-translate them, so we look up the singular name
     * via `_x()` against WP core's source strings instead. Custom post types fall back to the
     * stored label since we cannot know the source string.
     *
     * @param \WP_Post_Type $postTypeObject
     * @return string
     */
    private static function getPostTypeSingularName(\WP_Post_Type $postTypeObject): string
    {
        $builtIn = [
            'post' => _x('Post', 'post type singular name'),
            'page' => _x('Page', 'post type singular name'),
            'attachment' => _x('Media', 'post type singular name'),
        ];

        if (isset($builtIn[$postTypeObject->name])) return $builtIn[$postTypeObject->name];
        return $postTypeObject->labels->singular_name ?? '';
    }

    /**
     * Returns the plural name of a post type translated using the currently active locale.
     * See `getPostTypeSingularName()` for why built-ins are handled separately.
     *
     * @param \WP_Post_Type $postTypeObject
     * @return string
     */
    private static function getPostTypeName(\WP_Post_Type $postTypeObject): string
    {
        $builtIn = [
            'post' => _x('Posts', 'post type general name'),
            'page' => _x('Pages', 'post type general name'),
            'attachment' => _x('Media', 'post type general name'),
        ];

        if (isset($builtIn[$postTypeObject->name])) return $builtIn[$postTypeObject->name];
        return $postTypeObject->labels->name ?? '';
    }

    /**
     * Returns the singular name of a taxonomy translated using the currently active locale.
     * See `getPostTypeSingularName()` for why built-ins are handled separately.
     *
     * @param \WP_Taxonomy $taxonomy
     * @return string
     */
    private static function getTaxonomySingularName(\WP_Taxonomy $taxonomy): string
    {
        $builtIn = [
            'category' => _x('Category', 'taxonomy singular name'),
            'post_tag' => _x('Tag', 'taxonomy singular name'),
        ];

        if (isset($builtIn[$taxonomy->name])) return $builtIn[$taxonomy->name];
        return $taxonomy->labels->singular_name ?? '';
    }

    /**
     * Enqueues default admin bar toggle styling
     * @return void
     */
    public function initFrontendScriptsAdminBar(): void
    {
        $adminBarStyling = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'component-admin-bar.scss');
        if ($adminBarStyling) wp_enqueue_style('novi-core-admin-bar', $adminBarStyling, [], null);
    }

    /**
     * Enqueues admin bar light front-end assets
     * @return void
     */
    public function initFrontendScriptsAdminBarLight(): void
    {
        $adminBarLightStyling = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'component-admin-bar-light.scss');
        if ($adminBarLightStyling) wp_enqueue_style('novi-core-admin-bar-light', $adminBarLightStyling, [], null);

        $adminBarLightScript = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'admin-bar-light.js');
        if ($adminBarLightScript) {
            wp_enqueue_script('novi-core-admin-bar-light', $adminBarLightScript, [], null, true);
        }

        wp_enqueue_style('dashicons');
    }

    /**
     * Reads NectarBlocks global colors from the nectar_global_colors option.
     * Merges userSolids over coreSolids keyed by slug.
     *
     * @return array<string, string> slug => hex value
     */
    public static function getNectarGlobalColors(): array
    {
        $option = get_option('nectar_global_colors');
        if (!is_array($option)) return [];

        $colors = [];

        foreach (['coreSolids', 'userSolids'] as $group) {
            if (!isset($option[$group]) || !is_array($option[$group])) continue;

            foreach ($option[$group] as $color) {
                if (!is_array($color) || empty($color['slug']) || empty($color['value'])) continue;

                //handle reassigned colors that point at another slug in the same group
                if (!empty($color['reassigned'])) {
                    $reassignedSlug = $color['reassigned'];
                    foreach ($option[$group] as $sourceColor) {
                        if (is_array($sourceColor) && isset($sourceColor['slug']) && $sourceColor['slug'] === $reassignedSlug && !empty($sourceColor['value'])) {
                            $colors[$color['slug']] = $sourceColor['value'];
                            break;
                        }
                    }
                    continue;
                }

                $colors[$color['slug']] = $color['value'];
            }
        }

        //allow themes to override or extend the resolved NB color map (slug => hex)
        return apply_filters('novi_admin_bar_light_colors', $colors);
    }

    /**
     * Prints scoped CSS custom properties on body .admin-bar-light from NB global colors.
     * Missing colors are omitted so SCSS fallbacks apply per surface.
     *
     * @return void
     */
    public function printColorVariables(): void
    {
        $nbColors = self::getNectarGlobalColors();
        if (empty($nbColors)) return;

        $dark = $nbColors['dark'] ?? '';
        $accentPrimary = $nbColors['accentPrimary'] ?? '';
        $light = $nbColors['light'] ?? '';

        $declarations = [];

        if ($dark !== '') {
            $declarations['--novi-admin-bar-light-color-back'] = $dark;
            $declarations['--novi-admin-bar-light-secondary-front'] = $dark;
            $declarations['--novi-admin-bar-light-heading-front'] = $dark;
        }

        if ($light !== '') {
            $declarations['--novi-admin-bar-light-color-front'] = $light;
            $declarations['--novi-admin-bar-light-color-front-hover'] = $light;
            $declarations['--novi-admin-bar-light-secondary-back'] = $light;
            $declarations['--novi-admin-bar-light-heading-back'] = $light;
        }

        if ($accentPrimary !== '') {
            $declarations['--novi-admin-bar-light-color-back-hover'] = $accentPrimary;
            $declarations['--novi-admin-bar-light-secondary-back-hover'] = $accentPrimary;
            $declarations['--novi-admin-bar-light-secondary-front-hover'] = $light !== '' ? $light : '';
        }

        //allow themes to override or extend the light admin bar CSS custom properties
        $declarations = apply_filters('novi_admin_bar_light_css_variables', $declarations, $nbColors);

        $declarations = array_filter($declarations, static fn($value) => $value !== '');
        if (empty($declarations)) return;

        $css = '';
        foreach ($declarations as $property => $value) {
            $css .= sprintf('%s:%s;', $property, esc_attr($value));
        }

        printf('<style id="novi-admin-bar-light-colors">body .admin-bar-light{%s}</style>', $css);
    }

    /**
     * Returns an array of primary items for the light admin bar.
     * @return array
     */
    public static function getPrimaryAdminBarLightItems(): array
    {
        $items = [];

        $items[] = (object) [
            'href' => admin_url(),
            'label' => __('Dashboard', Core::TEXT_DOMAIN),
            'icon' => 'icon-novi',
        ];

        if (is_singular()) {
            $postId = get_the_ID();
            if ($postId) {
                $editUrl = get_edit_post_link($postId);
                if ($editUrl) {
                    $postTypeObject = get_post_type_object(get_post_type($postId));
                    $singularName = $postTypeObject ? strtolower(self::getPostTypeSingularName($postTypeObject)) : __('item', Core::TEXT_DOMAIN);
                    if ($singularName === '') $singularName = __('item', Core::TEXT_DOMAIN);
                    $iconData = $postTypeObject ? self::getPostTypeIcon($postTypeObject) : ['type' => 'dashicon', 'value' => 'dashicons-edit'];
                    $items[] = (object) [
                        'href' => $editUrl,
                        'label' => sprintf(__('Edit %s', Core::TEXT_DOMAIN), $singularName),
                        'icon_data' => $iconData,
                    ];
                }
            }
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $editTermUrl = admin_url('term.php?taxonomy=' . $term->taxonomy . '&tag_ID=' . $term->term_id);
                $tax = get_taxonomy($term->taxonomy);
                $taxName = $tax ? self::getTaxonomySingularName($tax) : __('item', Core::TEXT_DOMAIN);
                if ($taxName === '') $taxName = __('item', Core::TEXT_DOMAIN);
                $items[] = (object) [
                    'href' => $editTermUrl,
                    'label' => sprintf(__('Edit %s', Core::TEXT_DOMAIN), strtolower($taxName)),
                    'icon' => 'icon-pen',
                ];
            }
        }

        if (is_post_type_archive()) {
            $postType = get_query_var('post_type') ?? '';

            if (!empty($postType) && !is_array($postType)) {
                $postTypeObject = get_post_type_object($postType);
                if ($postTypeObject) {
                    $postTypeName = self::getPostTypeName($postTypeObject);
                    if ($postTypeName !== '') {
                        $items[] = (object) [
                            'href' => admin_url('edit.php?post_type=' . $postType),
                            'label' => $postTypeName,
                            'icon' => 'icon-list',
                        ];
                    }
                }
            }

            if (!empty($postType) && !is_array($postType) && function_exists('acf_get_options_pages')) {
                $acfOptionPages = acf_get_options_pages();
                $match = false;
                foreach ($acfOptionPages as $acfOptionPageKey => $acfOptionPage) {
                    if ($acfOptionPageKey === $postType . '-settings') {
                        $match = $acfOptionPage;
                        break;
                    }
                }

                if ($match && isset($match['menu_slug'], $match['page_title'], $match['parent_slug'])) {
                    $items[] = (object) [
                        'href' => admin_url($match['parent_slug'] . '&page=' . $match['menu_slug']),
                        'label' => $match['page_title'],
                        'icon' => 'icon-settings',
                    ];
                }
            }
        }

        return apply_filters('novi_admin_bar_light_primary_items', $items);
    }

    /**
     * Returns an array of secondary items for the light admin bar.
     * @return array
     */
    public static function getSecondaryAdminBarLightItems(): array
    {
        $items = [];

        //add edit links of synced block patterns used within current page (including nested patterns)
        //patterns are shown first as they are used most often
        $usedPatternIds = [];

        $patternBlocks = Gutenberg::getUsedBlocksByName('core/block');
        foreach ($patternBlocks as $patternBlock) {
            $patternId = isset($patternBlock['attrs']['ref']) ? (int) $patternBlock['attrs']['ref'] : 0;
            if ($patternId && !in_array($patternId, $usedPatternIds, true)) $usedPatternIds[] = $patternId;
        }

        $usedPatternIds = apply_filters('novi_admin_bar_light_used_pattern_ids', $usedPatternIds);
        $patternTree = self::buildPatternTree($usedPatternIds);

        if (count($patternTree) > 0) {
            $items[] = (object) ['heading' => __('Active patterns', Core::TEXT_DOMAIN)];

            foreach ($patternTree as $patternItem) {
                $items[] = $patternItem;
            }
        }

        global $otherPosts;
        if (is_array($otherPosts) && count($otherPosts) > 0) {
            $idsInAdminBar = [];
            $postsByType = self::groupPostsByTypeAndSort($otherPosts);

            foreach ($postsByType as $postType => $posts) {
                $postTypeObj = get_post_type_object($postType);
                if (!$postTypeObj) continue;

                $items[] = (object) ['heading' => sprintf(__('Active %s', Core::TEXT_DOMAIN), strtolower(self::getPostTypeName($postTypeObj)))];

                foreach ($posts as $post) {
                    if (is_a($post, '\WP_Post') && !in_array($post->ID, $idsInAdminBar, true)) {
                        $label = sprintf(__('Edit "%1s"', Core::TEXT_DOMAIN), $post->post_title);
                        if (is_user_logged_in() && current_user_can('administrator')) $label .= ' (ID: ' . $post->ID . ')';

                        $iconData = self::getPostTypeIcon($postTypeObj);

                        $items[] = (object) [
                            'href' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                            'label' => $label,
                            'icon_data' => $iconData,
                        ];

                        $idsInAdminBar[] = $post->ID;
                    }
                }
            }
        }

        //add edit links of used gravity forms within current page
        if (class_exists('\GFAPI')) {
            $usedGravityFormIds = [];

            //read formId from the default gravityforms/form block
            $formBlocks = Gutenberg::getUsedBlocksByName('gravityforms/form');
            foreach ($formBlocks as $formBlock) {
                $formId = isset($formBlock['attrs']['formId']) ? (int) $formBlock['attrs']['formId'] : 0;
                if ($formId && !in_array($formId, $usedGravityFormIds, true)) $usedGravityFormIds[] = $formId;
            }

            //make gravity form IDs filterable
            $usedGravityFormIds = apply_filters('novi_admin_bar_light_used_gravity_form_ids', $usedGravityFormIds);

            if (count($usedGravityFormIds) > 0) {
                $items[] = (object) ['heading' => __('Active forms', Core::TEXT_DOMAIN)];

                foreach ($usedGravityFormIds as $gfFormId) {
                    $form = \GFAPI::get_form($gfFormId);
                    if ($form && is_array($form)) {
                        $formTitle = !empty($form['title']) ? $form['title'] : __('Form', Core::TEXT_DOMAIN);
                        $label = sprintf(__('Edit "%1s"', Core::TEXT_DOMAIN), $formTitle);
                        if (current_user_can('administrator')) $label .= ' (ID: ' . $gfFormId . ')';

                        $items[] = (object) [
                            'href' => admin_url('admin.php?page=gf_edit_forms&id=' . $gfFormId),
                            'label' => $label,
                            'icon' => 'icon-gform',
                        ];
                    }
                }
            }
        }

        $items[] = (object) ['heading' => __('Actions', Core::TEXT_DOMAIN)];

        $items[] = (object) [
            'href' => add_query_arg(self::QUERY_PARAM_ENABLE_LIGHT_ADMIN_BAR, 'false'),
            'label' => __('Use default admin bar', Core::TEXT_DOMAIN),
            'icon' => 'icon-switch-off',
        ];

        $currentUser = wp_get_current_user();
        $logoutLabel = __('Log out', Core::TEXT_DOMAIN);
        $logoutTitle = $logoutLabel;
        if ($currentUser instanceof \WP_User) {
            $displayNameRaw = trim($currentUser->display_name);
            $login = $currentUser->user_login;
            $firstNameRaw = trim((string) get_user_meta($currentUser->ID, 'first_name', true));
            $safeDisplay = wp_strip_all_tags($displayNameRaw);
            $safeFirst = wp_strip_all_tags($firstNameRaw);
            $nameOrLogin = $safeDisplay !== '' ? $safeDisplay : $login;
            $shortName = $safeFirst !== '' ? $safeFirst : $nameOrLogin;
            $logoutLabel = sprintf(__('Log out (%s)', Core::TEXT_DOMAIN), $shortName);
            $logoutTitle = sprintf(__('Log out %s', Core::TEXT_DOMAIN), $nameOrLogin);
        }
        $items[] = (object) [
            'href' => wp_logout_url(home_url('/')),
            'label' => $logoutLabel,
            'title' => $logoutTitle,
            'icon' => 'icon-logout',
        ];

        return apply_filters('novi_admin_bar_light_secondary_items', $items);
    }

    /**
     * Builds a recursive tree of synced pattern items for the admin bar dropdown.
     *
     * @param int[] $refIds Top-level pattern ref IDs.
     * @param int[] $ancestors Pattern IDs in the current branch (cycle protection).
     * @param int $depth Nesting depth for unique accordion IDs.
     * @return array
     */
    private static function buildPatternTree(array $refIds, array $ancestors = [], int $depth = 0): array
    {
        $items = [];
        $seenInBranch = [];

        foreach ($refIds as $patternId) {
            if (!$patternId || in_array($patternId, $ancestors, true) || in_array($patternId, $seenInBranch, true)) continue;

            $patternPost = get_post($patternId);
            if (!$patternPost instanceof \WP_Post || $patternPost->post_type !== 'wp_block') continue;

            $seenInBranch[] = $patternId;
            $patternTitle = !empty($patternPost->post_title) ? $patternPost->post_title : sprintf(__('Pattern #%d', Core::TEXT_DOMAIN), $patternId);
            $label = sprintf(__('Edit "%1s"', Core::TEXT_DOMAIN), $patternTitle);
            if (current_user_can('administrator')) $label .= ' (ID: ' . $patternId . ')';

            $childRefs = Gutenberg::getPatternRefsForPost($patternPost);
            $childAncestors = array_merge($ancestors, [$patternId]);
            $children = self::buildPatternTree($childRefs, $childAncestors, $depth + 1);

            $items[] = (object) [
                'type' => 'pattern',
                'pattern_id' => $patternId,
                'pattern_depth' => $depth,
                'href' => admin_url('post.php?post=' . $patternId . '&action=edit'),
                'label' => $label,
                'icon_data' => ['type' => 'dashicon', 'value' => 'dashicons-welcome-widgets-menus'],
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * Groups posts by post type and sorts them by menu order.
     * @param array $posts Array of WP_Post objects.
     * @return array
     */
    private static function groupPostsByTypeAndSort(array $posts): array
    {
        $postsByType = [];

        foreach ($posts as $post) {
            if (is_a($post, '\WP_Post')) {
                $postType = $post->post_type;
                if (!isset($postsByType[$postType])) $postsByType[$postType] = [];
                $postsByType[$postType][] = $post;
            }
        }

        $postTypeObjects = get_post_types(['public' => true], 'objects');
        $postTypeOrder = [];
        foreach ($postTypeObjects as $postType => $postTypeObj) {
            $menuPosition = $postTypeObj->menu_position ?? 25;
            if ($postType === 'post') $menuPosition = 5;
            elseif ($postType === 'page') $menuPosition = 20;
            $postTypeOrder[$postType] = $menuPosition;
        }

        uksort($postsByType, function ($a, $b) use ($postTypeOrder) {
            $posA = $postTypeOrder[$a] ?? 999;
            $posB = $postTypeOrder[$b] ?? 999;
            return $posA <=> $posB;
        });

        return $postsByType;
    }

    /**
     * Gets the appropriate icon data for a post type.
     * @param \WP_Post_Type $postTypeObj The post type object.
     * @return array
     */
    private static function getPostTypeIcon(\WP_Post_Type $postTypeObj): array
    {
        $menuIcon = $postTypeObj->menu_icon;

        if (empty($menuIcon) || $menuIcon === 'none') {
            return [
                'type' => 'dashicon',
                'value' => 'dashicons-admin-post',
            ];
        }

        if (is_string($menuIcon) && str_starts_with($menuIcon, 'dashicons-')) {
            return [
                'type' => 'dashicon',
                'value' => $menuIcon,
            ];
        }

        if (is_string($menuIcon) && str_starts_with($menuIcon, 'data:image/svg+xml')) {
            return [
                'type' => 'svg_data',
                'value' => $menuIcon,
            ];
        }

        if (is_string($menuIcon) && (str_starts_with($menuIcon, 'http') || str_starts_with($menuIcon, '/'))) {
            return [
                'type' => 'image_url',
                'value' => $menuIcon,
            ];
        }

        return [
            'type' => 'dashicon',
            'value' => 'dashicons-admin-post',
        ];
    }
}
