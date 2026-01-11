<?php
/**
 * Plugin Name: JetEngine Dynamic Relation Injector
 * Plugin URI: https://github.com/yourusername/jet-engine-relation-injector
 * Description: Injects JetEngine relation selectors into CCT edit screens, enabling relation management before initial save. Eliminates the "save first, relate later" limitation.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: jet-relation-injector
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version
 */
define('JET_INJECTOR_VERSION', '1.0.0');

/**
 * Plugin directory path
 */
define('JET_INJECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL
 */
define('JET_INJECTOR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin base name
 */
define('JET_INJECTOR_PLUGIN_BASE', plugin_basename(__FILE__));

/**
 * Database table name (without prefix)
 */
define('JET_INJECTOR_TABLE', 'jet_injector_configs');

/**
 * Minimum required JetEngine version
 */
define('JET_INJECTOR_MIN_JETENGINE_VERSION', '3.3.1');

/**
 * Check dependencies on activation
 */
register_activation_hook(__FILE__, 'jet_injector_activate');

function jet_injector_activate() {
    // Check if JetEngine is active
    if (!class_exists('Jet_Engine')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('JetEngine Dynamic Relation Injector requires JetEngine to be installed and activated.', 'jet-relation-injector'),
            __('Plugin Activation Error', 'jet-relation-injector'),
            ['back_link' => true]
        );
    }
    
    // Check JetEngine version
    if (defined('JET_ENGINE_VERSION')) {
        if (version_compare(JET_ENGINE_VERSION, JET_INJECTOR_MIN_JETENGINE_VERSION, '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                sprintf(
                    __('JetEngine Dynamic Relation Injector requires JetEngine version %s or higher. You are running version %s.', 'jet-relation-injector'),
                    JET_INJECTOR_MIN_JETENGINE_VERSION,
                    JET_ENGINE_VERSION
                ),
                __('Plugin Activation Error', 'jet-relation-injector'),
                ['back_link' => true]
            );
        }
    }
    
    // Check if CCT module is enabled
    if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('JetEngine Dynamic Relation Injector requires JetEngine\'s Custom Content Types module to be enabled.', 'jet-relation-injector'),
            __('Plugin Activation Error', 'jet-relation-injector'),
            ['back_link' => true]
        );
    }
    
    // Check if Relations module is enabled
    if (!class_exists('\\Jet_Engine\\Relations\\Manager')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('JetEngine Dynamic Relation Injector requires JetEngine\'s Relations module to be enabled.', 'jet-relation-injector'),
            __('Plugin Activation Error', 'jet-relation-injector'),
            ['back_link' => true]
        );
    }
    
    // Create database table
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-config-db.php';
    Jet_Injector_Config_DB::create_table();
    
    // Initialize default debug options
    if (!get_option('jet_injector_debug_options')) {
        update_option('jet_injector_debug_options', [
            'enable_php_logging' => false,
            'enable_js_console' => false,
            'enable_admin_notices' => false,
        ]);
    }
}

/**
 * Load plugin text domain for translations
 */
add_action('plugins_loaded', 'jet_injector_load_textdomain');

function jet_injector_load_textdomain() {
    load_plugin_textdomain(
        'jet-relation-injector',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', 'jet_injector_init', 20);

function jet_injector_init() {
    // Check dependencies again before init
    if (!class_exists('Jet_Engine')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('JetEngine Dynamic Relation Injector', 'jet-relation-injector'); ?>:</strong>
                    <?php _e('JetEngine is required but not active.', 'jet-relation-injector'); ?>
                </p>
            </div>
            <?php
        });
        return;
    }
    
    // Load debug functions first
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/helpers/debug.php';
    
    // Load core classes
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-config-db.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-discovery.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-config-manager.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-transaction-processor.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-data-broker.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-runtime-loader.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-admin-page.php';
    require_once JET_INJECTOR_PLUGIN_DIR . 'includes/class-plugin.php';
    
    // Initialize plugin
    Jet_Injector_Plugin::instance();
    
    jet_injector_debug_log('Plugin initialized', ['version' => JET_INJECTOR_VERSION]);
}

/**
 * Add settings link on plugins page
 */
add_filter('plugin_action_links_' . JET_INJECTOR_PLUGIN_BASE, 'jet_injector_add_settings_link');

function jet_injector_add_settings_link($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=jet-relation-injector'),
        __('Settings', 'jet-relation-injector')
    );
    array_unshift($links, $settings_link);
    return $links;
}

