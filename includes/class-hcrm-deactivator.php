<?php
/**
 * Fired during plugin deactivation.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Deactivator
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 1.0.0
 */
class HCRM_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * Clears scheduled events and flushes rewrite rules.
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled sync events
        wp_clear_scheduled_hook('hcrm_scheduled_sync');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
