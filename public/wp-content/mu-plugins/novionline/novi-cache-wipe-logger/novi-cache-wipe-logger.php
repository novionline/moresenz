<?php

/**
 * Plugin Name:     Novi Cache Wipe Logger
 * Plugin URI:      https://novionline.nl
 * Description:     Logs Comet Cache wipes/clears, Autoptimize purges, and object-cache flushes; suppresses translation clears; restarts Cache Warmer after full clears.
 * Version:         1.2.2
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

//set define('NOVI_CACHE_WIPE_LOG', false) in wp-config.php to disable logging
if (defined('NOVI_CACHE_WIPE_LOG') && !NOVI_CACHE_WIPE_LOG) {
    return;
}

/**
 * Proxy around the Comet Cache plugin instance so wipe/clear calls can be attributed.
 */
final class NoviCometCacheProxy
{
  private object $inner;

  public function __construct(object $inner)
  {
    $this->inner = $inner;
  }

  public function __call(string $name, array $args)
  {
    if ($name === 'wipeCache') {
      return $this->runLogged('comet_wipe', $name, $args, fn () => $this->inner->wipeCache(...$args));
    }

    if ($name === 'clearCache' || $name === 'clear_cache') {
      $manually = (bool) ($args[0] ?? false);
      return $this->runLogged('comet_clear', $name, $args, fn () => $this->inner->{$name}(...$args), [
        'manually' => $manually,
      ]);
    }

    if ($name === 'purgeCache') {
      return $this->runLogged('comet_purge', $name, $args, fn () => $this->inner->purgeCache(...$args));
    }

    if ($name === 'wurgeCache') {
      return $this->runLogged('comet_wurge', $name, $args, fn () => $this->inner->wurgeCache(...$args));
    }

    if ($name === 'autoClearCache') {
      return $this->runLogged('comet_auto_clear', $name, $args, fn () => $this->inner->autoClearCache(...$args));
    }

    if ($name === 'autoWipeCache') {
      return $this->runLogged('comet_auto_wipe', $name, $args, fn () => $this->inner->autoWipeCache(...$args));
    }

    return $this->inner->{$name}(...$args);
  }

  private function runLogged(string $type, string $method, array $args, callable $callback, array $extra = [])
  {
    $htmlBefore = NoviCacheWipeLogger::cometHtmlFileCount();
    $result = $callback();
    $htmlAfter = NoviCacheWipeLogger::cometHtmlFileCount();

    NoviCacheWipeLogger::record(array_merge([
      'type' => $type,
      'method' => $method,
      'args' => NoviCacheWipeLogger::sanitizeArgs($args),
      'filesReported' => is_int($result) ? $result : null,
      'cometHtmlBefore' => $htmlBefore,
      'cometHtmlAfter' => $htmlAfter,
    ], $extra));

    if (in_array($type, ['comet_wipe', 'comet_clear', 'comet_auto_clear', 'comet_auto_wipe'], true)) {
      NoviCacheWipeLogger::maybeStartCacheWarmer($htmlBefore, $htmlAfter, $type);
    }

    return $result;
  }
}

final class NoviCacheWipeLogger
{
  private const logSubdir = 'novi-cache-wipe';
  private const logFile = 'cache-wipes.jsonl';

  private static ?int $upgraderHtmlBefore = null;
  private static ?int $themeHtmlBefore = null;
  private static ?int $wpFullClearHtmlBefore = null;
  private static bool $wpFullClearLogged = false;
  private static ?int $requestHtmlBaseline = null;
  private static bool $suppressClearForTranslation = false;
  private static bool $warmerHandledThisRequest = false;

  public static function boot(): void
  {
    add_action('plugins_loaded', [self::class, 'wrapCometInstance'], 1);
    add_action('plugins_loaded', [self::class, 'armRequestHtmlBaseline'], 99);
    add_action('shutdown', [self::class, 'onShutdownMaybeWarm'], 100);
    add_action('admin_init', [self::class, 'logPendingCometAdminAction'], 1);
    add_filter('pre_objectcache_flush', [self::class, 'onObjectCacheFlush'], 9, 2);
    add_action('wp_ajax_autoptimize_delete_cache', [self::class, 'onAutoptimizeAjaxDelete'], 0);
    add_action('autoptimize_action_cachepurged', [self::class, 'onAutoptimizeCachePurged'], 0);
    add_action('wp_ajax_ncs_flush_snippet_cache', [self::class, 'onSnippetCacheFlush'], 0);
    //comet autoClearCache is $this->… so the proxy misses WP core/theme upgrades
    add_action('upgrader_process_complete', [self::class, 'onUpgraderBefore'], 1, 2);
    add_action('upgrader_process_complete', [self::class, 'onUpgraderAfter'], 20, 2);
    add_action('switch_theme', [self::class, 'onSwitchTheme'], 1);
    add_action('switch_theme', [self::class, 'onSwitchThemeAfter'], 20);
    add_action('activated_plugin', [self::class, 'onPluginToggle'], 1, 2);
    add_action('deactivated_plugin', [self::class, 'onPluginToggle'], 1, 2);
    //comet autoClearCache is $this->… so the proxy misses nav-menu and sidebar widget saves
    add_action('wp_create_nav_menu', [self::class, 'onCometWpFullClearBefore'], 1);
    add_action('wp_create_nav_menu', [self::class, 'onCometWpFullClearAfter'], 20, 2);
    add_action('wp_update_nav_menu', [self::class, 'onCometWpFullClearBefore'], 1);
    add_action('wp_update_nav_menu', [self::class, 'onCometWpFullClearAfter'], 20, 2);
    add_action('wp_delete_nav_menu', [self::class, 'onCometWpFullClearBefore'], 1);
    add_action('wp_delete_nav_menu', [self::class, 'onCometWpFullClearAfter'], 20, 1);
    add_action('update_option_sidebars_widgets', [self::class, 'onCometWpFullClearBefore'], 1);
    add_action('update_option_sidebars_widgets', [self::class, 'onCometWpFullClearAfter'], 20);
  }

  /**
   * Baseline HTML count on admin/CLI/cron/ajax only (avoids walking the cache on every frontend HIT).
   */
  public static function armRequestHtmlBaseline(): void
  {
    if (!self::isLikelyCacheMutatingRequest()) {
      return;
    }
    self::$requestHtmlBaseline = self::cometHtmlFileCount();
  }

  public static function onShutdownMaybeWarm(): void
  {
    if (self::$requestHtmlBaseline === null) {
      return;
    }
    self::maybeStartCacheWarmer(
      self::$requestHtmlBaseline,
      self::cometHtmlFileCount(),
      'shutdown_html_drop'
    );
  }

  private static function isLikelyCacheMutatingRequest(): bool
  {
    if (defined('WP_CLI') && WP_CLI) {
      return true;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
      return true;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
      return true;
    }
    if (function_exists('is_admin') && is_admin()) {
      return true;
    }
    return false;
  }

  public static function wrapCometInstance(): void
  {
    if (!isset($GLOBALS['comet_cache']) || !is_object($GLOBALS['comet_cache'])) {
      return;
    }

    if ($GLOBALS['comet_cache'] instanceof NoviCometCacheProxy) {
      return;
    }

    $GLOBALS['comet_cache'] = new NoviCometCacheProxy($GLOBALS['comet_cache']);
  }

  public static function logPendingCometAdminAction(): void
  {
    if (!is_admin() || empty($_REQUEST['comet_cache']) || !is_array($_REQUEST['comet_cache'])) {
      return;
    }

    $actions = wp_unslash($_REQUEST['comet_cache']);
    $tracked = [
      'wipeCache',
      'clearCache',
      'ajaxWipeCache',
      'ajaxClearCache',
      'saveOptions',
      'restoreDefaultOptions',
    ];

    foreach ($tracked as $action) {
      if (empty($actions[$action])) {
        continue;
      }

      self::record([
        'type' => 'comet_admin_request',
        'action' => $action,
        'cometHtmlBefore' => self::cometHtmlFileCount(),
      ]);
    }
  }

  /**
   * @param bool $shouldFlush
   * @param array<int, array<string, mixed>>|mixed $backtrace
   * @return bool
   */
  public static function onObjectCacheFlush($shouldFlush, $backtrace = [])
  {
    if (!$shouldFlush) {
      return $shouldFlush;
    }

    self::record([
      'type' => 'object_cache_flush',
      'backtrace' => self::formatBacktrace(is_array($backtrace) ? $backtrace : []),
    ]);

    return $shouldFlush;
  }

  public static function onAutoptimizeAjaxDelete(): void
  {
    self::record([
      'type' => 'autoptimize_admin_delete',
      'cometHtmlBefore' => self::cometHtmlFileCount(),
    ]);
  }

  public static function onAutoptimizeCachePurged(): void
  {
    self::record([
      'type' => 'autoptimize_cache_purged',
      'cometHtmlBefore' => self::cometHtmlFileCount(),
      'note' => 'Comet clear usually follows on autoptimize_action_cachepurged via flushPageCache()',
    ]);
  }

  public static function onSnippetCacheFlush(): void
  {
    self::record([
      'type' => 'ncs_flush_snippet_cache',
      'cometHtmlBefore' => self::cometHtmlFileCount(),
      'note' => 'Snippet flush clears Autoptimize + Comet via clearExternalCaches()',
    ]);
  }

  /**
   * @param \WP_Upgrader $upgrader
   * @param array<string, mixed> $data
   */
  public static function onUpgraderBefore($upgrader, $data = []): void
  {
    self::$upgraderHtmlBefore = self::cometHtmlFileCount();
    $type = is_array($data) ? (string) ($data['type'] ?? '') : '';
    //translation updates hit Comet's default case and wipe the full page cache — skip that
    if ($type === 'translation') {
      self::$suppressClearForTranslation = true;
      add_filter('comet_cache_disable_auto_clear_cache_routines', '__return_true', 999);
    }
  }

  /**
   * @param \WP_Upgrader $upgrader
   * @param array<string, mixed> $data
   */
  public static function onUpgraderAfter($upgrader, $data = []): void
  {
    $type = is_array($data) ? (string) ($data['type'] ?? '') : '';
    $action = is_array($data) ? (string) ($data['action'] ?? '') : '';
    $targets = [];
    if (is_array($data)) {
      foreach (['plugin', 'theme'] as $key) {
        if (!empty($data[$key]) && is_string($data[$key])) {
          $targets[] = $data[$key];
        }
      }
      foreach (['plugins', 'themes'] as $key) {
        if (!empty($data[$key]) && is_array($data[$key])) {
          foreach (array_slice($data[$key], 0, 12) as $item) {
            if (is_string($item)) {
              $targets[] = $item;
            }
          }
        }
      }
    }

    $htmlAfter = self::cometHtmlFileCount();

    if (self::$suppressClearForTranslation) {
      remove_filter('comet_cache_disable_auto_clear_cache_routines', '__return_true', 999);
      self::$suppressClearForTranslation = false;
      self::record([
        'type' => 'wp_upgrader',
        'upgradeType' => 'translation',
        'upgradeAction' => $action !== '' ? $action : 'update',
        'targets' => $targets,
        'cometHtmlBefore' => self::$upgraderHtmlBefore,
        'cometHtmlAfter' => $htmlAfter,
        'note' => 'Comet auto-clear suppressed for translation updates (page HTML unchanged)',
      ]);
      self::$upgraderHtmlBefore = null;
      return;
    }

    self::record([
      'type' => 'wp_upgrader',
      'upgradeType' => $type !== '' ? $type : 'unknown',
      'upgradeAction' => $action !== '' ? $action : 'update',
      'targets' => $targets,
      'cometHtmlBefore' => self::$upgraderHtmlBefore,
      'cometHtmlAfter' => $htmlAfter,
      'note' => 'Comet autoClearCache on upgrader_process_complete (core/plugin/theme)',
    ]);
    self::maybeStartCacheWarmer(self::$upgraderHtmlBefore, $htmlAfter, 'wp_upgrader:' . ($type !== '' ? $type : 'unknown'));
    self::$upgraderHtmlBefore = null;
  }

  /**
   * @param string $newName
   */
  public static function onSwitchTheme($newName = '', $newTheme = null, $oldTheme = null): void
  {
    self::$themeHtmlBefore = self::cometHtmlFileCount();
  }

  public static function onSwitchThemeAfter($newName = '', $newTheme = null, $oldTheme = null): void
  {
    $htmlAfter = self::cometHtmlFileCount();
    self::record([
      'type' => 'switch_theme',
      'theme' => is_string($newName) ? sanitize_text_field($newName) : '',
      'cometHtmlBefore' => self::$themeHtmlBefore,
      'cometHtmlAfter' => $htmlAfter,
      'note' => 'Comet autoClearCache on switch_theme',
    ]);
    self::maybeStartCacheWarmer(self::$themeHtmlBefore, $htmlAfter, 'switch_theme');
    self::$themeHtmlBefore = null;
  }

  /**
   * @param string $plugin
   * @param bool $networkWide
   */
  public static function onPluginToggle(string $plugin, $networkWide = false): void
  {
    $htmlBefore = self::cometHtmlFileCount();
    //comet clears at default priority 10; log now and schedule warmer check on shutdown
    self::record([
      'type' => current_action() === 'activated_plugin' ? 'plugin_activated' : 'plugin_deactivated',
      'plugin' => sanitize_text_field($plugin),
      'cometHtmlBefore' => $htmlBefore,
      'note' => 'Comet autoClearCache on plugin activation/deactivation',
    ]);
    add_action('shutdown', static function () use ($htmlBefore): void {
      NoviCacheWipeLogger::maybeStartCacheWarmer($htmlBefore, NoviCacheWipeLogger::cometHtmlFileCount(), 'plugin_toggle');
    }, 20);
  }

  /**
   * Capture HTML before Comet autoClearCache on nav-menu / sidebar widget hooks (priority 10).
   */
  public static function onCometWpFullClearBefore(): void
  {
    if (self::$wpFullClearHtmlBefore === null) {
      self::$wpFullClearHtmlBefore = self::cometHtmlFileCount();
    }
  }

  /**
   * Log the first Comet full clear in this request and start Cache Warmer if idle.
   *
   * @param mixed $arg0 menu id on nav-menu hooks; ignored for sidebars_widgets
   */
  public static function onCometWpFullClearAfter($arg0 = null): void
  {
    if (self::$wpFullClearLogged) {
      return;
    }

    $htmlAfter = self::cometHtmlFileCount();
    $hook = current_action();
    $payload = [
      'type' => $hook,
      'cometHtmlBefore' => self::$wpFullClearHtmlBefore,
      'cometHtmlAfter' => $htmlAfter,
      'note' => 'Comet autoClearCache on ' . $hook,
    ];
    if (is_int($arg0) || (is_string($arg0) && is_numeric($arg0))) {
      $payload['menuId'] = (int) $arg0;
    }

    self::record($payload);
    self::maybeStartCacheWarmer(self::$wpFullClearHtmlBefore, $htmlAfter, $hook);
    self::$wpFullClearLogged = true;
  }

  /**
   * After a substantial full Comet clear, schedule Cache Warmer if it is not already running.
   */
  public static function maybeStartCacheWarmer(?int $htmlBefore, ?int $htmlAfter, string $trigger): void
  {
    if (self::$warmerHandledThisRequest) {
      return;
    }
    if ($htmlBefore === null || $htmlAfter === null) {
      return;
    }
    //only react to a real full clear (warm cache → nearly empty)
    if ($htmlBefore < 30 || $htmlAfter > 20) {
      return;
    }
    if (!class_exists('Cache_Warmer\\Warm_Up')) {
      return;
    }

    $state = \Cache_Warmer\Warm_Up::get_last_warm_up_state();
    if ($state === 'in-progress') {
      self::$warmerHandledThisRequest = true;
      self::record([
        'type' => 'cache_warmer_skip',
        'trigger' => $trigger,
        'warmerState' => $state,
        'cometHtmlBefore' => $htmlBefore,
        'cometHtmlAfter' => $htmlAfter,
        'note' => 'Warmer already in-progress; no new start',
      ]);
      return;
    }

    $scheduled = false;
    if (function_exists('as_enqueue_async_action')) {
      as_enqueue_async_action('cache-warmer-start-from-cli', [false]);
      $scheduled = true;
    } elseif (class_exists('Cache_Warmer\\AJAX')) {
      try {
        \Cache_Warmer\AJAX::start_warm_up(false);
        $scheduled = true;
      } catch (Throwable $e) {
        self::record([
          'type' => 'cache_warmer_error',
          'trigger' => $trigger,
          'error' => mb_substr($e->getMessage(), 0, 300),
        ]);
        return;
      }
    }

    if (!$scheduled) {
      return;
    }

    self::$warmerHandledThisRequest = true;
    self::record([
      'type' => 'cache_warmer_scheduled',
      'trigger' => $trigger,
      'cometHtmlBefore' => $htmlBefore,
      'cometHtmlAfter' => $htmlAfter,
      'note' => 'Scheduled cache-warmer-start-from-cli after full Comet clear',
    ]);
  }

  /**
   * @param array<int, mixed> $args
   * @return array<int, mixed>
   */
  public static function sanitizeArgs(array $args): array
  {
    return array_map(static function ($value) {
      if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
        return $value;
      }

      if (is_string($value)) {
        return mb_substr(sanitize_text_field($value), 0, 500);
      }

      if (is_array($value)) {
        return '[array]';
      }

      return '[object]';
    }, $args);
  }

  public static function cometHtmlFileCount(): ?int
  {
    $base = WP_CONTENT_DIR . '/cache/comet-cache/cache';
    if (!is_dir($base)) {
      return 0;
    }

    $count = 0;

    try {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
      );

      foreach ($iterator as $file) {
        if (!$file->isFile()) {
          continue;
        }

        if (str_ends_with($file->getFilename(), '.html')) {
          $count++;
        }
      }
    } catch (Throwable $e) {
      return null;
    }

    return $count;
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function record(array $payload): void
  {
    $row = array_merge([
      'ts' => gmdate('c'),
      'blogId' => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0,
      'actor' => self::collectActor(),
      'request' => self::collectRequest(),
      'backtrace' => self::formatBacktrace(),
    ], $payload);

    self::appendRow($row);
  }

  /**
   * @return array<string, mixed>
   */
  private static function collectActor(): array
  {
    $actor = [];

    if (function_exists('wp_get_current_user')) {
      $user = wp_get_current_user();
      if ($user instanceof WP_User && $user->ID > 0) {
        $actor['userId'] = (int) $user->ID;
        $actor['userLogin'] = $user->user_login;
        $actor['userEmail'] = $user->user_email;
      }
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
      $actor['remoteAddr'] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    if (defined('WP_CLI') && WP_CLI) {
      $actor['cli'] = true;
      if (!empty($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
        $actor['cliArgv'] = array_slice($GLOBALS['argv'], 0, 8);
      }
    }

    if (defined('DOING_CRON') && DOING_CRON) {
      $actor['cron'] = true;
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
      $actor['ajax'] = true;
      if (!empty($_REQUEST['action'])) {
        $actor['ajaxAction'] = sanitize_text_field(wp_unslash($_REQUEST['action']));
      }
    }

    return $actor;
  }

  /**
   * @return array<string, mixed>
   */
  private static function collectRequest(): array
  {
    $request = [
      'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : null,
      'uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : null,
    ];

    if (function_exists('is_admin') && is_admin()) {
      $request['isAdmin'] = true;
    }

    if (!empty($_REQUEST['page'])) {
      $request['page'] = sanitize_text_field(wp_unslash($_REQUEST['page']));
    }

    return $request;
  }

  /**
   * @param array<int, array<string, mixed>>|null $trace
   * @return array<int, array<string, mixed>>
   */
  public static function formatBacktrace(?array $trace = null): array
  {
    if ($trace === null) {
      $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);
    }

    $frames = [];
    $pluginNeedle = 'novi-cache-wipe-logger';

    foreach ($trace as $frame) {
      $file = isset($frame['file']) ? (string) $frame['file'] : '';
      if ($file !== '' && str_contains($file, $pluginNeedle)) {
        continue;
      }

      $relativeFile = $file !== '' ? str_replace(ABSPATH, '', $file) : null;
      $function = '';
      if (!empty($frame['class'])) {
        $function .= $frame['class'];
      }
      if (!empty($frame['type'])) {
        $function .= $frame['type'];
      }
      if (!empty($frame['function'])) {
        $function .= $frame['function'];
      }

      $frames[] = [
        'file' => $relativeFile,
        'line' => $frame['line'] ?? null,
        'function' => $function,
      ];

      if (count($frames) >= 8) {
        break;
      }
    }

    return $frames;
  }

  /**
   * @param array<string, mixed> $row
   */
  private static function appendRow(array $row): void
  {
    $dir = WP_CONTENT_DIR . '/uploads/' . self::logSubdir;
    if (!is_dir($dir)) {
      if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($dir)) {
        return;
      }

      $htaccess = $dir . '/.htaccess';
      if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
      }

      $index = $dir . '/index.php';
      if (!file_exists($index)) {
        @file_put_contents($index, "<?php\n//silence\n");
      }
    }

    $line = wp_json_encode($row);
    if (!is_string($line)) {
      return;
    }

    @file_put_contents($dir . '/' . self::logFile, $line . "\n", FILE_APPEND | LOCK_EX);
  }
}

NoviCacheWipeLogger::boot();
