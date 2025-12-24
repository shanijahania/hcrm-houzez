<?php
/**
 * User Sync class for handling agent and agency synchronization.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_User
 *
 * Handles the synchronization of agents, agencies, and WordPress users between WordPress and CRM.
 *
 * @since 1.0.0
 */
class HCRM_Sync_User {

    /**
     * API client instance.
     *
     * @var HCRM_API_Client
     */
    private $api_client;

    /**
     * Entity mapper instance.
     *
     * @var HCRM_Entity_Mapper
     */
    private $mapper;

    /**
     * WordPress roles that should sync to CRM users.
     *
     * @var array
     */
    private $syncable_wp_roles = ['houzez_agent', 'houzez_agency', 'houzez_manager', 'administrator'];

    /**
     * Mapping of WordPress roles to CRM roles.
     *
     * @var array
     */
    private $wp_role_to_crm_role = [
        'houzez_agent'   => 'Agent',
        'houzez_agency'  => 'Agency',
        'houzez_manager' => 'Manager',
        'administrator'  => 'Admin',
    ];

    /**
     * Constructor.
     *
     * @param HCRM_API_Client|null    $api_client API client instance.
     * @param HCRM_Entity_Mapper|null $mapper     Entity mapper instance.
     */
    public function __construct($api_client = null, $mapper = null) {
        $this->api_client = $api_client ?? HCRM_API_Client::from_settings();
        $this->mapper = $mapper ?? new HCRM_Entity_Mapper();
    }

    /**
     * Sync a single agency to CRM.
     *
     * @param int $agency_id WordPress agency post ID.
     * @return array Result with 'success' and 'message' keys.
     */
    public function sync_agency($agency_id) {
        $post = get_post($agency_id);
        if (!$post || $post->post_type !== 'houzez_agency') {
            return ['success' => false, 'message' => 'Invalid agency ID'];
        }

        // Prepare agency data
        $data = $this->prepare_agency_for_api($agency_id);

        // Check if already synced
        $crm_uuid = $this->mapper->get_crm_uuid($agency_id, 'agency');

        if ($crm_uuid) {
            // Update existing
            $response = $this->api_client->update_agency($crm_uuid, $data);
        } else {
            // Create new
            $response = $this->api_client->create_agency($data);
        }

        if ($response->is_success()) {
            $result_data = $response->get_data();
            $uuid = $result_data['uuid'] ?? null;

            if ($uuid) {
                $this->mapper->save_mapping($agency_id, 'agency', $uuid);
            }

            return [
                'success' => true,
                'message' => $crm_uuid ? 'Agency updated in CRM' : 'Agency created in CRM',
                'uuid'    => $uuid,
            ];
        }

        return [
            'success' => false,
            'message' => $response->get_error_message() ?: 'Failed to sync agency',
        ];
    }

    /**
     * Sync all agencies to CRM.
     *
     * @param bool $include_synced Whether to re-sync already synced agencies.
     * @return array Result with 'success', 'synced', 'failed' keys.
     */
    public function sync_all_agencies($include_synced = false) {
        $args = [
            'post_type'      => 'houzez_agency',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        $agency_ids = get_posts($args);
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($agency_ids as $agency_id) {
            // Skip if already synced and not including synced
            if (!$include_synced && $this->mapper->get_crm_uuid($agency_id, 'agency')) {
                continue;
            }

            $result = $this->sync_agency($agency_id);
            if ($result['success']) {
                $synced++;
            } else {
                $failed++;
                $errors[] = "Agency #{$agency_id}: " . $result['message'];
            }
        }

        return [
            'success' => $failed === 0,
            'synced'  => $synced,
            'failed'  => $failed,
            'errors'  => $errors,
            'message' => sprintf(
                /* translators: 1: number synced, 2: number failed */
                __( 'Synced %1$d agencies, %2$d failed', 'hcrm-houzez' ),
                $synced,
                $failed
            ),
        ];
    }

    /**
     * Sync all WordPress users with syncable roles to CRM.
     *
     * @param bool $include_synced Whether to re-sync already synced users.
     * @return array Result with 'success', 'synced', 'failed' keys.
     */
    public function sync_all_wp_users($include_synced = false) {
        $synced = 0;
        $failed = 0;
        $errors = [];

        // Get all users with syncable roles
        $users = get_users([
            'role__in' => $this->syncable_wp_roles,
            'fields'   => 'ID',
        ]);

        foreach ($users as $user_id) {
            // Skip if already synced and not including synced
            if (!$include_synced && $this->mapper->get_crm_uuid($user_id, 'wp_user')) {
                continue;
            }

            $result = $this->sync_wp_user($user_id);
            if ($result['success']) {
                $synced++;
            } else {
                $failed++;
                $errors[] = "User #{$user_id}: " . $result['message'];
            }
        }

        return [
            'success' => $failed === 0,
            'synced'  => $synced,
            'failed'  => $failed,
            'total'   => count($users),
            'errors'  => $errors,
            'message' => sprintf(
                /* translators: 1: number synced, 2: number failed */
                __( 'Synced %1$d WordPress users, %2$d failed', 'hcrm-houzez' ),
                $synced,
                $failed
            ),
        ];
    }

    /**
     * Sync all users (agencies and WordPress users) to CRM.
     *
     * @param bool $include_synced Whether to re-sync already synced items.
     * @return array Combined results.
     */
    public function sync_all_users($include_synced = false) {
        // First sync agencies (they are separate entities in CRM)
        $agencies_result = $this->sync_all_agencies($include_synced);
        $wp_users_result = $this->sync_all_wp_users($include_synced);

        $total_synced = $agencies_result['synced'] + $wp_users_result['synced'];
        $all_success = $agencies_result['success'] && $wp_users_result['success'];

        return [
            'success'  => $all_success,
            'agencies' => $agencies_result,
            'wp_users' => $wp_users_result,
            'message'  => sprintf(
                /* translators: 1: agencies synced, 2: users synced */
                __( 'Synced %1$d agencies and %2$d WordPress users', 'hcrm-houzez' ),
                $agencies_result['synced'],
                $wp_users_result['synced']
            ),
        ];
    }

    /**
     * Sync a WordPress user to CRM.
     *
     * @param int $user_id WordPress user ID.
     * @return array Result with 'success' and 'message' keys.
     */
    public function sync_wp_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }

        // Check if user has a syncable role
        $matching_roles = array_intersect($user->roles, $this->syncable_wp_roles);
        if (empty($matching_roles)) {
            return ['success' => false, 'message' => 'User role not syncable'];
        }

        // Check if already synced
        $crm_uuid = $this->mapper->get_crm_uuid($user_id, 'wp_user');
        $is_new = empty($crm_uuid);

        // Prepare user data (include password for new users)
        $data = $this->prepare_wp_user_for_api($user_id, $is_new);
        $email = $data['email'] ?? '';

        // Get CRM role based on WordPress role
        $wp_role = reset($matching_roles);
        $crm_role = $this->wp_role_to_crm_role[$wp_role] ?? 'Manager';

        if ($crm_uuid) {
            // Update existing
            $response = $this->api_client->update_user($crm_uuid, $data);
        } else {
            // Create new
            $response = $this->api_client->create_user($data);

            // Handle duplicate email error - user might already exist in CRM
            if ($response->is_validation_error()) {
                $errors = $response->get_errors();
                if (isset($errors['email']) && !empty($email)) {
                    // Try to find existing user by email
                    $find_response = $this->api_client->find_user_by_email($email);
                    if ($find_response->is_success()) {
                        $existing_user = $find_response->get_data();
                        $crm_uuid = $existing_user['uuid'] ?? null;

                        if ($crm_uuid) {
                            // Save mapping for existing user
                            $this->mapper->save_mapping($user_id, 'wp_user', $crm_uuid);

                            // Update the existing user with new data (without password)
                            unset($data['password'], $data['password_confirmation']);
                            $this->api_client->update_user($crm_uuid, $data);

                            // Assign role
                            $this->api_client->assign_user_role($crm_uuid, $crm_role);

                            return [
                                'success' => true,
                                'message' => 'Existing CRM user linked and updated',
                                'uuid'    => $crm_uuid,
                            ];
                        }
                    }
                }

                // If we couldn't recover, return the original error
                return [
                    'success' => false,
                    'message' => $response->get_error_message() ?: 'Failed to sync user',
                ];
            }
        }

        if ($response->is_success()) {
            $result_data = $response->get_data();
            $uuid = $result_data['uuid'] ?? null;

            if ($uuid) {
                $this->mapper->save_mapping($user_id, 'wp_user', $uuid);

                // Assign role for new users
                if ($is_new) {
                    $this->api_client->assign_user_role($uuid, $crm_role);
                }
            }

            return [
                'success' => true,
                'message' => $crm_uuid ? 'User updated in CRM' : 'User created in CRM',
                'uuid'    => $uuid,
            ];
        }

        return [
            'success' => false,
            'message' => $response->get_error_message() ?: 'Failed to sync user',
        ];
    }

    /**
     * Prepare WordPress user data for API submission.
     *
     * @param int  $user_id WordPress user ID.
     * @param bool $is_new  Whether this is a new user (requires password).
     * @return array API-ready user data.
     */
    public function prepare_wp_user_for_api($user_id, $is_new = false) {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $data = [
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name'  => $user->last_name ?: $user->display_name,
            'email'      => $user->user_email,
            'status'     => 'active',
        ];

        // Generate a random password for new users
        if ($is_new) {
            $password = wp_generate_password(16, true, true);
            $data['password'] = $password;
            $data['password_confirmation'] = $password;
        }

        // Add phone if available (from user meta)
        $phone = get_user_meta($user_id, 'phone', true);
        if (!empty($phone)) {
            $data['phone'] = $phone;
        }

        // Add bio if available
        if (!empty($user->description)) {
            $data['bio'] = $user->description;
        }

        return $data;
    }

    /**
     * Check if a WordPress user has a syncable role.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if user has a syncable role.
     */
    public function is_syncable_wp_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return !empty(array_intersect($user->roles, $this->syncable_wp_roles));
    }

    /**
     * Prepare agency data for API submission.
     *
     * @param int $agency_id WordPress agency post ID.
     * @return array API-ready agency data.
     */
    public function prepare_agency_for_api($agency_id) {
        $post = get_post($agency_id);
        if (!$post) {
            return [];
        }

        // Get agency meta
        $email = get_post_meta($agency_id, 'fave_agency_email', true);
        $phone = get_post_meta($agency_id, 'fave_agency_phone', true);
        $licenses = get_post_meta($agency_id, 'fave_agency_licenses', true);
        $address = get_post_meta($agency_id, 'fave_agency_address', true);
        $website = get_post_meta($agency_id, 'fave_agency_website', true);

        $data = [
            'name'      => $post->post_title,
            'is_active' => $post->post_status === 'publish',
        ];

        // Add optional fields with CRM-compatible field names
        if (!empty($email)) {
            $data['email'] = $email;
        }

        if (!empty($phone)) {
            $data['phone'] = $phone;
        }

        if (!empty($licenses)) {
            $data['license_number'] = $licenses;
        }

        if (!empty($address)) {
            $data['address_line1'] = $address;
        }

        // Website must be a valid URL format for CRM validation
        if (!empty($website) && filter_var($website, FILTER_VALIDATE_URL)) {
            $data['website'] = $website;
        }

        // Description
        if (!empty($post->post_content)) {
            $data['description'] = wp_strip_all_tags($post->post_content);
        }

        // Agency logo (featured image / post thumbnail)
        $logo_url = get_the_post_thumbnail_url($agency_id, 'full');
        if (!empty($logo_url) && $this->is_local_image_url($logo_url)) {
            $data['logo_url'] = $logo_url;
        }

        return $data;
    }

    /**
     * Check if an image URL is a local WordPress URL.
     *
     * @param string $url Image URL to check.
     * @return bool True if local, false if external.
     */
    private function is_local_image_url($url) {
        if (empty($url)) {
            return false;
        }

        // Get site URL host
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $image_host = wp_parse_url( $url, PHP_URL_HOST );

        // Check if same host
        if ($site_host && $image_host && $site_host === $image_host) {
            return true;
        }

        // Check for relative URLs (start with /)
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        // Reject known external/demo domains
        $external_domains = ['developer.developer', 'developer.developer', 'developer.developer', 'developer.developer'];

        foreach ($external_domains as $domain) {
            if ($image_host && stripos($image_host, $domain) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get sync statistics.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        global $wpdb;

        // Count agencies (CPT synced to CRM agencies)
        $total_agencies = wp_count_posts( 'houzez_agency' );
        $total_agencies_published = $total_agencies->publish ?? 0;

        // Get synced agencies count with caching
        $cache_key_agencies = 'hcrm_synced_agencies_count';
        $synced_agencies = wp_cache_get( $cache_key_agencies );
        if ( false === $synced_agencies ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query with caching
            $synced_agencies = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
                    'agency'
                )
            );
            wp_cache_set( $cache_key_agencies, $synced_agencies, '', 300 );
        }

        // Count WordPress users with syncable roles (synced to CRM users)
        $wp_users_with_syncable_roles = get_users( [
            'role__in' => $this->syncable_wp_roles,
            'fields'   => 'ID',
        ] );
        $total_wp_users = count( $wp_users_with_syncable_roles );

        // Count only synced users that still have syncable roles
        $synced_wp_users = 0;
        if ( ! empty( $wp_users_with_syncable_roles ) ) {
            $cache_key_users = 'hcrm_synced_wp_users_count';
            $synced_wp_users = wp_cache_get( $cache_key_users );
            if ( false === $synced_wp_users ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Stats query with caching
                $synced_wp_users = $wpdb->get_var(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause with safe %d placeholders
                        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND wp_id IN (" . implode( ',', array_fill( 0, count( $wp_users_with_syncable_roles ), '%d' ) ) . ")",
                        array_merge( [ 'wp_user' ], $wp_users_with_syncable_roles )
                    )
                );
                wp_cache_set( $cache_key_users, $synced_wp_users, '', 300 );
            }
        }

        return [
            'agencies' => [
                'total'   => (int) $total_agencies_published,
                'synced'  => (int) $synced_agencies,
                'pending' => max(0, (int) $total_agencies_published - (int) $synced_agencies),
            ],
            'wp_users' => [
                'total'   => (int) $total_wp_users,
                'synced'  => (int) $synced_wp_users,
                'pending' => max(0, (int) $total_wp_users - (int) $synced_wp_users),
            ],
        ];
    }

    /**
     * Register hooks for automatic user (agency/wp_user) sync.
     *
     * @since 1.0.0
     */
    public function register_auto_sync_hooks() {
        // Custom post type hook for agencies (syncs to CRM agencies)
        add_action('save_post_houzez_agency', [$this, 'on_agency_save'], 20, 3);

        // WordPress user hooks for all syncable roles (agent, agency, manager, admin)
        add_action('user_register', [$this, 'on_wp_user_register'], 20, 1);
        add_action('profile_update', [$this, 'on_wp_user_update'], 20, 2);
    }

    /**
     * Handle agency post save for auto-sync.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     *
     * @since 1.0.0
     */
    public function on_agency_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if processing a webhook (prevent infinite loop)
        if (defined('HCRM_WEBHOOK_PROCESSING') && HCRM_WEBHOOK_PROCESSING) {
            return;
        }

        // Check if user auto-sync is enabled
        if (!HCRM_Settings::is_users_auto_sync_enabled()) {
            return;
        }

        // Only sync published agencies
        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if API is configured
        if (!$this->api_client->is_configured()) {
            return;
        }

        try {
            // Sync the agency
            $result = $this->sync_agency($post_id);

            if ($result['success']) {
                HCRM_Logger::info(sprintf(
                    'Auto-synced agency %d to CRM',
                    $post_id
                ));
            } else {
                HCRM_Logger::error(sprintf(
                    'Failed to auto-sync agency %d: %s',
                    $post_id,
                    $result['message']
                ));
            }
        } catch (Exception $e) {
            HCRM_Logger::error(sprintf(
                'Exception during auto-sync of agency %d: %s',
                $post_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Handle WordPress user registration for auto-sync.
     *
     * @param int $user_id User ID.
     *
     * @since 1.0.0
     */
    public function on_wp_user_register($user_id) {
        // Skip if processing a webhook (prevent infinite loop)
        if (defined('HCRM_WEBHOOK_PROCESSING') && HCRM_WEBHOOK_PROCESSING) {
            return;
        }

        // Check if user auto-sync is enabled
        if (!HCRM_Settings::is_users_auto_sync_enabled()) {
            return;
        }

        // Check if API is configured
        if (!$this->api_client->is_configured()) {
            return;
        }

        // Check if user has a syncable role
        if (!$this->is_syncable_wp_user($user_id)) {
            return;
        }

        try {
            // Sync the user
            $result = $this->sync_wp_user($user_id);

            if ($result['success']) {
                HCRM_Logger::info(sprintf(
                    'Auto-synced WordPress user %d to CRM',
                    $user_id
                ));
            } else {
                HCRM_Logger::error(sprintf(
                    'Failed to auto-sync WordPress user %d: %s',
                    $user_id,
                    $result['message']
                ));
            }
        } catch (Exception $e) {
            HCRM_Logger::error(sprintf(
                'Exception during auto-sync of WordPress user %d: %s',
                $user_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Handle WordPress user profile update for auto-sync.
     *
     * @param int     $user_id       User ID.
     * @param WP_User $old_user_data Object containing user's data prior to update.
     *
     * @since 1.0.0
     */
    public function on_wp_user_update($user_id, $old_user_data) {
        // Skip if processing a webhook (prevent infinite loop)
        if (defined('HCRM_WEBHOOK_PROCESSING') && HCRM_WEBHOOK_PROCESSING) {
            return;
        }

        // Check if user auto-sync is enabled
        if (!HCRM_Settings::is_users_auto_sync_enabled()) {
            return;
        }

        // Check if API is configured
        if (!$this->api_client->is_configured()) {
            return;
        }

        // Check if user has a syncable role
        if (!$this->is_syncable_wp_user($user_id)) {
            return;
        }

        try {
            // Sync the user
            $result = $this->sync_wp_user($user_id);

            if ($result['success']) {
                HCRM_Logger::info(sprintf(
                    'Auto-synced WordPress user %d to CRM (profile update)',
                    $user_id
                ));
            } else {
                HCRM_Logger::error(sprintf(
                    'Failed to auto-sync WordPress user %d on update: %s',
                    $user_id,
                    $result['message']
                ));
            }
        } catch (Exception $e) {
            HCRM_Logger::error(sprintf(
                'Exception during auto-sync of WordPress user %d: %s',
                $user_id,
                $e->getMessage()
            ));
        }
    }
}
