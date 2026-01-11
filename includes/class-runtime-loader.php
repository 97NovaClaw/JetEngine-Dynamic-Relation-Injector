<?php
/**
 * Runtime Loader - Module C
 *
 * Detects CCT edit screens and loads injection assets
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Runtime Loader Class
 */
class Jet_Injector_Runtime_Loader {
    
    /**
     * Current CCT slug (if on CCT edit page)
     *
     * @var string|null
     */
    private $current_cct = null;
    
    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'maybe_load_assets']);
    }
    
    /**
     * Check if we're on a CCT edit page and load assets
     *
     * @param string $hook Current admin page hook
     */
    public function maybe_load_assets($hook) {
        // Only load on CCT edit pages
        if (!$this->is_cct_edit_page()) {
            return;
        }
        
        $this->current_cct = $this->get_current_cct_slug();
        
        if (!$this->current_cct) {
            return;
        }
        
        jet_injector_debug_log('Loading runtime assets', [
            'cct_slug' => $this->current_cct,
            'hook' => $hook,
        ]);
        
        // Check if this CCT has injection enabled
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $config = $config_manager->get_config($this->current_cct);
        
        if (!$config || !$config['is_enabled']) {
            jet_injector_debug_log('Injection not enabled for this CCT', ['cct_slug' => $this->current_cct]);
            return;
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Localize script with config data
        $this->localize_script($config);
    }
    
    /**
     * Check if current page is a CCT edit page
     *
     * @return bool
     */
    private function is_cct_edit_page() {
        global $pagenow;
        
        // Check if we're in admin
        if (!is_admin()) {
            return false;
        }
        
        // Check for JetEngine CCT edit pages
        if ($pagenow === 'admin.php' && isset($_GET['page']) && strpos($_GET['page'], 'jet-cct-') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current CCT slug from URL
     *
     * @return string|null
     */
    private function get_current_cct_slug() {
        if (!isset($_GET['page'])) {
            return null;
        }
        
        $page = $_GET['page'];
        
        // JetEngine CCT pages format: jet-cct-{slug}
        if (strpos($page, 'jet-cct-') === 0) {
            return str_replace('jet-cct-', '', $page);
        }
        
        return null;
    }
    
    /**
     * Enqueue runtime assets
     */
    private function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'jet-injector-runtime',
            JET_INJECTOR_PLUGIN_URL . 'assets/css/injector.css',
            [],
            JET_INJECTOR_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'jet-injector-runtime',
            JET_INJECTOR_PLUGIN_URL . 'assets/js/injector.js',
            ['jquery', 'wp-api'],
            JET_INJECTOR_VERSION,
            true
        );
        
        jet_injector_debug_log('Runtime assets enqueued');
    }
    
    /**
     * Localize script with configuration data
     *
     * @param array $config CCT configuration
     */
    private function localize_script($config) {
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        
        // Get relations for this CCT
        $relations = $discovery->get_relations_for_cct($this->current_cct, 'both', true);
        
        // Filter to only enabled relations
        $enabled_relations = [];
        foreach ($relations as $relation) {
            if (in_array($relation['id'], $config['config']['enabled_relations'])) {
                // Add display fields to relation
                $relation['display_fields'] = isset($config['config']['display_fields'][$relation['id']]) 
                    ? $config['config']['display_fields'][$relation['id']] 
                    : [];
                
                // Get the related object (CCT, taxonomy, or post type)
                $is_parent = $relation['cct_position'] === 'parent';
                $related_object = $is_parent ? $relation['child_object'] : $relation['parent_object'];
                
                // Pass full object slug (with prefix) for proper type detection
                $relation['related_cct_slug'] = $related_object;
                
                // Get related object name
                $relation['related_cct_name'] = $discovery->get_relation_object_name($related_object);
                
                // Get related object fields (only for CCTs)
                $parsed = $discovery->parse_relation_object($related_object);
                if ($parsed['type'] === 'cct') {
                    $related_cct = $discovery->get_cct($parsed['slug']);
                    if ($related_cct) {
                        $relation['related_cct_fields'] = $related_cct['fields'];
                    }
                } else {
                    // For taxonomies and post types, fields are handled differently
                    $relation['related_cct_fields'] = [];
                }
                
                $enabled_relations[] = $relation;
            }
        }
        
        wp_localize_script('jet-injector-runtime', 'jetInjectorConfig', [
            'cct_slug' => $this->current_cct,
            'injection_point' => $config['config']['injection_point'],
            'relations' => $enabled_relations,
            'ui_settings' => $config['config']['ui_settings'],
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jet_injector_nonce'),
            'debug' => jet_injector_is_js_debug_enabled(),
            'i18n' => [
                'search_placeholder' => __('Search...', 'jet-relation-injector'),
                'no_results' => __('No items found', 'jet-relation-injector'),
                'add_new' => __('Add New', 'jet-relation-injector'),
                'select' => __('Select', 'jet-relation-injector'),
                'cancel' => __('Cancel', 'jet-relation-injector'),
                'create' => __('Create', 'jet-relation-injector'),
                'remove' => __('Remove', 'jet-relation-injector'),
                'loading' => __('Loading...', 'jet-relation-injector'),
                'error' => __('An error occurred', 'jet-relation-injector'),
                'one_to_one_warning' => __('This relation only allows one item. The existing item will be replaced.', 'jet-relation-injector'),
            ],
        ]);
        
        jet_injector_debug_log('Script localized', [
            'cct_slug' => $this->current_cct,
            'relations_count' => count($enabled_relations),
        ]);
    }
    
    /**
     * Get current CCT slug (public accessor)
     *
     * @return string|null
     */
    public function get_current_cct() {
        return $this->current_cct;
    }
}

