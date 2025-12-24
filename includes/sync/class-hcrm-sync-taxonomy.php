<?php
/**
 * Taxonomy Sync class for handling taxonomy synchronization.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_Taxonomy
 *
 * Handles the synchronization of property taxonomies between WordPress and CRM.
 *
 * @since 1.0.0
 */
class HCRM_Sync_Taxonomy {

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
     * Taxonomy to CRM endpoint mapping.
     *
     * @var array
     */
    private $taxonomy_endpoints = [
        'property_type'    => 'listing_type',
        'property_status'  => 'listing_status',
        'property_label'   => 'listing_label',
        'property_feature' => 'facility',
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
     * Sync a single taxonomy term to CRM.
     *
     * @param int    $term_id  WordPress term ID.
     * @param string $taxonomy Taxonomy name.
     * @return array Result with 'success' and 'message' keys.
     */
    public function sync_term($term_id, $taxonomy) {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return ['success' => false, 'message' => 'Invalid term ID'];
        }

        if (!isset($this->taxonomy_endpoints[$taxonomy])) {
            return ['success' => false, 'message' => 'Unsupported taxonomy'];
        }

        // Prepare term data
        $data = $this->prepare_term_for_api($term);
        $entity_type = $this->taxonomy_endpoints[$taxonomy];

        // Step 1: Check if already synced (has UUID in wp_hcrm_entity_map)
        // Pass taxonomy name to properly identify the term
        $crm_uuid = $this->mapper->get_crm_uuid($term_id, 'taxonomy', $taxonomy);

        if ($crm_uuid) {
            // Already synced - Update by UUID
            $response = $this->call_update_api($entity_type, $crm_uuid, $data);
            $action = 'updated';
        } else {
            // Step 2: Check if exists in CRM by name
            $existing = $this->find_in_crm_by_name($entity_type, $term->name);

            if ($existing && isset($existing['uuid'])) {
                // Found by name - update existing record
                $crm_uuid = $existing['uuid'];
                $response = $this->call_update_api($entity_type, $crm_uuid, $data);
                $action = 'linked and updated';
            } else {
                // Not found - create new
                $response = $this->call_create_api($entity_type, $data);
                $action = 'created';
            }
        }

        if ($response->is_success()) {
            $result_data = $response->get_data();
            $uuid = $result_data['uuid'] ?? $crm_uuid;

            // Always save/update the mapping after successful sync
            // Include taxonomy name for proper identification
            if ($uuid) {
                $this->mapper->save_mapping($term_id, 'taxonomy', $uuid, [
                    'taxonomy'  => $taxonomy,
                    'direction' => 'push',
                ]);
            }

            return [
                'success' => true,
                'message' => "Term {$action} in CRM",
                'uuid'    => $uuid,
            ];
        }

        return [
            'success' => false,
            'message' => $response->get_message() ?: 'Failed to sync term',
        ];
    }

    /**
     * Find an entity in CRM by name.
     *
     * @param string $entity_type Entity type.
     * @param string $name        Name to search for.
     * @return array|null Entity data if found, null otherwise.
     */
    private function find_in_crm_by_name($entity_type, $name) {
        $response = $this->call_find_by_name_api($entity_type, $name);

        if ($response->is_success()) {
            return $response->get_data();
        }

        return null;
    }

    /**
     * Call the appropriate find-by-name API method based on entity type.
     *
     * @param string $entity_type Entity type.
     * @param string $name        Name to search for.
     * @return HCRM_API_Response
     */
    private function call_find_by_name_api($entity_type, $name) {
        switch ($entity_type) {
            case 'listing_type':
                return $this->api_client->find_listing_type_by_name($name);
            case 'listing_status':
                return $this->api_client->find_listing_status_by_name($name);
            case 'listing_label':
                return $this->api_client->find_listing_label_by_name($name);
            case 'facility':
                return $this->api_client->find_facility_by_name($name);
            default:
                return HCRM_API_Response::error('Unknown entity type');
        }
    }

    /**
     * Log sync error for debugging.
     *
     * @param int                $term_id  WordPress term ID.
     * @param string             $name     Term name.
     * @param HCRM_API_Response  $response API response.
     */
    private function log_sync_error($term_id, $name, $response) {
        HCRM_Logger::error(sprintf(
            'Taxonomy sync failed for term %d (%s) - Status: %s, Message: %s',
            $term_id,
            $name,
            $response->get_status_code(),
            $response->get_message()
        ), [
            'errors' => $response->get_errors(),
        ]);
    }

    /**
     * Sync all terms of a specific taxonomy to CRM.
     *
     * @param string $taxonomy       Taxonomy name.
     * @param bool   $include_synced Whether to re-sync already synced terms.
     * @return array Result with 'success', 'synced', 'failed' keys.
     */
    public function sync_taxonomy($taxonomy, $include_synced = false) {
        if (!isset($this->taxonomy_endpoints[$taxonomy])) {
            return [
                'success' => false,
                'message' => 'Unsupported taxonomy',
            ];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [
                'success' => false,
                'message' => $terms->get_error_message(),
            ];
        }

        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($terms as $term) {
            // Skip if already synced and not including synced
            // Pass taxonomy name for proper lookup
            if (!$include_synced && $this->mapper->get_crm_uuid($term->term_id, 'taxonomy', $taxonomy)) {
                continue;
            }

            $result = $this->sync_term($term->term_id, $taxonomy);
            if ($result['success']) {
                $synced++;
            } else {
                $failed++;
                $errors[] = "{$term->name}: " . $result['message'];
            }
        }

        return [
            'success'  => $failed === 0,
            'synced'   => $synced,
            'failed'   => $failed,
            'total'    => count( $terms ),
            'errors'   => $errors,
            'message'  => sprintf(
                /* translators: 1: number synced, 2: total terms */
                __( 'Synced %1$d of %2$d terms', 'hcrm-houzez' ),
                $synced,
                count( $terms )
            ),
        ];
    }

    /**
     * Sync all supported taxonomies to CRM.
     *
     * @param bool $include_synced Whether to re-sync already synced terms.
     * @return array Combined results.
     */
    public function sync_all_taxonomies($include_synced = false) {
        $results = [];
        $total_synced = 0;
        $total_failed = 0;

        foreach (array_keys($this->taxonomy_endpoints) as $taxonomy) {
            // Check if this taxonomy sync is enabled
            $setting_key = 'sync_' . $taxonomy;
            if (!HCRM_Settings::get($setting_key, true)) {
                continue;
            }

            $result = $this->sync_taxonomy($taxonomy, $include_synced);
            $results[$taxonomy] = $result;
            $total_synced += $result['synced'];
            $total_failed += $result['failed'];
        }

        return [
            'success' => $total_failed === 0,
            'synced'  => $total_synced,
            'failed'  => $total_failed,
            'details' => $results,
            'message' => sprintf(
                /* translators: 1: number synced, 2: number failed */
                __( 'Synced %1$d terms, %2$d failed', 'hcrm-houzez' ),
                $total_synced,
                $total_failed
            ),
        ];
    }

    /**
     * Prepare term data for API submission.
     *
     * @param WP_Term $term WordPress term object.
     * @return array API-ready term data.
     */
    public function prepare_term_for_api($term) {
        $data = [
            'name' => $term->name,
            'slug' => $term->slug,
        ];

        // Note: parent_id handling is done separately after all terms are synced
        // The CRM expects parent_id (integer), not parent_uuid
        // Description field is not supported by CRM

        return $data;
    }

    /**
     * Call the appropriate create API method based on entity type.
     *
     * @param string $entity_type Entity type.
     * @param array  $data        Data to send.
     * @return HCRM_API_Response
     */
    private function call_create_api($entity_type, $data) {
        switch ($entity_type) {
            case 'listing_type':
                return $this->api_client->create_listing_type($data);
            case 'listing_status':
                return $this->api_client->create_listing_status($data);
            case 'listing_label':
                return $this->api_client->create_listing_label($data);
            case 'facility':
                return $this->api_client->create_facility($data);
            default:
                return HCRM_API_Response::error('Unknown entity type');
        }
    }

    /**
     * Call the appropriate update API method based on entity type.
     *
     * @param string $entity_type Entity type.
     * @param string $uuid        CRM UUID.
     * @param array  $data        Data to send.
     * @return HCRM_API_Response
     */
    private function call_update_api($entity_type, $uuid, $data) {
        switch ($entity_type) {
            case 'listing_type':
                return $this->api_client->update_listing_type($uuid, $data);
            case 'listing_status':
                return $this->api_client->update_listing_status($uuid, $data);
            case 'listing_label':
                return $this->api_client->update_listing_label($uuid, $data);
            case 'facility':
                return $this->api_client->update_facility($uuid, $data);
            default:
                return HCRM_API_Response::error('Unknown entity type');
        }
    }

    /**
     * Get sync statistics for taxonomies.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        global $wpdb;

        $stats = [];

        foreach ( array_keys( $this->taxonomy_endpoints ) as $taxonomy ) {
            $total = wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
            $total = is_wp_error( $total ) ? 0 : (int) $total;

            // Count synced terms for this taxonomy (filter by taxonomy column)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
            $synced = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND taxonomy = %s",
                    'taxonomy',
                    $taxonomy
                )
            );

            $stats[ $taxonomy ] = [
                'total'   => $total,
                'synced'  => (int) $synced,
                'pending' => max( 0, $total - (int) $synced ),
            ];
        }

        return $stats;
    }

    /**
     * Get supported taxonomy names.
     *
     * @return array List of supported taxonomy names.
     */
    public function get_supported_taxonomies() {
        return array_keys($this->taxonomy_endpoints);
    }

    /**
     * Register hooks for automatic taxonomy sync.
     *
     * @since 1.0.0
     */
    public function register_auto_sync_hooks() {
        foreach (array_keys($this->taxonomy_endpoints) as $taxonomy) {
            add_action("created_{$taxonomy}", [$this, 'on_term_saved'], 10, 2);
            add_action("edited_{$taxonomy}", [$this, 'on_term_saved'], 10, 2);
        }
    }

    /**
     * Handle term create/edit for auto-sync.
     *
     * @param int $term_id         Term ID.
     * @param int $term_taxonomy_id Term taxonomy ID.
     *
     * @since 1.0.0
     */
    public function on_term_saved($term_id, $term_taxonomy_id) {
        // Skip if processing a webhook (prevent infinite loop)
        if (defined('HCRM_WEBHOOK_PROCESSING') && HCRM_WEBHOOK_PROCESSING) {
            return;
        }

        // Check if taxonomy auto-sync is enabled
        if (!HCRM_Settings::is_taxonomy_auto_sync_enabled()) {
            return;
        }

        // Check if API is configured
        if (!$this->api_client->is_configured()) {
            return;
        }

        // Get taxonomy from the current filter name
        $current_filter = current_filter();
        $taxonomy = str_replace(['created_', 'edited_'], '', $current_filter);

        // Verify it's a supported taxonomy
        if (!isset($this->taxonomy_endpoints[$taxonomy])) {
            return;
        }

        // Check if this specific taxonomy sync is enabled
        $setting_key = 'sync_' . $taxonomy;
        if (!HCRM_Settings::get($setting_key, true)) {
            return;
        }

        try {
            // Sync the term
            $result = $this->sync_term($term_id, $taxonomy);

            if ($result['success']) {
                HCRM_Logger::info(sprintf(
                    'Auto-synced taxonomy term %d (%s) to CRM',
                    $term_id,
                    $taxonomy
                ));
            } else {
                HCRM_Logger::error(sprintf(
                    'Failed to auto-sync taxonomy term %d (%s): %s',
                    $term_id,
                    $taxonomy,
                    $result['message']
                ));
            }
        } catch (Exception $e) {
            HCRM_Logger::error(sprintf(
                'Exception during auto-sync of taxonomy term %d: %s',
                $term_id,
                $e->getMessage()
            ));
        }
    }
}
