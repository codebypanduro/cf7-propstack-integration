<?php

/**
 * Propstack API Client
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CF7_Propstack_API
{

    private $api_key;
    private $api_url;
    private $debug_mode;

    /**
     * Constructor
     */
    public function __construct()
    {
        $options = get_option('cf7_propstack_options');
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->api_url = isset($options['api_url']) ? $options['api_url'] : 'https://api.propstack.de/v1';
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
    }

    /**
     * Create a contact in Propstack
     */
    public function create_contact($contact_data)
    {
        if (empty($this->api_key)) {
            $this->log_error('API key not configured');
            return false;
        }

        $endpoint = $this->api_url . '/contacts';

        $request_data = array(
            'client' => $contact_data
        );

        $response = $this->make_request('POST', $endpoint, $request_data);

        if ($response && isset($response['ok']) && $response['ok']) {
            $this->log_success('Contact created successfully with ID: ' . $response['id']);
            return $response['id'];
        }

        $this->log_error('Failed to create contact: ' . json_encode($response));
        return false;
    }

    /**
     * Update a contact in Propstack
     */
    public function update_contact($contact_id, $contact_data)
    {
        if (empty($this->api_key)) {
            $this->log_error('API key not configured');
            return false;
        }

        $endpoint = $this->api_url . '/contacts/' . $contact_id;

        $request_data = array(
            'client' => $contact_data
        );

        $response = $this->make_request('PUT', $endpoint, $request_data);

        if ($response && isset($response['id'])) {
            $this->log_success('Contact updated successfully: ' . $contact_id);
            return true;
        }

        $this->log_error('Failed to update contact: ' . json_encode($response));
        return false;
    }

    /**
     * Get contact by email
     */
    public function get_contact_by_email($email)
    {
        if (empty($this->api_key)) {
            $this->log_error('API key not configured');
            return false;
        }

        $endpoint = $this->api_url . '/contacts?email=' . urlencode($email);

        $response = $this->make_request('GET', $endpoint);

        if ($response && is_array($response) && !empty($response)) {
            return $response[0]; // Return first matching contact
        }

        return false;
    }

    /**
     * Get contact sources
     */
    public function get_contact_sources()
    {
        if (empty($this->api_key)) {
            $this->log_error('API key not configured');
            return array();
        }

        $endpoint = $this->api_url . '/contact_sources';

        $response = $this->make_request('GET', $endpoint);

        if ($response && is_array($response)) {
            return $response;
        }

        return array();
    }

    /**
     * Make HTTP request to Propstack API
     */
    private function make_request($method, $endpoint, $data = null)
    {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($data && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
        }

        $this->log_request($method, $endpoint, $data);

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            $this->log_error('WP Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $this->log_response($response_code, $response_body);

        if ($response_code >= 200 && $response_code < 300) {
            return json_decode($response_body, true);
        }

        $this->log_error('API Error: HTTP ' . $response_code . ' - ' . $response_body);
        return false;
    }

    /**
     * Log request details
     */
    private function log_request($method, $endpoint, $data = null)
    {
        if (!$this->debug_mode) {
            return;
        }

        $log_entry = sprintf(
            '[CF7 Propstack] %s %s - Data: %s',
            $method,
            $endpoint,
            $data ? json_encode($data) : 'null'
        );

        error_log($log_entry);
    }

    /**
     * Log response details
     */
    private function log_response($code, $body)
    {
        if (!$this->debug_mode) {
            return;
        }

        $log_entry = sprintf(
            '[CF7 Propstack] Response: HTTP %d - %s',
            $code,
            $body
        );

        error_log($log_entry);
    }

    /**
     * Log error messages
     */
    private function log_error($message)
    {
        if (!$this->debug_mode) {
            return;
        }

        error_log('[CF7 Propstack] ERROR: ' . $message);
    }

    /**
     * Log success messages
     */
    private function log_success($message)
    {
        if (!$this->debug_mode) {
            return;
        }

        error_log('[CF7 Propstack] SUCCESS: ' . $message);
    }

    /**
     * Validate contact data
     */
    public function validate_contact_data($data)
    {
        $errors = array();

        // Check required fields
        if (empty($data['email'])) {
            $errors[] = __('Email is required', 'cf7-propstack-integration');
        }

        if (empty($data['first_name']) && empty($data['last_name'])) {
            $errors[] = __('First name or last name is required', 'cf7-propstack-integration');
        }

        // Validate email format
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = __('Invalid email format', 'cf7-propstack-integration');
        }

        // Validate salutation
        if (!empty($data['salutation']) && !in_array($data['salutation'], array('mr', 'ms'))) {
            $errors[] = __('Salutation must be either "mr" or "ms"', 'cf7-propstack-integration');
        }

        // Validate language
        if (!empty($data['language']) && !in_array($data['language'], array('de', 'en', 'es'))) {
            $errors[] = __('Language must be one of: de, en, es', 'cf7-propstack-integration');
        }

        return $errors;
    }

    /**
     * Sanitize contact data
     */
    public function sanitize_contact_data($data)
    {
        $sanitized = array();

        // Basic text fields
        $text_fields = array(
            'first_name',
            'last_name',
            'email',
            'academic_title',
            'company',
            'position',
            'home_phone',
            'home_cell',
            'office_phone',
            'office_cell',
            'description',
            'birth_name',
            'birth_place',
            'birth_country',
            'identity_number',
            'issuing_authority',
            'tax_identification_number'
        );

        foreach ($text_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Email field
        if (isset($data['email']) && !empty($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        // Select fields
        if (isset($data['salutation']) && in_array($data['salutation'], array('mr', 'ms'))) {
            $sanitized['salutation'] = $data['salutation'];
        }

        if (isset($data['language']) && in_array($data['language'], array('de', 'en', 'es'))) {
            $sanitized['language'] = $data['language'];
        }

        // Boolean fields
        if (isset($data['newsletter'])) {
            $sanitized['newsletter'] = (bool) $data['newsletter'];
        }

        if (isset($data['accept_contact'])) {
            $sanitized['accept_contact'] = (bool) $data['accept_contact'];
        }

        // Integer fields
        if (isset($data['client_source_id']) && is_numeric($data['client_source_id'])) {
            $sanitized['client_source_id'] = intval($data['client_source_id']);
        }

        if (isset($data['client_status_id']) && is_numeric($data['client_status_id'])) {
            $sanitized['client_status_id'] = intval($data['client_status_id']);
        }

        // Date fields
        if (isset($data['dob']) && !empty($data['dob'])) {
            $sanitized['dob'] = sanitize_text_field($data['dob']);
        }

        return $sanitized;
    }
}
