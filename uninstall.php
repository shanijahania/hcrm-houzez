<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option('hcrm_api_settings');
delete_option('hcrm_sync_settings');
delete_option('hcrm_db_version');
delete_option('hcrm_webhook_secret');

// Drop custom tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hcrm_entity_map");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hcrm_sync_log");

// Delete all post meta with our prefix
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_hcrm_%'");

// Delete all term meta with our prefix
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup
$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_hcrm_%'");

// Delete all user meta with our prefix
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_hcrm_%'");

// Clear any cached data that may have been stored
wp_cache_flush();
