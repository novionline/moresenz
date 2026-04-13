<?php

namespace NoviOnline\Core;

/**
 * Singleton class
 * @package NoviOnline\Core
 * @source https://github.com/micropackage/singleton/blob/develop/src/Singleton.php
 */
class Singleton
{
    /**
     * Object instances
     * @var array
     */
    protected static array $instances = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
    }

    /**
     * Clone method
     * @return void
     */
    protected function __clone()
    {
    }

    /**
     * Wakeup method
     * @throws \Exception When used.
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Gets the instance
     * @return static
     */
    public static function getInstance()
    {
        $class = get_called_class();
        $args = func_get_args();

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static(...$args);
        }

        return self::$instances[$class];
    }
}