<?php

namespace Nectar\Utilities;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Log - Nectar logging wrapper
 * @since 0.0.3
 * @version 1.3.7
 * @see https://github.com/Seldaek/monolog/blob/2.x/README.md
 * @see https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 * @see https://github.com/advename/Simple-PHP-Logger/blob/master/Logger.php
 */
class Log {
  protected static $logger;

  static public function getLogger() {
    if (! self::$logger) {
      self::configureInstance();
    }

    return self::$logger;
  }

  /**
   * Configure Monolog to use a rotating files system.
   *
   * @return Logger
   */
  protected static function configureInstance() {
    $dir = NECTAR_BLOCKS_ROOT_DIR_PATH . DIRECTORY_SEPARATOR . 'nectar_logs';

    if (! file_exists($dir)){
      mkdir($dir, 0777, true);
    }

    $logger = new Logger('NectarBlocks');

    // Log level is set to DEBUG by default, but set to ERROR in production.
    $log_level = Logger::DEBUG;
    if (NECTAR_BUILD_MODE === 'production') {
      $log_level = Logger::ERROR;
    }

    $logger->pushHandler(new StreamHandler($dir . DIRECTORY_SEPARATOR . 'nectar_php.log', $log_level));
    self::$logger = $logger;
  }

  public static function debug($message, array $context = []){
    self::getLogger()->debug($message, $context);
  }

  public static function info($message, array $context = []){
    self::getLogger()->info($message, $context);
  }

  public static function notice($message, array $context = []){
    self::getLogger()->notice($message, $context);
  }

  public static function warning($message, array $context = []){
    self::getLogger()->warning($message, $context);
  }

  public static function error($message, array $context = []){
    self::getLogger()->error($message, $context);
  }

  public static function critical($message, array $context = []){
    self::getLogger()->critical($message, $context);
  }

  public static function alert($message, array $context = []){
    self::getLogger()->alert($message, $context);
  }

  public static function emergency($message, array $context = []){
    self::getLogger()->emergency($message, $context);
  }
}
