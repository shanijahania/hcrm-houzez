<?php
/**
 * Admin class for handling WordPress admin functionality.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Admin
 *
 * Handles admin menu, scripts, and settings page rendering.
 *
 * @since 1.0.0
 */
class HCRM_Admin {

    /**
     * The plugin name.
     *
     * @var string
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Settings handler.
     *
     * @var HCRM_Settings
     */
    private $settings;

    /**
     * Constructor.
     *
     * @param string $plugin_name The plugin name.
     * @param string $version     The plugin version.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings = new HCRM_Settings();
    }

    /**
     * Register the admin menu.
     */
    public function add_menu() {
        add_menu_page(
            __('HCRM Houzez', 'hcrm-houzez'),
            __('HCRM Houzez', 'hcrm-houzez'),
            'manage_options',
            'hcrm-houzez',
            [$this, 'render_settings_page'],
            'dashicons-update',
            30
        );

        // Add Logs submenu
        add_submenu_page(
            'hcrm-houzez',
            __('Sync Logs', 'hcrm-houzez'),
            __('Sync Logs', 'hcrm-houzez'),
            'manage_options',
            'hcrm-logs',
            ['HCRM_Logs', 'render_page']
        );
    }

    /**
     * Register settings.
     */
    public function init_settings() {
        $this->settings->register();
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_scripts($hook) {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_hcrm-houzez') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'hcrm-admin',
            HCRM_PLUGIN_URL . 'admin/css/hcrm-admin.css',
            [],
            $this->version
        );

        // Enqueue scripts
        wp_enqueue_script(
            'hcrm-admin',
            HCRM_PLUGIN_URL . 'admin/js/hcrm-admin.js',
            ['jquery'],
            $this->version,
            true
        );

        // Localize script
        wp_localize_script('hcrm-admin', 'hcrm_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hcrm_admin_nonce'),
            'i18n'     => [
                'connected'     => __('Connected', 'hcrm-houzez'),
                'not_connected' => __('Not connected', 'hcrm-houzez'),
                'testing'       => __('Testing connection...', 'hcrm-houzez'),
                'saving'        => __('Saving...', 'hcrm-houzez'),
                'syncing'       => __('Syncing...', 'hcrm-houzez'),
                'confirm_sync'  => __('Are you sure you want to sync? This may take a while for large datasets.', 'hcrm-houzez'),
                'copied'        => __('Copied to clipboard!', 'hcrm-houzez'),
                'error'         => __('An error occurred. Please try again.', 'hcrm-houzez'),
            ],
        ]);
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=hcrm-houzez'),
            __('Settings', 'hcrm-houzez')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include the settings page template
        include HCRM_PLUGIN_PATH . 'admin/partials/settings-page.php';
    }
}
