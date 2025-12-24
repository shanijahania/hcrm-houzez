<?php
/**
 * Webhook Handler class for processing incoming webhooks.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Webhook_Handler
 *
 * Handles incoming webhook requests from the CRM.
 *
 * @since 1.0.0
 */
class HCRM_Webhook_Handler {

    /**
     * Webhook processor instance.
     *
     * @var HCRM_Webhook_Processor
     */
    private $processor = null;

    /**
     * REST API namespace.
     *
     * @var string
     */
    const REST_NAMESPACE = 'hcrm/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        // Lazy initialization - don't create processor until needed
    }

    /**
     * Get the processor instance (lazy initialization).
     *
     * @return HCRM_Webhook_Processor
     */
    private function get_processor() {
        if ($this->processor === null) {
            $this->processor = new HCRM_Webhook_Processor();
        }
        return $this->processor;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_request'],
            'permission_callback' => [$this, 'validate_request'],
        ]);

        // Health check endpoint
        register_rest_route(self::REST_NAMESPACE, '/health', [
            'methods'             => 'GET',
            'callback'            => [$this, 'health_check'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Validate incoming webhook request.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public function validate_request($request) {
        // Get the signature header
        $signature = $request->get_header('X-Webhook-Signature');

        // For now, we use a simple token-based validation
        // In production, you might want HMAC signature verification
        $provided_secret = $request->get_header('X-Webhook-Secret');
        $stored_secret = get_option('hcrm_webhook_secret');

        // If secret header is provided, validate it
        if ($provided_secret && $stored_secret) {
            if (!hash_equals($stored_secret, $provided_secret)) {
                return new WP_Error(
                    'invalid_secret',
                    'Invalid webhook secret',
                    ['status' => 401]
                );
            }
            return true;
        }

        // If signature is provided, validate HMAC
        if ($signature && $stored_secret) {
            $body = $request->get_body();
            $expected = hash_hmac('sha256', $body, $stored_secret);

            if (!hash_equals($expected, $signature)) {
                return new WP_Error(
                    'invalid_signature',
                    'Invalid webhook signature',
                    ['status' => 401]
                );
            }
            return true;
        }

        // No authentication provided - reject in production, allow in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return new WP_Error(
            'missing_auth',
            'Webhook authentication required',
            ['status' => 401]
        );
    }

    /**
     * Handle incoming webhook request.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function handle_request($request) {
        $payload = $request->get_json_params();

        // Log the webhook
        $this->log_webhook($request, $payload);

        // Validate payload structure
        if (empty($payload['action'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing action in webhook payload',
            ], 400);
        }

        $action = sanitize_text_field($payload['action']);

        // Validate action
        if (!$this->get_processor()->is_valid_action($action)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Unsupported action: {$action}",
            ], 400);
        }

        try {
            $result = $this->get_processor()->process($action, $payload);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data'    => $result,
            ], 200);

        } catch (Exception $e) {
            $this->log_error($action, $payload, $e->getMessage());

            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function health_check($request) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'HCRM Houzez webhook endpoint is healthy',
            'version' => HCRM_VERSION,
            'time'    => current_time('c'),
        ], 200);
    }

    /**
     * Get the webhook URL.
     *
     * @return string
     */
    public static function get_webhook_url() {
        return rest_url(self::REST_NAMESPACE . '/webhook');
    }

    /**
     * Get the webhook secret.
     *
     * @return string
     */
    public static function get_webhook_secret() {
        return get_option('hcrm_webhook_secret', '');
    }

    /**
     * Log incoming webhook.
     *
     * @param WP_REST_Request $request Request object.
     * @param array           $payload Request payload.
     */
    private function log_webhook($request, $payload) {
        $ip_address = $request->get_header('X-Forwarded-For');
        if ( ! $ip_address ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP address for logging only
            $ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        }
        HCRM_Logger::info(sprintf(
            'Webhook received: Action: %s | IP: %s',
            $payload['action'] ?? 'unknown',
            $ip_address
        ));
    }

    /**
     * Log webhook error.
     *
     * @param string $action  Webhook action.
     * @param array  $payload Request payload.
     * @param string $error   Error message.
     */
    private function log_error($action, $payload, $error) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
        $wpdb->insert(
            $wpdb->prefix . 'hcrm_sync_log',
            [
                'entity_type'   => 'webhook',
                'entity_id'     => 0,
                'action'        => $action,
                'direction'     => 'webhook',
                'status'        => 'failed',
                'request_data'  => wp_json_encode($payload),
                'response_data' => null,
                'error_message' => $error,
                'created_at'    => current_time('mysql'),
            ]
        );

        HCRM_Logger::error("Webhook Error: Action: {$action} | Error: {$error}");
    }
}
