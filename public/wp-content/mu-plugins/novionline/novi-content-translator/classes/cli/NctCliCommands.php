<?php

namespace NoviOnline\ContentTranslator\Cli;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslationCache;
use NoviOnline\ContentTranslator\Core\GravityFormsTranslator;
use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;
use NoviOnline\ContentTranslator\Core\MenuTranslator;
use NoviOnline\ContentTranslator\Core\MenuBlockIdReplacer;
use NoviOnline\ContentTranslator\Core\PostDuplicator;

/**
 * WP-CLI commands for bulk translation and link sync.
 *
 * Usage:
 * - Translate missing only:
 *   wp nct translate-bulk --source=en --targets=nl,es,de,fr,pt,th --skip-themes
 * - Force update existing:
 *   wp nct translate-bulk --source=en --targets=nl,es --force --skip-themes
 * - Converge internal links only (no DeepL):
 *   wp nct sync-links --source=en --targets=nl,es,de,fr,pt,th --skip-themes
 */
class NctCliCommands
{
    public static function register(): void
    {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }
        if (!class_exists('\WP_CLI')) {
            return;
        }

        self::cli('add_command', 'nct translate-bulk', [self::class, 'translateBulk']);
        self::cli('add_command', 'nct translate-menus', [self::class, 'translateMenus']);
        self::cli('add_command', 'nct translate-menu', [self::class, 'translateMenu']);
        self::cli('add_command', 'nct translate-forms', [self::class, 'translateForms']);
        self::cli('add_command', 'nct translate-form', [self::class, 'translateForm']);
        self::cli('add_command', 'nct translate-post', [self::class, 'translatePost']);
        self::cli('add_command', 'nct sync-links', [self::class, 'syncLinks']);
        self::cli('add_command', 'nct check-internal-links', [self::class, 'checkInternalLinks']);
        self::cli('add_command', 'nct replace-novi-menu-ids', [self::class, 'replaceNoviMenuIds']);
        self::cli('add_command', 'nct replace-gf-form-ids', [self::class, 'replaceGfFormIds']);
        self::cli('add_command', 'nct check-block-integrity', [self::class, 'checkBlockIntegrity']);
    }

    /**
     * Persist post_content safely for Gutenberg JSON unicode escapes (\u003c, \u0022, …).
     * Delegates to PostDuplicator::persistPostContent() so CLI + AJAX share one write path.
     *
     * @param int $postId
     * @param string $content
     * @return int|\WP_Error
     */
    private static function updatePostContent(int $postId, string $content)
    {
        return PostDuplicator::persistPostContent($postId, $content);
    }

    /**
     * Menu writes require an authenticated user with the right caps.
     * In WP-CLI, there is often no current user, which can cause wp_update_nav_menu_item()
     * to die with "The link you followed has expired."
     */
    private static function ensureCliAdminUser(): void
    {
        if (!function_exists('wp_set_current_user')) {
            return;
        }
        // If a user is already set, don't override.
        if (function_exists('get_current_user_id') && (int) get_current_user_id() > 0) {
            return;
        }

        $adminId = 0;
        if (function_exists('get_users')) {
            $admins = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);
            if (is_array($admins) && isset($admins[0])) {
                $adminId = (int) $admins[0];
            }
        }
        if ($adminId <= 0) {
            // Common default for WP installs.
            $adminId = 1;
        }
        wp_set_current_user($adminId);

        // Some stacks/plugins expect the standard nav menu update nonce in request context,
        // and will die with "The link you followed has expired." if it's missing.
        if (function_exists('wp_create_nonce')) {
            $nonce = wp_create_nonce('update-nav_menu');
            $_REQUEST['update-nav-menu-nonce'] = $_REQUEST['update-nav-menu-nonce'] ?? $nonce;
            $_POST['update-nav-menu-nonce'] = $_POST['update-nav-menu-nonce'] ?? $nonce;
            $_POST['action'] = $_POST['action'] ?? 'update';
        }
    }

    /**
     * Some themes/plugins emit noisy E_WARNING messages during nav menu updates in CLI context.
     * Suppress only known benign warnings from theme nav menu helpers while we run menu translations.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private static function runWithSuppressedMenuWarnings(callable $fn)
    {
        $prevHandler = null;
        $installed = false;

        if (function_exists('set_error_handler')) {
            $prevHandler = set_error_handler(static function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$prevHandler): bool {
                // Only suppress a specific, known benign warning.
                if (
                    $errno === E_WARNING
                    && str_contains($errstr, 'foreach() argument must be of type array|object, null given')
                    && str_contains($errfile, '/wp-content/themes/nectar-blocks-theme/nectar/helpers/nav-menus.php')
                ) {
                    return true;
                }

                if (is_callable($prevHandler)) {
                    return (bool) $prevHandler($errno, $errstr, $errfile, $errline);
                }

                return false;
            });
            $installed = true;
        }

        try {
            return $fn();
        } finally {
            if ($installed && function_exists('restore_error_handler')) {
                restore_error_handler();
            }
        }
    }

    /**
     * Translate a single menu by ID (term_id).
     *
     * ## OPTIONS
     *
     * <id>
     * : Source menu term_id.
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * [--target=<slug>]
     * : Target Polylang language slug (e.g. nl).
     *
     * [--force]
     * : Rebuild/update the target menu even if it exists.
     *
     * [--dry-run]
     * : Print what would happen without writing.
     */
    public static function translateMenu(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $id = isset($args[0]) ? (int) $args[0] : 0;
        if ($id <= 0) {
            self::cli('error', 'Missing <id> (source menu ID).');
        }

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $target = trim((string) ($assocArgs['target'] ?? ''));
        if ($source === '' || $target === '') {
            self::cli('error', 'Missing --source or --target.');
        }

        $force = array_key_exists('force', $assocArgs);
        $dryRun = array_key_exists('dry-run', $assocArgs);

        self::cli('log', 'NCT translate menu');
        self::cli('log', sprintf('source=%s target=%s id=%d mode=%s', $source, $target, $id, $force ? 'force' : 'default'));

        if ($dryRun) {
            self::cli('success', sprintf('[dry-run] would translate menu %d -> %s', $id, $target));
            return;
        }

        self::ensureCliAdminUser();
        $out = self::runWithSuppressedMenuWarnings(static function () use ($id, $source, $target, $force) {
            return MenuTranslator::translateMenu($id, $source, $target, [
                'force' => $force,
                'strict_links' => true,
            ]);
        });
        if ($out === false || !is_numeric($out) || (int) $out <= 0) {
            self::cli('error', sprintf('Failed translating menu %d -> %s', $id, $target));
        }

        self::cli('success', sprintf('Done: menu %d -> %s (%d)', $id, $target, (int) $out));
    }

    /**
     * Bulk translate menus whose names end with \"- {SOURCE_LOCALE}\" (uppercase).
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * [--targets=<slugs>]
     * : Comma-separated target language slugs (e.g. nl,de).
     *
     * [--only-missing]
     * : Only create missing target menus (default).
     *
     * [--force]
     * : Force rebuild/update existing target menus too.
     *
     * [--dry-run]
     * : Print what would happen without writing.
     *
     * [--disable-tc]
     * : Disable 24h transient cache for DeepL translations (enabled by default for bulk runs).
     */
    public static function translateMenus(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $targets = self::parseCsv((string) ($assocArgs['targets'] ?? ''));
        if ($source === '' || $targets === []) {
            self::cli('error', 'Missing --source or --targets.');
        }

        $onlyMissing = array_key_exists('only-missing', $assocArgs) ? true : !array_key_exists('force', $assocArgs);
        $force = array_key_exists('force', $assocArgs);
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $disableTc = array_key_exists('disable-tc', $assocArgs);

        if (!function_exists('wp_get_nav_menus')) {
            self::cli('error', 'wp_get_nav_menus() not available.');
        }

        $menus = wp_get_nav_menus();
        if (!is_array($menus)) {
            $menus = [];
        }

        $suffix = ' - ' . strtoupper($source);
        $sourceMenus = [];
        foreach ($menus as $m) {
            if (!is_object($m) || !isset($m->term_id, $m->name)) {
                continue;
            }
            $name = (string) $m->name;
            if ($suffix !== '' && str_ends_with($name, $suffix)) {
                $sourceMenus[] = $m;
            }
        }

        self::cli('log', 'NCT bulk translate menus');
        self::cli('log', 'source=' . $source . ' targets=' . implode(',', $targets));
        self::cli('log', 'source_menus=' . count($sourceMenus));

        $totalSteps = count($sourceMenus) * count($targets);
        $progress = $dryRun ? null : self::makeProgressBar('Translating menus', $totalSteps);
        if (!$dryRun && !$disableTc) {
            $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            DeepLTranslationCache::enable($ttl);
        }
        try {
            self::ensureCliAdminUser();
            foreach ($sourceMenus as $menuObj) {
                $menuId = (int) $menuObj->term_id;
                foreach ($targets as $target) {
                    if ($dryRun) {
                        self::cli('log', sprintf('[dry-run] menu %d (%s) -> %s', $menuId, (string) $menuObj->name, $target));
                        continue;
                    }
                    if ($progress && method_exists($progress, 'setMessage')) {
                        $progress->setMessage(sprintf('%d -> %s', $menuId, (string) $target));
                    }

                    $out = self::runWithSuppressedMenuWarnings(static function () use ($menuId, $source, $target, $onlyMissing, $force) {
                        return MenuTranslator::translateMenu($menuId, $source, $target, [
                            'only_missing' => $onlyMissing,
                            'force' => $force,
                            'strict_links' => true,
                        ]);
                    });
                    if ($out === false) {
                        self::cli('warning', sprintf('Failed: menu %d -> %s', $menuId, $target));
                    }
                    if ($progress) {
                        $progress->tick();
                    }
                }
            }
        } finally {
            DeepLTranslationCache::disable();
        }

        if ($progress) {
            $progress->finish();
            self::cli('log', '');
        }
        self::cli('success', 'Done.');
    }

    /**
     * Translate a single Gravity Form by ID.
     *
     * Creates or updates a target form titled \"{Base} - {TARGET}\" (uppercase locale).
     * Default behaviour updates an existing target form; use --only-missing to skip.
     *
     * ## OPTIONS
     *
     * <id>
     * : Source Gravity Form ID.
     *
     * [--source=<slug>]
     * : Source language slug used in the form title suffix. Default: en
     *
     * [--target=<slug>]
     * : Target language slug (e.g. nl).
     *
     * [--only-missing]
     * : Skip when a form with the target title already exists.
     *
     * [--force]
     * : Force update existing target form from source (default when --only-missing is absent).
     *
     * [--dry-run]
     * : Print what would happen without writing.
     *
     * [--disable-tc]
     * : Disable 24h transient cache for DeepL translations.
     */
    public static function translateForm(array $args, array $assocArgs): void
    {
        self::requireGravityForms();

        $id = isset($args[0]) ? (int) $args[0] : 0;
        if ($id <= 0) {
            self::cli('error', 'Missing <id> (source form ID).');
        }

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $target = trim((string) ($assocArgs['target'] ?? ''));
        if ($source === '' || $target === '') {
            self::cli('error', 'Missing --source or --target.');
        }

        $onlyMissing = array_key_exists('only-missing', $assocArgs);
        $force = array_key_exists('force', $assocArgs) || !$onlyMissing;
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $disableTc = array_key_exists('disable-tc', $assocArgs);

        $sourceForm = \GFAPI::get_form($id);
        if (!is_array($sourceForm) || empty($sourceForm['title'])) {
            self::cli('error', sprintf('Source form %d not found.', $id));
        }
        $sourceTitle = (string) $sourceForm['title'];
        $targetTitle = GravityFormsTranslator::buildTargetTitle($sourceTitle, $source, $target);
        $existingId = GravityFormsTranslator::findFormIdByTitle($targetTitle);
        $action = $existingId > 0 ? 'update' : 'create';

        self::cli('log', 'NCT translate form');
        self::cli('log', sprintf(
            'source=%s target=%s id=%d title="%s" -> "%s" action=%s mode=%s',
            $source,
            $target,
            $id,
            $sourceTitle,
            $targetTitle,
            $action,
            $onlyMissing ? 'only-missing' : ($force ? 'upsert' : 'default')
        ));

        if ($dryRun) {
            self::cli('success', sprintf(
                '[dry-run] would %s form %d (%s) -> %s (%s)%s',
                $action,
                $id,
                $sourceTitle,
                $target,
                $targetTitle,
                $existingId > 0 ? ' existing=' . $existingId : ''
            ));
            return;
        }

        if (!$disableTc) {
            $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            DeepLTranslationCache::enable($ttl);
        }
        try {
            $out = GravityFormsTranslator::translateForm($id, $source, $target, [
                'only_missing' => $onlyMissing,
                'force' => $force,
            ]);
        } finally {
            DeepLTranslationCache::disable();
        }

        foreach (GravityFormsTranslator::consumeWarnings() as $warning) {
            self::cli('warning', $warning);
        }

        if ($out === false || !is_numeric($out) || (int) $out <= 0) {
            self::cli('error', sprintf('Failed translating form %d -> %s', $id, $target));
        }

        self::cli('success', sprintf('Done: form %d -> %s (%d) title="%s"', $id, $target, (int) $out, $targetTitle));
    }

    /**
     * Bulk translate active Gravity Forms whose titles end with \"- {SOURCE_LOCALE}\" (uppercase).
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source language slug used in the form title suffix. Default: en
     *
     * [--targets=<slugs>]
     * : Comma-separated target language slugs (e.g. en,de).
     *
     * [--only-missing]
     * : Only create missing target forms (skip existing titles).
     *
     * [--force]
     * : Force update existing target forms too (default when --only-missing is absent).
     *
     * [--dry-run]
     * : Print what would happen without writing.
     *
     * [--disable-tc]
     * : Disable 24h transient cache for DeepL translations (enabled by default for bulk runs).
     */
    public static function translateForms(array $args, array $assocArgs): void
    {
        self::requireGravityForms();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $targets = self::parseCsv((string) ($assocArgs['targets'] ?? ''));
        if ($source === '' || $targets === []) {
            self::cli('error', 'Missing --source or --targets.');
        }

        $onlyMissing = array_key_exists('only-missing', $assocArgs);
        $force = array_key_exists('force', $assocArgs) || !$onlyMissing;
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $disableTc = array_key_exists('disable-tc', $assocArgs);

        $forms = \GFAPI::get_forms(true);
        if (!is_array($forms)) {
            $forms = [];
        }

        $suffix = ' - ' . strtoupper($source);
        $sourceForms = [];
        foreach ($forms as $form) {
            $title = '';
            $formId = 0;
            if (is_array($form)) {
                $title = (string) ($form['title'] ?? '');
                $formId = (int) ($form['id'] ?? 0);
            } elseif (is_object($form)) {
                $title = (string) ($form->title ?? '');
                $formId = (int) ($form->id ?? 0);
            }
            if ($formId <= 0 || $title === '') {
                continue;
            }
            if ($suffix !== '' && str_ends_with($title, $suffix)) {
                $sourceForms[] = ['id' => $formId, 'title' => $title];
            }
        }

        self::cli('log', 'NCT bulk translate forms');
        self::cli('log', 'source=' . $source . ' targets=' . implode(',', $targets));
        self::cli('log', 'source_forms=' . count($sourceForms));

        $totalSteps = count($sourceForms) * count($targets);
        $progress = $dryRun ? null : self::makeProgressBar('Translating forms', $totalSteps);
        if (!$dryRun && !$disableTc) {
            $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            DeepLTranslationCache::enable($ttl);
        }
        try {
            foreach ($sourceForms as $formRow) {
                $formId = (int) $formRow['id'];
                $sourceTitle = (string) $formRow['title'];
                foreach ($targets as $target) {
                    $targetTitle = GravityFormsTranslator::buildTargetTitle($sourceTitle, $source, $target);
                    $existingId = GravityFormsTranslator::findFormIdByTitle($targetTitle);
                    $action = $existingId > 0 ? 'update' : 'create';

                    if ($dryRun) {
                        self::cli('log', sprintf(
                            '[dry-run] would %s form %d (%s) -> %s (%s)%s',
                            $action,
                            $formId,
                            $sourceTitle,
                            $target,
                            $targetTitle,
                            $existingId > 0 ? ' existing=' . $existingId : ''
                        ));
                        continue;
                    }

                    if ($progress && method_exists($progress, 'setMessage')) {
                        $progress->setMessage(sprintf('%d -> %s', $formId, (string) $target));
                    }

                    $out = GravityFormsTranslator::translateForm($formId, $source, $target, [
                        'only_missing' => $onlyMissing,
                        'force' => $force,
                    ]);

                    foreach (GravityFormsTranslator::consumeWarnings() as $warning) {
                        self::cli('warning', $warning);
                    }

                    if ($out === false) {
                        self::cli('warning', sprintf('Failed: form %d -> %s', $formId, $target));
                    } else {
                        self::cli('log', sprintf('OK: form %d -> %s (%d) "%s"', $formId, $target, (int) $out, $targetTitle));
                    }
                    if ($progress) {
                        $progress->tick();
                    }
                }
            }
        } finally {
            DeepLTranslationCache::disable();
        }

        if ($progress) {
            $progress->finish();
            self::cli('log', '');
        }
        self::cli('success', 'Done.');
    }

    /**
     * Translate a single post ID to a target language.
     *
     * ## OPTIONS
     *
     * <id>
     * : Source post ID (in --source language).
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * [--target=<slug>]
     * : Target Polylang language slug (e.g. nl).
     *
     * [--only-missing]
     * : Only create missing translation (default).
     *
     * [--force]
     * : Force update/re-translate even if translation exists.
     *
     * [--dry-run]
     * : Print what would happen without writing.
     */
    public static function translatePost(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $target = trim((string) ($assocArgs['target'] ?? ''));
        if ($source === '' || $target === '') {
            self::cli('error', 'Missing --source or --target.');
        }

        $id = isset($args[0]) ? (int) $args[0] : 0;
        if ($id <= 0) {
            self::cli('error', 'Missing <id> (source post ID).');
        }

        $onlyMissing = array_key_exists('only-missing', $assocArgs) ? true : !array_key_exists('force', $assocArgs);
        $force = array_key_exists('force', $assocArgs);
        $dryRun = array_key_exists('dry-run', $assocArgs);

        $sourcePost = get_post($id);
        if (!$sourcePost) {
            self::cli('error', sprintf('Post not found: %d', $id));
        }

        $lang = (string) pll_get_post_language($id, 'slug');
        if ($lang !== '' && $lang !== $source) {
            self::cli('warning', sprintf('Post %d language is "%s" (expected "%s")', $id, $lang, $source));
        }

        $existingTargetId = function_exists('pll_get_post') ? (int) pll_get_post($id, $target) : 0;
        if ($onlyMissing && $existingTargetId > 0) {
            if (!$dryRun) {
                PostDuplicator::syncHierarchicalParent((int) $id, $existingTargetId, (string) $target);
            }
            self::cli('success', sprintf('Already translated: %d -> %s (%d)', $id, $target, $existingTargetId));
            return;
        }

        self::cli('log', 'NCT translate post');
        self::cli('log', sprintf('source=%s target=%s id=%d type=%s existing=%d mode=%s', $source, $target, $id, (string) $sourcePost->post_type, $existingTargetId, $force ? 'force' : ($onlyMissing ? 'only-missing' : 'default')));

        if ($dryRun) {
            self::cli('success', sprintf('[dry-run] would translate %d -> %s (existing=%d)', $id, $target, $existingTargetId));
            return;
        }

        $result = PostDuplicator::duplicatePost((int) $id, (string) $target, $force && $existingTargetId > 0 ? $existingTargetId : null);
        if ($result === false || !is_numeric($result) || (int) $result <= 0) {
            self::cli('error', sprintf(
                'Failed: %d -> %s (%s)',
                (int) $id,
                $target,
                (string) (PostDuplicator::getLastError() ?? 'unknown')
            ));
        }

        $targetId = (int) $result;
        self::cli('success', sprintf('Done: %d -> %s (%d)', (int) $id, (string) $target, $targetId));
    }

    /**
     * Translate a source language into multiple Polylang target languages.
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * [--targets=<slugs>]
     * : Comma-separated target language slugs (e.g. nl,es,de).
     *
     * [--post-types=<types>]
     * : Comma-separated post types to include. If omitted, auto-detect from the DB.
     *
     * [--statuses=<statuses>]
     * : Comma-separated post statuses. Default includes drafts.
     *
     * [--only-missing]
     * : Only create missing translations (default).
     *
     * [--force]
     * : Force update/re-translate even if translation exists.
     *
     * [--limit=<n>]
     * : Max number of source posts to process.
     *
     * [--offset=<n>]
     * : Offset into the ordered source list.
     *
     * [--dry-run]
     * : Print what would happen without writing.
     *
     * [--disable-tc]
     * : Disable 24h transient cache for DeepL translations (enabled by default for bulk runs).
     */
    public static function translateBulk(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $targets = self::parseCsv((string) ($assocArgs['targets'] ?? ''));
        if ($source === '' || $targets === []) {
            self::cli('error', 'Missing --source or --targets.');
        }

        $statuses = self::parseCsv((string) ($assocArgs['statuses'] ?? 'publish,future,draft,pending,private'));
        if ($statuses === []) {
            $statuses = ['publish', 'future', 'draft', 'pending', 'private'];
        }

        $onlyMissing = array_key_exists('only-missing', $assocArgs) ? true : !array_key_exists('force', $assocArgs);
        $force = array_key_exists('force', $assocArgs);
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $disableTc = array_key_exists('disable-tc', $assocArgs);
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;

        $postTypes = self::parseCsv((string) ($assocArgs['post-types'] ?? ''));
        if ($postTypes === []) {
            $postTypes = self::detectPostTypesFromDb($statuses);
        }

        $sourceIds = self::selectSourcePostIds($source, $postTypes, $statuses);
        $sourceIds = self::orderSourceIds($sourceIds);
        if ($offset > 0) {
            $sourceIds = array_slice($sourceIds, $offset);
        }
        if ($limit > 0) {
            $sourceIds = array_slice($sourceIds, 0, $limit);
        }

        self::cli('log', 'NCT bulk translate');
        self::cli('log', 'source=' . $source . ' targets=' . implode(',', $targets));
        self::cli('log', 'post_types=' . implode(',', $postTypes));
        self::cli('log', 'statuses=' . implode(',', $statuses));
        self::cli('log', 'mode=' . ($force ? 'force' : ($onlyMissing ? 'only-missing' : 'default')));
        self::cli('log', 'count=' . count($sourceIds));

        $stats = [
            'skipped_existing' => 0,
            'synced_parent' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        $totalSteps = count($sourceIds) * count($targets);
        $progress = $dryRun ? null : self::makeProgressBar('Translating', $totalSteps);
        if (!$dryRun && !$disableTc) {
            $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            DeepLTranslationCache::enable($ttl);
        }
        try {
            foreach ($sourceIds as $i => $sourceId) {
                $sourcePost = get_post($sourceId);
                if (!$sourcePost) {
                    if ($progress) {
                        // advance for each target step to keep bar consistent
                        for ($x = 0; $x < count($targets); $x++) {
                            $progress->tick();
                        }
                    }
                    continue;
                }

                foreach ($targets as $target) {
                    $existingTargetId = function_exists('pll_get_post') ? (int) pll_get_post($sourceId, $target) : 0;
                    if ($onlyMissing && $existingTargetId > 0) {
                        if (!$dryRun) {
                            $parentSync = PostDuplicator::syncHierarchicalParent((int) $sourceId, $existingTargetId, (string) $target);
                            if ($parentSync === 'updated') {
                                $stats['synced_parent']++;
                            }
                        }
                        $stats['skipped_existing']++;
                        if ($progress) {
                            $progress->tick();
                        }
                        continue;
                    }

                    if ($dryRun) {
                        self::cli('log', sprintf(
                            '[dry-run] %d (%s) -> %s (existing=%d)',
                            (int) $sourceId,
                            (string) $sourcePost->post_type,
                            $target,
                            $existingTargetId
                        ));
                        continue;
                    }

                    if (count($targets) > 1) {
                        $label = sprintf('%d (%s) -> %s', (int) $sourceId, (string) $sourcePost->post_type, (string) $target);
                    } else {
                        $label = sprintf('%d (%s)', (int) $sourceId, (string) $sourcePost->post_type);
                    }
                    if ($progress && method_exists($progress, 'setMessage')) {
                        $progress->setMessage($label);
                    }

                    $result = PostDuplicator::duplicatePost((int) $sourceId, (string) $target, $force && $existingTargetId > 0 ? $existingTargetId : null);
                    if ($result === false || !is_numeric($result) || (int) $result <= 0) {
                        $stats['failed']++;
                        self::cli('warning', sprintf(
                            'Failed: %d -> %s (%s)',
                            (int) $sourceId,
                            $target,
                            (string) (PostDuplicator::getLastError() ?? 'unknown')
                        ));
                        if ($progress) {
                            $progress->tick();
                        }
                        continue;
                    }

                    if ($existingTargetId > 0) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }

                    if ($progress) {
                        $progress->tick();
                    }
                }
            }
        } finally {
            DeepLTranslationCache::disable();
        }

        if ($progress) {
            $progress->finish();
            self::cli('log', '');
        }

        self::cli('success', 'Done. ' . wp_json_encode($stats));
    }


    /**
     * Report-only: find internal hrefs in target-language content that look like
     * target-lang paths but do not resolve to a published post/term (likely DeepL invents / 404s).
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source language slug (for context in the report). Default: en
     *
     * [--target=<slug>]
     * : Target language slug to scan. Default: de
     *
     * [--post-types=<list>]
     * : Comma-separated post types. Default: page,post,article,project,vacancy,wp_block,nectar_sections
     *
     * [--statuses=<list>]
     * : Comma-separated statuses. Default: publish
     *
     * [--limit=<n>]
     * : Max posts to scan (0 = all). Default: 0
     *
     * [--format=<format>]
     * : table|csv|json. Default: table
     *
     * ## EXAMPLES
     *
     *     wp nct check-internal-links --source=en --target=de
     */
    public static function checkInternalLinks(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $target = trim((string) ($assocArgs['target'] ?? 'de'));
        if ($source === '' || $target === '') {
            self::cli('error', 'Missing --source or --target.');
        }

        $statuses = self::parseCsv((string) ($assocArgs['statuses'] ?? 'publish'));
        if ($statuses === []) {
            $statuses = ['publish'];
        }
        $postTypes = self::parseCsv((string) ($assocArgs['post-types'] ?? 'page,post,article,project,vacancy,wp_block,nectar_sections'));
        if ($postTypes === []) {
            $postTypes = ['page', 'post'];
        }
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $format = strtolower(trim((string) ($assocArgs['format'] ?? 'table')));
        if (!in_array($format, ['table', 'csv', 'json'], true)) {
            $format = 'table';
        }

        $targetIds = self::selectSourcePostIds($target, $postTypes, $statuses);
        $targetIds = self::orderSourceIds($targetIds);
        if ($limit > 0) {
            $targetIds = array_slice($targetIds, 0, $limit);
        }

        $langPrefix = '/' . $target . '/';
        $rows = [];
        $postsScanned = 0;
        $hrefsScanned = 0;

        foreach ($targetIds as $postId) {
            $post = get_post((int) $postId);
            if (!$post) {
                continue;
            }
            $postsScanned++;
            $content = (string) ($post->post_content ?? '');
            if ($content === '' || stripos($content, 'href') === false) {
                continue;
            }

            if (!preg_match_all('/\bhref\s*=\s*(["\'])(.*?)\1/i', $content, $matches)) {
                continue;
            }

            $seenInPost = [];
            foreach ($matches[2] as $rawHref) {
                $href = html_entity_decode(trim((string) $rawHref), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'mailto:') || str_starts_with(strtolower($href), 'tel:')) {
                    continue;
                }
                $hrefsScanned++;

                $norm = self::checkInternalLinksNormalizePath($href);
                if ($norm === '' || !str_contains($norm, $langPrefix)) {
                    continue;
                }

                $key = $postId . '|' . $norm;
                if (isset($seenInPost[$key])) {
                    continue;
                }
                $seenInPost[$key] = true;

                if (self::checkInternalLinksResolves($href, $target)) {
                    continue;
                }

                $rows[] = [
                    'post_id' => (int) $postId,
                    'post_type' => (string) $post->post_type,
                    'title' => (string) $post->post_title,
                    'href' => $href,
                    'path' => $norm,
                    'source' => $source,
                    'target' => $target,
                    'status' => 'unresolved_target_path',
                ];
            }
        }

        self::cli('log', sprintf(
            'NCT check-internal-links source=%s target=%s posts_scanned=%d hrefs_scanned=%d unresolved=%d',
            $source,
            $target,
            $postsScanned,
            $hrefsScanned,
            count($rows)
        ));

        if ($rows === []) {
            self::cli('success', 'No unresolved target-lang internal hrefs found.');
            return;
        }

        if ($format === 'json') {
            self::cli('log', wp_json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            self::cli('log', 'post_id,post_type,title,href,path,status');
            foreach ($rows as $row) {
                self::cli('log', sprintf(
                    '%d,%s,"%s","%s","%s",%s',
                    $row['post_id'],
                    $row['post_type'],
                    str_replace('"', '""', $row['title']),
                    str_replace('"', '""', $row['href']),
                    str_replace('"', '""', $row['path']),
                    $row['status']
                ));
            }
        } else {
            foreach ($rows as $row) {
                self::cli('warning', sprintf(
                    'post %d (%s) "%s" → %s',
                    $row['post_id'],
                    $row['post_type'],
                    $row['title'],
                    $row['href']
                ));
            }
        }

        self::cli('warning', sprintf('Found %d unresolved target-lang href(s). Report-only; no writes.', count($rows)));
    }

    /**
     * @return string path with leading slash
     */
    private static function checkInternalLinksNormalizePath(string $href): string
    {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($href) : parse_url($href);
        if (!is_array($parts)) {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    private static function checkInternalLinksResolves(string $href, string $targetLang): bool
    {
        if (class_exists(InternalLinkTranslator::class)) {
            $rm = new \ReflectionClass(InternalLinkTranslator::class);
            if ($rm->hasMethod('publishedObjectExistsForInternalUrl')) {
                $method = $rm->getMethod('publishedObjectExistsForInternalUrl');
                $method->setAccessible(true);
                return (bool) $method->invoke(null, $href, $targetLang);
            }
        }

        if (!function_exists('url_to_postid')) {
            return false;
        }
        $absolute = $href;
        if (!preg_match('#^https?://#i', $href)) {
            $absolute = home_url($href);
        }
        $postId = (int) url_to_postid($absolute);
        if ($postId <= 0) {
            return false;
        }
        if (!function_exists('pll_get_post_language')) {
            return true;
        }
        $lang = (string) pll_get_post_language($postId);
        return $lang === '' || $lang === $targetLang;
    }

    /**
     * Rewrite internal links only (no DeepL) on already translated posts.
     * Note: this command does not translate post text content, but it MAY call DeepL as a fallback
     * for translating unmatched slug segments in internal URLs (same behavior as the normal translator).
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * [--targets=<slugs>]
     * : Comma-separated target language slugs (e.g. nl,es,de).
     *
     * [--post-types=<types>]
     * : Comma-separated post types to include. If omitted, auto-detect from the DB.
     *
     * [--statuses=<statuses>]
     * : Comma-separated post statuses. Default includes drafts.
     *
     * [--limit=<n>]
     * : Max number of posts to process (per target).
     *
     * [--offset=<n>]
     * : Offset into the ordered list (per target).
     *
     * [--dry-run]
     * : Print what would happen without writing.
     */
    public static function syncLinks(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $targets = self::parseCsv((string) ($assocArgs['targets'] ?? ''));
        if ($source === '' || $targets === []) {
            self::cli('error', 'Missing --source or --targets.');
        }

        $statuses = self::parseCsv((string) ($assocArgs['statuses'] ?? 'publish,future,draft,pending,private'));
        if ($statuses === []) {
            $statuses = ['publish', 'future', 'draft', 'pending', 'private'];
        }
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;

        $postTypes = self::parseCsv((string) ($assocArgs['post-types'] ?? ''));
        if ($postTypes === []) {
            $postTypes = self::detectPostTypesFromDb($statuses);
        }

        $stats = [
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
        ];

        foreach ($targets as $target) {
            $targetIds = self::selectSourcePostIds($target, $postTypes, $statuses);
            $targetIds = self::orderSourceIds($targetIds);
            if ($offset > 0) {
                $targetIds = array_slice($targetIds, $offset);
            }
            if ($limit > 0) {
                $targetIds = array_slice($targetIds, 0, $limit);
            }

            self::cli('log', 'Sync links: target=' . $target . ' count=' . count($targetIds));
            $progress = $dryRun ? null : self::makeProgressBar('Syncing links (' . $target . ')', count($targetIds));

            foreach ($targetIds as $postId) {
                $p = get_post($postId);
                if (!$p) {
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }
                $old = (string) ($p->post_content ?? '');
                $new = BlockContentTranslator::syncLinksOnly($old, $source, $target);

                if ($new === $old) {
                    $stats['unchanged']++;
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                if ($dryRun) {
                    self::cli('log', sprintf('[dry-run] would update %d (%s)', (int) $postId, (string) $p->post_type));
                    continue;
                }

                if ($progress && method_exists($progress, 'setMessage')) {
                    $progress->setMessage(sprintf('%d (%s)', (int) $postId, (string) $p->post_type));
                }

                $ok = self::updatePostContent((int) $postId, $new);

                if (is_wp_error($ok)) {
                    $stats['failed']++;
                    self::cli('warning', sprintf('Failed update %d: %s', (int) $postId, $ok->get_error_message()));
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                $stats['updated']++;
                if ($progress) {
                    $progress->tick();
                }
            }

            if ($progress) {
                $progress->finish();
                self::cli('log', '');
            }
        }

        self::cli('success', 'Done. ' . wp_json_encode($stats));
    }

    /**
     * Replace ACF Novi menu block menu IDs inside Gutenberg content for one target language.
     * This updates only posts whose Polylang language matches the target.
     *
     * The Novi menu block stores the selected menu term id in block attrs at:
     * - blockName: acf/block-novi-menu
     * - attrs.data.menu: "<term_id>" (string in saved markup)
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source Polylang language slug. Default: en
     *
     * --target=<slug>
     * : Target Polylang language slug (only posts in this language will be updated).
     *
     * [--post-types=<types>]
     * : Comma-separated post types to include. If omitted, auto-detect from the DB.
     *
     * [--statuses=<statuses>]
     * : Comma-separated post statuses. Default includes drafts.
     *
     * [--limit=<n>]
     * : Max number of posts to process.
     *
     * [--offset=<n>]
     * : Offset into the ordered list.
     *
     * [--dry-run]
     * : Print what would happen without writing.
     */
    public static function replaceNoviMenuIds(array $args, array $assocArgs): void
    {
        self::requirePolylang();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $target = trim((string) ($assocArgs['target'] ?? ''));
        if ($source === '' || $target === '') {
            self::cli('error', 'Missing --source or --target.');
        }

        $statuses = self::parseCsv((string) ($assocArgs['statuses'] ?? 'publish,future,draft,pending,private'));
        if ($statuses === []) {
            $statuses = ['publish', 'future', 'draft', 'pending', 'private'];
        }
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;

        $postTypes = self::parseCsv((string) ($assocArgs['post-types'] ?? ''));
        if ($postTypes === []) {
            $postTypes = self::detectPostTypesFromDb($statuses);
        }

        $targetIds = self::selectSourcePostIds($target, $postTypes, $statuses);
        $targetIds = self::orderSourceIds($targetIds);
        if ($offset > 0) {
            $targetIds = array_slice($targetIds, $offset);
        }
        if ($limit > 0) {
            $targetIds = array_slice($targetIds, 0, $limit);
        }

        $stats = [
            'posts_scanned' => 0,
            'posts_updated' => 0,
            'posts_unchanged' => 0,
            'posts_failed' => 0,
            'blocks_updated' => 0,
            'menus_mapped' => 0,
            'menus_unmapped' => 0,
        ];

        self::cli('log', 'Replace Novi menu IDs: target=' . $target . ' count=' . count($targetIds));
        $progress = $dryRun ? null : self::makeProgressBar('Replace Novi menu IDs (' . $target . ')', count($targetIds));

        $mapStats = [
            'mapped' => 0,
            'unmapped' => 0,
        ];
        $mapFn = MenuBlockIdReplacer::buildMenuIdMapperBySuffix($source, $target, $mapStats);

        foreach ($targetIds as $postId) {
            $stats['posts_scanned']++;
            $p = get_post($postId);
            if (!$p) {
                if ($progress) {
                    $progress->tick();
                }
                continue;
            }

            $old = (string) ($p->post_content ?? '');
            $result = MenuBlockIdReplacer::replaceNoviMenuBlockMenuIds($old, $mapFn);
            $new = (string) ($result['content'] ?? $old);
            $updatedBlocks = (int) ($result['updated_blocks'] ?? 0);

            if ($new === $old) {
                $stats['posts_unchanged']++;
                if ($progress) {
                    $progress->tick();
                }
                continue;
            }

            if ($dryRun) {
                self::cli('log', sprintf('[dry-run] would update %d (%s) blocks=%d', (int) $postId, (string) $p->post_type, $updatedBlocks));
                continue;
            }

            if ($progress && method_exists($progress, 'setMessage')) {
                $progress->setMessage(sprintf('%d (%s)', (int) $postId, (string) $p->post_type));
            }

            $ok = self::updatePostContent((int) $postId, $new);

            if (is_wp_error($ok)) {
                $stats['posts_failed']++;
                self::cli('warning', sprintf('Failed update %d: %s', (int) $postId, $ok->get_error_message()));
                if ($progress) {
                    $progress->tick();
                }
                continue;
            }

            $stats['posts_updated']++;
            $stats['blocks_updated'] += $updatedBlocks;
            if ($progress) {
                $progress->tick();
            }
        }

        if ($progress) {
            $progress->finish();
            self::cli('log', '');
        }

        $stats['menus_mapped'] = (int) ($mapStats['mapped'] ?? 0);
        $stats['menus_unmapped'] = (int) ($mapStats['unmapped'] ?? 0);
        self::cli('success', 'Done. ' . wp_json_encode($stats));
    }

    /**
     * Replace gravityforms/form block formId attrs inside Gutenberg content for target-language posts.
     * Maps source-suffixed form titles (e.g. "Contact - NL") to target-suffixed titles ("Contact - EN").
     *
     * ## OPTIONS
     *
     * [--source=<slug>]
     * : Source language slug used in form title suffixes. Default: en
     *
     * [--targets=<slugs>]
     * : Comma-separated target language slugs (posts in these languages will be updated).
     *
     * [--post-types=<types>]
     * : Comma-separated post types to include. If omitted, auto-detect from the DB.
     *
     * [--statuses=<statuses>]
     * : Comma-separated post statuses. Default includes drafts.
     *
     * [--limit=<n>]
     * : Max number of posts to process (per target).
     *
     * [--offset=<n>]
     * : Offset into the ordered list (per target).
     *
     * [--dry-run]
     * : Print what would happen without writing.
     */
    public static function replaceGfFormIds(array $args, array $assocArgs): void
    {
        self::requirePolylang();
        self::requireGravityForms();

        $source = trim((string) ($assocArgs['source'] ?? 'en'));
        $targets = self::parseCsv((string) ($assocArgs['targets'] ?? ''));
        if ($targets === [] && isset($assocArgs['target'])) {
            $targets = self::parseCsv((string) $assocArgs['target']);
        }
        if ($source === '' || $targets === []) {
            self::cli('error', 'Missing --source or --targets.');
        }

        $statuses = self::parseCsv((string) ($assocArgs['statuses'] ?? 'publish,future,draft,pending,private'));
        if ($statuses === []) {
            $statuses = ['publish', 'future', 'draft', 'pending', 'private'];
        }
        $dryRun = array_key_exists('dry-run', $assocArgs);
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;

        $postTypes = self::parseCsv((string) ($assocArgs['post-types'] ?? ''));
        if ($postTypes === []) {
            $postTypes = self::detectPostTypesFromDb($statuses);
        }

        $stats = [
            'posts_scanned' => 0,
            'posts_updated' => 0,
            'posts_unchanged' => 0,
            'posts_failed' => 0,
            'blocks_updated' => 0,
            'forms_mapped' => 0,
            'forms_unmapped' => 0,
        ];

        foreach ($targets as $target) {
            $targetIds = self::selectSourcePostIds($target, $postTypes, $statuses);
            $targetIds = self::orderSourceIds($targetIds);
            if ($offset > 0) {
                $targetIds = array_slice($targetIds, $offset);
            }
            if ($limit > 0) {
                $targetIds = array_slice($targetIds, 0, $limit);
            }

            self::cli('log', 'Replace GF form IDs: target=' . $target . ' count=' . count($targetIds));
            $progress = $dryRun ? null : self::makeProgressBar('Replace GF form IDs (' . $target . ')', count($targetIds));

            $mapStats = [
                'mapped' => 0,
                'unmapped' => 0,
            ];
            $mapFn = GravityFormsTranslator::buildFormIdMapperBySuffix($source, $target, $mapStats);

            foreach ($targetIds as $postId) {
                $stats['posts_scanned']++;
                $p = get_post($postId);
                if (!$p) {
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                $old = (string) ($p->post_content ?? '');
                if ($old === '' || !str_contains($old, 'gravityforms/form')) {
                    $stats['posts_unchanged']++;
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                $result = GravityFormsTranslator::replaceGravityFormsBlockFormIds($old, $mapFn);
                $new = (string) ($result['content'] ?? $old);
                $updatedBlocks = (int) ($result['updated_blocks'] ?? 0);

                if ($new === $old) {
                    $stats['posts_unchanged']++;
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                if ($dryRun) {
                    self::cli('log', sprintf(
                        '[dry-run] would update %d (%s) blocks=%d',
                        (int) $postId,
                        (string) $p->post_type,
                        $updatedBlocks
                    ));
                    $stats['blocks_updated'] += $updatedBlocks;
                    continue;
                }

                if ($progress && method_exists($progress, 'setMessage')) {
                    $progress->setMessage(sprintf('%d (%s)', (int) $postId, (string) $p->post_type));
                }

                $ok = self::updatePostContent((int) $postId, $new);

                if (is_wp_error($ok)) {
                    $stats['posts_failed']++;
                    self::cli('warning', sprintf('Failed update %d: %s', (int) $postId, $ok->get_error_message()));
                    if ($progress) {
                        $progress->tick();
                    }
                    continue;
                }

                $stats['posts_updated']++;
                $stats['blocks_updated'] += $updatedBlocks;
                if ($progress) {
                    $progress->tick();
                }
            }

            if ($progress) {
                $progress->finish();
                self::cli('log', '');
            }

            $stats['forms_mapped'] += (int) ($mapStats['mapped'] ?? 0);
            $stats['forms_unmapped'] += (int) ($mapStats['unmapped'] ?? 0);
        }

        self::cli('success', 'Done. ' . wp_json_encode($stats));
    }

    /**
     * Check posts for broken Gutenberg unicode escapes (lost "\uXXXX" backslashes).
     *
     * ## OPTIONS
     *
     * [--post=<id>]
     * : Check a single post ID.
     *
     * [--post-types=<types>]
     * : Comma-separated post types when scanning. Default: page,post,wp_block,nectar_sections
     *
     * [--limit=<n>]
     * : Max posts to scan (0 = all). Default: 100
     *
     * [--repair]
     * : Repair broken escapes in place (uses wp_slash on save).
     *
     * [--summary]
     * : Print counts by post type and by symptom (u003c/u0022/…) instead of one line per post.
     *
     * ## EXAMPLES
     *
     *     wp nct check-block-integrity --post=11374
     *     wp nct check-block-integrity --limit=0 --summary
     *     wp nct check-block-integrity --post-types=page,wp_block --limit=0 --repair
     *
     * @param array $args
     * @param array $assocArgs
     */
    public static function checkBlockIntegrity(array $args, array $assocArgs): void
    {
        $postId = isset($assocArgs['post']) ? (int) $assocArgs['post'] : 0;
        $repair = isset($assocArgs['repair']);
        $summaryOnly = isset($assocArgs['summary']);
        $limit = isset($assocArgs['limit']) ? (int) $assocArgs['limit'] : 100;
        $postTypes = isset($assocArgs['post-types'])
            ? self::parseCsv((string) $assocArgs['post-types'])
            : ['page', 'post', 'wp_block', 'nectar_sections'];

        $ids = [];
        if ($postId > 0) {
            $ids = [$postId];
        } else {
            $query = new \WP_Query([
                'post_type' => $postTypes,
                'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
                'posts_per_page' => $limit > 0 ? $limit : -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'DESC',
                'no_found_rows' => true,
            ]);
            $ids = array_map('intval', (array) $query->posts);
        }

        $broken = 0;
        $repaired = 0;
        $checked = 0;
        $byPostType = [];
        $bySymptom = [
            'u003c' => 0,
            'u003e' => 0,
            'u0022' => 0,
            'u0026' => 0,
            'u002d' => 0,
            'u005c' => 0,
        ];
        $brokenIds = [];

        foreach ($ids as $id) {
            $p = get_post($id);
            if (!$p) {
                continue;
            }
            $checked++;
            $content = (string) ($p->post_content ?? '');
            if (!BlockContentTranslator::hasLostUnicodeEscapeBackslashes($content)) {
                continue;
            }

            $broken++;
            $postType = (string) $p->post_type;
            $byPostType[$postType] = ($byPostType[$postType] ?? 0) + 1;
            $brokenIds[] = (int) $id;

            $symptoms = BlockContentTranslator::countLostUnicodeEscapeSymptoms($content);
            foreach ($symptoms as $token => $count) {
                if ($count > 0 && isset($bySymptom[$token])) {
                    $bySymptom[$token] += $count;
                }
            }

            $lang = function_exists('pll_get_post_language') ? (string) pll_get_post_language($id) : '';
            $symptomBits = [];
            foreach ($symptoms as $token => $count) {
                if ($count > 0) {
                    $symptomBits[] = $token . '=' . $count;
                }
            }

            if (!$summaryOnly) {
                self::cli('warning', sprintf(
                    'Broken unicode escapes in post %d (%s%s) "%s"%s',
                    $id,
                    $postType,
                    $lang !== '' ? '/' . $lang : '',
                    (string) $p->post_title,
                    $symptomBits !== [] ? ' [' . implode(', ', $symptomBits) . ']' : ''
                ));
            }

            if (!$repair) {
                continue;
            }

            $ok = self::updatePostContent($id, BlockContentTranslator::repairLostUnicodeEscapeBackslashes($content));
            if (is_wp_error($ok)) {
                self::cli('warning', sprintf('Failed repair %d: %s', $id, $ok->get_error_message()));
                continue;
            }

            $after = (string) get_post_field('post_content', $id);
            if (BlockContentTranslator::hasLostUnicodeEscapeBackslashes($after)) {
                self::cli('warning', sprintf('Still broken after repair: %d', $id));
                continue;
            }
            $repaired++;
            if (!$summaryOnly) {
                self::cli('log', sprintf('Repaired post %d', $id));
            }
        }

        if ($summaryOnly) {
            ksort($byPostType);
            self::cli('log', 'By post type: ' . wp_json_encode($byPostType));
            self::cli('log', 'By symptom (occurrence counts): ' . wp_json_encode($bySymptom));
            if ($brokenIds !== []) {
                self::cli('log', 'Broken post IDs: ' . implode(',', $brokenIds));
            }
        }

        self::cli('success', wp_json_encode([
            'checked' => $checked,
            'broken' => $broken,
            'repaired' => $repaired,
            'by_post_type' => $byPostType,
            'by_symptom' => $bySymptom,
            'broken_ids' => $brokenIds,
        ]));
    }

    private static function requirePolylang(): void
    {
        if (!function_exists('pll_languages_list')) {
            self::cli('error', 'Polylang is required (pll_languages_list missing).');
        }
        if (!function_exists('pll_get_post_language')) {
            self::cli('error', 'Polylang is required (pll_get_post_language missing).');
        }
    }

    private static function requireGravityForms(): void
    {
        if (!class_exists('\GFAPI')) {
            self::cli('error', 'Gravity Forms is required (GFAPI missing).');
        }
    }

    /**
     * Call WP-CLI without hard dependency on WP_CLI stubs.
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private static function cli(string $method, ...$args)
    {
        if (!class_exists('\WP_CLI')) {
            return null;
        }
        return call_user_func(['\WP_CLI', $method], ...$args);
    }

    /**
     * Create a WP-CLI progress bar (like Yoast commands).
     * Falls back to null if the utils aren't available.
     *
     * @param string $label
     * @param int $count
     * @return object|null
     */
    private static function makeProgressBar(string $label, int $count): ?object
    {
        if ($count <= 0) {
            return null;
        }
        if (!class_exists('\WP_CLI\\Utils')) {
            return null;
        }
        if (!method_exists('\WP_CLI\\Utils', 'make_progress_bar')) {
            return null;
        }

        /** @var callable $maker */
        $maker = ['\\WP_CLI\\Utils', 'make_progress_bar'];
        $bar = call_user_func($maker, $label, $count);
        return is_object($bar) ? $bar : null;
    }

    /**
     * @return array<int, string>
     */
    private static function parseCsv(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), static function ($v) {
            return $v !== '';
        });
        return array_values(array_unique(array_map('strval', $parts)));
    }

    /**
     * @param array<int, string> $statuses
     * @return array<int, string>
     */
    private static function detectPostTypesFromDb(array $statuses): array
    {
        global $wpdb;
        if (!$wpdb) {
            return ['page', 'post'];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        // Exclude obvious system types; allow anything else (even if CPT not registered when --skip-themes).
        $sql = "SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_status IN ($placeholders)";
        $rows = $wpdb->get_col($wpdb->prepare($sql, ...array_values($statuses)));
        $rows = is_array($rows) ? array_map('strval', $rows) : [];

        $exclude = [
            'revision',
            'nav_menu_item',
            'attachment',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'acf-field-group',
            'acf-field',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
        ];

        $out = [];
        foreach ($rows as $t) {
            $t = trim($t);
            if ($t === '' || in_array($t, $exclude, true)) {
                continue;
            }
            $out[] = $t;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /**
     * Select IDs for a language slug.
     *
     * @param string $langSlug
     * @param array<int, string> $postTypes
     * @param array<int, string> $statuses
     * @return array<int, int>
     */
    private static function selectSourcePostIds(string $langSlug, array $postTypes, array $statuses): array
    {
        global $wpdb;
        $ids = [];
        if (!$wpdb) {
            return $ids;
        }
        if ($postTypes === []) {
            return $ids;
        }

        $ptPlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));
        $stPlaceholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($ptPlaceholders) AND post_status IN ($stPlaceholders)";
        $rows = $wpdb->get_col($wpdb->prepare($sql, ...array_values(array_merge($postTypes, $statuses))));
        $rows = is_array($rows) ? array_map('intval', $rows) : [];

        foreach ($rows as $id) {
            if ($id <= 0) {
                continue;
            }
            $postLang = (string) pll_get_post_language($id, 'slug');
            if ($postLang === $langSlug) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        return $ids;
    }

    /**
     * Order IDs to maximize link match rate.\n     * - shared/global first\n     * - pages parent-first\n     * - then remaining by post_date desc\n     *\n     * @param array<int, int> $ids\n     * @return array<int, int>\n     */
    private static function orderSourceIds(array $ids): array
    {
        $meta = [];
        foreach ($ids as $id) {
            $p = get_post($id);
            if (!$p) {
                continue;
            }
            $type = (string) $p->post_type;
            $priority = 50;
            if (in_array($type, ['nectar_sections', 'nectar_templates', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'], true)) {
                $priority = 0;
            } elseif (self::isHierarchicalPostType($type)) {
                // hierarchical types must be processed parent-first so children get correct post_parent translations
                $priority = 10;
            }

            $depth = 0;
            if (self::isHierarchicalPostType($type)) {
                $parent = (int) ($p->post_parent ?? 0);
                while ($parent > 0) {
                    $depth++;
                    $pp = get_post($parent);
                    if (!$pp) {
                        break;
                    }
                    $parent = (int) ($pp->post_parent ?? 0);
                    if ($depth > 50) {
                        break;
                    }
                }
            }

            $meta[$id] = [
                'priority' => $priority,
                'depth' => $depth,
                'date' => strtotime((string) ($p->post_date_gmt ?? $p->post_date ?? '')) ?: 0,
            ];
        }

        usort($ids, static function (int $a, int $b) use ($meta): int {
            $ma = $meta[$a] ?? ['priority' => 50, 'depth' => 0, 'date' => 0];
            $mb = $meta[$b] ?? ['priority' => 50, 'depth' => 0, 'date' => 0];
            if ($ma['priority'] !== $mb['priority']) {
                return $ma['priority'] <=> $mb['priority'];
            }
            if ($ma['depth'] !== $mb['depth']) {
                return $ma['depth'] <=> $mb['depth'];
            }
            // newest first
            return $mb['date'] <=> $ma['date'];
        });

        return $ids;
    }

    private static function isHierarchicalPostType(string $postType): bool
    {
        $postType = trim($postType);
        if ($postType === '') {
            return false;
        }

        if (function_exists('is_post_type_hierarchical')) {
            return (bool) is_post_type_hierarchical($postType);
        }

        if (function_exists('get_post_type_object')) {
            $obj = get_post_type_object($postType);
            return is_object($obj) && !empty($obj->hierarchical);
        }

        // minimal fallback
        return $postType === 'page';
    }
}

