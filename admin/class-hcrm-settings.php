<?php
/**
 * Settings class for managing plugin settings.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Settings
 *
 * Handles plugin settings registration and management.
 *
 * @since 1.0.0
 */
class HCRM_Settings {

    /**
     * Option group name.
     *
     * @var string
     */
    const OPTION_GROUP = 'hcrm_settings';

    /**
     * API settings option name.
     *
     * @var string
     */
    const API_SETTINGS = 'hcrm_api_settings';

    /**
     * Sync settings option name (legacy, kept for backward compatibility).
     *
     * @var string
     */
    const SYNC_SETTINGS = 'hcrm_sync_settings';

    /**
     * Properties settings option name.
     *
     * @var string
     */
    const PROPERTIES_SETTINGS = 'hcrm_properties_settings';

    /**
     * Taxonomy settings option name.
     *
     * @var string
     */
    const TAXONOMY_SETTINGS = 'hcrm_taxonomy_settings';

    /**
     * Users settings option name.
     *
     * @var string
     */
    const USERS_SETTINGS = 'hcrm_users_settings';

    /**
     * Leads settings option name.
     *
     * @var string
     */
    const LEADS_SETTINGS = 'hcrm_leads_settings';

    /**
     * Register settings.
     */
    public function register() {
        // Register API settings
        register_setting(
            self::OPTION_GROUP,
            self::API_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_api_settings'],
                'default'           => self::get_api_defaults(),
            ]
        );

        // Register Properties settings
        register_setting(
            self::OPTION_GROUP,
            self::PROPERTIES_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_properties_settings'],
                'default'           => self::get_properties_defaults(),
            ]
        );

        // Register Taxonomy settings
        register_setting(
            self::OPTION_GROUP,
            self::TAXONOMY_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_taxonomy_settings'],
                'default'           => self::get_taxonomy_defaults(),
            ]
        );

        // Register Users settings
        register_setting(
            self::OPTION_GROUP,
            self::USERS_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_users_settings'],
                'default'           => self::get_users_defaults(),
            ]
        );

        // Register Leads settings
        register_setting(
            self::OPTION_GROUP,
            self::LEADS_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_leads_settings'],
                'default'           => self::get_leads_defaults(),
            ]
        );

        // Keep legacy sync settings for backward compatibility
        register_setting(
            self::OPTION_GROUP,
            self::SYNC_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_sync_settings'],
                'default'           => self::get_sync_defaults(),
            ]
        );
    }

    /**
     * Get API settings defaults.
     *
     * @return array
     */
    public static function get_api_defaults() {
        return [
            'api_base_url' => '',
            'api_token'    => '',
            'auto_sync'    => false,
        ];
    }

    /**
     * Get properties settings defaults.
     *
     * @return array
     */
    public static function get_properties_defaults() {
        return [
            'sync_properties' => true,
            'sync_on_save'    => true,
        ];
    }

    /**
     * Get taxonomy settings defaults.
     *
     * @return array
     */
    public static function get_taxonomy_defaults() {
        return [
            'sync_property_type'    => true,
            'sync_property_status'  => true,
            'sync_property_label'   => true,
            'sync_property_feature' => true,
        ];
    }

    /**
     * Get users settings defaults.
     *
     * @return array
     */
    public static function get_users_defaults() {
        return [
            'sync_users'   => true,
            'sync_avatars' => true,
            'auto_sync'    => false,
            'role_mapping' => [
                'houzez_manager' => 'manager',
                'houzez_agency'  => 'agency',
                'houzez_agent'   => 'agent',
                'administrator'  => 'admin',
            ],
        ];
    }

    /**
     * Get leads settings defaults.
     *
     * @return array
     */
    public static function get_leads_defaults() {
        return [
            'sync_leads'           => true,
            'use_background_queue' => true,
            'hooks_enabled'        => [
                'houzez_ele_inquiry_form'      => true,
                'houzez_ele_contact_form'      => true,
                'houzez_contact_realtor'       => true,
                'houzez_schedule_send_message' => true,
                'houzez_property_agent_contact' => true,
            ],
        ];
    }

    /**
     * Get legacy sync settings defaults.
     *
     * @return array
     */
    public static function get_sync_defaults() {
        return [
            'sync_properties'     => true,
            'sync_taxonomies'     => true,
            'sync_users'          => false,
            'sync_leads'          => false,
            'auto_sync'           => false,
            'taxonomy_auto_sync'  => false,
        ];
    }

    /**
     * Sanitize API settings.
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_api_settings($input) {
        $sanitized = [];

        // API Base URL
        if (isset($input['api_base_url'])) {
            $url = esc_url_raw(trim($input['api_base_url']));
            $sanitized['api_base_url'] = rtrim($url, '/');
        }

        // API Token - only update if a new value is provided
        if (isset($input['api_token']) && !empty($input['api_token'])) {
            // Don't re-encrypt if it's the placeholder
            if ($input['api_token'] !== '********') {
                $sanitized['api_token'] = HCRM_API_Auth::encrypt_token(sanitize_text_field($input['api_token']));
            } else {
                // Keep existing token
                $existing = get_option(self::API_SETTINGS, []);
                $sanitized['api_token'] = $existing['api_token'] ?? '';
            }
        }

        // Auto sync toggle
        $sanitized['auto_sync'] = !empty($input['auto_sync']);

        return $sanitized;
    }

    /**
     * Sanitize properties settings.
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_properties_settings($input) {
        return [
            'sync_properties' => !empty($input['sync_properties']),
            'sync_on_save'    => !empty($input['sync_on_save']),
        ];
    }

    /**
     * Sanitize taxonomy settings.
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_taxonomy_settings($input) {
        return [
            'sync_property_type'    => !empty($input['sync_property_type']),
            'sync_property_status'  => !empty($input['sync_property_status']),
            'sync_property_label'   => !empty($input['sync_property_label']),
            'sync_property_feature' => !empty($input['sync_property_feature']),
        ];
    }

    /**
     * Sanitize users settings.
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_users_settings($input) {
        $sanitized = [
            'sync_users'   => !empty($input['sync_users']),
            'sync_avatars' => !empty($input['sync_avatars']),
            'auto_sync'    => !empty($input['auto_sync']),
            'role_mapping' => [],
        ];

        // Sanitize role mapping
        if (isset($input['role_mapping']) && is_array($input['role_mapping'])) {
            $valid_crm_roles = ['admin', 'manager', 'agency', 'agent'];
            foreach ($input['role_mapping'] as $wp_role => $crm_role) {
                $wp_role = sanitize_key($wp_role);
                $crm_role = sanitize_key($crm_role);
                if (in_array($crm_role, $valid_crm_roles, true)) {
                    $sanitized['role_mapping'][$wp_role] = $crm_role;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize leads settings.
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_leads_settings($input) {
        $sanitized = [
            'sync_leads'           => !empty($input['sync_leads']),
            'use_background_queue' => !empty($input['use_background_queue']),
            'hooks_enabled'        => [],
        ];

        // Sanitize hooks enabled
        $valid_hooks = [
            'houzez_ele_inquiry_form',
            'houzez_ele_contact_form',
            'houzez_contact_realtor',
            'houzez_schedule_send_message',
            'houzez_property_agent_contact',
        ];

        if (isset($input['hooks_enabled']) && is_array($input['hooks_enabled'])) {
            foreach ($valid_hooks as $hook) {
                $sanitized['hooks_enabled'][$hook] = !empty($input['hooks_enabled'][$hook]);
            }
        } else {
            // All hooks disabled if not set
            foreach ($valid_hooks as $hook) {
                $sanitized['hooks_enabled'][$hook] = false;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize sync settings (legacy).
     *
     * @param array $input Input values.
     * @return array Sanitized values.
     */
    public function sanitize_sync_settings($input) {
        return [
            'sync_properties'     => !empty($input['sync_properties']),
            'sync_taxonomies'     => !empty($input['sync_taxonomies']),
            'sync_users'          => !empty($input['sync_users']),
            'sync_leads'          => !empty($input['sync_leads']),
            'auto_sync'           => !empty($input['auto_sync']),
            'taxonomy_auto_sync'  => !empty($input['taxonomy_auto_sync']),
        ];
    }

    /**
     * Get a setting value.
     *
     * @param string $key     Setting key (e.g., 'api_base_url', 'sync_properties').
     * @param mixed  $default Default value.
     * @return mixed Setting value.
     */
    public static function get($key, $default = null) {
        // API settings keys
        $api_keys = ['api_base_url', 'api_token', 'auto_sync'];
        if (in_array($key, $api_keys, true)) {
            $settings = get_option(self::API_SETTINGS, self::get_api_defaults());
            return $settings[$key] ?? $default;
        }

        // Properties settings keys
        $properties_keys = ['sync_properties', 'sync_on_save'];
        if (in_array($key, $properties_keys, true)) {
            $settings = get_option(self::PROPERTIES_SETTINGS, self::get_properties_defaults());
            return $settings[$key] ?? $default;
        }

        // Taxonomy settings keys
        $taxonomy_keys = ['sync_property_type', 'sync_property_status', 'sync_property_label', 'sync_property_feature'];
        if (in_array($key, $taxonomy_keys, true)) {
            $settings = get_option(self::TAXONOMY_SETTINGS, self::get_taxonomy_defaults());
            return $settings[$key] ?? $default;
        }

        // Users settings keys
        $users_keys = ['sync_users', 'sync_avatars', 'role_mapping'];
        if (in_array($key, $users_keys, true)) {
            $settings = get_option(self::USERS_SETTINGS, self::get_users_defaults());
            return $settings[$key] ?? $default;
        }

        // Leads settings keys
        $leads_keys = ['sync_leads', 'use_background_queue', 'hooks_enabled'];
        if (in_array($key, $leads_keys, true)) {
            $settings = get_option(self::LEADS_SETTINGS, self::get_leads_defaults());
            return $settings[$key] ?? $default;
        }

        // Legacy sync settings keys (for sync_taxonomies, taxonomy_auto_sync)
        $sync_keys = ['sync_taxonomies', 'taxonomy_auto_sync'];
        if (in_array($key, $sync_keys, true)) {
            $settings = get_option(self::SYNC_SETTINGS, self::get_sync_defaults());
            return $settings[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Set a setting value.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool Success.
     */
    public static function set($key, $value) {
        $api_keys = ['api_base_url', 'api_token', 'auto_sync'];
        if (in_array($key, $api_keys, true)) {
            $settings = get_option(self::API_SETTINGS, self::get_api_defaults());
            $settings[$key] = $value;
            return update_option(self::API_SETTINGS, $settings);
        }

        $properties_keys = ['sync_properties', 'sync_on_save'];
        if (in_array($key, $properties_keys, true)) {
            $settings = get_option(self::PROPERTIES_SETTINGS, self::get_properties_defaults());
            $settings[$key] = $value;
            return update_option(self::PROPERTIES_SETTINGS, $settings);
        }

        $taxonomy_keys = ['sync_property_type', 'sync_property_status', 'sync_property_label', 'sync_property_feature'];
        if (in_array($key, $taxonomy_keys, true)) {
            $settings = get_option(self::TAXONOMY_SETTINGS, self::get_taxonomy_defaults());
            $settings[$key] = $value;
            return update_option(self::TAXONOMY_SETTINGS, $settings);
        }

        $users_keys = ['sync_users', 'sync_avatars', 'role_mapping'];
        if (in_array($key, $users_keys, true)) {
            $settings = get_option(self::USERS_SETTINGS, self::get_users_defaults());
            $settings[$key] = $value;
            return update_option(self::USERS_SETTINGS, $settings);
        }

        $leads_keys = ['sync_leads', 'use_background_queue', 'hooks_enabled'];
        if (in_array($key, $leads_keys, true)) {
            $settings = get_option(self::LEADS_SETTINGS, self::get_leads_defaults());
            $settings[$key] = $value;
            return update_option(self::LEADS_SETTINGS, $settings);
        }

        return false;
    }

    /**
     * Get all API settings.
     *
     * @return array
     */
    public static function get_api_settings() {
        return get_option(self::API_SETTINGS, self::get_api_defaults());
    }

    /**
     * Get all properties settings.
     *
     * @return array
     */
    public static function get_properties_settings() {
        return get_option(self::PROPERTIES_SETTINGS, self::get_properties_defaults());
    }

    /**
     * Get all taxonomy settings.
     *
     * @return array
     */
    public static function get_taxonomy_settings() {
        return get_option(self::TAXONOMY_SETTINGS, self::get_taxonomy_defaults());
    }

    /**
     * Get all users settings.
     *
     * @return array
     */
    public static function get_users_settings() {
        return get_option(self::USERS_SETTINGS, self::get_users_defaults());
    }

    /**
     * Get all leads settings.
     *
     * @return array
     */
    public static function get_leads_settings() {
        return get_option(self::LEADS_SETTINGS, self::get_leads_defaults());
    }

    /**
     * Get all sync settings (legacy).
     *
     * @return array
     */
    public static function get_sync_settings() {
        return get_option(self::SYNC_SETTINGS, self::get_sync_defaults());
    }

    /**
     * Check if API is configured.
     *
     * @return bool
     */
    public static function is_api_configured() {
        $settings = self::get_api_settings();
        return !empty($settings['api_base_url']) && !empty($settings['api_token']);
    }

    /**
     * Get the API base URL.
     *
     * @return string
     */
    public static function get_api_base_url() {
        return self::get('api_base_url', '');
    }

    /**
     * Get the decrypted API token.
     *
     * @return string
     */
    public static function get_api_token() {
        return HCRM_API_Auth::get_stored_token();
    }

    /**
     * Check if auto sync is enabled.
     *
     * @return bool
     */
    public static function is_auto_sync_enabled() {
        return (bool) self::get('auto_sync', false);
    }

    /**
     * Check if a specific lead hook is enabled.
     *
     * @param string $hook Hook name.
     * @return bool
     */
    public static function is_lead_hook_enabled($hook) {
        $hooks_enabled = self::get('hooks_enabled', []);
        return !empty($hooks_enabled[$hook]);
    }

    /**
     * Get the CRM role for a WordPress role.
     *
     * @param string $wp_role WordPress role.
     * @return string|null CRM role or null if not mapped.
     */
    public static function get_crm_role($wp_role) {
        $role_mapping = self::get('role_mapping', []);
        return $role_mapping[$wp_role] ?? null;
    }

    /**
     * Check if users auto-sync is enabled.
     *
     * @return bool
     */
    public static function is_users_auto_sync_enabled() {
        $settings = get_option(self::USERS_SETTINGS, self::get_users_defaults());
        return !empty($settings['auto_sync']) && !empty($settings['sync_users']);
    }

    /**
     * Check if taxonomy auto-sync is enabled.
     *
     * @return bool
     */
    public static function is_taxonomy_auto_sync_enabled() {
        $settings = get_option(self::SYNC_SETTINGS, self::get_sync_defaults());
        return !empty($settings['taxonomy_auto_sync']) && !empty($settings['sync_taxonomies']);
    }
}
