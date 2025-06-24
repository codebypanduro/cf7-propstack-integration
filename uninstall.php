<?php

/**
 * Uninstall script for CF7 Propstack Integration
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data including options and database tables.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('cf7_propstack_options');

// Delete form-specific meta data
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cf7_propstack_enabled'");

// Drop custom database table
$table_name = $wpdb->prefix . 'cf7_propstack_mappings';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any cached data
wp_cache_flush();

// Log uninstall for debugging (if WP_DEBUG is enabled)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[CF7 Propstack] Plugin uninstalled - all data cleaned up');
}
