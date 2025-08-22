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

        // Add AJAX handlers for refreshing projects and client sources
        add_action('wp_ajax_refresh_projects', array($this, 'refresh_projects'));
        add_action('wp_ajax_refresh_client_sources', array($this, 'refresh_client_sources'));

        add_action('wp_ajax_clear_propstack_cache', array($this, 'clear_propstack_cache'));

        // Instead of admin_enqueue_scripts, use wpcf7_admin_footer to inject panel.js for the CF7 form editor
        add_action('wpcf7_admin_footer', function ($post) {

            $src = CF7_PROPSTACK_PLUGIN_URL . 'assets/js/panel.js';
            echo '<script src="' . esc_url($src) . '?v=' . CF7_PROPSTACK_VERSION . '"></script>';
            // Output localization as a JS object
            $l10n = array(
                'deleteText' => __('Delete', 'cf7-propstack-integration'),
                'selectBothText' => __('Please select both CF7 and Propstack fields.', 'cf7-propstack-integration'),
                'addingText' => __('Adding...', 'cf7-propstack-integration'),
                'mappingAddedText' => __('Mapping added successfully!', 'cf7-propstack-integration'),
                'failedSaveText' => __('Failed to save mapping.', 'cf7-propstack-integration'),
                'errorSaveText' => __('Error saving mapping. Please try again.', 'cf7-propstack-integration'),
                'confirmDeleteText' => __('Are you sure you want to delete this mapping?', 'cf7-propstack-integration'),
                'deletingText' => __('Deleting...', 'cf7-propstack-integration'),
                'mappingDeletedText' => __('Mapping deleted successfully!', 'cf7-propstack-integration'),
                'failedDeleteText' => __('Failed to delete mapping.', 'cf7-propstack-integration'),
                'errorDeleteText' => __('Error deleting mapping. Please try again.', 'cf7-propstack-integration'),
                'noMappingsText' => __('No field mappings configured for this form.', 'cf7-propstack-integration'),
                'refreshingText' => __('Refreshing...', 'cf7-propstack-integration'),
                'projectsRefreshedText' => __('Projects refreshed successfully!', 'cf7-propstack-integration'),
                'clientSourcesRefreshedText' => __('Client sources refreshed successfully!', 'cf7-propstack-integration'),
                'failedRefreshProjectsText' => __('Failed to refresh projects.', 'cf7-propstack-integration'),
                'failedRefreshClientSourcesText' => __('Failed to refresh client sources.', 'cf7-propstack-integration'),
                'missingNonceText' => __('Missing nonce or AJAX URL. Please refresh the page.', 'cf7-propstack-integration'),
                'errorRefreshText' => __('Error refreshing. Please try again.', 'cf7-propstack-integration'),
            );
            echo '<script>window.cf7PropstackPanelL10n = ' . json_encode($l10n) . ';</script>';
        });
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
                'create_task' => false,
                'project_id' => '',
                'client_source_id' => '',
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

        // Fallback: try to get form ID from POST or GET
        if (empty($form_id) || $form_id === 0) {
            if (isset($_GET['post'])) {
                $form_id = intval($_GET['post']);
            } elseif (isset($_POST['post_ID'])) {
                $form_id = intval($_POST['post_ID']);
            }
        }

        // If still no form ID, try to get the first available form
        if (empty($form_id) || $form_id === 0) {
            $forms = WPCF7_ContactForm::find();
            if (!empty($forms)) {
                $first_form = is_array($forms) ? reset($forms) : $forms;
                $form_id = $first_form->id();
            }
        }

        // Get current field mappings for this form
        $current_mappings = $this->get_field_mappings($form_id);

        // Get available CF7 fields for this form
        $cf7_fields = $this->get_cf7_fields_for_form($form_id);

        // Get Propstack fields
        $propstack_fields = $this->get_propstack_fields();

        // Get projects for task creation dropdown
        $projects = $this->get_cached_projects();

        // Get client sources for task creation dropdown
        $client_sources = $this->get_cached_client_sources();

        // Debug information for troubleshooting
        $debug_info = array(
            'projects_count' => count($projects),
            'client_sources_count' => count($client_sources),
            'api_key_configured' => $this->api->is_api_key_configured(),
        );

        // Display debug info as an admin notice for troubleshooting
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-info" style="margin: 10px 0;"><p><strong>Debug Info:</strong> Projects: ' . count($projects) . ', Client Sources: ' . count($client_sources) . ', API Key: ' . ($this->api->is_api_key_configured() ? 'Configured' : 'Not Configured') . ' <button type="button" id="clear_propstack_cache" class="button button-secondary" style="margin-left: 10px;">Clear Cache</button></p></div>';
        }

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

        <!-- Task Creation Configuration -->
        <fieldset class="cf7-propstack-task-config">
            <legend><?php echo esc_html(__('Task Creation Settings', 'cf7-propstack-integration')); ?></legend>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html(__('Create Task on Signup', 'cf7-propstack-integration')); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php echo esc_html(__('Create Task on Signup', 'cf7-propstack-integration')); ?>
                                </legend>
                                <label for="wpcf7-propstack-create-task">
                                    <input type="checkbox"
                                        name="wpcf7-propstack[create_task]"
                                        id="wpcf7-propstack-create-task"
                                        value="1"
                                        <?php checked($prop['create_task']); ?> />
                                    <?php echo esc_html(__('Create a task in Propstack after successful contact creation', 'cf7-propstack-integration')); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html(__('Project', 'cf7-propstack-integration')); ?>
                        </th>
                        <td>
                            <select name="wpcf7-propstack[project_id]" id="wpcf7-propstack-project-id">
                                <option value=""><?php echo esc_html(__('Select a project (optional)', 'cf7-propstack-integration')); ?></option>
                                <?php if (empty($projects)): ?>
                                    <option value="" disabled><?php echo esc_html(__('No projects found - check API connection', 'cf7-propstack-integration')); ?></option>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo esc_attr($project['id']); ?>" <?php selected($prop['project_id'], $project['id']); ?>>
                                            <?php echo esc_html($project['title'] ?? $project['name'] ?? 'Project #' . $project['id']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="refresh_projects" class="button button-secondary" style="margin-left: 10px;">
                                <?php echo esc_html(__('Refresh', 'cf7-propstack-integration')); ?>
                            </button>
                            <p class="description">
                                <?php echo esc_html(__('Select a project to associate with the task (optional).', 'cf7-propstack-integration')); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html(__('Client Source', 'cf7-propstack-integration')); ?>
                        </th>
                        <td>
                            <select name="wpcf7-propstack[client_source_id]" id="wpcf7-propstack-client-source-id">
                                <option value=""><?php echo esc_html(__('Select a client source (optional)', 'cf7-propstack-integration')); ?></option>
                                <?php if (empty($client_sources)): ?>
                                    <option value="" disabled><?php echo esc_html(__('No client sources found - check API connection', 'cf7-propstack-integration')); ?></option>
                                <?php else: ?>
                                    <?php foreach ($client_sources as $source): ?>
                                        <option value="<?php echo esc_attr($source['id']); ?>" <?php selected($prop['client_source_id'], $source['id']); ?>>
                                            <?php echo esc_html($source['name'] ?? $source['title'] ?? 'Source #' . $source['id']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="refresh_client_sources" class="button button-secondary" style="margin-left: 10px;">
                                <?php echo esc_html(__('Refresh', 'cf7-propstack-integration')); ?>
                            </button>
                            <p class="description">
                                <?php echo esc_html(__('Select a client source to associate with the task (optional).', 'cf7-propstack-integration')); ?>
                            </p>
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
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html(__('CF7 Field', 'cf7-propstack-integration')); ?></th>
                            <th><?php echo esc_html(__('Propstack Field', 'cf7-propstack-integration')); ?></th>
                            <th><?php echo esc_html(__('Actions', 'cf7-propstack-integration')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($current_mappings)): ?>
                            <tr class="no-mappings-row">
                                <td colspan="3"><?php echo esc_html(__('No field mappings configured for this form.', 'cf7-propstack-integration')); ?></td>
                            </tr>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hidden form for AJAX nonce -->
        <input type="hidden" id="cf7_propstack_nonce" value="<?php echo wp_create_nonce('cf7_propstack_nonce'); ?>" />
        <input type="hidden" id="cf7_propstack_ajax_url" value="<?php echo admin_url('admin-ajax.php'); ?>" />
        <!-- The JavaScript for this panel is now loaded via admin.js -->
    <?php
    }

    /**
     * Get CF7 fields for a specific form
     */
    private function get_cf7_fields_for_form($form_id)
    {
        // Get the form post
        $form_post = get_post($form_id);
        if (!$form_post) {
            return array();
        }

        // Create form instance
        $contact_form = WPCF7_ContactForm::get_instance($form_id);
        if (!$contact_form) {
            return array();
        }

        // Get form content
        $form_content = $contact_form->prop('form');
        if (empty($form_content)) {
            return array();
        }

        // Extract fields from form content
        $fields = $this->extract_form_fields($form_content);

        return $fields;
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
                // Split the tag by spaces, but be careful with quoted strings
                $parts = $this->parse_cf7_tag($tag);

                if (count($parts) >= 2) {
                    $field_type = $parts[0];
                    $field_name = $parts[1];

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
                        continue;
                    }

                    // Skip closing tags (tags that start with /)
                    if (strpos($field_type, '/') === 0) {
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
                        'range',
                        'hidden'
                    );

                    if (in_array($field_type, $valid_field_types)) {
                        // Get field label
                        $label = $this->get_field_label($field_name, $field_type, $tag);
                        $fields[$field_name] = $label;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Parse CF7 tag properly, handling quoted strings
     */
    private function parse_cf7_tag($tag)
    {
        $parts = array();
        $current = '';
        $in_quotes = false;
        $quote_char = '';

        for ($i = 0; $i < strlen($tag); $i++) {
            $char = $tag[$i];

            if (($char === '"' || $char === "'") && !$in_quotes) {
                $in_quotes = true;
                $quote_char = $char;
                continue;
            }

            if ($char === $quote_char && $in_quotes) {
                $in_quotes = false;
                $quote_char = '';
                continue;
            }

            if ($char === ' ' && !$in_quotes) {
                if (!empty($current)) {
                    $parts[] = trim($current);
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if (!empty($current)) {
            $parts[] = trim($current);
        }

        return $parts;
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
        if ($this->api) {
            try {
                $api_custom_fields = $this->api->get_custom_fields();

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
                // Add sample fields as fallback
                $custom_fields = $this->get_sample_custom_fields();
                set_transient('cf7_propstack_custom_fields', $custom_fields, HOUR_IN_SECONDS);
            }
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
     * Get cached projects or fetch from API
     */
    private function get_cached_projects()
    {
        // Check cache first
        $cached_projects = get_transient('cf7_propstack_projects');
        if ($cached_projects !== false) {
            error_log('[CF7 Propstack] Using cached projects: ' . count($cached_projects) . ' projects');
            return $cached_projects;
        }

        error_log('[CF7 Propstack] No cached projects, fetching from API');

        // Fetch from API if cache is empty
        $projects = array();
        if ($this->api) {
            try {
                error_log('[CF7 Propstack] Calling API get_projects method');
                $api_projects = $this->api->get_projects();

                error_log('[CF7 Propstack] API projects result: ' . json_encode($api_projects));

                if (!empty($api_projects) && is_array($api_projects)) {
                    $projects = $api_projects;
                    error_log('[CF7 Propstack] Projects set successfully: ' . count($projects) . ' projects');
                } else {
                    error_log('[CF7 Propstack] API projects was empty or not an array');
                }

                // Cache the results for 1 hour
                set_transient('cf7_propstack_projects', $projects, HOUR_IN_SECONDS);
                error_log('[CF7 Propstack] Cached projects for 1 hour');
            } catch (Exception $e) {
                error_log('[CF7 Propstack] Exception fetching projects: ' . $e->getMessage());
                // Cache empty array on error to prevent repeated API calls
                set_transient('cf7_propstack_projects', array(), 5 * MINUTE_IN_SECONDS);
            }
        } else {
            error_log('[CF7 Propstack] API object is null');
        }

        error_log('[CF7 Propstack] Returning projects: ' . count($projects) . ' projects');
        return $projects;
    }

    /**
     * Get cached client sources or fetch from API
     */
    private function get_cached_client_sources()
    {
        // Check cache first
        $cached_sources = get_transient('cf7_propstack_client_sources');
        if ($cached_sources !== false) {
            return $cached_sources;
        }

        // Fetch from API if cache is empty
        $client_sources = array();
        if ($this->api) {
            try {
                $api_sources = $this->api->get_contact_sources();

                if (!empty($api_sources) && is_array($api_sources)) {
                    $client_sources = $api_sources;
                }

                // Cache the results for 1 hour
                set_transient('cf7_propstack_client_sources', $client_sources, HOUR_IN_SECONDS);
            } catch (Exception $e) {
                // Cache empty array on error to prevent repeated API calls
                set_transient('cf7_propstack_client_sources', array(), 5 * MINUTE_IN_SECONDS);
            }
        }

        return $client_sources;
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
                'create_task' => false,
                'project_id' => '',
                'client_source_id' => '',
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
            return;
        }

        // Map form data to Propstack fields
        $contact_data = $this->map_form_data($form_data, $mappings);

        if (empty($contact_data)) {
            return;
        }

        // Validate contact data
        $validation_errors = $this->api->validate_contact_data($contact_data);
        if (!empty($validation_errors)) {
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
        $contact_id = null;
        if ($existing_contact) {
            $result = $this->api->update_contact($existing_contact['id'], $contact_data);
            $contact_id = $existing_contact['id'];
        } else {
            $result = $this->api->create_contact($contact_data);
            $contact_id = $result;
        }

        // Create task if enabled and contact creation/update was successful
        if ($contact_id && $this->should_create_task($contact_form)) {
            $this->create_task_for_contact($contact_form, $contact_id);
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
     * Check if task creation is enabled for a form
     */
    private function should_create_task($contact_form)
    {
        $propstack_prop = $contact_form->prop('propstack');
        return !empty($propstack_prop['create_task']);
    }

    /**
     * Create a task for a contact in Propstack
     */
    private function create_task_for_contact($contact_form, $contact_id)
    {
        $propstack_prop = $contact_form->prop('propstack');
        
        $task_data = array(
            'client_ids' => array(intval($contact_id))
        );

        // Add project ID if configured
        if (!empty($propstack_prop['project_id'])) {
            $task_data['project_ids'] = array(intval($propstack_prop['project_id']));
        }

        // Add client source ID if configured
        if (!empty($propstack_prop['client_source_id'])) {
            $task_data['client_source_id'] = intval($propstack_prop['client_source_id']);
        }

        // Create the task
        $task_id = $this->api->create_task($task_data);
        
        if ($task_id) {
            // Log success if debug mode is enabled
            if ($this->api) {
                error_log('[CF7 Propstack] Task created successfully for contact ID: ' . $contact_id . ', Task ID: ' . $task_id);
            }
        }
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
        $custom_fields = array();

        // List of known checkbox fields (add more as needed)
        $checkbox_fields = array('accept_contact');

        foreach ($mappings as $cf7_field => $propstack_field) {
            // Special logic for newsletter
            if ($propstack_field === 'newsletter') {
                $value = isset($form_data[$cf7_field]) ? true : false;
            } else if (in_array($cf7_field, $checkbox_fields)) {
                $value = isset($form_data[$cf7_field]) ? true : false;
            } else if (isset($form_data[$cf7_field]) && !empty($form_data[$cf7_field])) {
                $value = $form_data[$cf7_field];
                // Handle array values (like checkboxes with multiple values)
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
            } else {
                // If not set, skip
                continue;
            }

            // Handle special field transformations
            $value = $this->transform_field_value($propstack_field, $value);

            // Check if this is a custom field
            if (strpos($propstack_field, 'custom_') === 0) {
                // Extract the actual custom field name (remove 'custom_' prefix)
                $custom_field_name = substr($propstack_field, 7);
                $custom_fields[$custom_field_name] = $value;
            } else {
                // Standard field
                $contact_data[$propstack_field] = $value;
            }
        }

        // Add custom fields to the contact data if any exist
        if (!empty($custom_fields)) {
            $contact_data['custom_fields'] = $custom_fields;
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

            /* Task Configuration Section */
            .cf7-propstack-task-config {
                background: #f0f8ff;
                border: 1px solid #0073aa;
                border-radius: 4px;
                padding: 15px;
                margin: 15px 0;
            }

            .cf7-propstack-task-config legend {
                font-weight: 600;
                color: #0073aa;
                padding: 0 10px;
            }

            .cf7-propstack-task-config .form-table th {
                width: 200px;
            }

            .cf7-propstack-task-config select {
                min-width: 250px;
                max-width: 400px;
            }

            .cf7-propstack-task-config .button-secondary {
                height: 30px;
                vertical-align: top;
            }
        </style>
    <?php
    }

    /**
     * Add admin scripts
     */
    public function add_admin_scripts()
    {
        // This method is kept for backward compatibility but functionality moved to panel.js
        // to avoid conflicts with existing JavaScript
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

    /**
     * Refresh projects cache via AJAX
     */
    public function refresh_projects()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        // Clear the cache
        delete_transient('cf7_propstack_projects');

        // Fetch fresh data
        $projects = $this->get_cached_projects();

        wp_send_json_success(array(
            'projects' => $projects,
            'message' => __('Projects refreshed successfully.', 'cf7-propstack-integration')
        ));
    }

    /**
     * Refresh client sources cache via AJAX
     */
    public function refresh_client_sources()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        // Clear the cache
        delete_transient('cf7_propstack_client_sources');

        // Fetch fresh data
        $client_sources = $this->get_cached_client_sources();

        wp_send_json_success(array(
            'client_sources' => $client_sources,
            'message' => __('Client sources refreshed successfully.', 'cf7-propstack-integration')
        ));
    }



    /**
     * Clear Propstack cache via AJAX
     */
    public function clear_propstack_cache()
    {
        check_ajax_referer('cf7_propstack_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'cf7-propstack-integration'));
        }

        // Clear all Propstack-related transients
        delete_transient('cf7_propstack_projects');
        delete_transient('cf7_propstack_client_sources');
        delete_transient('cf7_propstack_custom_fields');

        wp_send_json_success(array(
            'message' => __('All Propstack cache cleared successfully.', 'cf7-propstack-integration')
        ));
    }
}
