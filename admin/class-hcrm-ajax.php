<?php
/**
 * AJAX Handler class for admin actions.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Ajax
 *
 * Handles AJAX requests for the admin interface.
 *
 * @since 1.0.0
 */
class HCRM_Ajax {

    /**
     * Test API connection.
     */
    public function test_connection() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            // Get values from POST (form values) or fall back to saved settings
            $api_base_url = '';
            $api_token = '';

            if ( ! empty( $_POST['api_base_url'] ) ) {
                $api_base_url = sanitize_text_field( wp_unslash( $_POST['api_base_url'] ) );
            } else {
                $api_base_url = HCRM_Settings::get_api_base_url();
            }

            if ( ! empty( $_POST['api_token'] ) && $_POST['api_token'] !== '********' ) {
                $api_token = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
            } else {
                $api_token = HCRM_Settings::get_api_token();
            }

            // Validate inputs
            if (empty($api_base_url)) {
                wp_send_json_error([
                    'message' => __('Please enter the API Base URL.', 'hcrm-houzez'),
                ]);
            }

            if (empty($api_token)) {
                wp_send_json_error([
                    'message' => __('Please enter the API Token.', 'hcrm-houzez'),
                ]);
            }

            // Create API client with provided values
            $auth = new HCRM_API_Auth($api_token);
            $api_client = new HCRM_API_Client($api_base_url, $auth);

            // Make a test request
            $response = $api_client->get('/listings/search', ['per_page' => 1]);

            if ($response->is_success()) {
                wp_send_json_success([
                    'message' => __('Connection successful! API is working correctly.', 'hcrm-houzez'),
                    'status'  => 'connected',
                ]);
            } elseif ($response->is_auth_error()) {
                wp_send_json_error([
                    'message' => __('Authentication failed. Please check your API token.', 'hcrm-houzez'),
                ]);
            } elseif ( $response->get_status_code() === 0 ) {
                wp_send_json_error( [
                    'message' => sprintf(
                        /* translators: %s: error message */
                        __( 'Could not connect to the API. Error: %s', 'hcrm-houzez' ),
                        $response->get_message()
                    ),
                ] );
            } else {
                wp_send_json_error( [
                    'message' => sprintf(
                        /* translators: 1: status code, 2: error message */
                        __( 'API Error (%1$d): %2$s', 'hcrm-houzez' ),
                        $response->get_status_code(),
                        $response->get_message() ?: 'Unknown error'
                    ),
                ] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( [
                /* translators: %s: error message */
                'message' => sprintf( __( 'Error: %s', 'hcrm-houzez' ), $e->getMessage() ),
            ] );
        }
    }

    /**
     * Save settings via AJAX.
     */
    public function save_settings() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // Parse form data
        $form_data = [];
        if ( isset( $_POST['settings'] ) ) {
            // Note: Don't use sanitize_text_field() here as it corrupts URL-encoded characters
            // Individual fields are sanitized after parsing (esc_url_raw for URLs, sanitize_text_field for text)
            parse_str( wp_unslash( $_POST['settings'] ), $form_data );
        }

        // Update API settings (including auto_sync which was moved here)
        $api_settings = HCRM_Settings::get_api_settings();

        if (isset($form_data['api_base_url'])) {
            $api_settings['api_base_url'] = esc_url_raw(trim($form_data['api_base_url']));
        }

        if (isset($form_data['api_token']) && !empty($form_data['api_token']) && $form_data['api_token'] !== '********') {
            $api_settings['api_token'] = HCRM_API_Auth::encrypt_token(sanitize_text_field($form_data['api_token']));
        }

        // Auto sync is now part of API settings
        $api_settings['auto_sync'] = !empty($form_data['auto_sync']);

        update_option(HCRM_Settings::API_SETTINGS, $api_settings);

        // Update webhook secret if provided
        if (isset($form_data['webhook_secret']) && !empty($form_data['webhook_secret']) && $form_data['webhook_secret'] !== '********') {
            update_option('hcrm_webhook_secret', sanitize_text_field($form_data['webhook_secret']));
        }

        // Update legacy sync settings (for backward compatibility)
        $sync_settings = [
            'sync_properties'     => !empty($form_data['sync_properties']),
            'sync_taxonomies'     => !empty($form_data['sync_taxonomies']),
            'sync_users'          => !empty($form_data['sync_users']),
            'sync_leads'          => !empty($form_data['sync_leads']),
            'auto_sync'           => !empty($form_data['auto_sync']),
            'taxonomy_auto_sync'  => !empty($form_data['taxonomy_auto_sync']),
        ];
        update_option(HCRM_Settings::SYNC_SETTINGS, $sync_settings);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'hcrm-houzez'),
        ]);
    }

    /**
     * Sync properties to CRM (background mode).
     */
    public function sync_properties() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            $sync_manager = HCRM_Sync_Manager::get_instance();
            $result = $sync_manager->start_background_sync('properties');

            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('Property sync started in background.', 'hcrm-houzez'),
                    'sync_id' => $result['sync_id'],
                    'total'   => $result['total'],
                    'status'  => 'started',
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'],
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync taxonomies to CRM.
     */
    public function sync_taxonomies() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // For now, taxonomies are synced along with properties
        // This is a placeholder for dedicated taxonomy sync
        wp_send_json_success([
            'message' => __('Taxonomy sync is included with property sync.', 'hcrm-houzez'),
            'total'   => 0,
            'success' => 0,
            'failed'  => 0,
        ]);
    }

    /**
     * Sync users to CRM.
     */
    public function sync_users() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // Placeholder - User sync not implemented in initial version
        wp_send_json_success([
            'message' => __('User sync is not yet implemented.', 'hcrm-houzez'),
            'total'   => 0,
            'success' => 0,
            'failed'  => 0,
        ]);
    }

    /**
     * Sync leads to CRM.
     */
    public function sync_leads() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // Placeholder - Lead sync not implemented in initial version
        wp_send_json_success([
            'message' => __('Lead sync is not yet implemented.', 'hcrm-houzez'),
            'total'   => 0,
            'success' => 0,
            'failed'  => 0,
        ]);
    }

    /**
     * Save properties settings via AJAX.
     */
    public function save_properties_settings() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array values sanitized below
        $settings = isset( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : [];

        // Update properties settings
        $properties_settings = [
            'sync_properties' => ! empty( $settings['sync_properties'] ),
            'sync_on_save'    => ! empty( $settings['sync_on_save'] ),
        ];
        update_option( HCRM_Settings::PROPERTIES_SETTINGS, $properties_settings );

        // Update taxonomy settings
        $taxonomy_settings = [
            'sync_property_type'    => ! empty( $settings['sync_property_type'] ),
            'sync_property_status'  => ! empty( $settings['sync_property_status'] ),
            'sync_property_label'   => ! empty( $settings['sync_property_label'] ),
            'sync_property_feature' => ! empty( $settings['sync_property_feature'] ),
        ];
        update_option( HCRM_Settings::TAXONOMY_SETTINGS, $taxonomy_settings );

        wp_send_json_success([
            'message' => __('Properties settings saved successfully.', 'hcrm-houzez'),
        ]);
    }

    /**
     * Save users settings via AJAX.
     */
    public function save_users_settings() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array values sanitized below
        $settings = isset( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : [];

        $users_settings = [
            'sync_users'   => ! empty( $settings['sync_users'] ),
            'sync_avatars' => ! empty( $settings['sync_avatars'] ),
            'auto_sync'    => ! empty( $settings['auto_sync'] ),
            'role_mapping' => isset( $settings['role_mapping'] ) ? array_map( 'sanitize_text_field', (array) $settings['role_mapping'] ) : [],
        ];
        update_option( HCRM_Settings::USERS_SETTINGS, $users_settings );

        wp_send_json_success([
            'message' => __('Users settings saved successfully.', 'hcrm-houzez'),
        ]);
    }

    /**
     * Save leads settings via AJAX.
     */
    public function save_leads_settings() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array values sanitized below
        $settings = isset( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : [];

        $leads_settings = [
            'sync_leads'           => ! empty( $settings['sync_leads'] ),
            'use_background_queue' => ! empty( $settings['use_background_queue'] ),
            'hooks_enabled'        => isset( $settings['hooks_enabled'] ) ? array_map( 'absint', (array) $settings['hooks_enabled'] ) : [],
        ];
        update_option( HCRM_Settings::LEADS_SETTINGS, $leads_settings );

        wp_send_json_success([
            'message' => __('Leads settings saved successfully.', 'hcrm-houzez'),
        ]);
    }

    /**
     * Sync a specific taxonomy via AJAX (background mode).
     */
    public function sync_taxonomy() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';

        if (empty($taxonomy)) {
            wp_send_json_error([
                'message' => __('Taxonomy not specified.', 'hcrm-houzez'),
            ]);
        }

        try {
            $sync_manager = HCRM_Sync_Manager::get_instance();
            $result = $sync_manager->start_background_sync('taxonomy', ['taxonomy' => $taxonomy]);

            if ( $result['success'] ) {
                wp_send_json_success( [
                    /* translators: %s: taxonomy name */
                    'message'  => sprintf( __( '%s sync started in background.', 'hcrm-houzez' ), ucfirst( str_replace( '_', ' ', $taxonomy ) ) ),
                    'sync_id'  => $result['sync_id'],
                    'total'    => $result['total'],
                    'status'   => 'started',
                    'taxonomy' => $taxonomy,
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'],
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync users (WordPress users/agencies) via AJAX (background mode).
     */
    public function sync_users_ajax() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'all';

        try {
            $sync_manager = HCRM_Sync_Manager::get_instance();

            // Handle sync type
            $sync_type = $type;
            if ($type === 'all') {
                // For 'all', we start with wp_users first (then agencies)
                $sync_type = 'wp_users';
            }

            $result = $sync_manager->start_background_sync($sync_type);

            if ($result['success']) {
                $messages = [
                    'agencies' => __('Agency sync started in background.', 'hcrm-houzez'),
                    'wp_users' => __('WordPress user sync started in background.', 'hcrm-houzez'),
                    'all'      => __('User sync started in background.', 'hcrm-houzez'),
                ];
                $message = $messages[$type] ?? __('User sync started in background.', 'hcrm-houzez');

                wp_send_json_success([
                    'message'   => $message,
                    'sync_id'   => $result['sync_id'],
                    'total'     => $result['total'],
                    'status'    => 'started',
                    'sync_type' => $type,
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'],
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get users stats via AJAX.
     */
    public function get_users_stats() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            $sync_user = new HCRM_Sync_User();
            $stats = $sync_user->get_stats();

            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get sync status.
     */
    public function get_sync_status() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            $sync_manager = HCRM_Sync_Manager::get_instance();
            $stats = $sync_manager->get_sync_stats();

            // Get API connection status
            $api_client = HCRM_API_Client::from_settings();
            $is_connected = false;
            $endpoint = '';

            if ($api_client->is_configured()) {
                // Get endpoint hostname for display
                $base_url = $api_client->get_base_url();
                if ( ! empty( $base_url ) ) {
                    $endpoint = wp_parse_url( $base_url, PHP_URL_HOST );
                }

                // Try to test the connection
                $response = $api_client->get('/listings/search', ['per_page' => 1]);
                $status_code = $response->get_status_code();

                // Connected if we get a successful response
                $is_connected = ($status_code >= 200 && $status_code < 300);
            }

            wp_send_json_success([
                'connected'          => $is_connected,
                'endpoint'           => $endpoint,
                'properties_synced'  => (int) $stats['properties_synced'],
                'last_sync'          => $stats['last_sync'] ? human_time_diff(strtotime($stats['last_sync'])) . ' ago' : __('Never', 'hcrm-houzez'),
                'errors_24h'         => (int) $stats['sync_errors_24h'],
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get sync progress for polling.
     */
    public function get_sync_progress() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        $sync_id = isset( $_POST['sync_id'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_id'] ) ) : '';

        if (empty($sync_id)) {
            wp_send_json_error([
                'message' => __('Sync ID not specified.', 'hcrm-houzez'),
            ]);
        }

        try {
            $progress = HCRM_Sync_Progress::get($sync_id);

            if ($progress === null) {
                wp_send_json_error([
                    'message' => __('Sync not found or expired.', 'hcrm-houzez'),
                    'status'  => 'not_found',
                ]);
            }

            // If sync is active, trigger background processing after sending response
            $should_trigger = ($progress['status'] === 'pending' || $progress['status'] === 'running');

            // Send JSON response
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            echo wp_json_encode(['success' => true, 'data' => $progress]);

            // Flush output to browser immediately
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                // Flush output buffers
                if (ob_get_level() > 0) {
                    ob_end_flush();
                }
                flush();
            }

            // Now run Action Scheduler in the background (after response sent)
            if ($should_trigger && class_exists('ActionScheduler_QueueRunner')) {
                ActionScheduler_QueueRunner::instance()->run();
            }

            exit;
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel a running sync.
     */
    public function cancel_sync() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        $sync_id = isset( $_POST['sync_id'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_id'] ) ) : '';

        if (empty($sync_id)) {
            wp_send_json_error([
                'message' => __('Sync ID not specified.', 'hcrm-houzez'),
            ]);
        }

        try {
            $sync_manager = HCRM_Sync_Manager::get_instance();
            $result = $sync_manager->cancel_sync($sync_id);

            if (!empty($result['success'])) {
                wp_send_json_success([
                    'message' => __('Sync cancelled successfully.', 'hcrm-houzez'),
                    'status'  => 'cancelled',
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'] ?? __('Failed to cancel sync.', 'hcrm-houzez'),
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get active syncs.
     */
    public function get_active_syncs() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            $active = HCRM_Sync_Progress::get_active();
            $syncs = [];

            foreach ($active as $sync) {
                $progress = HCRM_Sync_Progress::get($sync['sync_id']);
                if ($progress && in_array($progress['status'], ['pending', 'running'], true)) {
                    $syncs[] = $progress;
                }
            }

            wp_send_json_success([
                'syncs' => $syncs,
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Force clear all stuck syncs.
     */
    public function clear_stuck_syncs() {
        // Verify nonce
        check_ajax_referer('hcrm_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'hcrm-houzez'),
            ]);
        }

        try {
            $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : null;
            $cleared = HCRM_Sync_Progress::force_clear_all($type);

            wp_send_json_success( [
                /* translators: %d: number of syncs cleared */
                'message' => sprintf( __( 'Cleared %d stuck sync(s).', 'hcrm-houzez' ), $cleared ),
                'cleared' => $cleared,
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger Action Scheduler to process pending actions.
     * This is called via async request to ensure immediate processing.
     */
    public function trigger_sync() {
        // Verify nonce if provided
        if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'hcrm_trigger_sync' ) ) {
            wp_die( 'Invalid nonce' );
        }

        // Run Action Scheduler queue
        if (class_exists('ActionScheduler_QueueRunner')) {
            $runner = ActionScheduler_QueueRunner::instance();
            $runner->run();
        }

        wp_die();
    }

    /**
     * Get sync logs via AJAX.
     */
    public function get_logs() {
        check_ajax_referer('hcrm_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'hcrm-houzez')]);
        }

        wp_send_json_success([
            'logs' => HCRM_Logger::get_logs(500),
            'size' => HCRM_Logger::get_formatted_log_size(),
        ]);
    }

    /**
     * Clear sync logs via AJAX.
     */
    public function clear_logs() {
        check_ajax_referer('hcrm_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'hcrm-houzez')]);
        }

        $result = HCRM_Logger::clear_logs();

        if ($result) {
            wp_send_json_success(['message' => __('Logs cleared successfully', 'hcrm-houzez')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear logs', 'hcrm-houzez')]);
        }
    }

    // =========================================================================
    // CUSTOM FIELDS MAPPING METHODS
    // =========================================================================

    /**
     * Get Houzez custom fields via AJAX.
     */
    public function get_houzez_custom_fields() {
        check_ajax_referer( 'hcrm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'hcrm-houzez' ) ] );
        }

        try {
            $fields = HCRM_Custom_Fields_Mapper::get_houzez_custom_fields();

            wp_send_json_success( [
                'fields' => $fields,
            ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [
                'message' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Get CRM custom fields via AJAX.
     */
    public function get_crm_custom_fields() {
        check_ajax_referer( 'hcrm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'hcrm-houzez' ) ] );
        }

        try {
            $api_client = HCRM_API_Client::from_settings();

            if ( ! $api_client->is_configured() ) {
                wp_send_json_error( [
                    'message' => __( 'API is not configured. Please set up the API connection first.', 'hcrm-houzez' ),
                ] );
            }

            $response = $api_client->get_custom_fields( 'listing' );

            if ( $response->is_success() ) {
                // get_data() already returns the 'data' array from the API response
                $fields = $response->get_data();

                // Ensure we have an array
                if ( ! is_array( $fields ) ) {
                    $fields = [];
                }

                // Normalize the field structure.
                $normalized = [];
                foreach ( $fields as $field ) {
                    $normalized[] = [
                        'slug'  => $field['slug'] ?? '',
                        'name'  => $field['name'] ?? '',
                        'type'  => $field['fieldType'] ?? 'text',
                    ];
                }

                wp_send_json_success( [
                    'fields' => $normalized,
                ] );
            } else {
                wp_send_json_error( [
                    'message' => $response->get_message() ?: __( 'Failed to fetch CRM custom fields.', 'hcrm-houzez' ),
                ] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( [
                'message' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Save custom fields mapping via AJAX.
     */
    public function save_custom_fields_mapping() {
        check_ajax_referer( 'hcrm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'hcrm-houzez' ) ] );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized in mapper.
        $mapping_json = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '[]';
        $mapping      = json_decode( $mapping_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [
                'message' => __( 'Invalid mapping data.', 'hcrm-houzez' ),
            ] );
        }

        $result = HCRM_Custom_Fields_Mapper::save_mapping( $mapping );

        if ( $result ) {
            wp_send_json_success( [
                'message' => __( 'Custom fields mapping saved successfully.', 'hcrm-houzez' ),
                'count'   => HCRM_Custom_Fields_Mapper::get_mapped_count(),
            ] );
        } else {
            wp_send_json_error( [
                'message' => __( 'Failed to save custom fields mapping.', 'hcrm-houzez' ),
            ] );
        }
    }

    /**
     * Get current custom fields mapping via AJAX.
     */
    public function get_custom_fields_mapping() {
        check_ajax_referer( 'hcrm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'hcrm-houzez' ) ] );
        }

        wp_send_json_success( [
            'mapping' => HCRM_Custom_Fields_Mapper::get_mapping(),
            'count'   => HCRM_Custom_Fields_Mapper::get_mapped_count(),
        ] );
    }
}
