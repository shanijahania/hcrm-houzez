<?php
/**
 * Webhook Processor class for processing webhook actions.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Webhook_Processor
 *
 * Processes incoming webhook actions from the CRM.
 *
 * @since 1.0.0
 */
class HCRM_Webhook_Processor {

    /**
     * Sync manager instance.
     *
     * @var HCRM_Sync_Manager
     */
    private $sync_manager = null;

    /**
     * Supported webhook actions.
     *
     * @var array
     */
    const SUPPORTED_ACTIONS = [
        'listing.created',
        'listing.updated',
        'listing.deleted',
        'listing.status_changed',
        'taxonomy.created',
        'taxonomy.updated',
        'taxonomy.deleted',
        'user.created',
        'user.updated',
        'user.deleted',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        // Lazy initialization - don't load sync manager until needed
    }

    /**
     * Get the sync manager instance.
     *
     * @return HCRM_Sync_Manager
     */
    private function get_sync_manager() {
        if ($this->sync_manager === null) {
            $this->sync_manager = HCRM_Sync_Manager::get_instance();
        }
        return $this->sync_manager;
    }

    /**
     * Check if an action is valid.
     *
     * @param string $action Action name.
     * @return bool
     */
    public function is_valid_action($action) {
        return in_array($action, self::SUPPORTED_ACTIONS, true);
    }

    /**
     * Process a webhook action.
     *
     * @param string $action  Webhook action.
     * @param array  $payload Webhook payload.
     * @return array Result data.
     * @throws Exception On processing error.
     */
    public function process($action, $payload) {
        if (!$this->is_valid_action($action)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for internal use
            throw new Exception( 'Unsupported action: ' . esc_html( $action ) );
        }

        // Set webhook processing flag to prevent infinite loops
        // This flag is checked by auto-sync hooks to skip sending data back to CRM
        if (!defined('HCRM_WEBHOOK_PROCESSING')) {
            define('HCRM_WEBHOOK_PROCESSING', true);
        }

        // Map action to handler method
        $method = str_replace('.', '_', "handle_{$action}");

        if (!method_exists($this, $method)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for internal use
            throw new Exception( 'Handler not found for action: ' . esc_html( $action ) );
        }

        return $this->$method($payload);
    }

    /**
     * Handle listing.created webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_listing_created($payload) {
        $uuid = $payload['uuid'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid)) {
            throw new Exception('Missing listing UUID');
        }

        // Check if property already exists
        $existing_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'property');
        if ($existing_id) {
            return [
                'status'      => 'skipped',
                'message'     => 'Property already exists',
                'property_id' => $existing_id,
            ];
        }

        // Create property from CRM data (silently - don't trigger auto-sync hooks)
        $property_sync = new HCRM_Sync_Property();
        $property_id = $property_sync->create_or_update_from_crm($data, null, true);

        if (is_wp_error($property_id)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $property_id->get_error_message() ) );
        }

        // Save entity mapping
        $this->get_sync_manager()->save_entity_map('property', $property_id, $uuid, 'webhook');

        return [
            'status'      => 'created',
            'property_id' => $property_id,
        ];
    }

    /**
     * Handle listing.updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_listing_updated($payload) {
        $uuid = $payload['uuid'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid)) {
            throw new Exception('Missing listing UUID');
        }

        // Find existing property
        $property_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'property');

        if (!$property_id) {
            // Property doesn't exist, create it
            return $this->handle_listing_created($payload);
        }

        // Update property (silently)
        $property_sync = new HCRM_Sync_Property();
        $result = $property_sync->create_or_update_from_crm($data, $property_id, true);

        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $result->get_error_message() ) );
        }

        // Update entity mapping timestamp
        $this->get_sync_manager()->save_entity_map('property', $property_id, $uuid, 'webhook');

        return [
            'status'      => 'updated',
            'property_id' => $property_id,
        ];
    }

    /**
     * Handle listing.deleted webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_listing_deleted($payload) {
        $uuid = $payload['uuid'] ?? null;

        if (empty($uuid)) {
            throw new Exception('Missing listing UUID');
        }

        // Find existing property
        $property_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'property');

        if (!$property_id) {
            return [
                'status'  => 'skipped',
                'message' => 'Property not found',
            ];
        }

        // Move to trash (silently - hooks are already disabled by HCRM_WEBHOOK_PROCESSING flag)
        $result = wp_trash_post($property_id);

        if (!$result) {
            throw new Exception('Failed to trash property');
        }

        return [
            'status'      => 'deleted',
            'property_id' => $property_id,
        ];
    }

    /**
     * Handle listing.status_changed webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_listing_status_changed($payload) {
        $uuid = $payload['uuid'] ?? null;
        $new_status = $payload['status'] ?? null;

        if (empty($uuid)) {
            throw new Exception('Missing listing UUID');
        }

        // Find existing property
        $property_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'property');

        if (!$property_id) {
            return [
                'status'  => 'skipped',
                'message' => 'Property not found',
            ];
        }

        // Update property status taxonomy (silently)
        if (!empty($new_status['name'])) {
            // Find or create the status term
            $term = get_term_by('name', $new_status['name'], 'property_status');

            if (!$term) {
                $term_result = wp_insert_term($new_status['name'], 'property_status');
                if (!is_wp_error($term_result)) {
                    $term = get_term($term_result['term_id']);
                }
            }

            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($property_id, $term->term_id, 'property_status');

                // Save status UUID mapping if provided
                if (!empty($new_status['uuid'])) {
                    $this->get_sync_manager()->save_entity_map(
                        'taxonomy',
                        $term->term_id,
                        $new_status['uuid'],
                        'webhook',
                        'property_status'
                    );
                }
            }
        }

        return [
            'status'      => 'updated',
            'property_id' => $property_id,
        ];
    }

    /**
     * Handle taxonomy.created webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_taxonomy_created($payload) {
        $uuid = $payload['uuid'] ?? null;
        $taxonomy_type = $payload['taxonomy_type'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid) || empty($taxonomy_type)) {
            throw new Exception('Missing taxonomy UUID or type');
        }

        // Map CRM taxonomy type to WordPress taxonomy
        $wp_taxonomy = $this->map_taxonomy_type($taxonomy_type);
        if (!$wp_taxonomy) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message
            throw new Exception( 'Unsupported taxonomy type: ' . esc_html( $taxonomy_type ) );
        }

        $term_name = $data['name'] ?? '';
        if (empty($term_name)) {
            throw new Exception('Missing taxonomy term name');
        }

        // Check if term exists
        $existing_term = get_term_by('name', $term_name, $wp_taxonomy);
        if ($existing_term) {
            // Save mapping and skip
            $this->get_sync_manager()->save_entity_map('taxonomy', $existing_term->term_id, $uuid, 'webhook', $wp_taxonomy);
            return [
                'status'  => 'skipped',
                'term_id' => $existing_term->term_id,
                'message' => 'Term already exists',
            ];
        }

        // Create term (silently - HCRM_WEBHOOK_PROCESSING flag prevents auto-sync)
        $term_args = [];
        if (!empty($data['slug'])) {
            $term_args['slug'] = $data['slug'];
        }
        if (!empty($data['description'])) {
            $term_args['description'] = $data['description'];
        }

        $result = wp_insert_term($term_name, $wp_taxonomy, $term_args);

        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $result->get_error_message() ) );
        }

        // Save entity mapping
        $this->get_sync_manager()->save_entity_map('taxonomy', $result['term_id'], $uuid, 'webhook', $wp_taxonomy);

        return [
            'status'  => 'created',
            'term_id' => $result['term_id'],
        ];
    }

    /**
     * Handle taxonomy.updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_taxonomy_updated($payload) {
        $uuid = $payload['uuid'] ?? null;
        $taxonomy_type = $payload['taxonomy_type'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid) || empty($taxonomy_type)) {
            throw new Exception('Missing taxonomy UUID or type');
        }

        $wp_taxonomy = $this->map_taxonomy_type($taxonomy_type);
        if (!$wp_taxonomy) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message
            throw new Exception( 'Unsupported taxonomy type: ' . esc_html( $taxonomy_type ) );
        }

        // Find existing term by UUID
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $term_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wp_id FROM {$wpdb->prefix}hcrm_entity_map WHERE crm_uuid = %s AND entity_type = 'taxonomy' AND taxonomy = %s",
                $uuid,
                $wp_taxonomy
            )
        );

        if (!$term_id) {
            // Term doesn't exist, create it
            return $this->handle_taxonomy_created($payload);
        }

        // Update term (silently)
        $term_args = [];
        if (!empty($data['name'])) {
            $term_args['name'] = $data['name'];
        }
        if (!empty($data['slug'])) {
            $term_args['slug'] = $data['slug'];
        }
        if (!empty($data['description'])) {
            $term_args['description'] = $data['description'];
        }

        $result = wp_update_term($term_id, $wp_taxonomy, $term_args);

        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $result->get_error_message() ) );
        }

        return [
            'status'  => 'updated',
            'term_id' => $term_id,
        ];
    }

    /**
     * Handle taxonomy.deleted webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_taxonomy_deleted($payload) {
        $uuid = $payload['uuid'] ?? null;
        $taxonomy_type = $payload['taxonomy_type'] ?? null;

        if (empty($uuid) || empty($taxonomy_type)) {
            throw new Exception('Missing taxonomy UUID or type');
        }

        $wp_taxonomy = $this->map_taxonomy_type($taxonomy_type);
        if (!$wp_taxonomy) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal error message
            throw new Exception( 'Unsupported taxonomy type: ' . esc_html( $taxonomy_type ) );
        }

        // Find existing term by UUID
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $term_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wp_id FROM {$wpdb->prefix}hcrm_entity_map WHERE crm_uuid = %s AND entity_type = 'taxonomy' AND taxonomy = %s",
                $uuid,
                $wp_taxonomy
            )
        );

        if (!$term_id) {
            return [
                'status'  => 'skipped',
                'message' => 'Term not found',
            ];
        }

        // Delete the term (silently)
        $result = wp_delete_term($term_id, $wp_taxonomy);

        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $result->get_error_message() ) );
        }

        // Remove entity mapping
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete(
            $wpdb->prefix . 'hcrm_entity_map',
            [
                'crm_uuid'    => $uuid,
                'entity_type' => 'taxonomy',
                'taxonomy'    => $wp_taxonomy,
            ]
        );

        return [
            'status'  => 'deleted',
            'term_id' => $term_id,
        ];
    }

    /**
     * Handle user.created webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_user_created($payload) {
        $uuid = $payload['uuid'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid)) {
            throw new Exception('Missing user UUID');
        }

        // Check if user already exists by UUID mapping
        $existing_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'wp_user');
        if ($existing_id) {
            return [
                'status'  => 'skipped',
                'message' => 'User already exists',
                'user_id' => $existing_id,
            ];
        }

        // Check if user exists by email
        $email = $data['email'] ?? '';
        if (!empty($email)) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                // Save mapping and skip
                $this->get_sync_manager()->save_entity_map('wp_user', $existing_user->ID, $uuid, 'webhook');
                return [
                    'status'  => 'skipped',
                    'message' => 'User with this email already exists',
                    'user_id' => $existing_user->ID,
                ];
            }
        }

        // Create WordPress user (silently)
        $user_data = $this->prepare_wp_user_data($data);

        // Generate password for new user
        $user_data['user_pass'] = wp_generate_password(16, true, true);

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $user_id->get_error_message() ) );
        }

        // Assign role based on CRM roles
        $this->assign_wp_role_from_crm($user_id, $data);

        // Save entity mapping
        $this->get_sync_manager()->save_entity_map('wp_user', $user_id, $uuid, 'webhook');

        return [
            'status'  => 'created',
            'user_id' => $user_id,
        ];
    }

    /**
     * Handle user.updated webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_user_updated($payload) {
        $uuid = $payload['uuid'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($uuid)) {
            throw new Exception('Missing user UUID');
        }

        // Find existing user by UUID
        $user_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'wp_user');

        if (!$user_id) {
            // User doesn't exist, create it
            return $this->handle_user_created($payload);
        }

        // Update WordPress user (silently)
        $user_data = $this->prepare_wp_user_data($data);
        $user_data['ID'] = $user_id;

        // Don't update password on update
        unset($user_data['user_pass']);

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WP_Error message
            throw new Exception( esc_html( $result->get_error_message() ) );
        }

        // Update role if needed
        $this->assign_wp_role_from_crm($user_id, $data);

        // Update entity mapping timestamp
        $this->get_sync_manager()->save_entity_map('wp_user', $user_id, $uuid, 'webhook');

        return [
            'status'  => 'updated',
            'user_id' => $user_id,
        ];
    }

    /**
     * Handle user.deleted webhook.
     *
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    protected function handle_user_deleted($payload) {
        $uuid = $payload['uuid'] ?? null;

        if (empty($uuid)) {
            throw new Exception('Missing user UUID');
        }

        // Find existing user by UUID
        $user_id = $this->get_sync_manager()->get_wp_id_by_uuid($uuid, 'wp_user');

        if (!$user_id) {
            return [
                'status'  => 'skipped',
                'message' => 'User not found',
            ];
        }

        // Don't delete administrators
        $user = get_userdata($user_id);
        if ($user && in_array('administrator', $user->roles)) {
            return [
                'status'  => 'skipped',
                'message' => 'Cannot delete administrator users via webhook',
                'user_id' => $user_id,
            ];
        }

        // Remove entity mapping first
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete(
            $wpdb->prefix . 'hcrm_entity_map',
            [
                'crm_uuid'    => $uuid,
                'entity_type' => 'wp_user',
            ]
        );

        // Delete user (reassign content to admin)
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id, 1); // Reassign to user ID 1 (admin)

        if (!$result) {
            throw new Exception('Failed to delete user');
        }

        return [
            'status'  => 'deleted',
            'user_id' => $user_id,
        ];
    }

    /**
     * Prepare WordPress user data from CRM data.
     *
     * @param array $crm_data CRM user data.
     * @return array WordPress user data array.
     */
    private function prepare_wp_user_data($crm_data) {
        $first_name = $crm_data['profile']['first_name'] ?? ($crm_data['first_name'] ?? '');
        $last_name = $crm_data['profile']['last_name'] ?? ($crm_data['last_name'] ?? '');
        $email = $crm_data['email'] ?? '';
        $display_name = trim("$first_name $last_name") ?: $email;

        // Generate username from email
        $username = !empty($email) ? sanitize_user(explode('@', $email)[0], true) : 'user_' . time();

        // Ensure unique username
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        $user_data = [
            'user_login'   => $username,
            'user_email'   => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
        ];

        // Add phone if available
        $phone = $crm_data['profile']['phone'] ?? ($crm_data['phone'] ?? '');
        if (!empty($phone)) {
            $user_data['meta_input'] = [
                'phone' => $phone,
            ];
        }

        // Add bio if available
        $bio = $crm_data['profile']['bio'] ?? ($crm_data['bio'] ?? '');
        if (!empty($bio)) {
            $user_data['description'] = $bio;
        }

        return $user_data;
    }

    /**
     * Assign WordPress role based on CRM roles.
     *
     * @param int   $user_id User ID.
     * @param array $crm_data CRM user data.
     */
    private function assign_wp_role_from_crm($user_id, $crm_data) {
        $roles = $crm_data['roles'] ?? [];
        $wp_role = 'houzez_agent'; // Default role

        // Map CRM roles to WordPress roles
        $role_map = [
            'Admin'   => 'administrator',
            'Manager' => 'houzez_manager',
            'Agency'  => 'houzez_agency',
            'Agent'   => 'houzez_agent',
        ];

        foreach ($roles as $role) {
            $role_name = is_array($role) ? ($role['name'] ?? '') : $role;
            if (isset($role_map[$role_name])) {
                $wp_role = $role_map[$role_name];
                break; // Use first matching role
            }
        }

        // Get user and update role
        $user = new WP_User($user_id);
        if (!in_array($wp_role, $user->roles)) {
            $user->set_role($wp_role);
        }
    }

    /**
     * Map CRM taxonomy type to WordPress taxonomy.
     *
     * @param string $crm_type CRM taxonomy type.
     * @return string|null WordPress taxonomy or null.
     */
    private function map_taxonomy_type($crm_type) {
        $map = [
            'status'         => 'property_status',
            'listing_type'   => 'property_type',
            'listing_label'  => 'property_label',
            'listing_status' => 'property_status',
            'city'           => 'property_city',
            'state'          => 'property_state',
            'country'        => 'property_country',
            'area'           => 'property_area',
            'facility'       => 'property_feature',
            'feature'        => 'property_feature',
        ];

        return $map[$crm_type] ?? null;
    }
}
