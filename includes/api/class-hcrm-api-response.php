<?php
/**
 * API Response handler class.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_API_Response
 *
 * Wraps API responses for consistent handling throughout the plugin.
 *
 * @since 1.0.0
 */
class HCRM_API_Response {

    /**
     * Whether the request was successful.
     *
     * @var bool
     */
    private $success;

    /**
     * Response message.
     *
     * @var string
     */
    private $message;

    /**
     * Response data.
     *
     * @var array
     */
    private $data;

    /**
     * Validation errors.
     *
     * @var array
     */
    private $errors;

    /**
     * HTTP status code.
     *
     * @var int
     */
    private $status_code;

    /**
     * Raw response body.
     *
     * @var array
     */
    private $raw;

    /**
     * Constructor.
     *
     * @param array|WP_Error $response    The response from wp_remote_request.
     * @param int            $status_code HTTP status code.
     */
    public function __construct($response, $status_code = 0) {
        if (is_wp_error($response)) {
            $this->success = false;
            $this->message = $response->get_error_message();
            $this->data = [];
            $this->errors = [];
            $this->status_code = 0;
            $this->raw = [];
            return;
        }

        $this->status_code = $status_code;
        $this->raw = $response;

        // Parse response
        if (isset($response['success'])) {
            $this->success = (bool) $response['success'];
        } else {
            $this->success = $status_code >= 200 && $status_code < 300;
        }

        $this->message = $response['message'] ?? '';
        $this->data = $response['data'] ?? [];
        $this->errors = $response['errors'] ?? [];
    }

    /**
     * Create a response from a WP_Error.
     *
     * @param WP_Error $error The WordPress error object.
     * @return self
     */
    public static function from_error($error) {
        return new self($error, 0);
    }

    /**
     * Create a successful response.
     *
     * @param array  $data    Response data.
     * @param string $message Success message.
     * @return self
     */
    public static function success($data = [], $message = '') {
        return new self([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], 200);
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message.
     * @param array  $errors  Validation errors.
     * @param int    $code    HTTP status code.
     * @return self
     */
    public static function error($message, $errors = [], $code = 400) {
        return new self([
            'success' => false,
            'message' => $message,
            'data'    => [],
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Check if the request was successful.
     *
     * @return bool
     */
    public function is_success() {
        return $this->success;
    }

    /**
     * Get the response message.
     *
     * @return string
     */
    public function get_message() {
        return $this->message;
    }

    /**
     * Get a formatted error message including validation errors.
     *
     * @return string
     */
    public function get_error_message() {
        if ($this->success) {
            return '';
        }

        $message = $this->message ?: 'Unknown error';

        // Append validation errors if present (422 response)
        if (!empty($this->errors)) {
            $error_details = [];
            foreach ($this->errors as $field => $messages) {
                if (is_array($messages)) {
                    $error_details[] = $field . ': ' . implode(', ', $messages);
                } else {
                    $error_details[] = $field . ': ' . $messages;
                }
            }
            if (!empty($error_details)) {
                $message .= ' [' . implode('; ', $error_details) . ']';
            }
        }

        return $message;
    }

    /**
     * Get the response data.
     *
     * @return array
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get a specific data value.
     *
     * @param string $key     The key to retrieve.
     * @param mixed  $default Default value if key doesn't exist.
     * @return mixed
     */
    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get the UUID from the response data.
     *
     * @return string|null
     */
    public function get_uuid() {
        return $this->data['uuid'] ?? null;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function get_status_code() {
        return $this->status_code;
    }

    /**
     * Get the raw response.
     *
     * @return array
     */
    public function get_raw() {
        return $this->raw;
    }

    /**
     * Check if the response indicates an authentication error.
     *
     * @return bool
     */
    public function is_auth_error() {
        return in_array($this->status_code, [401, 403], true);
    }

    /**
     * Check if the response indicates a validation error.
     *
     * @return bool
     */
    public function is_validation_error() {
        return $this->status_code === 422;
    }

    /**
     * Check if the response indicates a not found error.
     *
     * @return bool
     */
    public function is_not_found() {
        return $this->status_code === 404;
    }

    /**
     * Check if the response indicates a rate limit error.
     *
     * @return bool
     */
    public function is_rate_limited() {
        return $this->status_code === 429;
    }

    /**
     * Check if the response indicates a server error.
     *
     * @return bool
     */
    public function is_server_error() {
        return $this->status_code >= 500;
    }

    /**
     * Convert response to array.
     *
     * @return array
     */
    public function to_array() {
        return [
            'success'     => $this->success,
            'message'     => $this->message,
            'data'        => $this->data,
            'errors'      => $this->errors,
            'status_code' => $this->status_code,
        ];
    }

    /**
     * Convert response to JSON string.
     *
     * @return string
     */
    public function to_json() {
        return wp_json_encode($this->to_array());
    }
}
