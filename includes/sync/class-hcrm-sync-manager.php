<?php
/**
 * Sync Manager class for orchestrating synchronization.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_Manager
 *
 * Orchestrates the synchronization of data between WordPress and CRM.
 *
 * @since 1.0.0
 */
class HCRM_Sync_Manager {

    use HCRM_Singleton;

    /**
     * API client instance.
     *
     * @var HCRM_API_Client
     */
    private $api_client = null;

    /**
     * Property sync handler.
     *
     * @var HCRM_Sync_Property
     */
    private $property_sync = null;

    /**
     * Data mapper instance.
     *
     * @var HCRM_Data_Mapper
     */
    private $data_mapper = null;

    /**
     * Flag to prevent recursive syncing.
     *
     * @var bool
     */
    private $is_syncing = false;

    /**
     * Constructor.
     */
    private function __construct() {
        // Lazy initialization - don't create dependencies until needed
    }

    /**
     * Get API client instance (lazy initialization).
     *
     * @return HCRM_API_Client
     */
    private function get_api_client() {
        if ($this->api_client === null) {
            $this->api_client = HCRM_API_Client::from_settings();
        }
        return $this->api_client;
    }

    /**
     * Get data mapper instance (lazy initialization).
     *
     * @return HCRM_Data_Mapper
     */
    private function get_data_mapper() {
        if ($this->data_mapper === null) {
            $this->data_mapper = new HCRM_Data_Mapper();
        }
        return $this->data_mapper;
    }

    /**
     * Get property sync instance (lazy initialization).
     *
     * @return HCRM_Sync_Property
     */
    private function get_property_sync() {
        if ($this->property_sync === null) {
            $this->property_sync = new HCRM_Sync_Property($this->get_data_mapper());
        }
        return $this->property_sync;
    }

    /**
     * Check if sync is enabled for an entity type.
     *
     * @param string $entity_type Entity type (properties, taxonomies, users, leads).
     * @return bool
     */
    public function is_sync_enabled($entity_type) {
        // Map entity types to their entity-specific settings options
        $option_map = [
            'properties'  => 'hcrm_properties_settings',
            'taxonomies'  => 'hcrm_taxonomy_settings',
            'users'       => 'hcrm_users_settings',
            'leads'       => 'hcrm_leads_settings',
        ];

        // Check entity-specific settings first
        $option_name = $option_map[$entity_type] ?? null;
        if ($option_name) {
            $settings = get_option($option_name, []);
            if (!empty($settings["sync_{$entity_type}"])) {
                return true;
            }
        }

        // Also check global sync settings (hcrm_sync_settings tab)
        $sync_settings = get_option('hcrm_sync_settings', []);
        return !empty($sync_settings["sync_{$entity_type}"]);
    }

    /**
     * Push a property to the CRM.
     *
     * @param int $property_id WordPress property ID.
     * @return HCRM_API_Response
     */
    public function push_property($property_id) {
        if (!$this->get_api_client()->is_configured()) {
            return HCRM_API_Response::error('API client not configured');
        }

        // Check for existing UUID
        $existing_uuid = $this->get_crm_uuid($property_id, 'property');

        // Prepare data
        $data = $this->get_property_sync()->prepare_for_api($property_id);

        if (empty($data)) {
            return HCRM_API_Response::error('Failed to prepare property data');
        }

        // Create or update
        if ($existing_uuid) {
            $response = $this->get_api_client()->update_listing($existing_uuid, $data);
            $action = 'update';
        } else {
            $response = $this->get_api_client()->create_listing($data);
            $action = 'create';
        }

        // Store UUID mapping on success
        if ($response->is_success()) {
            $uuid = $response->get_uuid();
            if ($uuid) {
                $this->save_entity_map('property', $property_id, $uuid, 'push');
            }

            // Store related entity UUIDs (including images)
            $this->store_related_uuids($property_id, $response->get_data());
        }

        // Log the sync
        $this->log_sync('property', $property_id, $action, 'push', $response);

        return $response;
    }

    /**
     * Pull a property from the CRM.
     *
     * @param string $uuid CRM listing UUID.
     * @return int|WP_Error Property ID or error.
     */
    public function pull_property($uuid) {
        if (!$this->get_api_client()->is_configured()) {
            return new WP_Error('api_not_configured', 'API client not configured');
        }

        // Fetch from CRM
        $response = $this->get_api_client()->get_listing($uuid, ['agency', 'assignees', 'detail', 'address']);

        if (!$response->is_success()) {
            $this->log_sync('property', 0, 'pull', 'pull', $response);
            return new WP_Error('api_error', $response->get_message());
        }

        $crm_data = $response->get_data();

        // Check if property exists in WordPress
        $existing_wp_id = $this->get_wp_id_by_uuid($uuid, 'property');

        // Create or update property
        $property_id = $this->get_property_sync()->create_or_update_from_crm($crm_data, $existing_wp_id);

        if (is_wp_error($property_id)) {
            return $property_id;
        }

        // Update entity mapping
        $this->save_entity_map('property', $property_id, $uuid, 'pull');

        // Log success
        $this->log_sync('property', $property_id, $existing_wp_id ? 'update' : 'create', 'pull', $response);

        return $property_id;
    }

    /**
     * Push all properties to CRM.
     *
     * @param array $filters Query filters.
     * @return array Results with success and failed counts.
     */
    public function push_all_properties($filters = []) {
        $results = [
            'total'   => 0,
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        // Get all published properties
        $args = array_merge([
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ], $filters);

        $properties = get_posts($args);
        $results['total'] = count($properties);

        foreach ($properties as $property_id) {
            $response = $this->push_property($property_id);

            if ($response->is_success()) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'id'      => $property_id,
                    'message' => $response->get_message(),
                ];
            }
        }

        return $results;
    }

    /**
     * Hook: Handle property save via save_post_property.
     *
     * This hook fires early (before Houzez saves all meta).
     * For dashboard submissions, we skip here and let on_houzez_property_save handle it.
     * This method handles admin panel edits where Houzez hooks don't fire.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     */
    public function on_property_save($post_id, $post, $update) {
        HCRM_Logger::info("=== on_property_save START for post_id: {$post_id} ===");

        // Skip if this is a Houzez dashboard submission
        // The Houzez hooks fire AFTER all meta is saved, so we defer to them
        if ($this->is_houzez_dashboard_submission()) {
            HCRM_Logger::info("BLOCKED: Houzez dashboard submission detected, deferring to houzez_after_property_* hooks");
            return;
        }

        // Skip autosaves and revisions (hook-specific checks)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            HCRM_Logger::info("BLOCKED: DOING_AUTOSAVE");
            return;
        }
        if (wp_is_post_revision($post_id)) {
            HCRM_Logger::info("BLOCKED: is post revision");
            return;
        }

        // Skip auto-draft and inherit statuses
        if (in_array($post->post_status, ['auto-draft', 'inherit'], true)) {
            HCRM_Logger::info("BLOCKED: post_status is {$post->post_status}");
            return;
        }

        // Trigger the common sync logic
        $this->trigger_property_sync($post_id);
    }

    /**
     * Check if the current request is a Houzez dashboard property submission.
     *
     * Houzez dashboard uses POST action values to identify submissions.
     * We detect these to skip save_post_property and wait for houzez_after_property_* hooks.
     *
     * @return bool True if this is a Houzez dashboard submission.
     */
    private function is_houzez_dashboard_submission() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Just checking context, not processing
        if (!isset($_POST['action'])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Just checking context, not processing
        $action = sanitize_text_field(wp_unslash($_POST['action']));

        // Houzez dashboard submission actions
        $houzez_actions = [
            'add_property',
            'update_property',
            'save_as_draft',
        ];

        return in_array($action, $houzez_actions, true);
    }

    /**
     * Hook: Handle property trash.
     *
     * @param int $post_id Post ID.
     */
    public function on_property_trash($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'property') {
            return;
        }

        // For now, we don't delete from CRM when trashed
        // This could be implemented if needed
    }

    /**
     * Hook: Handle post status transitions.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post       Post object.
     */
    public function on_status_transition($new_status, $old_status, $post) {
        if ($post->post_type !== 'property') {
            return;
        }

        // When property becomes published, sync it
        if ($new_status === 'publish' && $old_status !== 'publish') {
            // Will be handled by on_property_save
        }
    }

    /**
     * Hook: Handle Houzez dashboard property save.
     *
     * This hook fires AFTER all property meta (including images) is saved,
     * making it the correct hook for syncing complete property data.
     *
     * @param int $property_id Property ID.
     */
    public function on_houzez_property_save($property_id) {
        HCRM_Logger::info("=== on_houzez_property_save START for property_id: {$property_id} ===");

        // Trigger the sync with complete property data
        $this->trigger_property_sync($property_id);
    }

    /**
     * Common method to trigger property sync to CRM.
     *
     * Used by both Houzez hooks and save_post_property hook.
     *
     * @param int $property_id Property ID.
     * @return void
     */
    private function trigger_property_sync($property_id) {
        // Prevent recursive sync
        if ($this->is_syncing) {
            HCRM_Logger::info("BLOCKED: is_syncing flag is true");
            return;
        }

        // Skip if processing a webhook (prevent infinite loop)
        if (defined('HCRM_WEBHOOK_PROCESSING') && HCRM_WEBHOOK_PROCESSING) {
            HCRM_Logger::info("BLOCKED: HCRM_WEBHOOK_PROCESSING flag is defined");
            return;
        }

        // Check if auto-sync is enabled
        if (!$this->is_sync_enabled('properties')) {
            $props_settings = get_option('hcrm_properties_settings', []);
            $sync_settings = get_option('hcrm_sync_settings', []);
            HCRM_Logger::info("BLOCKED: properties sync is disabled. hcrm_properties_settings[sync_properties]=" . var_export($props_settings['sync_properties'] ?? null, true) . ", hcrm_sync_settings[sync_properties]=" . var_export($sync_settings['sync_properties'] ?? null, true));
            return;
        }
        HCRM_Logger::info("PASSED: properties sync is enabled");

        // Check if API is configured
        if (!$this->get_api_client()->is_configured()) {
            HCRM_Logger::info("BLOCKED: API client not configured");
            return;
        }

        // Check if data has changed
        $current_hash = $this->get_property_sync()->calculate_sync_hash($property_id);
        $stored_hash = $this->get_sync_hash($property_id, 'property');
        HCRM_Logger::info("Hash check - Current: {$current_hash}, Stored: {$stored_hash}");

        if ($current_hash === $stored_hash) {
            HCRM_Logger::info("BLOCKED: hash unchanged, no sync needed");
            return;
        }

        HCRM_Logger::info("All checks passed, proceeding with sync...");

        // Mark as syncing
        $this->is_syncing = true;

        // Sync property
        $response = $this->push_property($property_id);

        // Update hash on success
        if ($response->is_success()) {
            $this->update_sync_hash($property_id, 'property', $current_hash);
        }

        // Reset flag
        $this->is_syncing = false;
    }

    /**
     * Get CRM UUID for a WordPress entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @param string $taxonomy    Taxonomy name (for terms).
     * @return string|null CRM UUID or null.
     */
    public function get_crm_uuid( $wp_id, $entity_type, $taxonomy = null ) {
        global $wpdb;

        if ( $taxonomy ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Entity mapping lookup
            $uuid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND wp_id = %d AND taxonomy = %s",
                    $entity_type,
                    $wp_id,
                    $taxonomy
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Entity mapping lookup
            $uuid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND wp_id = %d AND taxonomy IS NULL",
                    $entity_type,
                    $wp_id
                )
            );
        }

        return $uuid ?: null;
    }

    /**
     * Get WordPress ID by CRM UUID.
     *
     * @param string $uuid        CRM UUID.
     * @param string $entity_type Entity type.
     * @return int|null WordPress ID or null.
     */
    public function get_wp_id_by_uuid( $uuid, $entity_type ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Entity mapping lookup
        $wp_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wp_id FROM {$wpdb->prefix}hcrm_entity_map WHERE crm_uuid = %s AND entity_type = %s",
                $uuid,
                $entity_type
            )
        );

        return $wp_id ? (int) $wp_id : null;
    }

    /**
     * Save entity mapping.
     *
     * @param string      $entity_type Entity type.
     * @param int         $wp_id       WordPress ID.
     * @param string      $crm_uuid    CRM UUID.
     * @param string      $direction   Sync direction.
     * @param string|null $taxonomy    Taxonomy name (for terms).
     * @return bool Success.
     */
    public function save_entity_map($entity_type, $wp_id, $crm_uuid, $direction, $taxonomy = null) {
        global $wpdb;

        $data = [
            'entity_type'         => $entity_type,
            'wp_id'               => $wp_id,
            'crm_uuid'            => $crm_uuid,
            'taxonomy'            => $taxonomy,
            'last_synced_at'      => current_time('mysql'),
            'last_sync_direction' => $direction,
        ];

        // Check if mapping exists
        $existing = $this->get_crm_uuid($wp_id, $entity_type, $taxonomy);

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            return $wpdb->update(
                $wpdb->prefix . 'hcrm_entity_map',
                $data,
                [
                    'entity_type' => $entity_type,
                    'wp_id'       => $wp_id,
                    'taxonomy'    => $taxonomy,
                ]
            ) !== false;
        }

        $data['created_at'] = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
        return $wpdb->insert( $wpdb->prefix . 'hcrm_entity_map', $data ) !== false;
    }

    /**
     * Store related entity UUIDs from API response.
     *
     * @param int   $property_id   WordPress property ID.
     * @param array $response_data API response data.
     */
    private function store_related_uuids($property_id, $response_data) {
        // Store status UUID
        if (!empty($response_data['status']['uuid']) && !empty($response_data['status']['name'])) {
            $term = get_term_by('name', $response_data['status']['name'], 'property_status');
            if ($term) {
                $this->save_entity_map('taxonomy', $term->term_id, $response_data['status']['uuid'], 'push', 'property_status');
            }
        }

        // Store listing type UUID
        if (!empty($response_data['listing_type']['uuid']) && !empty($response_data['listing_type']['name'])) {
            $term = get_term_by('name', $response_data['listing_type']['name'], 'property_type');
            if ($term) {
                $this->save_entity_map('taxonomy', $term->term_id, $response_data['listing_type']['uuid'], 'push', 'property_type');
            }
        }

        // Store agency UUID
        if (!empty($response_data['agency']['uuid']) && !empty($response_data['agency']['name'])) {
            $agency_query = new WP_Query([
                'post_type'      => 'houzez_agency',
                'title'          => $response_data['agency']['name'],
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
            ]);
            if ($agency_query->have_posts()) {
                $this->save_entity_map('agency', $agency_query->posts[0], $response_data['agency']['uuid'], 'push');
            }
        }

        // Store assignee UUIDs
        if (!empty($response_data['assignees']) && is_array($response_data['assignees'])) {
            foreach ($response_data['assignees'] as $assignee) {
                if (!empty($assignee['uuid']) && !empty($assignee['email'])) {
                    $user = get_user_by('email', $assignee['email']);
                    if ($user) {
                        $this->save_entity_map('user', $user->ID, $assignee['uuid'], 'push');
                    }
                }
            }
        }

        // Store owner_contact UUID
        if (!empty($response_data['owner_contact']['uuid']) && !empty($response_data['owner_contact']['email'])) {
            $user = get_user_by('email', $response_data['owner_contact']['email']);
            if ($user) {
                $this->save_entity_map('user', $user->ID, $response_data['owner_contact']['uuid'], 'push');
            }
        }

        // Store creator UUID
        if (!empty($response_data['creator']['uuid']) && !empty($response_data['creator']['email'])) {
            $user = get_user_by('email', $response_data['creator']['email']);
            if ($user) {
                $this->save_entity_map('user', $user->ID, $response_data['creator']['uuid'], 'push');
            }
        }

        // Store image UUIDs and download new CRM images
        if (!empty($response_data['images']) && is_array($response_data['images'])) {
            $this->sync_images($property_id, $response_data['images']);
        }

        // Store floor plan UUIDs
        if (!empty($response_data['floor_plans']) && is_array($response_data['floor_plans'])) {
            $this->store_floor_plan_uuids($property_id, $response_data['floor_plans']);
        }
    }

    /**
     * Store floor plan UUIDs from CRM response into WordPress meta.
     * Uses title+size matching instead of index-based matching.
     *
     * @param int   $property_id WordPress property ID.
     * @param array $crm_floor_plans Floor plans from CRM response.
     */
    private function store_floor_plan_uuids($property_id, $crm_floor_plans) {
        // Get existing floor plans from WordPress
        $wp_floor_plans = get_post_meta($property_id, 'floor_plans', true);
        if (empty($wp_floor_plans) || !is_array($wp_floor_plans)) {
            return;
        }

        // Build a lookup for CRM plans by match key
        $crm_by_match_key = [];
        foreach ($crm_floor_plans as $crm_plan) {
            $uuid = $crm_plan['uuid'] ?? null;
            if (!$uuid) {
                continue;
            }

            $crm_size = isset($crm_plan['area_size']) ? (float) $crm_plan['area_size'] : 0.0;
            $match_key = $this->generate_floor_plan_match_key(
                $crm_plan['plan_title'] ?? '',
                $crm_size
            );
            $crm_by_match_key[$match_key] = $uuid;
        }

        $updated = false;

        // Match WP floor plans to CRM floor plans by title+size
        foreach ($wp_floor_plans as $index => &$wp_plan) {
            // Skip if already has a UUID
            if (!empty($wp_plan['crm_uuid'])) {
                continue;
            }

            // Extract numeric size from WP format (e.g., "670 Sqft" -> 670)
            $size = null;
            if (isset($wp_plan['fave_plan_size'])) {
                preg_match('/[\d,.]+/', $wp_plan['fave_plan_size'], $matches);
                if (!empty($matches[0])) {
                    $size = (float) str_replace(',', '', $matches[0]);
                }
            }

            $match_key = $this->generate_floor_plan_match_key(
                $wp_plan['fave_plan_title'] ?? '',
                $size
            );

            if (isset($crm_by_match_key[$match_key])) {
                $wp_plan['crm_uuid'] = $crm_by_match_key[$match_key];
                $updated = true;

                HCRM_Logger::info(sprintf(
                    'Matched floor plan by title+size for property %d: "%s" (size: %s) -> UUID: %s',
                    $property_id,
                    $wp_plan['fave_plan_title'] ?? '',
                    $wp_plan['fave_plan_size'] ?? '',
                    $crm_by_match_key[$match_key]
                ));
            }
        }
        unset($wp_plan); // Break reference

        // Update meta if changed
        if ($updated) {
            update_post_meta($property_id, 'floor_plans', $wp_floor_plans);
        }
    }

    /**
     * Generate a match key for floor plan deduplication.
     * Uses title + size for fallback matching when UUID is not available.
     *
     * @param string|null $title Floor plan title.
     * @param mixed       $size  Floor plan area size.
     * @return string Match key.
     */
    private function generate_floor_plan_match_key($title, $size) {
        $normalized_title = strtolower(trim($title ?? ''));
        $normalized_size = is_numeric($size) ? (float) $size : 0.0;

        return $normalized_title . '_' . $normalized_size;
    }

    /**
     * Sync images between WordPress and CRM.
     *
     * Stores CRM UUIDs for images that were pushed to CRM.
     * Uses wp_attachment_id for matching (sent in API request, returned in response).
     *
     * @param int   $property_id WordPress property ID.
     * @param array $crm_images  Images from CRM response.
     */
    private function sync_images( $property_id, $crm_images ) {
        if ( empty( $crm_images ) || ! is_array( $crm_images ) ) {
            return;
        }

        $wp_attachment_ids = $this->get_property_attachment_ids( $property_id );

        HCRM_Logger::info( sprintf(
            'sync_images: Property %d has %d WP attachments, CRM returned %d images',
            $property_id,
            count( $wp_attachment_ids ),
            count( $crm_images )
        ) );

        // Update _hcrm_crm_uuid meta for each image based on wp_attachment_id mapping
        $updated_count = 0;
        foreach ( $crm_images as $crm_image ) {
            $crm_uuid = $crm_image['uuid'] ?? null;
            $wp_attachment_id = $crm_image['wp_attachment_id'] ?? null;

            if ( ! $crm_uuid ) {
                continue;
            }

            // Strategy 1: Match by wp_attachment_id (for images pushed from WP to CRM)
            if ( $wp_attachment_id && in_array( (int) $wp_attachment_id, $wp_attachment_ids, true ) ) {
                $existing_uuid = get_post_meta( $wp_attachment_id, '_hcrm_crm_uuid', true );
                if ( $existing_uuid !== $crm_uuid ) {
                    update_post_meta( $wp_attachment_id, '_hcrm_crm_uuid', $crm_uuid );
                    $updated_count++;
                    HCRM_Logger::info( sprintf(
                        'Updated CRM UUID for attachment %d: %s',
                        $wp_attachment_id,
                        $crm_uuid
                    ) );
                }
                continue;
            }

            // Strategy 2: Match by name (fallback for legacy images)
            $name = $crm_image['name'] ?? null;
            if ( $name ) {
                foreach ( $wp_attachment_ids as $attachment_id ) {
                    $post_name = get_post_field( 'post_name', $attachment_id );
                    if ( $post_name === $name ) {
                        $existing_uuid = get_post_meta( $attachment_id, '_hcrm_crm_uuid', true );
                        if ( $existing_uuid !== $crm_uuid ) {
                            update_post_meta( $attachment_id, '_hcrm_crm_uuid', $crm_uuid );
                            $updated_count++;
                            HCRM_Logger::info( sprintf(
                                'Updated CRM UUID for attachment %d (matched by name %s): %s',
                                $attachment_id,
                                $name,
                                $crm_uuid
                            ) );
                        }
                        break;
                    }
                }
            }
        }

        HCRM_Logger::info( sprintf(
            'sync_images: Updated CRM UUID for %d images on property %d',
            $updated_count,
            $property_id
        ) );
    }

    /**
     * Normalize URL for comparison.
     *
     * Removes protocol, query strings, and trailing slashes for reliable matching.
     *
     * @param string $url URL to normalize.
     * @return string Normalized URL.
     */
    private function normalize_image_url( $url ) {
        // Remove protocol (http:// or https://)
        $url = preg_replace( '#^https?://#', '', $url );
        // Remove query strings
        $url = strtok( $url, '?' );
        // Remove trailing slashes
        $url = rtrim( $url, '/' );
        return $url;
    }

    /**
     * Get all attachment IDs for a property (featured + gallery).
     *
     * @param int $property_id WordPress property ID.
     * @return array Array of attachment IDs in order.
     */
    private function get_property_attachment_ids($property_id) {
        $ids = [];

        // Featured image first
        $featured_id = get_post_thumbnail_id($property_id);
        if ($featured_id) {
            $ids[] = (int) $featured_id;
        }

        // Gallery images
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        if (!empty($gallery)) {
            foreach ($gallery as $attachment_id) {
                if ($attachment_id && !in_array((int) $attachment_id, $ids)) {
                    $ids[] = (int) $attachment_id;
                }
            }
        }

        return $ids;
    }

    /**
     * Download an image from URL and create WP attachment.
     *
     * @param string $url         Image URL.
     * @param int    $property_id Property ID to attach to.
     * @return int|false Attachment ID or false on failure.
     */
    private function download_and_attach_image($url, $property_id) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Use wp_remote_get for better control over SSL and local domains
        $response = wp_remote_get($url, [
            'sslverify' => !$this->is_local_development_url($url),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return false;
        }

        // Create temp file
        $tmp = wp_tempnam(basename($url));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for sideload
        file_put_contents($tmp, $body);

        $file_array = [
            'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        ];

        // Upload and create attachment
        $attachment_id = media_handle_sideload($file_array, $property_id);

        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_file( $tmp );
            return false;
        }

        return $attachment_id;
    }

    /**
     * Check if URL is a local development domain.
     *
     * @param string $url URL to check.
     * @return bool True if local development URL.
     */
    private function is_local_development_url( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return false;
        }

        $local_tlds = ['.test', '.local', '.localhost', '.dev'];
        foreach ($local_tlds as $tld) {
            if (substr($host, -strlen($tld)) === $tld) {
                return true;
            }
        }

        return $host === 'localhost' || $host === '127.0.0.1';
    }

    /**
     * Add an image to property gallery.
     *
     * @param int $property_id   Property ID.
     * @param int $attachment_id Attachment ID.
     */
    private function add_image_to_property_gallery($property_id, $attachment_id) {
        $gallery = get_post_meta($property_id, 'fave_property_images', false);
        if (!in_array($attachment_id, $gallery)) {
            add_post_meta($property_id, 'fave_property_images', $attachment_id);
        }
    }

    /**
     * Log a sync operation.
     *
     * @param string             $entity_type Entity type.
     * @param int                $entity_id   Entity ID.
     * @param string             $action      Action (create, update, delete).
     * @param string             $direction   Direction (push, pull, webhook).
     * @param HCRM_API_Response $response    API response.
     */
    private function log_sync($entity_type, $entity_id, $action, $direction, $response) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for logging
        $wpdb->insert(
            $wpdb->prefix . 'hcrm_sync_log',
            [
                'entity_type'   => $entity_type,
                'entity_id'     => $entity_id,
                'action'        => $action,
                'direction'     => $direction,
                'status'        => $response->is_success() ? 'success' : 'failed',
                'request_data'  => wp_json_encode(['entity_id' => $entity_id]),
                'response_data' => $response->to_json(),
                'error_message' => $response->is_success() ? null : $response->get_message(),
                'created_at'    => current_time('mysql'),
            ]
        );
    }

    /**
     * Get sync hash for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @return string|null Hash or null.
     */
    private function get_sync_hash( $wp_id, $entity_type ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Sync hash lookup
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sync_hash FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND wp_id = %d",
                $entity_type,
                $wp_id
            )
        );
    }

    /**
     * Update sync hash for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @param string $hash        New hash.
     * @return bool Success.
     */
    private function update_sync_hash( $wp_id, $entity_type, $hash ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Sync hash update
        return $wpdb->update(
            $wpdb->prefix . 'hcrm_entity_map',
            [ 'sync_hash' => $hash ],
            [
                'entity_type' => $entity_type,
                'wp_id'       => $wp_id,
            ]
        ) !== false;
    }

    /**
     * Get sync statistics.
     *
     * @return array Stats.
     */
    public function get_sync_stats() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
        $properties_synced = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
                'property'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
        $last_sync = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(last_synced_at) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
                'property'
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
        $sync_errors_24h = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_sync_log WHERE status = 'failed' AND created_at > %s",
            gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
        ) );

        $stats = [
            'properties_synced' => $properties_synced,
            'last_sync'         => $last_sync,
            'sync_errors_24h'   => $sync_errors_24h,
        ];

        return $stats;
    }

    /**
     * Batch size for background sync.
     *
     * @var int
     */
    const BATCH_SIZE = 25;

    /**
     * Start a background sync operation.
     *
     * @param string $type    Sync type (properties, agents, agencies, taxonomy).
     * @param array  $options Additional options (taxonomy name, filters, etc.).
     * @return array Result with sync_id or error.
     */
    public function start_background_sync($type, $options = []) {
        // Check if API is configured
        if (!$this->get_api_client()->is_configured()) {
            return [
                'success' => false,
                'message' => __('API client not configured', 'hcrm-houzez'),
            ];
        }

        // Check for existing active sync of this type
        $active_sync = HCRM_Sync_Progress::has_active_sync($type);
        if ($active_sync) {
            return [
                'success' => false,
                'sync_id' => $active_sync,
                'message' => __('A sync is already in progress for this type', 'hcrm-houzez'),
            ];
        }

        // Get total items count based on type
        $total = $this->get_sync_item_count($type, $options);

        if ($total === 0) {
            return [
                'success' => false,
                'message' => __('No items to sync', 'hcrm-houzez'),
            ];
        }

        // Create progress record
        $sync_id = HCRM_Sync_Progress::create($type, $total, $options);

        // Schedule first batch for immediate execution
        // This returns control to the browser immediately so progress bar shows
        $this->schedule_sync_batch($sync_id, $type, 0, $options);

        return [
            'success' => true,
            'sync_id' => $sync_id,
            'total'   => $total,
            /* translators: %d: number of items to sync */
            'message' => sprintf( __( 'Sync started for %d items', 'hcrm-houzez' ), $total ),
        ];
    }

    /**
     * Get the count of items to sync for a given type.
     *
     * @param string $type    Sync type.
     * @param array  $options Options.
     * @return int Count.
     */
    private function get_sync_item_count($type, $options = []) {
        switch ($type) {
            case 'properties':
                $count = wp_count_posts('property');
                return (int) ($count->publish ?? 0);

            case 'agencies':
                $count = wp_count_posts('houzez_agency');
                return (int) ($count->publish ?? 0);

            case 'wp_users':
                $syncable_roles = ['houzez_agent', 'houzez_agency', 'houzez_manager', 'administrator'];
                $users = get_users([
                    'role__in' => $syncable_roles,
                    'fields'   => 'ID',
                ]);
                return count($users);

            case 'taxonomy':
                $taxonomy = $options['taxonomy'] ?? '';
                if (empty($taxonomy)) {
                    return 0;
                }
                $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                return is_wp_error($count) ? 0 : (int) $count;

            default:
                return 0;
        }
    }

    /**
     * Get items to sync for a batch.
     *
     * @param string $type    Sync type.
     * @param int    $offset  Offset for pagination.
     * @param int    $limit   Number of items.
     * @param array  $options Options.
     * @return array Array of item IDs and their titles/names.
     */
    private function get_sync_items($type, $offset, $limit, $options = []) {
        switch ($type) {
            case 'properties':
                $args = [
                    'post_type'      => 'property',
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ];
                $posts = get_posts($args);
                return array_map(function ($post) {
                    return ['id' => $post->ID, 'title' => $post->post_title];
                }, $posts);

            case 'agencies':
                $args = [
                    'post_type'      => 'houzez_agency',
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ];
                $posts = get_posts($args);
                return array_map(function ($post) {
                    return ['id' => $post->ID, 'title' => $post->post_title];
                }, $posts);

            case 'wp_users':
                $syncable_roles = ['houzez_agent', 'houzez_agency', 'houzez_manager', 'administrator'];
                $users = get_users([
                    'role__in' => $syncable_roles,
                    'number'   => $limit,
                    'offset'   => $offset,
                    'orderby'  => 'ID',
                    'order'    => 'ASC',
                ]);
                return array_map(function ($user) {
                    return ['id' => $user->ID, 'title' => $user->display_name];
                }, $users);

            case 'taxonomy':
                $taxonomy = $options['taxonomy'] ?? '';
                if (empty($taxonomy)) {
                    return [];
                }
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'number'     => $limit,
                    'offset'     => $offset,
                    'orderby'    => 'term_id',
                    'order'      => 'ASC',
                ]);
                if (is_wp_error($terms)) {
                    return [];
                }
                return array_map(function ($term) {
                    return ['id' => $term->term_id, 'title' => $term->name];
                }, $terms);

            default:
                return [];
        }
    }

    /**
     * Schedule a sync batch via Action Scheduler.
     *
     * @param string $sync_id Sync ID.
     * @param string $type    Sync type.
     * @param int    $offset  Offset.
     * @param array  $options Options.
     */
    private function schedule_sync_batch($sync_id, $type, $offset, $options = []) {
        $args = [
            'sync_id' => $sync_id,
            'type'    => $type,
            'offset'  => $offset,
            'options' => $options,
        ];

        if (function_exists('as_enqueue_async_action')) {
            // Use async action for immediate execution
            as_enqueue_async_action(
                'hcrm_process_sync_batch',
                $args,
                'hcrm-houzez'
            );

            // Spawn async request to trigger immediate processing
            $this->spawn_async_request();
        } elseif (function_exists('as_schedule_single_action')) {
            // Fallback to scheduled action
            as_schedule_single_action(
                time(),
                'hcrm_process_sync_batch',
                $args,
                'hcrm-houzez'
            );

            // Spawn async request to trigger immediate processing
            $this->spawn_async_request();
        } else {
            // Fallback to immediate processing if Action Scheduler not available
            $this->process_sync_batch($sync_id, $type, $offset, $options);
        }
    }

    /**
     * Spawn an async request to trigger Action Scheduler processing immediately.
     * This sends a non-blocking HTTP request to process the queue.
     */
    private function spawn_async_request() {
        // Send a non-blocking loopback request to trigger queue processing
        // This runs independently of the current request
        $url = add_query_arg([
            'action' => 'hcrm_trigger_sync',
            'nonce'  => wp_create_nonce('hcrm_trigger_sync'),
            '_'      => time(), // Cache buster
        ], admin_url('admin-ajax.php'));

        // Use wp_remote_post with non-blocking settings
        // Disable SSL verification for local loopback requests to admin-ajax.php
        wp_remote_post( $url, [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters( 'hcrm_local_ssl_verify', false ),
            'cookies'   => $_COOKIE, // Pass cookies for auth
        ] );
    }

    /**
     * Process a sync batch (called by Action Scheduler or synchronously).
     *
     * @param string $sync_id     Sync ID.
     * @param string $type        Sync type.
     * @param int    $offset      Offset.
     * @param array  $options     Options.
     * @param bool   $synchronous Whether this is being called synchronously (skip delays).
     */
    public function process_sync_batch($sync_id, $type, $offset, $options = [], $synchronous = false) {
        // Check if sync was cancelled
        if (HCRM_Sync_Progress::is_cancelled($sync_id)) {
            return;
        }

        // Get progress
        $progress = HCRM_Sync_Progress::get($sync_id);
        if (!$progress) {
            return;
        }

        // Mark as running if still pending
        if ($progress['status'] === 'pending') {
            HCRM_Sync_Progress::start($sync_id);
        }

        // Get items for this batch
        $items = $this->get_sync_items($type, $offset, self::BATCH_SIZE, $options);

        if (empty($items)) {
            // No more items, mark as complete
            HCRM_Sync_Progress::complete($sync_id);
            return;
        }

        // Process each item
        foreach ($items as $item) {
            // Check for cancellation between items
            if (HCRM_Sync_Progress::is_cancelled($sync_id)) {
                return;
            }

            $result = $this->sync_single_item($type, $item['id'], $options);

            // Update progress
            HCRM_Sync_Progress::increment(
                $sync_id,
                $result['success'],
                $item['title'],
                $result['success'] ? '' : ($result['message'] ?? 'Unknown error')
            );

            // Small delay between items to make progress visible in UI (500ms)
            // This allows the polling to catch incremental progress updates
            // Skip delay when processing synchronously (first batch) for faster response
            if (!$synchronous) {
                usleep(500000);
            }
        }

        // Check if there are more items
        $new_offset = $offset + self::BATCH_SIZE;
        $progress = HCRM_Sync_Progress::get($sync_id);

        // Check conditions for continuing
        $should_continue = $progress &&
                          $progress['processed'] < $progress['total'] &&
                          $progress['status'] === 'running';

        if ($should_continue) {
            // Schedule next batch
            $this->schedule_sync_batch($sync_id, $type, $new_offset, $options);
        } else {
            // All done
            HCRM_Sync_Progress::complete($sync_id);
        }
    }

    /**
     * Sync a single item based on type.
     *
     * @param string $type    Sync type.
     * @param int    $item_id Item ID.
     * @param array  $options Options.
     * @return array Result with 'success' and 'message'.
     */
    private function sync_single_item($type, $item_id, $options = []) {
        switch ($type) {
            case 'properties':
                $response = $this->push_property($item_id);
                return [
                    'success' => $response->is_success(),
                    'message' => $response->get_message(),
                ];

            case 'agencies':
                $sync_user = new HCRM_Sync_User();
                return $sync_user->sync_agency($item_id);

            case 'wp_users':
                $sync_user = new HCRM_Sync_User();
                return $sync_user->sync_wp_user($item_id);

            case 'taxonomy':
                $taxonomy = $options['taxonomy'] ?? '';
                if (empty($taxonomy)) {
                    return ['success' => false, 'message' => 'No taxonomy specified'];
                }
                $sync_taxonomy = new HCRM_Sync_Taxonomy();
                return $sync_taxonomy->sync_term($item_id, $taxonomy);

            default:
                return ['success' => false, 'message' => 'Unknown sync type'];
        }
    }

    /**
     * Cancel a background sync.
     *
     * @param string $sync_id Sync ID.
     * @return array Result.
     */
    public function cancel_sync($sync_id) {
        $progress = HCRM_Sync_Progress::get($sync_id);

        if (!$progress) {
            return [
                'success' => false,
                'message' => __('Sync not found', 'hcrm-houzez'),
            ];
        }

        if (!in_array($progress['status'], ['pending', 'running'], true)) {
            return [
                'success' => false,
                'message' => __('Sync is not active', 'hcrm-houzez'),
            ];
        }

        HCRM_Sync_Progress::cancel($sync_id);

        return [
            'success' => true,
            'message' => __('Sync cancelled', 'hcrm-houzez'),
        ];
    }
}
