<?php
/**
 * Fired during plugin activation.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Activator
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class HCRM_Activator {

    /**
     * Database version for migrations.
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Plugin activation handler.
     *
     * Creates database tables, sets default options, and flushes rewrite rules.
     *
     * @since 1.0.0
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();

        // Flush rewrite rules for webhook endpoint
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     *
     * @since 1.0.0
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Entity mapping table
        $sql_entity_map = "CREATE TABLE {$wpdb->prefix}hcrm_entity_map (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            wp_id BIGINT(20) UNSIGNED NOT NULL,
            crm_uuid VARCHAR(36) NOT NULL,
            taxonomy VARCHAR(50) DEFAULT NULL,
            last_synced_at DATETIME DEFAULT NULL,
            last_sync_direction VARCHAR(10) DEFAULT NULL,
            sync_hash VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entity_unique (entity_type, wp_id, taxonomy),
            KEY crm_uuid (crm_uuid),
            KEY entity_type (entity_type),
            KEY last_synced_at (last_synced_at)
        ) $charset_collate;";

        // Sync log table
        $sql_sync_log = "CREATE TABLE {$wpdb->prefix}hcrm_sync_log (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            direction VARCHAR(10) NOT NULL,
            status VARCHAR(20) NOT NULL,
            request_data LONGTEXT DEFAULT NULL,
            response_data LONGTEXT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity_lookup (entity_type, entity_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_entity_map);
        dbDelta($sql_sync_log);

        // Store database version
        update_option('hcrm_db_version', self::DB_VERSION);
    }

    /**
     * Set default plugin options.
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        $api_settings = get_option('hcrm_api_settings');
        if (false === $api_settings) {
            add_option('hcrm_api_settings', [
                'api_base_url' => '',
                'api_token'    => '',
            ]);
        }

        $sync_settings = get_option('hcrm_sync_settings');
        if (false === $sync_settings) {
            add_option('hcrm_sync_settings', [
                'sync_properties'  => true,
                'sync_taxonomies'  => true,
                'sync_users'       => false,
                'sync_leads'       => false,
                'auto_sync'        => false,
            ]);
        }
    }

}
