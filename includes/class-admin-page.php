<?php
/**
 * Admin Page Handler
 *
 * Manages the admin settings page
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Admin Page Class
 */
class Jet_Injector_Admin_Page {
    
    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        $this->register_ajax_handlers();
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            __('Relation Injector', 'jet-relation-injector'),
            __('Relation Injector', 'jet-relation-injector'),
            'manage_options',
            'jet-relation-injector',
            [$this, 'render_admin_page'],
            'dashicons-networking',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if ($hook !== 'toplevel_page_jet-relation-injector') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'jet-injector-admin',
            JET_INJECTOR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            JET_INJECTOR_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'jet-injector-admin',
            JET_INJECTOR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            JET_INJECTOR_VERSION,
            true
        );
        
        // Localize script
        $this->localize_admin_script();
    }
    
    /**
     * Localize admin script
     */
    private function localize_admin_script() {
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        
        wp_localize_script('jet-injector-admin', 'jetInjectorAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jet_injector_nonce'),
            'ccts' => $discovery->get_all_ccts(),
            'relations' => $discovery->get_all_relations(),
            'debug' => jet_injector_is_js_debug_enabled(),
            'jetengine_relations_url' => admin_url('admin.php?page=jet-engine-relations'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this configuration?', 'jet-relation-injector'),
                'save_success' => __('Settings saved successfully', 'jet-relation-injector'),
                'save_error' => __('Failed to save settings', 'jet-relation-injector'),
                'delete_success' => __('Configuration deleted successfully', 'jet-relation-injector'),
                'delete_error' => __('Failed to delete configuration', 'jet-relation-injector'),
                'log_cleared' => __('Log cleared successfully', 'jet-relation-injector'),
                'loading' => __('Loading...', 'jet-relation-injector'),
            ],
        ]);
    }
    
    /**
     * Register AJAX handlers for admin page
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_jet_injector_save_config', [$this, 'ajax_save_config']);
        add_action('wp_ajax_jet_injector_delete_config', [$this, 'ajax_delete_config']);
        add_action('wp_ajax_jet_injector_toggle_config', [$this, 'ajax_toggle_config']);
        add_action('wp_ajax_jet_injector_get_cct_relations', [$this, 'ajax_get_cct_relations']);
        add_action('wp_ajax_jet_injector_save_debug_settings', [$this, 'ajax_save_debug_settings']);
        add_action('wp_ajax_jet_injector_view_log', [$this, 'ajax_view_log']);
        add_action('wp_ajax_jet_injector_clear_log', [$this, 'ajax_clear_log']);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        
        // Get all CCTs
        $ccts = $discovery->get_all_ccts();
        
        // Get all configurations
        $configs = $config_manager->get_all_configs();
        
        // Get debug options
        $debug_options = get_option('jet_injector_debug_options', [
            'enable_php_logging' => false,
            'enable_js_console' => false,
            'enable_admin_notices' => false,
        ]);
        
        // Load template
        include JET_INJECTOR_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    /**
     * AJAX: Save configuration
     */
    public function ajax_save_config() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        $config_data = isset($_POST['config']) ? $_POST['config'] : [];
        $is_enabled = isset($_POST['is_enabled']) ? (bool) $_POST['is_enabled'] : true;
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
        }
        
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $result = $config_manager->save_config($cct_slug, $config_data, $is_enabled);
        
        if ($result) {
            wp_send_json_success([
                'message' => __('Configuration saved successfully', 'jet-relation-injector'),
                'config_id' => $result,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration', 'jet-relation-injector')]);
        }
    }
    
    /**
     * AJAX: Delete configuration
     */
    public function ajax_delete_config() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
        }
        
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $result = $config_manager->delete_config($cct_slug);
        
        if ($result) {
            wp_send_json_success(['message' => __('Configuration deleted successfully', 'jet-relation-injector')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete configuration', 'jet-relation-injector')]);
        }
    }
    
    /**
     * AJAX: Toggle configuration
     */
    public function ajax_toggle_config() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        $is_enabled = isset($_POST['is_enabled']) ? (bool) $_POST['is_enabled'] : false;
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
        }
        
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $result = $config_manager->toggle_config($cct_slug, $is_enabled);
        
        if ($result) {
            wp_send_json_success(['message' => __('Configuration toggled successfully', 'jet-relation-injector')]);
        } else {
            wp_send_json_error(['message' => __('Failed to toggle configuration', 'jet-relation-injector')]);
        }
    }
    
    /**
     * AJAX: Get relations for a CCT
     */
    public function ajax_get_cct_relations() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
        }
        
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $relations = $discovery->get_relations_for_cct($cct_slug, 'both', true);
        
        wp_send_json_success([
            'relations' => $relations,
            'count' => count($relations),
        ]);
    }
    
    /**
     * AJAX: Save debug settings
     */
    public function ajax_save_debug_settings() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $options = [
            'enable_php_logging' => !empty($_POST['debug_options']['enable_php_logging']),
            'enable_js_console' => !empty($_POST['debug_options']['enable_js_console']),
            'enable_admin_notices' => !empty($_POST['debug_options']['enable_admin_notices']),
        ];
        
        update_option('jet_injector_debug_options', $options);
        
        wp_send_json_success(['message' => __('Debug settings saved', 'jet-relation-injector')]);
    }
    
    /**
     * AJAX: View log
     */
    public function ajax_view_log() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
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
    
    /**
     * AJAX: Clear log
     */
    public function ajax_clear_log() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $result = jet_injector_clear_log();
        
        if ($result) {
            wp_send_json_success(['message' => __('Log cleared successfully', 'jet-relation-injector')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear log', 'jet-relation-injector')]);
        }
    }
}

