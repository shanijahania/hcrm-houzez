<?php
/**
 * API Authentication handler class.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_API_Auth
 *
 * Handles authentication for the Laravel CRM API using Sanctum tokens.
 *
 * @since 1.0.0
 */
class HCRM_API_Auth {

    /**
     * The API token.
     *
     * @var string
     */
    private $token;

    /**
     * Constructor.
     *
     * @param string $token The API token.
     */
    public function __construct($token = '') {
        $this->token = $token;
    }

    /**
     * Get authorization headers for API requests.
     *
     * @return array
     */
    public function get_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Check if the token is valid (basic format validation).
     *
     * @return bool
     */
    public function is_valid_token() {
        if (empty($this->token)) {
            return false;
        }

        // Sanctum tokens typically have format: id|token
        // But we accept any non-empty string
        return strlen($this->token) >= 10;
    }

    /**
     * Get the current token.
     *
     * @return string
     */
    public function get_token() {
        return $this->token;
    }

    /**
     * Set a new token.
     *
     * @param string $token The new token.
     */
    public function set_token($token) {
        $this->token = $token;
    }

    /**
     * Store token - no encryption, just return as-is.
     *
     * @param string $token The token.
     * @return string The token.
     */
    public static function encrypt_token($token) {
        return $token;
    }

    /**
     * Get token - no decryption, just return as-is.
     *
     * @param string $token The token.
     * @return string The token.
     */
    public static function decrypt_token($token) {
        return $token;
    }

    /**
     * Get stored API token from options.
     *
     * @return string
     */
    public static function get_stored_token() {
        $settings = get_option('hcrm_api_settings', []);
        return $settings['api_token'] ?? '';
    }

    /**
     * Store API token in options (encrypted).
     *
     * @param string $token The token to store.
     * @return bool Whether the token was stored successfully.
     */
    public static function store_token($token) {
        $settings = get_option('hcrm_api_settings', []);
        $settings['api_token'] = self::encrypt_token($token);

        return update_option('hcrm_api_settings', $settings);
    }

    /**
     * Create an auth instance from stored settings.
     *
     * @return self
     */
    public static function from_settings() {
        return new self(self::get_stored_token());
    }
}
