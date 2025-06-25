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

        // Register custom property for CF7 forms
        add_filter('wpcf7_pre_construct_contact_form_properties', array($this, 'register_propstack_property'), 10, 2);

        // Add panel to CF7 editor
        add_filter('wpcf7_editor_panels', array($this, 'add_propstack_panel'), 10, 1);

        // Save form settings
        add_action('wpcf7_save_contact_form', array($this, 'save_propstack_settings'), 10, 1);

        // Add admin styles and scripts
        add_action('admin_head', array($this, 'add_admin_styles'));
        add_action('admin_footer', array($this, 'add_admin_scripts'));

        // Fallback script loading
        add_action('wp_footer', array($this, 'add_admin_scripts'));
        add_action('admin_footer', array($this, 'add_admin_scripts'));

        // Add AJAX handlers for field mapping operations
        add_action('wp_ajax_save_field_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_delete_field_mapping_by_fields', array($this, 'delete_field_mapping_by_fields'));
    }

    /**
     * Register Propstack property for CF7 forms
     */
    public function register_propstack_property($properties, $contact_form)
    {
        $properties += array(
            'propstack' => array(),
        );

        return $properties;
    }

    /**
     * Add Propstack panel to CF7 editor
     */
    public function add_propstack_panel($panels)
    {
        $contact_form = WPCF7_ContactForm::get_current();

        $prop = wp_parse_args(
            $contact_form->prop('propstack'),
            array(
                'enabled' => false,
                'field_mappings' => array(),
            )
        );

        $editor_panel = function () use ($prop) {
            $this->render_propstack_panel($prop);
        };

        $panels += array(
            'propstack-panel' => array(
                'title' => __('Propstack', 'cf7-propstack-integration'),
                'callback' => $editor_panel,
            ),
        );

        return $panels;
    }

    /**
     * Render the Propstack panel content
     */
    private function render_propstack_panel($prop)
    {
        $contact_form = WPCF7_ContactForm::get_current();
        $form_id = $contact_form->id();

        // Get current field mappings for this form
        $current_mappings = $this->get_field_mappings($form_id);

        // Get available CF7 fields for this form
        $cf7_fields = $this->get_cf7_fields_for_form($form_id);

        // Get Propstack fields
        $propstack_fields = $this->get_propstack_fields();

        $description = sprintf(
            esc_html(
                /* translators: %s: link labeled 'Propstack integration' */
                __('You can set up the Propstack CRM integration here. When enabled, form submissions will be automatically sent to Propstack.', 'cf7-propstack-integration')
            )
        );
?>
        <h2><?php echo esc_html(__('Propstack Integration', 'cf7-propstack-integration')); ?></h2>

        <fieldset>
            <legend><?php echo $description; ?></legend>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html(__('Enable Integration', 'cf7-propstack-integration')); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php echo esc_html(__('Enable Integration', 'cf7-propstack-integration')); ?>
                                </legend>
                                <label for="wpcf7-propstack-enabled">
                                    <input type="checkbox"
                                        name="wpcf7-propstack[enabled]"
                                        id="wpcf7-propstack-enabled"
                                        value="1"
                                        <?php checked($prop['enabled']); ?> />
                                    <?php echo esc_html(__('Enable Propstack integration for this form', 'cf7-propstack-integration')); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>

        <!-- Field Mappings Management -->
        <div class="cf7-propstack-mappings-section">
            <h3><?php echo esc_html(__('Field Mappings', 'cf7-propstack-integration')); ?></h3>

            <!-- Add New Mapping Form -->
            <div class="mapping-form">
                <h4><?php echo esc_html(__('Add New Mapping', 'cf7-propstack-integration')); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html(__('CF7 Field', 'cf7-propstack-integration')); ?></th>
                        <td>
                            <select id="cf7_field_select" class="cf7-field-select">
                                <option value=""><?php echo esc_html(__('Select a field', 'cf7-propstack-integration')); ?></option>
                                <?php foreach ($cf7_fields as $field => $label): ?>
                                    <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html(__('Propstack Field', 'cf7-propstack-integration')); ?></th>
                        <td>
                            <select id="propstack_field_select" class="propstack-field-select">
                                <option value=""><?php echo esc_html(__('Select a field', 'cf7-propstack-integration')); ?></option>
                                <?php foreach ($propstack_fields as $field => $label): ?>
                                    <option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" id="add_mapping" class="button button-primary" data-form-id="<?php echo esc_attr($form_id); ?>">
                        <?php echo esc_html(__('Add Mapping', 'cf7-propstack-integration')); ?>
                    </button>
                </p>
            </div>

            <!-- Current Mappings Table -->
            <div class="mappings-list">
                <h4><?php echo esc_html(__('Current Mappings', 'cf7-propstack-integration')); ?></h4>
                <?php if (empty($current_mappings)): ?>
                    <p><?php echo esc_html(__('No field mappings configured for this form.', 'cf7-propstack-integration')); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html(__('CF7 Field', 'cf7-propstack-integration')); ?></th>
                                <th><?php echo esc_html(__('Propstack Field', 'cf7-propstack-integration')); ?></th>
                                <th><?php echo esc_html(__('Actions', 'cf7-propstack-integration')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_mappings as $cf7_field => $propstack_field): ?>
                                <tr>
                                    <td><?php echo esc_html($cf7_fields[$cf7_field] ?? $cf7_field); ?></td>
                                    <td><?php echo esc_html($propstack_fields[$propstack_field] ?? $propstack_field); ?></td>
                                    <td>
                                        <button type="button" class="button button-small delete-mapping"
                                            data-form-id="<?php echo esc_attr($form_id); ?>"
                                            data-cf7-field="<?php echo esc_attr($cf7_field); ?>"
                                            data-propstack-field="<?php echo esc_attr($propstack_field); ?>">
                                            <?php echo esc_html(__('Delete', 'cf7-propstack-integration')); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hidden form for AJAX nonce -->
        <input type="hidden" id="cf7_propstack_nonce" value="<?php echo wp_create_nonce('cf7_propstack_nonce'); ?>" />
        <input type="hidden" id="cf7_propstack_ajax_url" value="<?php echo admin_url('admin-ajax.php'); ?>" />

        <!-- Inline JavaScript for field mapping functionality -->
        <script type="text/javascript">
            console.log("CF7 Propstack: Panel script loaded");

            jQuery(document).ready(function($) {
                console.log("CF7 Propstack: Panel jQuery ready");

                // Function to update CF7 field dropdown to disable already mapped fields
                function updateCF7FieldDropdown() {
                    var mappedFields = [];
                    $(".mappings-list tbody tr").each(function() {
                        var cf7Field = $(this).find("td:first").text().trim();
                        // Get the actual field name from the display text
                        var fieldName = getFieldNameFromDisplayText(cf7Field);
                        if (fieldName) {
                            mappedFields.push(fieldName);
                        }
                    });

                    $("#cf7_field_select option").each(function() {
                        var optionValue = $(this).val();
                        if (mappedFields.indexOf(optionValue) !== -1) {
                            $(this).prop("disabled", true);
                        } else {
                            $(this).prop("disabled", false);
                        }
                    });
                }

                // Function to get field name from display text
                function getFieldNameFromDisplayText(displayText) {
                    // This is a simple mapping - you might need to adjust based on your field labels
                    var fieldName = displayText.toLowerCase().replace(/\s+/g, '-');
                    return fieldName;
                }

                // Function to get display text for CF7 field
                function getCF7FieldDisplayText(fieldName) {
                    var option = $("#cf7_field_select option[value=\"" + fieldName + "\"]");
                    return option.length ? option.text() : fieldName;
                }

                // Function to get display text for Propstack field
                function getPropstackFieldDisplayText(fieldName) {
                    var option = $("#propstack_field_select option[value=\"" + fieldName + "\"]");
                    return option.length ? option.text() : fieldName;
                }

                // Function to add new mapping row to table
                function addMappingRow(cf7Field, propstackField) {
                    var cf7DisplayText = getCF7FieldDisplayText(cf7Field);
                    var propstackDisplayText = getPropstackFieldDisplayText(propstackField);
                    var formId = $("#add_mapping").data("form-id");

                    var newRow = $("<tr>").html(
                        "<td>" + cf7DisplayText + "</td>" +
                        "<td>" + propstackDisplayText + "</td>" +
                        "<td>" +
                        "<button type=\"button\" class=\"button button-small delete-mapping\" " +
                        "data-form-id=\"" + formId + "\" " +
                        "data-cf7-field=\"" + cf7Field + "\" " +
                        "data-propstack-field=\"" + propstackField + "\">" +
                        "<?php echo esc_js(__('Delete', 'cf7-propstack-integration')); ?>" +
                        "</button>" +
                        "</td>"
                    );

                    $(".mappings-list tbody").append(newRow);

                    // Clear the form
                    $("#cf7_field_select").val("");
                    $("#propstack_field_select").val("");

                    // Update dropdown to disable newly mapped field
                    updateCF7FieldDropdown();
                }

                // Initialize dropdown state
                updateCF7FieldDropdown();

                // Test if elements exist
                console.log("CF7 Propstack: Add mapping button exists:", $("#add_mapping").length);
                console.log("CF7 Propstack: Delete mapping buttons exist:", $(".delete-mapping").length);

                // Handle add mapping button
                $(document).on("click", "#add_mapping", function(e) {
                    console.log("CF7 Propstack: Add mapping button clicked");
                    e.preventDefault();

                    var formId = $(this).data("form-id");
                    var cf7Field = $("#cf7_field_select").val();
                    var propstackField = $("#propstack_field_select").val();
                    var nonce = $("#cf7_propstack_nonce").val();
                    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

                    console.log("CF7 Propstack: Form data - formId:", formId, "cf7Field:", cf7Field, "propstackField:", propstackField);

                    if (!cf7Field || !propstackField) {
                        alert("<?php echo esc_js(__('Please select both CF7 and Propstack fields.', 'cf7-propstack-integration')); ?>");
                        return;
                    }

                    var button = $(this);
                    var originalText = button.text();
                    button.text("<?php echo esc_js(__('Adding...', 'cf7-propstack-integration')); ?>").prop("disabled", true);

                    console.log("CF7 Propstack: Sending AJAX request");

                    $.ajax({
                        url: ajaxUrl,
                        type: "POST",
                        data: {
                            action: "save_field_mapping",
                            form_id: formId,
                            cf7_field: cf7Field,
                            propstack_field: propstackField,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log("CF7 Propstack: AJAX success response:", response);
                            if (response.success) {
                                // Add the new mapping row dynamically instead of reloading
                                addMappingRow(cf7Field, propstackField);

                                // Show success message
                                showMessage("<?php echo esc_js(__('Mapping added successfully!', 'cf7-propstack-integration')); ?>", "success");
                            } else {
                                alert(response.data || "<?php echo esc_js(__('Failed to save mapping.', 'cf7-propstack-integration')); ?>");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("CF7 Propstack: AJAX error:", status, error);
                            alert("<?php echo esc_js(__('Error saving mapping. Please try again.', 'cf7-propstack-integration')); ?>");
                        },
                        complete: function() {
                            button.text(originalText).prop("disabled", false);
                        }
                    });
                });

                // Handle delete mapping buttons
                $(document).on("click", ".delete-mapping", function(e) {
                    console.log("CF7 Propstack: Delete mapping button clicked");
                    e.preventDefault();

                    if (!confirm("<?php echo esc_js(__('Are you sure you want to delete this mapping?', 'cf7-propstack-integration')); ?>")) {
                        return;
                    }

                    var formId = $(this).data("form-id");
                    var cf7Field = $(this).data("cf7-field");
                    var propstackField = $(this).data("propstack-field");
                    var nonce = $("#cf7_propstack_nonce").val();
                    var ajaxUrl = $("#cf7_propstack_ajax_url").val();
                    var button = $(this);

                    console.log("CF7 Propstack: Delete data - formId:", formId, "cf7Field:", cf7Field, "propstackField:", propstackField);

                    button.prop("disabled", true).text("<?php echo esc_js(__('Deleting...', 'cf7-propstack-integration')); ?>");

                    $.ajax({
                        url: ajaxUrl,
                        type: "POST",
                        data: {
                            action: "delete_field_mapping_by_fields",
                            form_id: formId,
                            cf7_field: cf7Field,
                            propstack_field: propstackField,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log("CF7 Propstack: Delete AJAX success response:", response);
                            if (response.success) {
                                button.closest("tr").fadeOut(function() {
                                    $(this).remove();
                                    if ($(".mappings-list tbody tr").length === 0) {
                                        $(".mappings-list").html("<p><?php echo esc_js(__('No field mappings configured for this form.', 'cf7-propstack-integration')); ?></p>");
                                    }
                                    // Update dropdown to re-enable deleted field
                                    updateCF7FieldDropdown();
                                });

                                // Show success message
                                showMessage("<?php echo esc_js(__('Mapping deleted successfully!', 'cf7-propstack-integration')); ?>", "success");
                            } else {
                                alert(response.data || "<?php echo esc_js(__('Failed to delete mapping.', 'cf7-propstack-integration')); ?>");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("CF7 Propstack: Delete AJAX error:", status, error);
                            alert("<?php echo esc_js(__('Error deleting mapping. Please try again.', 'cf7-propstack-integration')); ?>");
                        },
                        complete: function() {
                            button.prop("disabled", false).text("<?php echo esc_js(__('Delete', 'cf7-propstack-integration')); ?>");
                        }
                    });
                });

                // Function to show success/error messages
                function showMessage(message, type) {
                    var messageClass = type === 'success' ? 'success' : 'error';
                    var messageHtml = '<div class="cf7-propstack-message ' + messageClass + '">' + message + '</div>';
                    $(".cf7-propstack-mappings-section").prepend(messageHtml);

                    setTimeout(function() {
                        $(".cf7-propstack-message").fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }

                console.log("CF7 Propstack: Panel event handlers set up successfully");
            });
        </script>
    <?php
    }

    /**
     * Get CF7 fields for a specific form
     */
    private function get_cf7_fields_for_form($form_id)
    {
        $forms = WPCF7_ContactForm::find($form_id);
        $form = is_array($forms) ? reset($forms) : $forms;

        if (!$form || !is_object($form)) {
            return array();
        }

        $form_content = $form->prop('form');
        return $this->extract_form_fields($form_content);
    }

    /**
     * Extract form fields from CF7 form content
     */
    private function extract_form_fields($form_content)
    {
        $fields = array();

        // Match CF7 form tags like [text* first-name "First Name"]
        preg_match_all('/\[([^\]]+)\]/', $form_content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $tag) {
                $parts = explode(' ', trim($tag));
                if (count($parts) >= 2) {
                    $field_type = $parts[0];
                    $field_name = $parts[1];

                    // Skip non-field tags
                    if (in_array($field_type, array('submit', 'propstack_enable'))) {
                        continue;
                    }

                    // Get field label
                    $label = $this->get_field_label($field_name, $field_type, $tag);
                    $fields[$field_name] = $label;
                }
            }
        }

        return $fields;
    }

    /**
     * Get field label from CF7 tag
     */
    private function get_field_label($field_name, $field_type, $full_tag)
    {
        // Try to extract label from quotes
        if (preg_match('/"([^"]+)"/', $full_tag, $matches)) {
            return $matches[1];
        }

        // Fallback to field name with formatting
        return ucwords(str_replace(array('-', '_'), ' ', $field_name));
    }

    /**
     * Get Propstack fields
     */
    private function get_propstack_fields()
    {
        return array(
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
    }

    /**
     * Save Propstack settings when form is saved
     */
    public function save_propstack_settings($contact_form)
    {
        $prop = isset($_POST['wpcf7-propstack'])
            ? (array) $_POST['wpcf7-propstack']
            : array();

        $prop = wp_parse_args(
            $prop,
            array(
                'enabled' => false,
                'field_mappings' => array(),
            )
        );

        $contact_form->set_properties(array(
            'propstack' => $prop,
        ));
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
        // Check for the custom tag in form content
        $form_content = $contact_form->prop('form');
        if (strpos($form_content, '[propstack_enable]') !== false) {
            return true;
        }

        // Check for form-specific setting
        $propstack_prop = $contact_form->prop('propstack');
        return !empty($propstack_prop['enabled']);
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

    /**
     * Add admin styles
     */
    public function add_admin_styles()
    {
        global $post;

        // Only add styles on Contact Form 7 edit pages
        if (!$post || $post->post_type !== 'wpcf7_contact_form') {
            return;
        }
    ?>
        <style>
            .cf7-propstack-panel {
                background-color: #f0f8ff;
                border: 1px solid #0073aa;
                border-radius: 4px;
                padding: 15px;
                margin: 10px 0;
            }

            .cf7-propstack-panel h2 {
                margin-top: 0;
                color: #0073aa;
            }

            .cf7-propstack-panel .form-table th {
                width: 200px;
            }

            .cf7-propstack-panel .description {
                font-size: 0.9em;
                color: #666;
                margin: 5px 0;
            }

            /* Field Mappings Section */
            .cf7-propstack-mappings-section {
                margin-top: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 20px;
            }

            .cf7-propstack-mappings-section h3 {
                margin-top: 0;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                color: #23282d;
                font-size: 1.2em;
            }

            .cf7-propstack-mappings-section h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #23282d;
                font-size: 1.1em;
            }

            /* Mapping Form */
            .mapping-form {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 30px;
            }

            .mapping-form .form-table th {
                width: 150px;
                padding: 15px 10px 15px 0;
                vertical-align: top;
                font-weight: 600;
            }

            .mapping-form .form-table td {
                padding: 15px 10px;
                vertical-align: top;
            }

            .mapping-form select {
                min-width: 250px;
                max-width: 400px;
            }

            .mapping-form .button-primary {
                margin-top: 10px;
            }

            /* Mappings List */
            .mappings-list h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #23282d;
            }

            .mappings-list table {
                border-collapse: collapse;
                width: 100%;
            }

            .mappings-list th {
                background: #f1f1f1;
                padding: 12px;
                text-align: left;
                font-weight: 600;
                border-bottom: 2px solid #ddd;
            }

            .mappings-list td {
                padding: 12px;
                border-bottom: 1px solid #eee;
                vertical-align: middle;
            }

            .mappings-list tr:hover {
                background-color: #f9f9f9;
            }

            .mappings-list .button-small {
                padding: 4px 8px;
                font-size: 11px;
                line-height: 1.4;
            }

            /* Loading states */
            .loading {
                opacity: 0.6;
                pointer-events: none;
            }

            /* Success/Error messages */
            .cf7-propstack-message {
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
                border-left: 4px solid;
            }

            .cf7-propstack-message.success {
                background-color: #d4edda;
                border-color: #28a745;
                color: #155724;
            }

            .cf7-propstack-message.error {
                background-color: #f8d7da;
                border-color: #dc3545;
                color: #721c24;
            }
        </style>
    <?php
    }

    /**
     * Add admin scripts
     */
    public function add_admin_scripts()
    {
        global $post;

        // Debug: Check if we're on the right page
        error_log('CF7 Propstack: add_admin_scripts called. Post type: ' . ($post ? $post->post_type : 'no post'));

        // Only add scripts on Contact Form 7 edit pages
        if (!$post || $post->post_type !== 'wpcf7_contact_form') {
            error_log('CF7 Propstack: Not on CF7 edit page, skipping script');
            return;
        }

        error_log('CF7 Propstack: Adding scripts for CF7 edit page');

        // Add a simple test output first
        echo "<!-- CF7 Propstack: Script method called -->\n";
    ?>
        <script type="text/javascript">
            console.log("CF7 Propstack: Script loaded successfully");

            jQuery(document).ready(function($) {
                console.log("CF7 Propstack: jQuery ready, setting up event handlers");

                // Test if elements exist
                console.log("CF7 Propstack: Add mapping button exists:", $("#add_mapping").length);
                console.log("CF7 Propstack: Delete mapping buttons exist:", $(".delete-mapping").length);

                // Handle add mapping button
                $(document).on("click", "#add_mapping", function(e) {
                    console.log("CF7 Propstack: Add mapping button clicked");
                    e.preventDefault();

                    var formId = $(this).data("form-id");
                    var cf7Field = $("#cf7_field_select").val();
                    var propstackField = $("#propstack_field_select").val();
                    var nonce = $("#cf7_propstack_nonce").val();
                    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

                    console.log("CF7 Propstack: Form data - formId:", formId, "cf7Field:", cf7Field, "propstackField:", propstackField);

                    if (!cf7Field || !propstackField) {
                        alert("<?php echo esc_js(__('Please select both CF7 and Propstack fields.', 'cf7-propstack-integration')); ?>");
                        return;
                    }

                    var button = $(this);
                    var originalText = button.text();
                    button.text("<?php echo esc_js(__('Adding...', 'cf7-propstack-integration')); ?>").prop("disabled", true);

                    console.log("CF7 Propstack: Sending AJAX request");

                    $.ajax({
                        url: ajaxUrl,
                        type: "POST",
                        data: {
                            action: "save_field_mapping",
                            form_id: formId,
                            cf7_field: cf7Field,
                            propstack_field: propstackField,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log("CF7 Propstack: AJAX success response:", response);
                            if (response.success) {
                                // Reload the page to show updated mappings
                                location.reload();
                            } else {
                                alert(response.data || "<?php echo esc_js(__('Failed to save mapping.', 'cf7-propstack-integration')); ?>");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("CF7 Propstack: AJAX error:", status, error);
                            alert("<?php echo esc_js(__('Error saving mapping. Please try again.', 'cf7-propstack-integration')); ?>");
                        },
                        complete: function() {
                            button.text(originalText).prop("disabled", false);
                        }
                    });
                });

                // Handle delete mapping buttons
                $(document).on("click", ".delete-mapping", function(e) {
                    console.log("CF7 Propstack: Delete mapping button clicked");
                    e.preventDefault();

                    if (!confirm("<?php echo esc_js(__('Are you sure you want to delete this mapping?', 'cf7-propstack-integration')); ?>")) {
                        return;
                    }

                    var formId = $(this).data("form-id");
                    var cf7Field = $(this).data("cf7-field");
                    var propstackField = $(this).data("propstack-field");
                    var nonce = $("#cf7_propstack_nonce").val();
                    var ajaxUrl = $("#cf7_propstack_ajax_url").val();
                    var button = $(this);

                    console.log("CF7 Propstack: Delete data - formId:", formId, "cf7Field:", cf7Field, "propstackField:", propstackField);

                    button.prop("disabled", true).text("<?php echo esc_js(__('Deleting...', 'cf7-propstack-integration')); ?>");

                    $.ajax({
                        url: ajaxUrl,
                        type: "POST",
                        data: {
                            action: "delete_field_mapping_by_fields",
                            form_id: formId,
                            cf7_field: cf7Field,
                            propstack_field: propstackField,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log("CF7 Propstack: Delete AJAX success response:", response);
                            if (response.success) {
                                button.closest("tr").fadeOut(function() {
                                    $(this).remove();
                                    if ($(".mappings-list tbody tr").length === 0) {
                                        $(".mappings-list").html("<p><?php echo esc_js(__('No field mappings configured for this form.', 'cf7-propstack-integration')); ?></p>");
                                    }
                                });
                            } else {
                                alert(response.data || "<?php echo esc_js(__('Failed to delete mapping.', 'cf7-propstack-integration')); ?>");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("CF7 Propstack: Delete AJAX error:", status, error);
                            alert("<?php echo esc_js(__('Error deleting mapping. Please try again.', 'cf7-propstack-integration')); ?>");
                        },
                        complete: function() {
                            button.prop("disabled", false).text("<?php echo esc_js(__('Delete', 'cf7-propstack-integration')); ?>");
                        }
                    });
                });

                console.log("CF7 Propstack: Event handlers set up successfully");
            });
        </script>
<?php
        error_log('CF7 Propstack: Scripts added successfully');
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
     * Delete field mapping by fields via AJAX
     */
    public function delete_field_mapping_by_fields()
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

        $result = $wpdb->delete(
            $table_name,
            array(
                'form_id' => $form_id,
                'cf7_field' => $cf7_field,
                'propstack_field' => $propstack_field,
            ),
            array('%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to delete mapping.', 'cf7-propstack-integration'));
        }

        wp_send_json_success(__('Mapping deleted successfully.', 'cf7-propstack-integration'));
    }
}
