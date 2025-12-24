<?php
/**
 * Sync Progress class for tracking background sync operations.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_Progress
 *
 * Handles progress tracking for background sync operations using WordPress transients.
 *
 * @since 1.0.0
 */
class HCRM_Sync_Progress {

    /**
     * Transient prefix.
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'hcrm_sync_progress_';

    /**
     * Active syncs option name.
     *
     * @var string
     */
    const ACTIVE_SYNCS_OPTION = 'hcrm_active_syncs';

    /**
     * Transient expiry time (1 hour).
     *
     * @var int
     */
    const EXPIRY_TIME = HOUR_IN_SECONDS;

    /**
     * Create a new sync progress record.
     *
     * @param string $type    Sync type (properties, taxonomies, users, agents, agencies).
     * @param int    $total   Total items to sync.
     * @param array  $options Additional options.
     * @return string Sync ID.
     */
    public static function create($type, $total, $options = []) {
        $sync_id = $type . '_' . wp_generate_uuid4();

        $progress = [
            'sync_id'      => $sync_id,
            'type'         => $type,
            'status'       => 'pending',
            'total'        => (int) $total,
            'processed'    => 0,
            'success'      => 0,
            'failed'       => 0,
            'current_item' => '',
            'errors'       => [],
            'options'      => $options,
            'started_at'   => time(),
            'updated_at'   => time(),
        ];

        set_transient(self::TRANSIENT_PREFIX . $sync_id, $progress, self::EXPIRY_TIME);

        // Track active syncs
        self::add_to_active($sync_id, $type);

        // Log sync start
        HCRM_Logger::sync_start($type, $total, $sync_id);

        return $sync_id;
    }

    /**
     * Get sync progress.
     *
     * @param string $sync_id Sync ID.
     * @return array|null Progress data or null if not found.
     */
    public static function get($sync_id) {
        $progress = get_transient(self::TRANSIENT_PREFIX . $sync_id);

        if ($progress === false) {
            return null;
        }

        // Calculate percentage
        $progress['percentage'] = $progress['total'] > 0
            ? round(($progress['processed'] / $progress['total']) * 100)
            : 0;

        // Calculate elapsed time
        $progress['elapsed'] = time() - $progress['started_at'];
        $progress['elapsed_formatted'] = self::format_time($progress['elapsed']);

        // Estimate remaining time
        if ($progress['processed'] > 0 && $progress['status'] === 'running') {
            $avg_time_per_item = $progress['elapsed'] / $progress['processed'];
            $remaining_items = $progress['total'] - $progress['processed'];
            $progress['estimated_remaining'] = round($avg_time_per_item * $remaining_items);
            $progress['estimated_remaining_formatted'] = self::format_time($progress['estimated_remaining']);
        } else {
            $progress['estimated_remaining'] = 0;
            $progress['estimated_remaining_formatted'] = '';
        }

        return $progress;
    }

    /**
     * Update sync progress.
     *
     * @param string $sync_id Sync ID.
     * @param array  $data    Data to update.
     * @return bool Success.
     */
    public static function update($sync_id, $data) {
        $progress = get_transient(self::TRANSIENT_PREFIX . $sync_id);

        if ($progress === false) {
            return false;
        }

        $progress = array_merge($progress, $data);
        $progress['updated_at'] = time();

        return set_transient(self::TRANSIENT_PREFIX . $sync_id, $progress, self::EXPIRY_TIME);
    }

    /**
     * Increment progress by one item.
     *
     * @param string $sync_id      Sync ID.
     * @param bool   $success      Whether the item synced successfully.
     * @param string $current_item Current item being processed.
     * @param string $error        Error message if failed.
     * @return bool Success.
     */
    public static function increment($sync_id, $success = true, $current_item = '', $error = '') {
        $progress = get_transient(self::TRANSIENT_PREFIX . $sync_id);

        if ($progress === false) {
            return false;
        }

        $progress['processed']++;
        $progress['current_item'] = $current_item;
        $progress['updated_at'] = time();

        if ($success) {
            $progress['success']++;
        } else {
            $progress['failed']++;
            if (!empty($error)) {
                $progress['errors'][] = [
                    'item'    => $current_item,
                    'message' => $error,
                    'time'    => time(),
                ];
                // Keep only last 50 errors
                if (count($progress['errors']) > 50) {
                    $progress['errors'] = array_slice($progress['errors'], -50);
                }

                // Log failed items
                HCRM_Logger::sync_item(0, $current_item, false, $error);
            }
        }

        // Auto-complete if all items processed
        if ($progress['processed'] >= $progress['total'] && $progress['status'] === 'running') {
            $progress['status'] = 'completed';
            $progress['current_item'] = '';
            self::remove_from_active($sync_id);
        }

        return set_transient(self::TRANSIENT_PREFIX . $sync_id, $progress, self::EXPIRY_TIME);
    }

    /**
     * Set sync status to running.
     *
     * @param string $sync_id Sync ID.
     * @return bool Success.
     */
    public static function start($sync_id) {
        return self::update($sync_id, ['status' => 'running']);
    }

    /**
     * Mark sync as completed.
     *
     * @param string $sync_id Sync ID.
     * @return bool Success.
     */
    public static function complete($sync_id) {
        // Get progress for logging before updating
        $progress = self::get($sync_id);

        self::remove_from_active($sync_id);
        $result = self::update($sync_id, [
            'status'       => 'completed',
            'current_item' => '',
        ]);

        // Log sync completion
        if ($progress) {
            HCRM_Logger::sync_complete(
                $sync_id,
                $progress['success'] ?? 0,
                $progress['failed'] ?? 0,
                $progress['elapsed'] ?? 0
            );
        }

        return $result;
    }

    /**
     * Mark sync as failed.
     *
     * @param string $sync_id Sync ID.
     * @param string $error   Error message.
     * @return bool Success.
     */
    public static function fail($sync_id, $error = '') {
        self::remove_from_active($sync_id);

        $data = [
            'status'       => 'failed',
            'current_item' => '',
        ];

        if (!empty($error)) {
            $progress = self::get($sync_id);
            if ($progress) {
                $data['errors'] = $progress['errors'] ?? [];
                $data['errors'][] = [
                    'item'    => 'Sync Process',
                    'message' => $error,
                    'time'    => time(),
                ];
            }

            // Log sync failure
            HCRM_Logger::error("Sync failed: {$sync_id} - {$error}");
        }

        return self::update($sync_id, $data);
    }

    /**
     * Mark sync as cancelled.
     *
     * @param string $sync_id Sync ID.
     * @return bool Success.
     */
    public static function cancel($sync_id) {
        self::remove_from_active($sync_id);

        // Cancel any pending Action Scheduler actions
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('hcrm_process_sync_batch', ['sync_id' => $sync_id], 'hcrm-houzez');
        }

        // Log sync cancellation
        HCRM_Logger::warning("Sync cancelled: {$sync_id}");

        return self::update($sync_id, [
            'status'       => 'cancelled',
            'current_item' => '',
        ]);
    }

    /**
     * Check if sync is cancelled.
     *
     * @param string $sync_id Sync ID.
     * @return bool True if cancelled.
     */
    public static function is_cancelled($sync_id) {
        $progress = get_transient(self::TRANSIENT_PREFIX . $sync_id);
        return $progress && $progress['status'] === 'cancelled';
    }

    /**
     * Get active syncs.
     *
     * @param string|null $type Optional type filter.
     * @return array Array of sync IDs.
     */
    public static function get_active($type = null) {
        $active = get_option(self::ACTIVE_SYNCS_OPTION, []);

        if ($type !== null) {
            return array_filter($active, function ($sync) use ($type) {
                return $sync['type'] === $type;
            });
        }

        return $active;
    }

    /**
     * Check if there's an active sync of a specific type.
     *
     * @param string $type Sync type.
     * @return string|null Sync ID if active, null otherwise.
     */
    public static function has_active_sync($type) {
        $active = self::get_active($type);

        foreach ($active as $sync) {
            $progress = self::get($sync['sync_id']);
            if ($progress && in_array($progress['status'], ['pending', 'running'], true)) {
                return $sync['sync_id'];
            }
        }

        return null;
    }

    /**
     * Add sync to active list.
     *
     * @param string $sync_id Sync ID.
     * @param string $type    Sync type.
     */
    private static function add_to_active($sync_id, $type) {
        $active = get_option(self::ACTIVE_SYNCS_OPTION, []);
        $active[$sync_id] = [
            'sync_id'    => $sync_id,
            'type'       => $type,
            'started_at' => time(),
        ];
        update_option(self::ACTIVE_SYNCS_OPTION, $active);
    }

    /**
     * Remove sync from active list.
     *
     * @param string $sync_id Sync ID.
     */
    private static function remove_from_active($sync_id) {
        $active = get_option(self::ACTIVE_SYNCS_OPTION, []);
        unset($active[$sync_id]);
        update_option(self::ACTIVE_SYNCS_OPTION, $active);
    }

    /**
     * Clean up old/stale syncs.
     */
    public static function cleanup_old() {
        $active = get_option(self::ACTIVE_SYNCS_OPTION, []);
        $cleaned = false;

        foreach ($active as $sync_id => $sync) {
            $progress = self::get($sync_id);

            // Remove if transient expired or sync is old (> 2 hours)
            if ($progress === null || (time() - $sync['started_at']) > (2 * HOUR_IN_SECONDS)) {
                unset($active[$sync_id]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            update_option(self::ACTIVE_SYNCS_OPTION, $active);
        }
    }

    /**
     * Force clear all stuck syncs.
     *
     * @param string|null $type Optional type filter to only clear syncs of a specific type.
     * @return int Number of syncs cleared.
     */
    public static function force_clear_all($type = null) {
        $active = get_option(self::ACTIVE_SYNCS_OPTION, []);
        $cleared = 0;

        foreach ($active as $sync_id => $sync) {
            // Filter by type if specified
            if ($type !== null && $sync['type'] !== $type) {
                continue;
            }

            // Delete the transient
            delete_transient(self::TRANSIENT_PREFIX . $sync_id);

            // Remove from active list
            unset($active[$sync_id]);
            $cleared++;
        }

        update_option(self::ACTIVE_SYNCS_OPTION, $active);

        // Also unschedule any pending Action Scheduler actions
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('hcrm_process_sync_batch', [], 'hcrm-houzez');
        }

        return $cleared;
    }

    /**
     * Delete a sync progress record.
     *
     * @param string $sync_id Sync ID.
     * @return bool Success.
     */
    public static function delete($sync_id) {
        self::remove_from_active($sync_id);
        return delete_transient(self::TRANSIENT_PREFIX . $sync_id);
    }

    /**
     * Format seconds to human readable time.
     *
     * @param int $seconds Seconds.
     * @return string Formatted time.
     */
    private static function format_time( $seconds ) {
        if ( $seconds < 60 ) {
            /* translators: %d: number of seconds */
            return sprintf( __( '%ds', 'hcrm-houzez' ), $seconds );
        }

        $minutes = floor( $seconds / 60 );
        $secs = $seconds % 60;

        if ( $minutes < 60 ) {
            /* translators: 1: minutes, 2: seconds */
            return sprintf( __( '%1$dm %2$ds', 'hcrm-houzez' ), $minutes, $secs );
        }

        $hours = floor( $minutes / 60 );
        $mins = $minutes % 60;

        /* translators: 1: hours, 2: minutes */
        return sprintf( __( '%1$dh %2$dm', 'hcrm-houzez' ), $hours, $mins );
    }
}
