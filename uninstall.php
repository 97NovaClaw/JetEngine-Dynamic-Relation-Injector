<?php
/**
 * Uninstall Script
 *
 * Cleanup all plugin data when uninstalled
 *
 * @package JetRelationInjector
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Delete custom database table
 */
$table_name = $wpdb->prefix . 'jet_injector_configs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

/**
 * Delete options
 */
delete_option('jet_injector_debug_options');
delete_option('jet_injector_version');

/**
 * Delete debug log file
 */
$plugin_dir = plugin_dir_path(__FILE__);
$log_file = $plugin_dir . 'debug.txt';

if (file_exists($log_file)) {
    @unlink($log_file);
}

/**
 * Clear any cached data
 */
wp_cache_flush();

