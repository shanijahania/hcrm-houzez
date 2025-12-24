<?php
/**
 * Lead Sync class for handling lead form submissions.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Sync_Lead
 *
 * Handles the synchronization of form leads between WordPress and CRM.
 *
 * @since 1.0.0
 */
class HCRM_Sync_Lead {

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
     * Available form hooks.
     *
     * @var array
     */
    private $form_hooks = [
        'houzez_ele_inquiry_form'       => 'Elementor Inquiry Form',
        'houzez_ele_contact_form'       => 'Elementor Contact Form',
        'houzez_contact_realtor'        => 'Agent/Agency Contact Form',
        'houzez_schedule_send_message'  => 'Schedule Tour Form',
        'houzez_property_agent_contact' => 'Property Contact Form',
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
     * Register hooks for form submissions.
     *
     * Note: Houzez forms use `houzez_record_activities` hook (not individual form hooks).
     * The form hooks in $form_hooks are AJAX action names used for filtering, not do_action hooks.
     */
    public function register_hooks() {
        // Hook into the universal Houzez activity hook that fires for all forms
        add_action('houzez_record_activities', [$this, 'process_activity'], 10, 1);
    }

    /**
     * Process activity from Houzez houzez_record_activities hook.
     *
     * This is the main entry point for lead capture from Houzez forms.
     * The hook passes activity_args with type, name, email, phone, message.
     * Property ID must be retrieved from $_POST.
     *
     * @param array $activity_args Activity data from Houzez.
     * @return array|void Result array or void if skipped.
     */
    public function process_activity($activity_args) {
        // Debug: Log that we received the hook
        HCRM_Logger::debug('[HCRM Lead] process_activity called', ['activity_args' => $activity_args]);

        // Only process lead-type activities (lead, lead_contact, lead_agent)
        $valid_types = ['lead', 'lead_contact', 'lead_agent'];
        if (empty($activity_args['type']) || !in_array($activity_args['type'], $valid_types, true)) {
            HCRM_Logger::debug('[HCRM Lead] Skipped: not a valid lead type activity', ['type' => $activity_args['type'] ?? 'empty']);
            return;
        }

        // Check if lead sync is enabled
        if (!HCRM_Settings::get('sync_leads', false)) {
            HCRM_Logger::debug('[HCRM Lead] Skipped: lead sync is disabled in settings');
            return;
        }

        // Detect which form triggered this (from AJAX action)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified by Houzez before this hook fires
        $ajax_action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        HCRM_Logger::debug('[HCRM Lead] AJAX action: ' . $ajax_action);

        // Check if this specific form is enabled (if we can detect the form type)
        if (!empty($ajax_action) && array_key_exists($ajax_action, $this->form_hooks)) {
            if (!HCRM_Settings::is_lead_hook_enabled($ajax_action)) {
                HCRM_Logger::debug('[HCRM Lead] Skipped: form hook disabled', ['ajax_action' => $ajax_action]);
                return; // This form type is disabled in settings
            }
        }

        // Get property_id from $_POST (not included in activity_args)
        // Check listing_id FIRST (always set on property pages), then fall back to property_id
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
        $property_id = isset($_POST['listing_id']) ? absint(wp_unslash($_POST['listing_id'])) : null;
        if (!$property_id) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $property_id = isset($_POST['property_id']) ? absint(wp_unslash($_POST['property_id'])) : null;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
        $hcrm_listing_id = isset($_POST['listing_id']) ? sanitize_text_field(wp_unslash($_POST['listing_id'])) : 'unset';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
        $hcrm_post_property_id = isset($_POST['property_id']) ? sanitize_text_field(wp_unslash($_POST['property_id'])) : 'unset';
        HCRM_Logger::debug('[HCRM Lead] Property ID from POST', ['property_id' => $property_id ?: 'none', 'listing_id' => $hcrm_listing_id, 'post_property_id' => $hcrm_post_property_id]);

        // Prepare lead data from activity args
        $lead_data = $this->prepare_lead_data_from_activity($activity_args, $ajax_action, $property_id);
        HCRM_Logger::debug('[HCRM Lead] Prepared lead data', ['lead_data' => $lead_data]);

        // Use background queue if enabled
        if (HCRM_Settings::get('use_background_queue', true)) {
            HCRM_Logger::debug('[HCRM Lead] Queuing lead for background processing');
            return $this->queue_lead($lead_data);
        }

        // Process immediately
        HCRM_Logger::debug('[HCRM Lead] Processing lead immediately');
        return $this->sync_lead($lead_data);
    }

    /**
     * Prepare lead data from activity args.
     *
     * @param array  $activity_args Activity data from Houzez.
     * @param string $ajax_action   The AJAX action that triggered this.
     * @param int|null $property_id Property ID from $_POST.
     * @return array Normalized lead data.
     */
    private function prepare_lead_data_from_activity($activity_args, $ajax_action, $property_id) {
        $name = $activity_args['name'] ?? '';
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        // If no last name provided, use a placeholder (CRM requires last_name)
        if (empty($last_name)) {
            $last_name = '-';
        }

        $data = [
            'name'          => $name,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email'         => sanitize_email($activity_args['email'] ?? ''),
            'phone'         => sanitize_text_field($activity_args['phone'] ?? ''),
            'message'       => sanitize_textarea_field($activity_args['message'] ?? ''),
            'property_id'   => $property_id,
            'hook_name'     => $ajax_action,
            'source'        => 'website',
            'source_detail' => $this->form_hooks[$ajax_action] ?? ($ajax_action ?: 'Houzez Form'),
            'submitted_at'  => current_time('mysql'),
        ];

        // Capture schedule tour data if available
        if ($ajax_action === 'houzez_schedule_send_message') {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['schedule_date'] = isset($_POST['schedule_date']) ? sanitize_text_field(wp_unslash($_POST['schedule_date'])) : null;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['schedule_time'] = isset($_POST['schedule_time']) ? sanitize_text_field(wp_unslash($_POST['schedule_time'])) : null;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['schedule_tour_type'] = isset($_POST['schedule_tour_type']) ? sanitize_text_field(wp_unslash($_POST['schedule_tour_type'])) : null;
        }

        // Capture agent/agency contact data if available
        if ($ajax_action === 'houzez_contact_realtor') {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['agent_id'] = isset($_POST['agent_id']) ? absint(wp_unslash($_POST['agent_id'])) : null;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['agent_type'] = isset($_POST['agent_type']) ? sanitize_text_field(wp_unslash($_POST['agent_type'])) : null;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['agent_email'] = isset($_POST['target_email']) ? sanitize_email(wp_unslash($_POST['target_email'])) : null;

            // Get agent name for title
            if (!empty($data['agent_id'])) {
                $agent_post = get_post($data['agent_id']);
                if ($agent_post) {
                    $data['agent_name'] = $agent_post->post_title;
                }
            }
        }

        // Capture inquiry form data if available
        if ($ajax_action === 'houzez_ele_inquiry_form') {
            // enquiry_type is a direct POST field (note: spelled "enquiry" not "inquiry")
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['enquiry_type'] = isset($_POST['enquiry_type']) ? sanitize_text_field(wp_unslash($_POST['enquiry_type'])) : null;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $data['user_type'] = isset($_POST['user_type']) ? sanitize_text_field(wp_unslash($_POST['user_type'])) : null;

            // Property search fields are nested under e_meta array
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Houzez before this hook fires
            $e_meta = isset($_POST['e_meta']) ? map_deep(wp_unslash($_POST['e_meta']), 'sanitize_text_field') : [];
            HCRM_Logger::debug('[HCRM Lead] Raw e_meta from POST', ['e_meta' => $e_meta]);
            if (!empty($e_meta)) {
                $data['property_type'] = isset($e_meta['property_type']) ? sanitize_text_field($e_meta['property_type']) : null;
                $data['city'] = isset($e_meta['city']) ? sanitize_text_field($e_meta['city']) : null;
                $data['area'] = isset($e_meta['area']) ? sanitize_text_field($e_meta['area']) : null;
                $data['zip_code'] = isset($e_meta['zipcode']) ? sanitize_text_field($e_meta['zipcode']) : null;
                $data['min_price'] = isset($e_meta['price']) ? floatval($e_meta['price']) : null;
                $data['max_price'] = isset($e_meta['max-price']) ? floatval($e_meta['max-price']) : null;
                $data['min_size'] = isset($e_meta['area-size']) ? sanitize_text_field($e_meta['area-size']) : null;
                $data['beds'] = isset($e_meta['beds']) ? sanitize_text_field($e_meta['beds']) : null;
                $data['baths'] = isset($e_meta['baths']) ? sanitize_text_field($e_meta['baths']) : null;
            }
        }

        return $data;
    }

    /**
     * Process a form submission (legacy method, kept for backwards compatibility).
     *
     * @param array $form_data Form data from the hook.
     * @return array Result with 'success' and 'message' keys.
     * @deprecated Use process_activity() instead.
     */
    public function process_form_submission($form_data) {
        if (!HCRM_Settings::get('sync_leads', false)) {
            return ['success' => false, 'message' => 'Lead sync is disabled'];
        }

        // Determine current hook
        $current_hook = current_filter();

        // Check if this specific hook is enabled
        if (!HCRM_Settings::is_lead_hook_enabled($current_hook)) {
            return ['success' => false, 'message' => 'This form hook is disabled'];
        }

        // Prepare lead data
        $lead_data = $this->prepare_lead_data($form_data, $current_hook);

        // Use background queue if enabled
        if (HCRM_Settings::get('use_background_queue', true)) {
            return $this->queue_lead($lead_data);
        }

        // Process immediately
        return $this->sync_lead($lead_data);
    }

    /**
     * Queue a lead for background processing.
     *
     * @param array $lead_data Lead data.
     * @return array Result array.
     */
    public function queue_lead($lead_data) {
        // Schedule with Action Scheduler if available
        if (function_exists('as_schedule_single_action')) {
            $action_id = as_schedule_single_action(
                time(),
                'hcrm_process_lead',
                [$lead_data],
                'hcrm-houzez'
            );

            return [
                'success'   => true,
                'message'   => 'Lead queued for processing',
                'action_id' => $action_id,
            ];
        }

        // Fallback to WP cron
        wp_schedule_single_event(time(), 'hcrm_process_lead', [$lead_data]);

        return [
            'success' => true,
            'message' => 'Lead scheduled for processing',
        ];
    }

    /**
     * Sync a lead to CRM.
     *
     * Sends lead and contact data in a single API request.
     * CRM handles contact creation/lookup within a DB transaction for atomic operations.
     *
     * @param array $lead_data Lead data.
     * @return array Result with 'success' and 'message' keys.
     */
    public function sync_lead($lead_data) {
        HCRM_Logger::debug('[HCRM Lead] sync_lead() called', ['lead_data' => $lead_data]);

        // Validate required email
        if (empty($lead_data['email'])) {
            HCRM_Logger::debug('[HCRM Lead] Error: Email is required');
            return ['success' => false, 'message' => 'Email is required for lead'];
        }

        // Generate lead title based on context
        $lead_title = '';
        if (!empty($lead_data['agent_name'])) {
            // Agent/Agency contact form - use appropriate prefix based on type
            $prefix = 'Agent';
            if (!empty($lead_data['agent_type']) && $lead_data['agent_type'] === 'agency_info') {
                $prefix = 'Agency';
            }
            $lead_title = $prefix . ': ' . $lead_data['agent_name'];
        } elseif (!empty($lead_data['property_id'])) {
            $property = get_post($lead_data['property_id']);
            if ($property) {
                // Use "Tour:" for schedule tour forms, "Property:" for others
                if ($lead_data['hook_name'] === 'houzez_schedule_send_message') {
                    $lead_title = 'Tour: ' . $property->post_title;
                } else {
                    $lead_title = 'Property: ' . $property->post_title;
                }
            }
        } elseif (!empty($lead_data['source_detail'])) {
            // For forms without property - use appropriate prefix based on form type
            $prefix = 'Inquiry';
            if ($lead_data['hook_name'] === 'houzez_ele_contact_form') {
                $prefix = 'Contact';
            }
            $lead_title = $prefix . ': ' . $lead_data['source_detail'];
        }

        // Prepare combined lead + contact data for single API request
        // CRM will create/find contact in same transaction as lead
        $api_data = [
            // Contact data (CRM will find or create contact by email)
            'email'         => $lead_data['email'],
            'first_name'    => $lead_data['first_name'] ?? '',
            'last_name'     => $lead_data['last_name'] ?? '-',
            'phone'         => $lead_data['phone'] ?? '',
            'contact_type'  => 'Buyer',

            // Lead data
            'title'         => $lead_title,
            'source'        => $lead_data['source'] ?? 'website',
            'source_detail' => $lead_data['source_detail'] ?? $lead_data['hook_name'] ?? '',
            'notes'         => $lead_data['message'] ?? '',
        ];

        // Add property link if available
        if (!empty($lead_data['property_id'])) {
            $property_uuid = $this->mapper->get_crm_uuid($lead_data['property_id'], 'property');
            if ($property_uuid) {
                $api_data['listing_uuid'] = $property_uuid;
            } else {
                // Fallback: send property slug for CRM to find and attach
                $property = get_post($lead_data['property_id']);
                if ($property && $property->post_status === 'publish') {
                    $api_data['listing_slug'] = $property->post_name;
                }
            }
        }

        // Add schedule tour details to meta (for scoring)
        $meta = [];
        if (!empty($lead_data['schedule_date'])) {
            $meta['schedule_date'] = $lead_data['schedule_date'];
        }
        if (!empty($lead_data['schedule_time'])) {
            $meta['schedule_time'] = $lead_data['schedule_time'];
        }
        if (!empty($lead_data['schedule_tour_type'])) {
            $meta['schedule_tour_type'] = $lead_data['schedule_tour_type'];
        }

        // Add inquiry form details to meta (for scoring)
        if (!empty($lead_data['enquiry_type'])) {
            $meta['enquiry_type'] = $lead_data['enquiry_type'];
        }
        if (!empty($lead_data['user_type'])) {
            $meta['user_type'] = $lead_data['user_type'];
        }
        if (!empty($lead_data['city'])) {
            $meta['city'] = $lead_data['city'];
        }
        if (!empty($lead_data['area'])) {
            $meta['area'] = $lead_data['area'];
        }
        if (!empty($lead_data['zip_code'])) {
            $meta['zip_code'] = $lead_data['zip_code'];
        }
        if (!empty($lead_data['property_type'])) {
            $meta['property_type'] = $lead_data['property_type'];
        }
        if (!empty($lead_data['min_size'])) {
            $meta['min_size'] = $lead_data['min_size'];
        }
        if (!empty($lead_data['beds'])) {
            $meta['beds'] = $lead_data['beds'];
        }
        if (!empty($lead_data['baths'])) {
            $meta['baths'] = $lead_data['baths'];
        }

        if (!empty($meta)) {
            $api_data['meta'] = $meta;
        }

        // Add budget from inquiry form
        if (!empty($lead_data['min_price'])) {
            $api_data['budget_min'] = $lead_data['min_price'];
        }
        if (!empty($lead_data['max_price'])) {
            $api_data['budget_max'] = $lead_data['max_price'];
        }

        // Add agent email for assignment (CRM will match by email)
        if (!empty($lead_data['agent_email'])) {
            $api_data['assignee_email'] = $lead_data['agent_email'];
        }

        HCRM_Logger::debug('[HCRM Lead] API data to send (combined lead+contact)', ['api_data' => $api_data]);

        // Create lead in CRM (contact created/found in same transaction)
        $response = $this->api_client->create_lead($api_data);
        HCRM_Logger::debug('[HCRM Lead] API response', [
            'success' => $response->is_success() ? 'yes' : 'no',
            'data' => $response->get_data(),
            'error' => $response->get_error_message(),
            'status' => $response->get_status_code(),
        ]);

        if ($response->is_success()) {
            $result_data = $response->get_data();
            $lead_uuid = $result_data['uuid'] ?? null;
            $contact_uuid = $result_data['contact']['uuid'] ?? null;

            return [
                'success'      => true,
                'message'      => 'Lead created in CRM',
                'lead_uuid'    => $lead_uuid,
                'contact_uuid' => $contact_uuid,
            ];
        }

        return [
            'success' => false,
            'message' => $response->get_error_message() ?: 'Failed to create lead',
        ];
    }

    /**
     * Ensure a contact exists in CRM for the lead.
     *
     * @param array $lead_data Lead data.
     * @return array Result with 'success', 'uuid' keys.
     */
    private function ensure_contact($lead_data) {
        $email = $lead_data['email'] ?? '';

        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required for contact'];
        }

        // Check if contact already exists (search by email)
        $search_response = $this->api_client->search_contacts($email);

        if ($search_response->is_success()) {
            $contacts = $search_response->get_data();
            if (!empty($contacts) && is_array($contacts)) {
                // Find exact email match
                foreach ($contacts as $contact) {
                    if (isset($contact['email']) && strtolower($contact['email']) === strtolower($email)) {
                        return [
                            'success' => true,
                            'uuid'    => $contact['uuid'],
                            'message' => 'Existing contact found',
                        ];
                    }
                }
            }
        }

        // Create new contact
        $contact_data = $this->prepare_contact_data($lead_data);
        $response = $this->api_client->create_contact($contact_data);

        if ($response->is_success()) {
            $result_data = $response->get_data();
            return [
                'success' => true,
                'uuid'    => $result_data['uuid'] ?? null,
                'message' => 'Contact created',
            ];
        }

        return [
            'success' => false,
            'message' => $response->get_error_message() ?: 'Failed to create contact',
        ];
    }

    /**
     * Prepare lead data from form submission.
     *
     * @param array  $form_data Form data.
     * @param string $hook_name Hook name.
     * @return array Normalized lead data.
     */
    public function prepare_lead_data($form_data, $hook_name) {
        // Normalize field names (Houzez uses various naming conventions)
        $name = $form_data['name'] ?? $form_data['your_name'] ?? $form_data['sender_name'] ?? '';
        $email = $form_data['email'] ?? $form_data['your_email'] ?? $form_data['sender_email'] ?? '';
        $phone = $form_data['phone'] ?? $form_data['your_phone'] ?? $form_data['sender_phone'] ?? '';
        $message = $form_data['message'] ?? $form_data['your_message'] ?? $form_data['inquiry_message'] ?? '';
        $property_id = $form_data['property_id'] ?? $form_data['listing_id'] ?? null;

        // Parse name into first and last
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        // CRM requires last_name - use placeholder if empty
        if (empty($last_name)) {
            $last_name = '-';
        }

        return [
            'name'          => $name,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email'         => sanitize_email($email),
            'phone'         => sanitize_text_field($phone),
            'message'       => sanitize_textarea_field($message),
            'property_id'   => $property_id ? absint($property_id) : null,
            'hook_name'     => $hook_name,
            'source'        => 'website',
            'source_detail' => $this->form_hooks[$hook_name] ?? $hook_name,
            'submitted_at'  => current_time('mysql'),
        ];
    }

    /**
     * Prepare contact data for API.
     *
     * @param array $lead_data Lead data.
     * @return array Contact data for API.
     */
    private function prepare_contact_data($lead_data) {
        $first_name = $lead_data['first_name'] ?? '';
        $last_name = $lead_data['last_name'] ?? '';

        // CRM requires last_name - use placeholder if empty
        if (empty($last_name)) {
            $last_name = '-';
        }

        $data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $lead_data['email'],
        ];

        if (!empty($lead_data['phone'])) {
            $data['phone'] = $lead_data['phone'];
        }

        // Set contact type to "Buyer" as default for leads
        $data['type'] = 'Buyer';

        return $data;
    }

    /**
     * Ensure required contact types exist in CRM.
     *
     * @return array Result array.
     */
    public function ensure_contact_types() {
        $required_types = ['Buyer', 'Seller', 'Owner'];
        $created = [];
        $errors = [];

        // Get existing types
        $response = $this->api_client->get_contact_types();
        $existing_types = [];

        if ($response->is_success()) {
            $types = $response->get_data();
            if (is_array($types)) {
                foreach ($types as $type) {
                    $existing_types[] = $type['name'] ?? '';
                }
            }
        }

        // Create missing types
        foreach ($required_types as $type_name) {
            if (!in_array($type_name, $existing_types, true)) {
                $create_response = $this->api_client->create_contact_type([
                    'name' => $type_name,
                ]);

                if ($create_response->is_success()) {
                    $created[] = $type_name;
                } else {
                    $errors[] = $type_name . ': ' . $create_response->get_error_message();
                }
            }
        }

        return [
            'success' => empty($errors),
            'created' => $created,
            'errors'  => $errors,
        ];
    }

    /**
     * Get available form hooks.
     *
     * @return array Form hooks array.
     */
    public function get_form_hooks() {
        return $this->form_hooks;
    }

    /**
     * Get sync statistics.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
        $leads_synced = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = 'lead'"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
        $contacts_created = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = 'contact'"
        );

        return [
            'leads_synced'     => (int) $leads_synced,
            'contacts_created' => (int) $contacts_created,
        ];
    }
}
