<?php

/**
 * Admin functionality for CF7 Propstack Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CF7_Propstack_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_save_field_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_delete_field_mapping', array($this, 'delete_field_mapping'));
        add_action('wp_ajax_get_cf7_fields', array($this, 'get_cf7_fields'));
        add_action('wp_ajax_refresh_custom_fields', array($this, 'refresh_custom_fields'));
        add_action('wp_ajax_get_field_mappings', array($this, 'get_field_mappings_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'wpcf7',
            __('Propstack Integration', 'cf7-propstack-integration'),
            __('Propstack', 'cf7-propstack-integration'),
            'manage_options',
            'cf7-propstack-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('cf7_propstack_options', 'cf7_propstack_options', array($this, 'sanitize_options'));

        add_settings_section(
            'cf7_propstack_general',
            __('General Settings', 'cf7-propstack-integration'),
            array($this, 'general_section_callback'),
            'cf7_propstack_settings'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'cf7-propstack-integration'),
            array($this, 'api_key_field_callback'),
            'cf7_propstack_settings',
            'cf7_propstack_general'
        );

        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'cf7-propstack-integration'),
            array($this, 'debug_mode_field_callback'),
            'cf7_propstack_settings',
            'cf7_propstack_general'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input)
    {
        $sanitized = array();

        $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? true : false;

        return $sanitized;
    }

    /**
     * General section callback
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Configure your Propstack API settings below.', 'cf7-propstack-integration') . '</p>';
    }

    /**
     * API key field callback
     */
    public function api_key_field_callback()
    {
        $options = get_option('cf7_propstack_options');
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
?>
        <input type="text" id="api_key" name="cf7_propstack_options[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
        <p class="description"><?php _e('Enter your Propstack API key.', 'cf7-propstack-integration'); ?></p>
    <?php
    }

    /**
     * Debug mode field callback
     */
    public function debug_mode_field_callback()
    {
        $options = get_option('cf7_propstack_options');
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
    ?>
        <input type="checkbox" id="debug_mode" name="cf7_propstack_options[debug_mode]" <?php checked($debug_mode); ?> />
        <label for="debug_mode"><?php _e('Enable debug mode for logging API requests', 'cf7-propstack-integration'); ?></label>
    <?php
    }

    /**
     * Settings page
     */
    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('cf7_propstack_messages', 'cf7_propstack_message', __('Settings Saved', 'cf7-propstack-integration'), 'updated');
        }

    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('cf7_propstack_messages'); ?>

            <div class="cf7-propstack-admin-container">
                <div class="cf7-propstack-settings-section">
                    <h2><?php _e('API Settings', 'cf7-propstack-integration'); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('cf7_propstack_options');
                        do_settings_sections('cf7_propstack_settings');
                        submit_button();
                        ?>
                    </form>
                </div>

                <div class="cf7-propstack-mappings-section">
                    <h2><?php _e('Field Mappings', 'cf7-propstack-integration'); ?></h2>
                    <?php $this->field_mappings_interface(); ?>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Field mappings interface
     */
    private function field_mappings_interface()
    {
        $forms = $this->get_cf7_forms();
        $mappings = $this->get_field_mappings();
        $propstack_fields = $this->get_propstack_fields();

    ?>
        <div class="cf7-propstack-mappings">
            <div class="mapping-form">
                <h3><?php _e('Add New Mapping', 'cf7-propstack-integration'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Contact Form 7', 'cf7-propstack-integration'); ?></th>
                        <td>
                            <select id="cf7_form_select">
                                <option value=""><?php _e('Select a form', 'cf7-propstack-integration'); ?></option>
                                <?php foreach ($forms as $form): ?>
                                    <option value="<?php echo esc_attr($form->id()); ?>"><?php echo esc_html($form->title()); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('CF7 Field', 'cf7-propstack-integration'); ?></th>
                        <td>
                            <select id="cf7_field_select" disabled>
                                <option value=""><?php _e('Select a form first', 'cf7-propstack-integration'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Propstack Field', 'cf7-propstack-integration'); ?></th>
                        <td>
                            <select id="propstack_field_select">
                                <option value=""><?php _e('Select a field', 'cf7-propstack-integration'); ?></option>
                                <?php foreach ($propstack_fields as $field => $label): ?>
                                    <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="refresh_custom_fields" class="button button-secondary" style="margin-left: 10px;">
                                <?php _e('Refresh Custom Fields', 'cf7-propstack-integration'); ?>
                            </button>
                            <p class="description"><?php _e('Click to refresh custom fields from Propstack API', 'cf7-propstack-integration'); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" id="add_mapping" class="button button-primary"><?php _e('Add Mapping', 'cf7-propstack-integration'); ?></button>
                </p>
            </div>

            <div class="mappings-list">
                <h3><?php _e('Current Mappings', 'cf7-propstack-integration'); ?></h3>
                <?php if (empty($mappings)): ?>
                    <p><?php _e('No field mappings configured yet.', 'cf7-propstack-integration'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Form', 'cf7-propstack-integration'); ?></th>
                                <th><?php _e('CF7 Field', 'cf7-propstack-integration'); ?></th>
                                <th><?php _e('Propstack Field', 'cf7-propstack-integration'); ?></th>
                                <th><?php _e('Actions', 'cf7-propstack-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappings as $mapping): ?>
                                <tr>
                                    <td><?php echo esc_html($this->get_form_title($mapping->form_id)); ?></td>
                                    <td><?php echo esc_html($mapping->cf7_field); ?></td>
                                    <td><?php echo esc_html($propstack_fields[$mapping->propstack_field] ?? $mapping->propstack_field); ?></td>
                                    <td>
                                        <button type="button" class="button button-small delete-mapping" data-id="<?php echo esc_attr($mapping->id); ?>">
                                            <?php _e('Delete', 'cf7-propstack-integration'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Get CF7 forms
     */
    private function get_cf7_forms()
    {
        if (!class_exists('WPCF7_ContactForm')) {
            return array();
        }

        return WPCF7_ContactForm::find();
    }

    /**
     * Get form title by ID
     */
    private function get_form_title($form_id)
    {
        $forms = WPCF7_ContactForm::find($form_id);
        $form = is_array($forms) ? reset($forms) : $forms;
        return ($form && is_object($form)) ? $form->title() : $form_id;
    }

    /**
     * Get field mappings
     */
    private function get_field_mappings()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY form_id, cf7_field");
    }

    /**
     * Get Propstack fields
     */
    private function get_propstack_fields()
    {
        // Standard Propstack fields
        $standard_fields = array(
            'first_name' => __('First Name', 'cf7-propstack-integration'),
            'last_name' => __('Last Name', 'cf7-propstack-integration'),
            'email' => __('Email', 'cf7-propstack-integration'),
            'salutation' => __('Salutation', 'cf7-propstack-integration'),
            'academic_title' => __('Academic Title', 'cf7-propstack-integration'),
            'company' => __('Company', 'cf7-propstack-integration'),
            'position' => __('Position', 'cf7-propstack-integration'),
            'home_phone' => __('Home Phone', 'cf7-propstack-integration'),
            'home_cell' => __('Home Cell', 'cf7-propstack-integration'),
            'office_phone' => __('Office Phone', 'cf7-propstack-integration'),
            'office_cell' => __('Office Cell', 'cf7-propstack-integration'),
            'description' => __('Description', 'cf7-propstack-integration'),
            'language' => __('Language', 'cf7-propstack-integration'),
            'newsletter' => __('Newsletter', 'cf7-propstack-integration'),
            'accept_contact' => __('Accept Contact', 'cf7-propstack-integration'),
            'client_source_id' => __('Client Source ID', 'cf7-propstack-integration'),
            'client_status_id' => __('Client Status ID', 'cf7-propstack-integration'),
        );

        // Get custom fields from cache or API
        $custom_fields = $this->get_cached_custom_fields();

        // Combine standard and custom fields
        return array_merge($standard_fields, $custom_fields);
    }

    /**
     * Get cached custom fields or fetch from API
     */
    private function get_cached_custom_fields()
    {
        // Check cache first
        $cached_fields = get_transient('cf7_propstack_custom_fields');
        if ($cached_fields !== false) {
            return $cached_fields;
        }

        // Fetch from API if cache is empty
        $custom_fields = array();
        if (class_exists('CF7_Propstack_API')) {
            try {
                $api = new CF7_Propstack_API();
                $api_custom_fields = $api->get_custom_fields();
                if (!empty($api_custom_fields) && is_array($api_custom_fields)) {
                    foreach ($api_custom_fields as $custom_field) {
                        if (isset($custom_field['name']) && isset($custom_field['label'])) {
                            $field_key = 'custom_' . $custom_field['name'];
                            $custom_fields[$field_key] = __('Custom: ', 'cf7-propstack-integration') . $custom_field['label'];
                        }
                    }
                }

                // If no custom fields found, add some sample ones for testing
                if (empty($custom_fields)) {
                    $custom_fields = $this->get_sample_custom_fields();
                }

                // Cache the results for 1 hour
                set_transient('cf7_propstack_custom_fields', $custom_fields, HOUR_IN_SECONDS);
            } catch (Exception $e) {
                // Log error silently in admin context
                error_log('[CF7 Propstack] Failed to fetch custom fields: ' . $e->getMessage());
                // Add sample fields as fallback
                $custom_fields = $this->get_sample_custom_fields();
                set_transient('cf7_propstack_custom_fields', $custom_fields, HOUR_IN_SECONDS);
            }
        } else {
            // Add sample fields as fallback
            $custom_fields = $this->get_sample_custom_fields();
            set_transient('cf7_propstack_custom_fields', $custom_fields, HOUR_IN_SECONDS);
        }

        return $custom_fields;
    }

    /**
     * Get sample custom fields for testing
     */
    private function get_sample_custom_fields()
    {
        return array(
            'custom_interests' => __('Custom: Interests', 'cf7-propstack-integration'),
            'custom_budget' => __('Custom: Budget Range', 'cf7-propstack-integration'),
            'custom_property_type' => __('Custom: Property Type', 'cf7-propstack-integration'),
            'custom_location' => __('Custom: Preferred Location', 'cf7-propstack-integration'),
            'custom_move_date' => __('Custom: Move Date', 'cf7-propstack-integration'),
            'custom_source' => __('Custom: Lead Source', 'cf7-propstack-integration'),
        );
    }

    /**
     * Save field mapping via AJAX
     */
    public function save_field_mapping()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        $form_id = sanitize_text_field($_POST['form_id']);
        $cf7_field = sanitize_text_field($_POST['cf7_field']);
        $propstack_field = sanitize_text_field($_POST['propstack_field']);

        if (empty($form_id) || empty($cf7_field) || empty($propstack_field)) {
            wp_send_json_error(__('All fields are required.', 'cf7-propstack-integration'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';

        $result = $wpdb->replace(
            $table_name,
            array(
                'form_id' => $form_id,
                'cf7_field' => $cf7_field,
                'propstack_field' => $propstack_field,
            ),
            array('%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to save mapping.', 'cf7-propstack-integration'));
        }

        wp_send_json_success(__('Mapping saved successfully.', 'cf7-propstack-integration'));
    }

    /**
     * Delete field mapping via AJAX
     */
    public function delete_field_mapping()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        $mapping_id = intval($_POST['mapping_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';

        $result = $wpdb->delete($table_name, array('id' => $mapping_id), array('%d'));

        if ($result === false) {
            wp_send_json_error(__('Failed to delete mapping.', 'cf7-propstack-integration'));
        }

        wp_send_json_success(__('Mapping deleted successfully.', 'cf7-propstack-integration'));
    }

    /**
     * Get CF7 fields via AJAX
     */
    public function get_cf7_fields()
    {
        // Basic debugging to see if this method is being called
        error_log('[CF7 Propstack Admin] get_cf7_fields method called');
        error_log('[CF7 Propstack Admin] POST data: ' . json_encode($_POST));

        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        $form_id = sanitize_text_field($_POST['form_id']);

        // Debug logging
        error_log('[CF7 Propstack Admin] AJAX get_cf7_fields called with form_id: ' . $form_id);
        error_log('[CF7 Propstack Admin] Form ID type: ' . gettype($form_id));
        error_log('[CF7 Propstack Admin] Form ID value: "' . $form_id . '"');

        if (empty($form_id)) {
            error_log('[CF7 Propstack Admin] Form ID is empty');
            wp_send_json_error(__('Form ID is required.', 'cf7-propstack-integration'));
        }

        // Try to get the form directly by ID using get_post
        $form_post = get_post($form_id);
        if (!$form_post || $form_post->post_type !== 'wpcf7_contact_form') {
            error_log('[CF7 Propstack Admin] Form post not found for ID: ' . $form_id);
            wp_send_json_error(__('Form not found.', 'cf7-propstack-integration'));
        }

        // Create the form object from the post
        $form = WPCF7_ContactForm::get_instance($form_id);
        if (!$form) {
            error_log('[CF7 Propstack Admin] Could not create form instance for ID: ' . $form_id);
            wp_send_json_error(__('Form not found.', 'cf7-propstack-integration'));
        }

        error_log('[CF7 Propstack Admin] Found form: ' . ($form ? 'yes' : 'no'));

        if (!$form || !is_object($form)) {
            error_log('[CF7 Propstack Admin] Form not found for ID: ' . $form_id);
            wp_send_json_error(__('Form not found.', 'cf7-propstack-integration'));
        }

        // Log form details for debugging
        $form_title = $form->title();
        $form_id_actual = $form->id();
        error_log('[CF7 Propstack Admin] Form details - ID: ' . $form_id_actual . ', Title: ' . $form_title);
        error_log('[CF7 Propstack Admin] Requested ID vs Actual ID: ' . $form_id . ' vs ' . $form_id_actual);

        $form_content = $form->prop('form');
        error_log('[CF7 Propstack Admin] Form content length: ' . strlen($form_content));
        error_log('[CF7 Propstack Admin] Form content preview: ' . substr($form_content, 0, 200));

        $fields = $this->extract_form_fields($form_content);
        error_log('[CF7 Propstack Admin] Extracted fields: ' . json_encode($fields));

        // Remove already-mapped fields for this form
        global $wpdb;
        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';
        $mapped = $wpdb->get_col($wpdb->prepare(
            "SELECT cf7_field FROM $table_name WHERE form_id = %s",
            $form_id
        ));

        error_log('[CF7 Propstack Admin] Already mapped fields: ' . json_encode($mapped));

        if (!empty($mapped)) {
            foreach ($mapped as $mapped_field) {
                unset($fields[$mapped_field]);
            }
        }

        if (empty($fields)) {
            error_log('[CF7 Propstack Admin] No fields found after processing');
            wp_send_json_error(__('No fields found in this form.', 'cf7-propstack-integration'));
        }

        error_log('[CF7 Propstack Admin] Returning fields: ' . json_encode($fields));
        wp_send_json_success($fields);
    }

    /**
     * Refresh custom fields cache via AJAX
     */
    public function refresh_custom_fields()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        // Delete the existing cache
        delete_transient('cf7_propstack_custom_fields');

        // Fetch fresh custom fields
        $custom_fields = array();
        if (class_exists('CF7_Propstack_API')) {
            try {
                $api = new CF7_Propstack_API();
                $api_custom_fields = $api->get_custom_fields();
                if (!empty($api_custom_fields) && is_array($api_custom_fields)) {
                    foreach ($api_custom_fields as $custom_field) {
                        if (isset($custom_field['name']) && isset($custom_field['label'])) {
                            $field_key = 'custom_' . $custom_field['name'];
                            $custom_fields[$field_key] = __('Custom: ', 'cf7-propstack-integration') . $custom_field['label'];
                        }
                    }
                }

                // Cache the results for 1 hour
                set_transient('cf7_propstack_custom_fields', $custom_fields, HOUR_IN_SECONDS);

                wp_send_json_success(array(
                    'message' => __('Custom fields refreshed successfully.', 'cf7-propstack-integration'),
                    'fields' => $custom_fields
                ));
            } catch (Exception $e) {
                wp_send_json_error(__('Failed to refresh custom fields: ', 'cf7-propstack-integration') . $e->getMessage());
            }
        } else {
            wp_send_json_error(__('Propstack API class not available.', 'cf7-propstack-integration'));
        }
    }

    /**
     * Extract form fields from CF7 form content
     */
    private function extract_form_fields($form_content)
    {
        $fields = array();

        error_log('[CF7 Propstack Admin] Extracting fields from form content');

        // Match CF7 form tags
        preg_match_all('/\[([^\]]+)\]/', $form_content, $matches);

        error_log('[CF7 Propstack Admin] Found ' . count($matches[1]) . ' form tags');

        if (!empty($matches[1])) {
            foreach ($matches[1] as $tag) {
                error_log('[CF7 Propstack Admin] Processing tag: ' . $tag);

                // Split the tag by spaces, the second part is usually the field name
                $tag_parts = preg_split('/\s+/', trim($tag));
                if (count($tag_parts) > 1) {
                    $field_type = $tag_parts[0];
                    $field_name = $tag_parts[1];
                    // Remove asterisk for required fields
                    $field_type = rtrim($field_type, '*');

                    // Skip non-field tags and layout tags
                    $skip_tags = array(
                        'submit',
                        'propstack_enable',
                        'uacf7-row',
                        'uacf7-col',
                        '/uacf7-row',
                        '/uacf7-col',
                        'row',
                        'col',
                        '/row',
                        '/col',
                        'div',
                        '/div',
                        'span',
                        '/span'
                    );

                    if (in_array($field_type, $skip_tags)) {
                        error_log('[CF7 Propstack Admin] Skipping non-field tag: ' . $field_type);
                        continue;
                    }

                    // Skip closing tags (tags that start with /)
                    if (strpos($field_type, '/') === 0) {
                        error_log('[CF7 Propstack Admin] Skipping closing tag: ' . $field_type);
                        continue;
                    }

                    // Only include actual form input field types
                    $valid_field_types = array(
                        'text',
                        'email',
                        'tel',
                        'textarea',
                        'select',
                        'checkbox',
                        'radio',
                        'number',
                        'date',
                        'file',
                        'url',
                        'password',
                        'search',
                        'range'
                    );

                    if (in_array($field_type, $valid_field_types)) {
                        $label = $this->get_field_label($field_name, $field_type);
                        $fields[$field_name] = $label;
                        error_log('[CF7 Propstack Admin] Added field: ' . $field_name . ' => ' . $label);
                    } else {
                        error_log('[CF7 Propstack Admin] Skipping invalid field type: ' . $field_type);
                    }
                } else {
                    error_log('[CF7 Propstack Admin] Invalid tag format: ' . $tag);
                }
            }
        }

        error_log('[CF7 Propstack Admin] Final fields array: ' . json_encode($fields));
        return $fields;
    }

    /**
     * Get human-readable field label
     */
    private function get_field_label($field_name, $field_type)
    {
        // Convert field name to label
        $label = str_replace('_', ' ', $field_name);
        $label = ucwords($label);

        // Add field type indicator
        switch ($field_type) {
            case 'text':
                $label .= ' (Text)';
                break;
            case 'email':
                $label .= ' (Email)';
                break;
            case 'tel':
                $label .= ' (Phone)';
                break;
            case 'textarea':
                $label .= ' (Textarea)';
                break;
            case 'select':
                $label .= ' (Select)';
                break;
            case 'checkbox':
                $label .= ' (Checkbox)';
                break;
            case 'radio':
                $label .= ' (Radio)';
                break;
            case 'number':
                $label .= ' (Number)';
                break;
            case 'date':
                $label .= ' (Date)';
                break;
            case 'file':
                $label .= ' (File)';
                break;
            default:
                $label .= ' (' . ucfirst($field_type) . ')';
        }

        return $label;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'contact_page_cf7-propstack-settings') {
            return;
        }

        wp_enqueue_script(
            'cf7-propstack-admin',
            CF7_PROPSTACK_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CF7_PROPSTACK_VERSION,
            true
        );

        wp_localize_script('cf7-propstack-admin', 'cf7PropstackAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cf7_propstack_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this mapping?', 'cf7-propstack-integration'),
                'selectFormFirst' => __('Select a form first', 'cf7-propstack-integration'),
                'selectField' => __('Select a field', 'cf7-propstack-integration'),
                'noFieldsFound' => __('No fields found', 'cf7-propstack-integration'),
                'errorLoadingFields' => __('Error loading fields', 'cf7-propstack-integration'),
                'allFieldsRequired' => __('All fields are required.', 'cf7-propstack-integration'),
                'errorSavingMapping' => __('Error saving mapping.', 'cf7-propstack-integration'),
                'errorDeletingMapping' => __('Error deleting mapping.', 'cf7-propstack-integration'),
                'noMappingsConfigured' => __('No field mappings configured yet.', 'cf7-propstack-integration'),
                'loading' => __('Loading...', 'cf7-propstack-integration'),
                'saving' => __('Saving...', 'cf7-propstack-integration'),
                'pleaseFillAllFields' => __('Please fill in all required fields.', 'cf7-propstack-integration'),
                'helpTitle' => __('How to use field mappings:', 'cf7-propstack-integration'),
                'helpText' => __('Select a Contact Form 7 form, choose the form field you want to map, and select the corresponding Propstack field. This will automatically send form data to Propstack when the form is submitted.', 'cf7-propstack-integration'),
                'refreshing' => __('Refreshing...', 'cf7-propstack-integration'),
                'errorRefreshingFields' => __('Error refreshing custom fields', 'cf7-propstack-integration'),
            )
        ));

        wp_enqueue_style(
            'cf7-propstack-admin',
            CF7_PROPSTACK_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CF7_PROPSTACK_VERSION
        );
    }

    /**
     * AJAX: Get field mappings table HTML for a form
     */
    public function get_field_mappings_ajax()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }
        $form_id = sanitize_text_field($_POST['form_id']);
        $mappings = array_filter($this->get_field_mappings(), function ($m) use ($form_id) {
            return $m->form_id == $form_id;
        });
        $propstack_fields = $this->get_propstack_fields();
        ob_start();
    ?>
        <h3><?php _e('Current Mappings', 'cf7-propstack-integration'); ?></h3>
        <?php if (empty($mappings)): ?>
            <p><?php _e('No field mappings configured yet.', 'cf7-propstack-integration'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Form', 'cf7-propstack-integration'); ?></th>
                        <th><?php _e('CF7 Field', 'cf7-propstack-integration'); ?></th>
                        <th><?php _e('Propstack Field', 'cf7-propstack-integration'); ?></th>
                        <th><?php _e('Actions', 'cf7-propstack-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $mapping): ?>
                        <tr>
                            <td><?php echo esc_html($this->get_form_title($mapping->form_id)); ?></td>
                            <td><?php echo esc_html($mapping->cf7_field); ?></td>
                            <td><?php echo esc_html($propstack_fields[$mapping->propstack_field] ?? $mapping->propstack_field); ?></td>
                            <td>
                                <button type="button" class="button button-small delete-mapping" data-id="<?php echo esc_attr($mapping->id); ?>">
                                    <?php _e('Delete', 'cf7-propstack-integration'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
<?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}
