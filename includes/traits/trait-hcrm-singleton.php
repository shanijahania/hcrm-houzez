<?php
/**
 * Singleton trait for creating single instance classes.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Trait HCRM_Singleton
 *
 * Provides a singleton pattern implementation for classes.
 *
 * @since 1.0.0
 */
trait HCRM_Singleton {

    /**
     * The single instance of the class.
     *
     * @var static
     */
    private static $instance = null;

    /**
     * Get the single instance of the class.
     *
     * @return static
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     *
     * @throws \Exception
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
