<?php
/**
 * API Client class for communicating with Laravel CRM.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_API_Client
 *
 * HTTP client for communicating with the Laravel CRM API.
 *
 * @since 1.0.0
 */
class HCRM_API_Client {

    /**
     * The base URL of the API.
     *
     * @var string
     */
    private $base_url;

    /**
     * The authentication handler.
     *
     * @var HCRM_API_Auth
     */
    private $auth;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Constructor.
     *
     * @param string|null       $base_url The API base URL.
     * @param HCRM_API_Auth|null $auth    The authentication handler.
     */
    public function __construct($base_url = null, $auth = null) {
        if ($base_url === null) {
            $settings = get_option('hcrm_api_settings', []);
            $base_url = $settings['api_base_url'] ?? '';
        }

        $this->base_url = rtrim($base_url, '/');
        $this->auth = $auth ?? HCRM_API_Auth::from_settings();
    }

    /**
     * Create a client from stored settings.
     *
     * @return self
     */
    public static function from_settings() {
        return new self();
    }

    /**
     * Make an API request.
     *
     * @param string $method   HTTP method (GET, POST, PUT, DELETE).
     * @param string $endpoint API endpoint (e.g., '/listings').
     * @param array  $data     Request data.
     * @return HCRM_API_Response
     */
    public function request($method, $endpoint, $data = []) {
        $url = $this->base_url . '/' . ltrim($endpoint, '/');

        $args = [
            'method'  => strtoupper($method),
            'headers' => $this->auth->get_headers(),
            'timeout' => $this->timeout,
        ];

        // Disable SSL verification for local development domains
        if ($this->is_local_domain($url)) {
            $args['sslverify'] = false;
        }

        // Handle data based on method
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }

        // Make the request
        $response = wp_remote_request($url, $args);

        // Handle WP_Error
        if (is_wp_error($response)) {
            $this->log_request($method, $endpoint, $data, null, $response->get_error_message());
            return HCRM_API_Response::from_error($response);
        }

        // Parse response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_request($method, $endpoint, $data, $status_code, 'Invalid JSON response');
            return HCRM_API_Response::error(
                'Invalid JSON response from API',
                [],
                $status_code
            );
        }

        // Log the request (include validation errors for 422 responses)
        $error_msg = null;
        if ($status_code === 422 && isset($parsed['errors'])) {
            $error_details = [];
            foreach ($parsed['errors'] as $field => $messages) {
                if (is_array($messages)) {
                    $error_details[] = $field . ': ' . implode(', ', $messages);
                } else {
                    $error_details[] = $field . ': ' . $messages;
                }
            }
            $error_msg = 'Validation failed: ' . implode('; ', $error_details);
        }
        $this->log_request($method, $endpoint, $data, $status_code, $error_msg);

        return new HCRM_API_Response($parsed, $status_code);
    }

    /**
     * Make a GET request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @return HCRM_API_Response
     */
    public function get($endpoint, $params = []) {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return HCRM_API_Response
     */
    public function post($endpoint, $data) {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PUT request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return HCRM_API_Response
     */
    public function put($endpoint, $data) {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a DELETE request.
     *
     * @param string $endpoint API endpoint.
     * @return HCRM_API_Response
     */
    public function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Test the API connection.
     *
     * @return bool|HCRM_API_Response Returns true on success, or response object for debugging.
     */
    public function test_connection() {
        if (empty($this->base_url)) {
            return false;
        }

        if (!$this->auth->is_valid_token()) {
            return false;
        }

        // Try to make a simple request to verify credentials
        // Using a lightweight endpoint or the listings search with limit
        $response = $this->get('/listings/search', ['per_page' => 1]);

        // If successful or not an auth error, connection is valid
        if ($response->is_success()) {
            return true;
        }

        // If it's an auth error, the credentials are wrong
        if ($response->is_auth_error()) {
            return false;
        }

        // For other errors (404, 500, etc.), the connection itself works
        // but there might be an API issue - still consider it "connected"
        return true;
    }

    /**
     * Create a new listing in the CRM.
     *
     * @param array $data Listing data.
     * @return HCRM_API_Response
     */
    public function create_listing($data) {
        return $this->post('/listings', $data);
    }

    /**
     * Update an existing listing in the CRM.
     *
     * @param string $uuid Listing UUID.
     * @param array  $data Listing data.
     * @return HCRM_API_Response
     */
    public function update_listing($uuid, $data) {
        return $this->put("/listings/{$uuid}", $data);
    }

    /**
     * Get a single listing from the CRM.
     *
     * @param string $uuid    Listing UUID.
     * @param array  $include Relationships to include.
     * @return HCRM_API_Response
     */
    public function get_listing($uuid, $include = []) {
        $params = [];
        if (!empty($include)) {
            $params['include'] = implode(',', $include);
        }

        return $this->get("/listings/{$uuid}", $params);
    }

    /**
     * Search listings in the CRM.
     *
     * @param array $filters Search filters.
     * @param int   $page    Page number.
     * @param int   $per_page Results per page.
     * @return HCRM_API_Response
     */
    public function search_listings($filters = [], $page = 1, $per_page = 15) {
        $params = array_merge($filters, [
            'page'     => $page,
            'per_page' => $per_page,
        ]);

        return $this->get('/listings/search', $params);
    }

    /**
     * Delete a listing from the CRM.
     *
     * @param string $uuid Listing UUID.
     * @return HCRM_API_Response
     */
    public function delete_listing($uuid) {
        return $this->delete("/listings/{$uuid}");
    }

    // =========================================================================
    // AGENCY METHODS
    // =========================================================================

    /**
     * Get all agencies from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_agencies() {
        return $this->get('/agencies');
    }

    /**
     * Get a single agency from the CRM.
     *
     * @param string $uuid Agency UUID.
     * @return HCRM_API_Response
     */
    public function get_agency($uuid) {
        return $this->get("/agencies/{$uuid}");
    }

    /**
     * Create a new agency in the CRM.
     *
     * @param array $data Agency data.
     * @return HCRM_API_Response
     */
    public function create_agency($data) {
        return $this->post('/agencies', $data);
    }

    /**
     * Update an existing agency in the CRM.
     *
     * @param string $uuid Agency UUID.
     * @param array  $data Agency data.
     * @return HCRM_API_Response
     */
    public function update_agency($uuid, $data) {
        return $this->put("/agencies/{$uuid}", $data);
    }

    /**
     * Delete an agency from the CRM.
     *
     * @param string $uuid Agency UUID.
     * @return HCRM_API_Response
     */
    public function delete_agency($uuid) {
        return $this->delete("/agencies/{$uuid}");
    }

    /**
     * Search agencies in the CRM.
     *
     * @param string $query Search query.
     * @return HCRM_API_Response
     */
    public function search_agencies($query) {
        return $this->get('/agencies/search', ['q' => $query]);
    }

    // =========================================================================
    // USER METHODS
    // =========================================================================

    /**
     * Get all users from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_users() {
        return $this->get('/users');
    }

    /**
     * Get a single user from the CRM.
     *
     * @param string $uuid User UUID.
     * @return HCRM_API_Response
     */
    public function get_user($uuid) {
        return $this->get("/users/{$uuid}");
    }

    /**
     * Create a new user in the CRM.
     *
     * @param array $data User data.
     * @return HCRM_API_Response
     */
    public function create_user($data) {
        return $this->post('/users', $data);
    }

    /**
     * Update an existing user in the CRM.
     *
     * @param string $uuid User UUID.
     * @param array  $data User data.
     * @return HCRM_API_Response
     */
    public function update_user($uuid, $data) {
        return $this->put("/users/{$uuid}", $data);
    }

    /**
     * Delete a user from the CRM.
     *
     * @param string $uuid User UUID.
     * @return HCRM_API_Response
     */
    public function delete_user($uuid) {
        return $this->delete("/users/{$uuid}");
    }

    /**
     * Find a user by email in the CRM.
     *
     * @param string $email User email.
     * @return HCRM_API_Response
     */
    public function find_user_by_email($email) {
        return $this->get('/users/find-by-email', ['email' => $email]);
    }

    /**
     * Assign a role to a user in the CRM.
     *
     * @param string $uuid User UUID.
     * @param string $role Role name.
     * @return HCRM_API_Response
     */
    public function assign_user_role($uuid, $role) {
        return $this->post("/users/{$uuid}/roles", ['role' => $role]);
    }

    // =========================================================================
    // TAXONOMY METHODS (Listing Types, Statuses, Labels, Features)
    // =========================================================================

    /**
     * Get all listing types from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_listing_types() {
        return $this->get('/listing-types');
    }

    /**
     * Create a listing type in the CRM.
     *
     * @param array $data Type data.
     * @return HCRM_API_Response
     */
    public function create_listing_type($data) {
        return $this->post('/listing-types', $data);
    }

    /**
     * Update a listing type in the CRM.
     *
     * @param string $uuid Type UUID.
     * @param array  $data Type data.
     * @return HCRM_API_Response
     */
    public function update_listing_type($uuid, $data) {
        return $this->put("/listing-types/{$uuid}", $data);
    }

    /**
     * Delete a listing type from the CRM.
     *
     * @param string $uuid Type UUID.
     * @return HCRM_API_Response
     */
    public function delete_listing_type($uuid) {
        return $this->delete("/listing-types/{$uuid}");
    }

    /**
     * Find a listing type by name in the CRM.
     *
     * @param string $name Type name.
     * @return HCRM_API_Response
     */
    public function find_listing_type_by_name($name) {
        return $this->get('/listing-types/find-by-name', ['name' => $name]);
    }

    /**
     * Get all listing statuses from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_listing_statuses() {
        return $this->get('/listing-statuses');
    }

    /**
     * Create a listing status in the CRM.
     *
     * @param array $data Status data.
     * @return HCRM_API_Response
     */
    public function create_listing_status($data) {
        return $this->post('/listing-statuses', $data);
    }

    /**
     * Update a listing status in the CRM.
     *
     * @param string $uuid Status UUID.
     * @param array  $data Status data.
     * @return HCRM_API_Response
     */
    public function update_listing_status($uuid, $data) {
        return $this->put("/listing-statuses/{$uuid}", $data);
    }

    /**
     * Delete a listing status from the CRM.
     *
     * @param string $uuid Status UUID.
     * @return HCRM_API_Response
     */
    public function delete_listing_status($uuid) {
        return $this->delete("/listing-statuses/{$uuid}");
    }

    /**
     * Find a listing status by name in the CRM.
     *
     * @param string $name Status name.
     * @return HCRM_API_Response
     */
    public function find_listing_status_by_name($name) {
        return $this->get('/listing-statuses/find-by-name', ['name' => $name]);
    }

    /**
     * Get all listing labels from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_listing_labels() {
        return $this->get('/listing-labels');
    }

    /**
     * Create a listing label in the CRM.
     *
     * @param array $data Label data.
     * @return HCRM_API_Response
     */
    public function create_listing_label($data) {
        return $this->post('/listing-labels', $data);
    }

    /**
     * Update a listing label in the CRM.
     *
     * @param string $uuid Label UUID.
     * @param array  $data Label data.
     * @return HCRM_API_Response
     */
    public function update_listing_label($uuid, $data) {
        return $this->put("/listing-labels/{$uuid}", $data);
    }

    /**
     * Delete a listing label from the CRM.
     *
     * @param string $uuid Label UUID.
     * @return HCRM_API_Response
     */
    public function delete_listing_label($uuid) {
        return $this->delete("/listing-labels/{$uuid}");
    }

    /**
     * Find a listing label by name in the CRM.
     *
     * @param string $name Label name.
     * @return HCRM_API_Response
     */
    public function find_listing_label_by_name($name) {
        return $this->get('/listing-labels/find-by-name', ['name' => $name]);
    }

    /**
     * Get all amenities from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_amenities() {
        return $this->get('/amenities');
    }

    /**
     * Create an amenity in the CRM.
     *
     * @param array $data Amenity data.
     * @return HCRM_API_Response
     */
    public function create_amenity($data) {
        return $this->post('/amenities', $data);
    }

    /**
     * Update an amenity in the CRM.
     *
     * @param string $uuid Amenity UUID.
     * @param array  $data Amenity data.
     * @return HCRM_API_Response
     */
    public function update_amenity($uuid, $data) {
        return $this->put("/amenities/{$uuid}", $data);
    }

    /**
     * Delete an amenity from the CRM.
     *
     * @param string $uuid Amenity UUID.
     * @return HCRM_API_Response
     */
    public function delete_amenity($uuid) {
        return $this->delete("/amenities/{$uuid}");
    }

    /**
     * Find an amenity by name in the CRM.
     *
     * @param string $name Amenity name.
     * @return HCRM_API_Response
     */
    public function find_amenity_by_name($name) {
        return $this->get('/amenities/find-by-name', ['name' => $name]);
    }

    // =========================================================================
    // FACILITY METHODS
    // =========================================================================

    /**
     * Get all facilities from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_facilities() {
        return $this->get('/facilities');
    }

    /**
     * Create a facility in the CRM.
     *
     * @param array $data Facility data.
     * @return HCRM_API_Response
     */
    public function create_facility($data) {
        return $this->post('/facilities', $data);
    }

    /**
     * Update a facility in the CRM.
     *
     * @param string $uuid Facility UUID.
     * @param array  $data Facility data.
     * @return HCRM_API_Response
     */
    public function update_facility($uuid, $data) {
        return $this->put("/facilities/{$uuid}", $data);
    }

    /**
     * Delete a facility from the CRM.
     *
     * @param string $uuid Facility UUID.
     * @return HCRM_API_Response
     */
    public function delete_facility($uuid) {
        return $this->delete("/facilities/{$uuid}");
    }

    /**
     * Find a facility by name in the CRM.
     *
     * @param string $name Facility name.
     * @return HCRM_API_Response
     */
    public function find_facility_by_name($name) {
        return $this->get('/facilities/find-by-name', ['name' => $name]);
    }

    // =========================================================================
    // CONTACT METHODS
    // =========================================================================

    /**
     * Get all contacts from the CRM.
     *
     * @param array $params Query parameters.
     * @return HCRM_API_Response
     */
    public function get_contacts($params = []) {
        return $this->get('/contacts', $params);
    }

    /**
     * Get a single contact from the CRM.
     *
     * @param string $uuid Contact UUID.
     * @return HCRM_API_Response
     */
    public function get_contact($uuid) {
        return $this->get("/contacts/{$uuid}");
    }

    /**
     * Create a new contact in the CRM.
     *
     * @param array $data Contact data.
     * @return HCRM_API_Response
     */
    public function create_contact($data) {
        return $this->post('/contacts', $data);
    }

    /**
     * Update an existing contact in the CRM.
     *
     * @param string $uuid Contact UUID.
     * @param array  $data Contact data.
     * @return HCRM_API_Response
     */
    public function update_contact($uuid, $data) {
        return $this->put("/contacts/{$uuid}", $data);
    }

    /**
     * Delete a contact from the CRM.
     *
     * @param string $uuid Contact UUID.
     * @return HCRM_API_Response
     */
    public function delete_contact($uuid) {
        return $this->delete("/contacts/{$uuid}");
    }

    /**
     * Search contacts in the CRM.
     *
     * @param string $query Search query.
     * @return HCRM_API_Response
     */
    public function search_contacts($query) {
        return $this->get('/contacts/search', ['q' => $query]);
    }

    /**
     * Get all contact types from the CRM.
     *
     * @return HCRM_API_Response
     */
    public function get_contact_types() {
        return $this->get('/contact-types');
    }

    /**
     * Create a contact type in the CRM.
     *
     * @param array $data Contact type data.
     * @return HCRM_API_Response
     */
    public function create_contact_type($data) {
        return $this->post('/contact-types', $data);
    }

    // =========================================================================
    // LEAD METHODS
    // =========================================================================

    /**
     * Get all leads from the CRM.
     *
     * @param array $params Query parameters.
     * @return HCRM_API_Response
     */
    public function get_leads($params = []) {
        return $this->get('/leads', $params);
    }

    /**
     * Get a single lead from the CRM.
     *
     * @param string $uuid Lead UUID.
     * @return HCRM_API_Response
     */
    public function get_lead($uuid) {
        return $this->get("/leads/{$uuid}");
    }

    /**
     * Create a new lead in the CRM.
     *
     * @param array $data Lead data.
     * @return HCRM_API_Response
     */
    public function create_lead($data) {
        return $this->post('/leads', $data);
    }

    /**
     * Update an existing lead in the CRM.
     *
     * @param string $uuid Lead UUID.
     * @param array  $data Lead data.
     * @return HCRM_API_Response
     */
    public function update_lead($uuid, $data) {
        return $this->put("/leads/{$uuid}", $data);
    }

    /**
     * Delete a lead from the CRM.
     *
     * @param string $uuid Lead UUID.
     * @return HCRM_API_Response
     */
    public function delete_lead($uuid) {
        return $this->delete("/leads/{$uuid}");
    }

    /**
     * Set the request timeout.
     *
     * @param int $seconds Timeout in seconds.
     * @return self
     */
    public function set_timeout($seconds) {
        $this->timeout = max(1, (int) $seconds);
        return $this;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function get_base_url() {
        return $this->base_url;
    }

    /**
     * Check if the client is configured.
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->base_url) && $this->auth->is_valid_token();
    }

    /**
     * Log an API request (for debugging).
     *
     * @param string      $method   HTTP method.
     * @param string      $endpoint API endpoint.
     * @param array       $data     Request data.
     * @param int|null    $status   HTTP status code.
     * @param string|null $error    Error message if any.
     */
    private function log_request($method, $endpoint, $data, $status, $error = null) {
        // Log API calls - use HCRM_Logger for sync log file
        if ($error) {
            HCRM_Logger::error(sprintf(
                'API %s %s - Status: %s - Error: %s',
                $method,
                $endpoint,
                $status ?? 'N/A',
                $error
            ));
        } else {
            HCRM_Logger::api_call($method, $endpoint, $status ?? 0);
        }
    }

    /**
     * Check if URL is a local development domain.
     *
     * @param string $url The URL to check.
     * @return bool True if local domain.
     */
    private function is_local_domain($url) {
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (empty($host)) {
            return false;
        }

        // Check for common local development domains
        $local_patterns = [
            '/\.test$/',
            '/\.local$/',
            '/\.localhost$/',
            '/^localhost$/',
            '/^127\.0\.0\.1$/',
            '/^192\.168\./',
            '/^10\./',
            '/\.dev$/',
        ];

        foreach ($local_patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }
}
