<?php
/**
 * Entity Mapper class for managing WordPress to CRM entity mappings.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Entity_Mapper
 *
 * Handles the mapping between WordPress entity IDs and CRM UUIDs.
 *
 * @since 1.0.0
 */
class HCRM_Entity_Mapper {

    /**
     * Table name (without prefix).
     *
     * @var string
     */
    private $table_name = 'hcrm_entity_map';

    /**
     * Get the full table name with prefix.
     *
     * @return string
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->table_name;
    }

    /**
     * Save a mapping between WordPress ID and CRM UUID.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type (property, agent, agency, taxonomy, lead, contact).
     * @param string $crm_uuid    CRM UUID.
     * @param array  $extra_data  Extra data to store (optional). Supports: taxonomy, sync_hash, direction.
     * @return bool Success.
     */
    public function save_mapping($wp_id, $entity_type, $crm_uuid, $extra_data = []) {
        global $wpdb;

        // Get taxonomy from extra_data if provided
        $taxonomy = isset($extra_data['taxonomy']) ? $extra_data['taxonomy'] : null;

        // Check if mapping already exists
        $existing = $this->get_crm_uuid($wp_id, $entity_type, $taxonomy);

        $data = [
            'wp_id'               => $wp_id,
            'entity_type'         => $entity_type,
            'crm_uuid'            => $crm_uuid,
            'taxonomy'            => $taxonomy,
            'last_synced_at'      => current_time('mysql'),
            'last_sync_direction' => isset($extra_data['direction']) ? $extra_data['direction'] : 'push',
        ];

        // Add extra data fields if table supports them
        if (isset($extra_data['sync_hash'])) {
            $data['sync_hash'] = $extra_data['sync_hash'];
        }

        if ($existing) {
            // Update existing - include taxonomy in WHERE clause for unique key
            $where = [
                'wp_id'       => $wp_id,
                'entity_type' => $entity_type,
            ];

            // Handle taxonomy in WHERE clause (NULL requires special handling)
            if ($taxonomy !== null) {
                $where['taxonomy'] = $taxonomy;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table
            $result = $wpdb->update( $wpdb->prefix . 'hcrm_entity_map', $data, $where );

            // If update didn't find a row (taxonomy mismatch), try insert
            if ($result === 0 && $wpdb->rows_affected === 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table
                return $wpdb->insert( $wpdb->prefix . 'hcrm_entity_map', $data ) !== false;
            }

            return $result !== false;
        }

        // Insert new
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table
        $result = $wpdb->insert( $wpdb->prefix . 'hcrm_entity_map', $data );

        if ($result === false) {
            HCRM_Logger::error(sprintf(
                'Entity mapper insert failed for wp_id=%d, entity_type=%s, crm_uuid=%s',
                $wp_id,
                $entity_type,
                $crm_uuid
            ), ['db_error' => $wpdb->last_error]);
        }

        return $result !== false;
    }

    /**
     * Get CRM UUID for a WordPress entity.
     *
     * @param int         $wp_id       WordPress ID.
     * @param string      $entity_type Entity type.
     * @param string|null $taxonomy    Taxonomy name (for taxonomy entity types).
     * @return string|null CRM UUID or null if not found.
     */
    public function get_crm_uuid($wp_id, $entity_type, $taxonomy = null) {
        global $wpdb;

        if ($taxonomy !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE wp_id = %d AND entity_type = %s AND taxonomy = %s",
                    $wp_id,
                    $entity_type,
                    $taxonomy
                )
            );
        }

        // When taxonomy is null, explicitly check for NULL in database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT crm_uuid FROM {$wpdb->prefix}hcrm_entity_map WHERE wp_id = %d AND entity_type = %s AND taxonomy IS NULL",
                $wp_id,
                $entity_type
            )
        );
    }

    /**
     * Get WordPress ID for a CRM UUID.
     *
     * @param string $crm_uuid    CRM UUID.
     * @param string $entity_type Entity type.
     * @return int|null WordPress ID or null if not found.
     */
    public function get_wp_id($crm_uuid, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wp_id FROM {$wpdb->prefix}hcrm_entity_map WHERE crm_uuid = %s AND entity_type = %s",
                $crm_uuid,
                $entity_type
            )
        );

        return $result ? (int) $result : null;
    }

    /**
     * Delete a mapping.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @return bool Success.
     */
    public function delete_mapping($wp_id, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        return $wpdb->delete(
            $wpdb->prefix . 'hcrm_entity_map',
            [
                'wp_id'       => $wp_id,
                'entity_type' => $entity_type,
            ]
        ) !== false;
    }

    /**
     * Delete mapping by CRM UUID.
     *
     * @param string $crm_uuid    CRM UUID.
     * @param string $entity_type Entity type.
     * @return bool Success.
     */
    public function delete_by_uuid($crm_uuid, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        return $wpdb->delete(
            $wpdb->prefix . 'hcrm_entity_map',
            [
                'crm_uuid'    => $crm_uuid,
                'entity_type' => $entity_type,
            ]
        ) !== false;
    }

    /**
     * Get all mappings for an entity type.
     *
     * @param string $entity_type Entity type.
     * @return array Array of mapping rows.
     */
    public function get_all_by_type($entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
                $entity_type
            ),
            ARRAY_A
        );
    }

    /**
     * Get mapping statistics.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Stats query, no user input
        $results = $wpdb->get_results(
            "SELECT entity_type, COUNT(*) as count FROM {$wpdb->prefix}hcrm_entity_map GROUP BY entity_type",
            ARRAY_A
        );

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['entity_type']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Check if entity is synced.
     *
     * @param int         $wp_id       WordPress ID.
     * @param string      $entity_type Entity type.
     * @param string|null $taxonomy    Taxonomy name (for taxonomy entity types).
     * @return bool True if synced.
     */
    public function is_synced($wp_id, $entity_type, $taxonomy = null) {
        return $this->get_crm_uuid($wp_id, $entity_type, $taxonomy) !== null;
    }

    /**
     * Get sync hash for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @return string|null Sync hash or null.
     */
    public function get_sync_hash($wp_id, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        return $wpdb->get_var($wpdb->prepare(
            "SELECT sync_hash FROM {$wpdb->prefix}hcrm_entity_map WHERE wp_id = %d AND entity_type = %s",
            $wp_id,
            $entity_type
        ));
    }

    /**
     * Update sync hash for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @param string $hash        New hash value.
     * @return bool Success.
     */
    public function update_sync_hash($wp_id, $entity_type, $hash) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
        return $wpdb->update(
            $wpdb->prefix . 'hcrm_entity_map',
            [
                'sync_hash'      => $hash,
                'last_synced_at' => current_time('mysql'),
            ],
            [
                'wp_id'       => $wp_id,
                'entity_type' => $entity_type,
            ]
        ) !== false;
    }

    /**
     * Clear all mappings for an entity type.
     *
     * @param string $entity_type Entity type.
     * @return int Number of rows deleted.
     */
    public function clear_type($entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
            $entity_type
        ));
    }

    /**
     * Get last sync time for an entity.
     *
     * @param int    $wp_id       WordPress ID.
     * @param string $entity_type Entity type.
     * @return string|null MySQL datetime or null.
     */
    public function get_last_sync($wp_id, $entity_type) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        return $wpdb->get_var($wpdb->prepare(
            "SELECT synced_at FROM {$wpdb->prefix}hcrm_entity_map WHERE wp_id = %d AND entity_type = %s",
            $wp_id,
            $entity_type
        ));
    }
}
