<?php
/**
 * Debug Functions
 *
 * In-plugin debug logging system
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Check if PHP debug logging is enabled
 *
 * @return bool
 */
function jet_injector_is_debug_enabled() {
    $options = get_option('jet_injector_debug_options', [
        'enable_php_logging' => false,
        'enable_js_console' => false,
        'enable_admin_notices' => false,
    ]);
    
    return !empty($options['enable_php_logging']);
}

/**
 * Check if JS console logging is enabled
 *
 * @return bool
 */
function jet_injector_is_js_debug_enabled() {
    $options = get_option('jet_injector_debug_options', []);
    return !empty($options['enable_js_console']);
}

/**
 * Check if admin notices are enabled
 *
 * @return bool
 */
function jet_injector_is_notices_enabled() {
    $options = get_option('jet_injector_debug_options', []);
    return !empty($options['enable_admin_notices']);
}

/**
 * Write to plugin debug log
 *
 * @param string $message The log message
 * @param mixed  $data    Optional data to include (will be JSON encoded if array/object)
 * @param string $level   Log level: 'info', 'warning', 'error', 'debug'
 */
function jet_injector_debug_log($message, $data = null, $level = 'info') {
    // Only log if debug is enabled
    if (!jet_injector_is_debug_enabled()) {
        return;
    }
    
    // Get log file path
    $log_file = JET_INJECTOR_PLUGIN_DIR . 'debug.txt';
    
    // Format timestamp
    $timestamp = current_time('Y-m-d H:i:s');
    
    // Format level
    $level_prefix = strtoupper($level);
    
    // Build log entry
    $log_entry = "[{$timestamp}] [{$level_prefix}] {$message}";
    
    // Append data if provided
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log_entry .= "\n" . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $log_entry .= ' ' . $data;
        }
    }
    
    $log_entry .= "\n";
    
    // Append to log file
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log an error message
 *
 * @param string $message Error message
 * @param mixed  $data    Optional data
 */
function jet_injector_log_error($message, $data = null) {
    jet_injector_debug_log($message, $data, 'error');
}

/**
 * Log a warning message
 *
 * @param string $message Warning message
 * @param mixed  $data    Optional data
 */
function jet_injector_log_warning($message, $data = null) {
    jet_injector_debug_log($message, $data, 'warning');
}

/**
 * Get debug log file path
 *
 * @return string
 */
function jet_injector_get_log_file_path() {
    return JET_INJECTOR_PLUGIN_DIR . 'debug.txt';
}

/**
 * Get debug log contents
 *
 * @return string
 */
function jet_injector_get_log_contents() {
    $log_file = jet_injector_get_log_file_path();
    
    if (!file_exists($log_file)) {
        return '';
    }
    
    return file_get_contents($log_file);
}

/**
 * Get debug log file size (formatted)
 *
 * @return string
 */
function jet_injector_get_log_size() {
    $log_file = jet_injector_get_log_file_path();
    
    if (!file_exists($log_file)) {
        return '0 bytes';
    }
    
    $bytes = filesize($log_file);
    
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Clear debug log
 *
 * @return bool Success
 */
function jet_injector_clear_log() {
    $log_file = jet_injector_get_log_file_path();
    
    if (file_exists($log_file)) {
        return @file_put_contents($log_file, '') !== false;
    }
    
    return true;
}

/**
 * Show admin notice if enabled
 *
 * @param string $message Notice message
 * @param string $type    Notice type: 'success', 'warning', 'error', 'info'
 */
function jet_injector_admin_notice($message, $type = 'info') {
    if (!jet_injector_is_notices_enabled()) {
        return;
    }
    
    add_action('admin_notices', function() use ($message, $type) {
        printf(
            '<div class="notice notice-%s is-dismissible"><p><strong>Relation Injector:</strong> %s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    });
}

