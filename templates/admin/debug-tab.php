<?php
/**
 * Debug Tab Template
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$log_file = jet_injector_get_log_file_path();
$log_exists = file_exists($log_file);
$log_size = jet_injector_get_log_size();
?>

<h3><?php _e('Debug Settings', 'jet-relation-injector'); ?></h3>

<!-- File Locations -->
<div class="notice notice-info inline">
    <h4><?php _e('📁 Debug Files', 'jet-relation-injector'); ?></h4>
    <p>
        <strong><?php _e('Log File:', 'jet-relation-injector'); ?></strong>
        <code><?php echo esc_html($log_file); ?></code>
    </p>
    <p>
        <strong><?php _e('Log Size:', 'jet-relation-injector'); ?></strong>
        <span id="log-size"><?php echo esc_html($log_size); ?></span>
    </p>
</div>

<!-- Debug Toggles -->
<form id="debug-settings-form">
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="enable-php-logging">
                        <?php _e('PHP Debug Logging', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_php_logging]" 
                               id="enable-php-logging" 
                               value="1" 
                               <?php checked(!empty($debug_options['enable_php_logging'])); ?>>
                        <?php _e('Write debug messages to debug.txt', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Logs all plugin operations, AJAX requests, and relation processing.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable-js-console">
                        <?php _e('JavaScript Console Logging', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_js_console]" 
                               id="enable-js-console" 
                               value="1" 
                               <?php checked(!empty($debug_options['enable_js_console'])); ?>>
                        <?php _e('Output debug info to browser console', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Helpful for debugging UI injection and AJAX calls.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable-admin-notices">
                        <?php _e('Admin Notices', 'jet-relation-injector'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="debug_options[enable_admin_notices]" 
                               id="enable-admin-notices" 
                               value="1" 
                               <?php checked(!empty($debug_options['enable_admin_notices'])); ?>>
                        <?php _e('Show debug notices in admin header', 'jet-relation-injector'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Shows WordPress admin notices for key operations.', 'jet-relation-injector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    
    <p class="submit">
        <button type="button" id="save-debug-settings" class="button button-primary">
            <span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
            <?php _e('Save Debug Settings', 'jet-relation-injector'); ?>
        </button>
        <span class="spinner" id="debug-spinner" style="float: none;"></span>
        <span id="debug-message"></span>
    </p>
</form>

<!-- Log Viewer -->
<?php if (!empty($debug_options['enable_php_logging'])): ?>
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
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        "></pre>
    </div>
<?php else: ?>
    <div class="notice notice-warning inline">
        <p>
            <?php _e('Enable PHP Debug Logging above to view the log file.', 'jet-relation-injector'); ?>
        </p>
    </div>
<?php endif; ?>

<style>
.notice.inline {
    margin: 20px 0;
    padding: 15px;
}

#debug-message.success,
#log-message.success {
    color: #46b450;
    font-weight: 600;
}

#debug-message.error,
#log-message.error {
    color: #dc3232;
    font-weight: 600;
}
</style>

