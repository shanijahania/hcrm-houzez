<?php
/**
 * The core plugin class.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Load trait
require_once HCRM_PLUGIN_PATH . 'includes/traits/trait-hcrm-singleton.php';

/**
 * Class HCRM_Houzez
 *
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @since 1.0.0
 */
class HCRM_Houzez {

    use HCRM_Singleton;

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @var HCRM_Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->version = defined('HCRM_VERSION') ? HCRM_VERSION : '1.0.0';
        $this->plugin_name = 'hcrm-houzez';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_webhook_hooks();
        $this->define_sync_hooks();
        $this->define_lead_hooks();
        $this->define_taxonomy_hooks();
        $this->define_user_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core classes
        require_once HCRM_PLUGIN_PATH . 'includes/class-hcrm-loader.php';
        require_once HCRM_PLUGIN_PATH . 'includes/class-hcrm-logger.php';

        // API classes
        require_once HCRM_PLUGIN_PATH . 'includes/api/class-hcrm-api-response.php';
        require_once HCRM_PLUGIN_PATH . 'includes/api/class-hcrm-api-auth.php';
        require_once HCRM_PLUGIN_PATH . 'includes/api/class-hcrm-api-client.php';

        // Sync classes
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-data-mapper.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-progress.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-property.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-manager.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-user.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-taxonomy.php';
        require_once HCRM_PLUGIN_PATH . 'includes/sync/class-hcrm-sync-lead.php';

        // Entity mapper
        require_once HCRM_PLUGIN_PATH . 'includes/class-hcrm-entity-mapper.php';

        // Webhook classes
        require_once HCRM_PLUGIN_PATH . 'includes/webhook/class-hcrm-webhook-processor.php';
        require_once HCRM_PLUGIN_PATH . 'includes/webhook/class-hcrm-webhook-handler.php';

        // Admin classes
        require_once HCRM_PLUGIN_PATH . 'admin/class-hcrm-settings.php';
        require_once HCRM_PLUGIN_PATH . 'admin/class-hcrm-ajax.php';
        require_once HCRM_PLUGIN_PATH . 'admin/class-hcrm-admin.php';
        require_once HCRM_PLUGIN_PATH . 'admin/class-hcrm-logs.php';

        $this->loader = new HCRM_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Note: Since WordPress 4.6+, translations are automatically loaded
     * for plugins hosted on WordPress.org. No manual load_plugin_textdomain() needed.
     *
     * @since 1.0.0
     */
    private function set_locale() {
        // WordPress 4.6+ automatically loads translations for plugins on WordPress.org
        // No action needed here - kept for backwards compatibility structure
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since 1.0.0
     */
    private function define_admin_hooks() {
        $admin = new HCRM_Admin($this->get_plugin_name(), $this->get_version());

        // Admin menu and scripts
        $this->loader->add_action('admin_menu', $admin, 'add_menu');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->loader->add_action('admin_init', $admin, 'init_settings');

        // Plugin action links
        $this->loader->add_filter('plugin_action_links_' . HCRM_PLUGIN_BASENAME, $admin, 'add_action_links');

        // AJAX handlers
        $ajax = new HCRM_Ajax();
        $this->loader->add_action('wp_ajax_hcrm_test_connection', $ajax, 'test_connection');
        $this->loader->add_action('wp_ajax_hcrm_save_settings', $ajax, 'save_settings');
        $this->loader->add_action('wp_ajax_hcrm_sync_properties', $ajax, 'sync_properties');
        $this->loader->add_action('wp_ajax_hcrm_sync_taxonomies', $ajax, 'sync_taxonomies');
        $this->loader->add_action('wp_ajax_hcrm_sync_users', $ajax, 'sync_users_ajax');
        $this->loader->add_action('wp_ajax_hcrm_sync_leads', $ajax, 'sync_leads');
        $this->loader->add_action('wp_ajax_hcrm_get_sync_status', $ajax, 'get_sync_status');

        // New AJAX handlers for settings tabs
        $this->loader->add_action('wp_ajax_hcrm_save_properties_settings', $ajax, 'save_properties_settings');
        $this->loader->add_action('wp_ajax_hcrm_save_users_settings', $ajax, 'save_users_settings');
        $this->loader->add_action('wp_ajax_hcrm_save_leads_settings', $ajax, 'save_leads_settings');
        $this->loader->add_action('wp_ajax_hcrm_sync_taxonomy', $ajax, 'sync_taxonomy');
        $this->loader->add_action('wp_ajax_hcrm_get_users_stats', $ajax, 'get_users_stats');

        // Background sync progress handlers
        $this->loader->add_action('wp_ajax_hcrm_get_sync_progress', $ajax, 'get_sync_progress');
        $this->loader->add_action('wp_ajax_hcrm_cancel_sync', $ajax, 'cancel_sync');
        $this->loader->add_action('wp_ajax_hcrm_get_active_syncs', $ajax, 'get_active_syncs');
        $this->loader->add_action('wp_ajax_hcrm_clear_stuck_syncs', $ajax, 'clear_stuck_syncs');
        $this->loader->add_action('wp_ajax_nopriv_hcrm_trigger_sync', $ajax, 'trigger_sync');
        $this->loader->add_action('wp_ajax_hcrm_trigger_sync', $ajax, 'trigger_sync');

        // Logs handlers
        $this->loader->add_action('wp_ajax_hcrm_get_logs', $ajax, 'get_logs');
        $this->loader->add_action('wp_ajax_hcrm_clear_logs', $ajax, 'clear_logs');
    }

    /**
     * Register all of the hooks related to the webhook functionality.
     *
     * @since 1.0.0
     */
    private function define_webhook_hooks() {
        $webhook = new HCRM_Webhook_Handler();
        $this->loader->add_action('rest_api_init', $webhook, 'register_routes');
    }

    /**
     * Register all of the hooks related to the sync functionality.
     *
     * @since 1.0.0
     */
    private function define_sync_hooks() {
        $sync_manager = HCRM_Sync_Manager::get_instance();

        // Hook into property save for automatic sync
        $this->loader->add_action('save_post_property', $sync_manager, 'on_property_save', 20, 3);

        // Hook into property trash
        $this->loader->add_action('wp_trash_post', $sync_manager, 'on_property_trash');

        // Hook into property status transitions
        $this->loader->add_action('transition_post_status', $sync_manager, 'on_status_transition', 10, 3);

        // Action Scheduler hook for background batch sync processing
        // Action Scheduler passes associative array values as separate arguments
        add_action('hcrm_process_sync_batch', function ($sync_id, $type, $offset, $options = []) use ($sync_manager) {
            if ($sync_id && $type) {
                try {
                    $sync_manager->process_sync_batch($sync_id, $type, $offset, $options);
                } catch (Exception $e) {
                    HCRM_Sync_Progress::fail($sync_id, $e->getMessage());
                }
            }
        }, 10, 4);

        // Clean up old sync progress records daily
        if (!wp_next_scheduled('hcrm_cleanup_sync_progress')) {
            wp_schedule_event(time(), 'daily', 'hcrm_cleanup_sync_progress');
        }
        add_action('hcrm_cleanup_sync_progress', ['HCRM_Sync_Progress', 'cleanup_old']);
    }

    /**
     * Register all of the hooks related to lead capture functionality.
     *
     * @since 1.0.0
     */
    private function define_lead_hooks() {
        // Only register if lead sync is enabled
        if (!HCRM_Settings::get('sync_leads', false)) {
            return;
        }

        $lead_sync = new HCRM_Sync_Lead();
        $lead_sync->register_hooks();

        // Register Action Scheduler hook for background processing
        $this->loader->add_action('hcrm_process_lead', $lead_sync, 'sync_lead');
    }

    /**
     * Register taxonomy auto-sync hooks.
     *
     * @since 1.0.0
     */
    private function define_taxonomy_hooks() {
        // Only register if taxonomy sync is enabled
        if (!HCRM_Settings::get('sync_taxonomies', false)) {
            return;
        }

        $taxonomy_sync = new HCRM_Sync_Taxonomy();
        $taxonomy_sync->register_auto_sync_hooks();
    }

    /**
     * Register user (agent/agency) auto-sync hooks.
     *
     * @since 1.0.0
     */
    private function define_user_hooks() {
        // Only register if user sync is enabled
        if (!HCRM_Settings::get('sync_users', false)) {
            return;
        }

        $user_sync = new HCRM_Sync_User();
        $user_sync->register_auto_sync_hooks();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since 1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since  1.0.0
     * @return string The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since  1.0.0
     * @return HCRM_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since  1.0.0
     * @return string The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
