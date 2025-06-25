<?php

/**
 * Plugin Name: Contact Form 7 - Propstack Integration
 * Plugin URI: https://github.com/codebypanduro/cf7-propstack-integration
 * Description: Integrate Contact Form 7 with Propstack CRM to automatically create contacts from form submissions.
 * Version: 1.0.0
 * Author: Code by Panduro
 * Author URI: https://codebypanduro.dk
 * License: GPL v2 or later
 * Text Domain: cf7-propstack-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CF7_PROPSTACK_VERSION', '1.0.0');
define('CF7_PROPSTACK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CF7_PROPSTACK_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CF7_PROPSTACK_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class CF7_Propstack_Integration
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Check if Contact Form 7 is active
        if (!$this->is_cf7_active()) {
            add_action('admin_notices', array($this, 'cf7_missing_notice'));
            return;
        }

        // Load the API client first
        require_once CF7_PROPSTACK_PLUGIN_PATH . 'includes/class-propstack-api.php';

        // Load admin functionality
        if (is_admin()) {
            require_once CF7_PROPSTACK_PLUGIN_PATH . 'includes/admin/class-admin.php';
            new CF7_Propstack_Admin();
        }

        // Load the integration handler after CF7 is fully loaded
        add_action('wpcf7_init', array($this, 'load_integration_handler'));
    }

    /**
     * Load the integration handler
     */
    public function load_integration_handler()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CF7 Propstack] Loading integration handler');
        }

        require_once CF7_PROPSTACK_PLUGIN_PATH . 'includes/class-integration-handler.php';
        new CF7_Propstack_Integration_Handler();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('cf7-propstack-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Check if Contact Form 7 is active
     */
    private function is_cf7_active()
    {
        return class_exists('WPCF7');
    }

    /**
     * Display notice if Contact Form 7 is not active
     */
    public function cf7_missing_notice()
    {
?>
        <div class="notice notice-error">
            <p><?php _e('Contact Form 7 - Propstack Integration requires Contact Form 7 to be installed and activated.', 'cf7-propstack-integration'); ?></p>
        </div>
<?php
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create default options
        $default_options = array(
            'api_key' => '',
            'debug_mode' => false
        );

        add_option('cf7_propstack_options', $default_options);

        // Create database table for field mappings
        $this->create_mappings_table();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up if needed
    }

    /**
     * Create database table for field mappings
     */
    private function create_mappings_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cf7_propstack_mappings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id varchar(255) NOT NULL,
            cf7_field varchar(255) NOT NULL,
            propstack_field varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY form_field (form_id, cf7_field)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
new CF7_Propstack_Integration();
