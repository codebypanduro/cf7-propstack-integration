<?php

/**
 * Integration Handler for Contact Form 7 and Propstack
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CF7_Propstack_Integration_Handler
{

    private $api;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api = new CF7_Propstack_API();

        // Hook into Contact Form 7 submission
        add_action('wpcf7_mail_sent', array($this, 'handle_form_submission'));
        add_action('wpcf7_mail_failed', array($this, 'handle_form_submission'));

        // Add custom tag for enabling integration per form
        add_action('wpcf7_init', array($this, 'add_custom_tag'));

        // Add form editor integration (meta box)
        add_action('add_meta_boxes', array($this, 'register_propstack_meta_box'));
        add_action('save_post', array($this, 'save_form_editor_settings'));
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission($contact_form)
    {
        // Check if integration is enabled for this form
        if (!$this->is_integration_enabled($contact_form)) {
            return;
        }

        // Get form data
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $form_data = $submission->get_posted_data();
        $form_id = $contact_form->id();

        // Get field mappings for this form
        $mappings = $this->get_field_mappings($form_id);

        if (empty($mappings)) {
            $this->log_error('No field mappings found for form ID: ' . $form_id);
            return;
        }

        // Map form data to Propstack fields
        $contact_data = $this->map_form_data($form_data, $mappings);

        if (empty($contact_data)) {
            $this->log_error('No valid contact data mapped for form ID: ' . $form_id);
            return;
        }

        // Validate contact data
        $validation_errors = $this->api->validate_contact_data($contact_data);
        if (!empty($validation_errors)) {
            $this->log_error('Validation errors: ' . implode(', ', $validation_errors));
            return;
        }

        // Sanitize contact data
        $contact_data = $this->api->sanitize_contact_data($contact_data);

        // Check if contact already exists by email
        $existing_contact = null;
        if (!empty($contact_data['email'])) {
            $existing_contact = $this->api->get_contact_by_email($contact_data['email']);
        }

        // Create or update contact
        if ($existing_contact) {
            $result = $this->api->update_contact($existing_contact['id'], $contact_data);
            if ($result) {
                $this->log_success('Contact updated in Propstack: ' . $existing_contact['id']);
            }
        } else {
            $result = $this->api->create_contact($contact_data);
            if ($result) {
                $this->log_success('Contact created in Propstack: ' . $result);
            }
        }
    }

    /**
     * Check if integration is enabled for a form
     */
    private function is_integration_enabled($contact_form)
    {
        $form_content = $contact_form->prop('form');

        // Check for the custom tag
        if (strpos($form_content, '[propstack_enable]') !== false) {
            return true;
        }

        // Check for form-specific setting
        $form_settings = get_post_meta($contact_form->id(), '_cf7_propstack_enabled', true);
        return !empty($form_settings);
    }

    /**
     * Get field mappings for a specific form
     */
    private function get_field_mappings($form_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';

        $mappings = $wpdb->get_results(
            $wpdb->prepare("SELECT cf7_field, propstack_field FROM $table_name WHERE form_id = %s", $form_id),
            ARRAY_A
        );

        $mapped_fields = array();
        foreach ($mappings as $mapping) {
            $mapped_fields[$mapping['cf7_field']] = $mapping['propstack_field'];
        }

        return $mapped_fields;
    }

    /**
     * Map form data to Propstack fields
     */
    private function map_form_data($form_data, $mappings)
    {
        $contact_data = array();

        foreach ($mappings as $cf7_field => $propstack_field) {
            if (isset($form_data[$cf7_field]) && !empty($form_data[$cf7_field])) {
                $value = $form_data[$cf7_field];

                // Handle array values (like checkboxes)
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                // Handle special field transformations
                $value = $this->transform_field_value($propstack_field, $value);

                $contact_data[$propstack_field] = $value;
            }
        }

        return $contact_data;
    }

    /**
     * Transform field values based on Propstack requirements
     */
    private function transform_field_value($propstack_field, $value)
    {
        switch ($propstack_field) {
            case 'salutation':
                // Convert common salutation values to Propstack format
                $value = strtolower(trim($value));
                if (in_array($value, array('mr', 'herr', 'male'))) {
                    return 'mr';
                } elseif (in_array($value, array('ms', 'frau', 'female'))) {
                    return 'ms';
                }
                break;

            case 'language':
                // Convert language values to Propstack format
                $value = strtolower(trim($value));
                if (in_array($value, array('de', 'deutsch', 'german'))) {
                    return 'de';
                } elseif (in_array($value, array('en', 'english'))) {
                    return 'en';
                } elseif (in_array($value, array('es', 'spanish'))) {
                    return 'es';
                }
                break;

            case 'newsletter':
            case 'accept_contact':
                // Convert checkbox/radio values to boolean
                $value = strtolower(trim($value));
                return in_array($value, array('yes', 'true', '1', 'on', 'checked'));

            case 'client_source_id':
            case 'client_status_id':
                // Ensure numeric values
                return is_numeric($value) ? intval($value) : null;
        }

        return $value;
    }

    /**
     * Add custom tag for enabling integration
     */
    public function add_custom_tag()
    {
        wpcf7_add_form_tag(
            array('propstack_enable', 'propstack_enable*'),
            array($this, 'custom_tag_handler'),
            array('name-attr' => true)
        );
    }

    /**
     * Custom tag handler
     */
    public function custom_tag_handler($tag)
    {
        // This tag is just for enabling the integration, no output needed
        return '';
    }

    /**
     * Register the Propstack Integration meta box on CF7 forms
     */
    public function register_propstack_meta_box()
    {
        add_meta_box(
            'cf7-propstack-meta-box',
            __('Propstack Integration', 'cf7-propstack-integration'),
            array($this, 'add_form_editor_meta_box'),
            'wpcf7_contact_form',
            'side',
            'default'
        );
    }

    /**
     * Output the meta box content
     */
    public function add_form_editor_meta_box($post)
    {
        $enabled = get_post_meta($post->ID, '_cf7_propstack_enabled', true);
?>
        <div class="cf7-propstack-meta-box">
            <p>
                <label>
                    <input type="checkbox" name="cf7_propstack_enabled" value="1" <?php checked($enabled); ?> />
                    <?php _e('Enable Propstack integration for this form', 'cf7-propstack-integration'); ?>
                </label>
            </p>
            <p class="description">
                <?php _e('When enabled, form submissions will be automatically sent to Propstack CRM.', 'cf7-propstack-integration'); ?>
            </p>
        </div>
<?php
    }

    /**
     * Save form editor settings
     */
    public function save_form_editor_settings($post_id)
    {
        if (get_post_type($post_id) !== 'wpcf7_contact_form') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['cf7_propstack_enabled']) ? '1' : '';
        update_post_meta($post_id, '_cf7_propstack_enabled', $enabled);
    }

    /**
     * Log error messages
     */
    private function log_error($message)
    {
        $options = get_option('cf7_propstack_options');
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

        if ($debug_mode) {
            error_log('[CF7 Propstack] ERROR: ' . $message);
        }
    }

    /**
     * Log success messages
     */
    private function log_success($message)
    {
        $options = get_option('cf7_propstack_options');
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

        if ($debug_mode) {
            error_log('[CF7 Propstack] SUCCESS: ' . $message);
        }
    }
}
