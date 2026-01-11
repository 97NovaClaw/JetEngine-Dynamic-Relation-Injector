# Debug System Specification

## Overview

The JetEngine Dynamic Relation Injector uses an **in-plugin debug log file** stored at the plugin root as `debug.txt`. This pattern is identical to the Amelia CPT Sync reference plugin and provides a reliable, WordPress-independent logging system.

---

## Why In-Plugin Debug File?

1. **Independence from WordPress**: Works even if WordPress logging is disabled
2. **Plugin-specific**: Only logs our plugin's events, not mixed with other logs
3. **Easy access**: Visible in admin UI, downloadable, clearable
4. **Persists across settings**: Stored in wp_options, survives plugin updates
5. **No server config needed**: Unlike WP_DEBUG_LOG which may require wp-config.php changes

---

## File Locations

| File | Path | Purpose |
|------|------|---------|
| `debug.txt` | `{plugin_root}/debug.txt` | Log output file |
| `debug.php` | `{plugin_root}/includes/helpers/debug.php` | Debug functions |

---

## Debug Options Schema

Stored in WordPress options table.

**Option Name:** `jet_injector_debug_options`

```php
$default_options = [
    'enable_php_logging' => false,     // Write to debug.txt
    'enable_js_console' => false,      // Console.log in browser
    'enable_admin_notices' => false,   // Show WP admin notices
];
```

---

## PHP Implementation

### File: `includes/helpers/debug.php`

```php
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
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log an error message
 */
function jet_injector_log_error($message, $data = null) {
    jet_injector_debug_log($message, $data, 'error');
}

/**
 * Log a warning message
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
        return file_put_contents($log_file, '') !== false;
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
```

---

## Admin UI Template

### File: `templates/admin/debug-tab.php`

```php
<?php
/**
 * Debug Tab Template
 *
 * @package JetRelationInjector
 */

if (!defined('WPINC')) {
    die;
}

$options = get_option('jet_injector_debug_options', [
    'enable_php_logging' => false,
    'enable_js_console' => false,
    'enable_admin_notices' => false,
]);

$log_file = jet_injector_get_log_file_path();
$log_exists = file_exists($log_file);
$log_size = jet_injector_get_log_size();
?>

<div id="tab-debug" class="tab-content">
    <h3><?php _e('Debug Settings', 'jet-relation-injector'); ?></h3>
    
    <!-- File Locations -->
    <div class="notice notice-info">
        <h4><?php _e('📁 Debug Files', 'jet-relation-injector'); ?></h4>
        <p>
            <strong><?php _e('Log File:', 'jet-relation-injector'); ?></strong>
            <code><?php echo esc_html($log_file); ?></code>
        </p>
        <p>
            <strong><?php _e('Log Size:', 'jet-relation-injector'); ?></strong>
            <?php echo esc_html($log_size); ?>
        </p>
    </div>
    
    <!-- Debug Toggles -->
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="enable_php_logging">
                        <?php _e('PHP Debug Logging', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_php_logging]" 
                               id="enable_php_logging" 
                               value="1" 
                               <?php checked(!empty($options['enable_php_logging'])); ?>>
                        <?php _e('Write debug messages to debug.txt', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Logs all plugin operations, AJAX requests, and relation processing.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable_js_console">
                        <?php _e('JavaScript Console Logging', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_js_console]" 
                               id="enable_js_console" 
                               value="1" 
                               <?php checked(!empty($options['enable_js_console'])); ?>>
                        <?php _e('Output debug info to browser console', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Helpful for debugging UI injection and AJAX calls.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable_admin_notices">
                        <?php _e('Admin Notices', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_admin_notices]" 
                               id="enable_admin_notices" 
                               value="1" 
                               <?php checked(!empty($options['enable_admin_notices'])); ?>>
                        <?php _e('Show debug notices in admin header', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Shows WordPress admin notices for key operations.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Log Viewer -->
    <?php if (!empty($options['enable_php_logging'])): ?>
        <hr>
        <h3><?php _e('Debug Log Viewer', 'jet-relation-injector'); ?></h3>
        
        <div class="log-controls" style="margin: 20px 0;">
            <button type="button" id="view-log" class="button button-secondary">
                <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                <?php _e('View Log', 'jet-relation-injector'); ?>
            </button>
            
            <button type="button" id="refresh-log" class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php _e('Refresh', 'jet-relation-injector'); ?>
            </button>
            
            <button type="button" id="clear-log" class="button button-secondary">
                <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                <?php _e('Clear Log', 'jet-relation-injector'); ?>
            </button>
            
            <span class="spinner" id="log-spinner" style="float: none;"></span>
            <span id="log-message"></span>
        </div>
        
        <div id="log-viewer" style="display: none;">
            <pre id="log-contents" style="
                background: #1e1e1e;
                color: #d4d4d4;
                padding: 20px;
                border-radius: 4px;
                max-height: 500px;
                overflow: auto;
                font-family: 'Consolas', 'Monaco', monospace;
                font-size: 12px;
                line-height: 1.5;
                white-space: pre-wrap;
                word-wrap: break-word;
            "></pre>
        </div>
    <?php else: ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Enable PHP Debug Logging above to view the log file.', 'jet-relation-injector'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
```

---

## AJAX Handlers

### View Log

```php
public function ajax_view_log() {
    check_ajax_referer('jet_injector_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $contents = jet_injector_get_log_contents();
    
    if (empty($contents)) {
        $contents = __('Log file is empty or does not exist yet.', 'jet-relation-injector');
    }
    
    wp_send_json_success([
        'contents' => $contents,
        'size' => jet_injector_get_log_size(),
    ]);
}
```

### Clear Log

```php
public function ajax_clear_log() {
    check_ajax_referer('jet_injector_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $result = jet_injector_clear_log();
    
    if ($result) {
        wp_send_json_success(['message' => __('Log cleared successfully.', 'jet-relation-injector')]);
    } else {
        wp_send_json_error(['message' => __('Failed to clear log.', 'jet-relation-injector')]);
    }
}
```

### Save Debug Settings

```php
public function ajax_save_debug_settings() {
    check_ajax_referer('jet_injector_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $options = [
        'enable_php_logging' => !empty($_POST['debug_options']['enable_php_logging']),
        'enable_js_console' => !empty($_POST['debug_options']['enable_js_console']),
        'enable_admin_notices' => !empty($_POST['debug_options']['enable_admin_notices']),
    ];
    
    update_option('jet_injector_debug_options', $options);
    
    wp_send_json_success(['message' => __('Debug settings saved.', 'jet-relation-injector')]);
}
```

---

## JavaScript Debug Logging

Pass debug state to JavaScript:

```php
wp_localize_script('jet-injector', 'jetInjectorConfig', [
    'debug' => jet_injector_is_js_debug_enabled(),
    // ... other config
]);
```

Use in JavaScript:

```javascript
const JetInjector = {
    log: function(...args) {
        if (window.jetInjectorConfig?.debug) {
            console.log('[JetInjector]', ...args);
        }
    },
    
    error: function(...args) {
        if (window.jetInjectorConfig?.debug) {
            console.error('[JetInjector]', ...args);
        }
    },
    
    warn: function(...args) {
        if (window.jetInjectorConfig?.debug) {
            console.warn('[JetInjector]', ...args);
        }
    }
};

// Usage
JetInjector.log('Initializing on CCT:', cctSlug);
JetInjector.log('Found relations:', relations);
```

---

## Log Entry Format

```
[2026-01-10 20:30:15] [INFO] Plugin initialized
[2026-01-10 20:30:16] [INFO] Detected CCT edit screen: service-guides
[2026-01-10 20:30:16] [DEBUG] Found 3 relations for CCT
{
    "relations": [
        {"id": 12, "name": "Guide to Vehicle"},
        {"id": 15, "name": "Guide to Category"}
    ]
}
[2026-01-10 20:30:45] [INFO] Processing relation save for item 123
[2026-01-10 20:30:45] [INFO] Created relation: guide_to_vehicle -> 505
[2026-01-10 20:30:46] [ERROR] Failed to create relation: relation_xyz not found
```

---

## Cleanup on Uninstall

```php
// In uninstall.php
$log_file = plugin_dir_path(__FILE__) . 'debug.txt';
if (file_exists($log_file)) {
    unlink($log_file);
}

delete_option('jet_injector_debug_options');
```

---

## Security Considerations

1. **File permissions**: debug.txt is created with default permissions, readable only by web server
2. **No sensitive data**: Never log passwords, API keys, or full user data
3. **Admin only**: Log viewing/clearing requires `manage_options` capability
4. **Nonce verification**: All AJAX operations require valid nonce

