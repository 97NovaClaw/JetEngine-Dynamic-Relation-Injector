<?php
/**
 * Transaction Processor - Module D
 *
 * Processes relation saves after CCT item save using the "Trojan Horse" method
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Transaction Processor Class
 */
class Jet_Injector_Transaction_Processor {
    
    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks for all configured CCTs
     */
    private function register_hooks() {
        // Get all enabled configurations
        $config_manager = Jet_Injector_Plugin::instance()->get_config_manager();
        $enabled_configs = $config_manager->get_all_configs(true);
        
        foreach ($enabled_configs as $config) {
            $cct_slug = $config['cct_slug'];
            
            // Hook after CCT item is created
            add_action(
                "jet-engine/custom-content-types/created-item/{$cct_slug}",
                [$this, 'process_relation_save'],
                10,
                3
            );
            
            // Hook after CCT item is updated
            add_action(
                "jet-engine/custom-content-types/updated-item/{$cct_slug}",
                [$this, 'process_relation_save'],
                10,
                3
            );
            
            jet_injector_debug_log("Registered hooks for CCT: {$cct_slug}");
        }
    }
    
    /**
     * Process relation save from injected form data
     *
     * @param int    $item_id   CCT item ID
     * @param array  $data      CCT item data
     * @param object $instance  CCT instance
     */
    public function process_relation_save($item_id, $data, $instance) {
        jet_injector_debug_log('Processing relation save', [
            'item_id' => $item_id,
            'cct_slug' => $instance->get_arg('slug'),
        ]);
        
        // Check if our injected data exists in $_POST
        if (empty($_POST['jet_injector_relations'])) {
            jet_injector_debug_log('No injected relation data found in POST');
            return;
        }
        
        // Verify nonce
        if (empty($_POST['jet_injector_nonce']) || !wp_verify_nonce($_POST['jet_injector_nonce'], 'jet_injector_save_relations')) {
            jet_injector_log_error('Nonce verification failed');
            return;
        }
        
        // Parse the relation data (it's JSON-encoded)
        $relations_data = json_decode(stripslashes($_POST['jet_injector_relations']), true);
        
        if (!is_array($relations_data)) {
            jet_injector_log_error('Invalid relations data format');
            return;
        }
        
        jet_injector_debug_log('Parsed relation data', $relations_data);
        
        // Process each relation
        foreach ($relations_data as $relation_id => $relation_items) {
            $this->save_relation_items($item_id, $relation_id, $relation_items);
        }
        
        jet_injector_debug_log('Relation processing complete', ['item_id' => $item_id]);
    }
    
    /**
     * Save relation items for a specific relation
     *
     * @param int    $item_id        CCT item ID
     * @param int    $relation_id    Relation ID
     * @param array  $relation_items Array of related item IDs
     */
    private function save_relation_items($item_id, $relation_id, $relation_items) {
        if (!function_exists('jet_engine') || !jet_engine()->relations) {
            jet_injector_log_error('JetEngine relations not available');
            return;
        }
        
        // Get the relation object
        $relations = jet_engine()->relations->get_active_relations();
        
        if (!isset($relations[$relation_id])) {
            jet_injector_log_error('Relation not found', ['relation_id' => $relation_id]);
            return;
        }
        
        $relation = $relations[$relation_id];
        
        // Determine if this CCT is parent or child in the relation
        $args = $relation->get_args();
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $cct_instance = get_post_type_object($item_id);
        
        // Get current CCT slug from item
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return;
        }
        
        $cct_module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $current_cct = null;
        
        // Find which CCT this item belongs to
        foreach ($cct_module->manager->get_content_types() as $slug => $cct_data) {
            $handler = $cct_module->manager->get_item_handler($slug);
            if ($handler) {
                $item = $handler->get_item($item_id);
                if ($item) {
                    $current_cct = $slug;
                    break;
                }
            }
        }
        
        if (!$current_cct) {
            jet_injector_log_error('Could not determine CCT for item', ['item_id' => $item_id]);
            return;
        }
        
        // Determine position (parent or child)
        $is_parent = strpos($args['parent_object'], $current_cct) !== false;
        
        jet_injector_debug_log('Saving relation', [
            'relation_id' => $relation_id,
            'item_id' => $item_id,
            'is_parent' => $is_parent,
            'related_items' => $relation_items,
        ]);
        
        // Clear existing relations first (if relation type requires it)
        if ($args['type'] === 'one_to_one' || $args['type'] === 'one_to_many') {
            $this->clear_existing_relations($item_id, $relation_id, $is_parent);
        }
        
        // Create new relations
        foreach ($relation_items as $related_item_id) {
            if (empty($related_item_id)) {
                continue;
            }
            
            $result = $this->create_relation(
                $relation,
                $is_parent ? $item_id : $related_item_id,
                $is_parent ? $related_item_id : $item_id
            );
            
            if ($result) {
                jet_injector_debug_log('Relation created', [
                    'parent_id' => $is_parent ? $item_id : $related_item_id,
                    'child_id' => $is_parent ? $related_item_id : $item_id,
                ]);
            } else {
                jet_injector_log_error('Failed to create relation', [
                    'relation_id' => $relation_id,
                    'parent_id' => $is_parent ? $item_id : $related_item_id,
                    'child_id' => $is_parent ? $related_item_id : $item_id,
                ]);
            }
        }
    }
    
    /**
     * Create a relation between two items
     *
     * @param object $relation  Relation object
     * @param int    $parent_id Parent item ID
     * @param int    $child_id  Child item ID
     * @return bool Success
     */
    private function create_relation($relation, $parent_id, $child_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'jet_rel_' . $relation->get_id();
        
        // Check if relation already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rel_id FROM {$table} WHERE parent_object_id = %d AND child_object_id = %d",
                $parent_id,
                $child_id
            )
        );
        
        if ($exists) {
            jet_injector_debug_log('Relation already exists', [
                'parent_id' => $parent_id,
                'child_id' => $child_id,
            ]);
            return true;
        }
        
        // Insert relation
        $result = $wpdb->insert(
            $table,
            [
                'parent_object_id' => $parent_id,
                'child_object_id' => $child_id,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Clear existing relations for an item
     *
     * @param int  $item_id     Item ID
     * @param int  $relation_id Relation ID
     * @param bool $is_parent   Whether item is parent in relation
     */
    private function clear_existing_relations($item_id, $relation_id, $is_parent) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'jet_rel_' . $relation_id;
        $column = $is_parent ? 'parent_object_id' : 'child_object_id';
        
        $wpdb->delete(
            $table,
            [$column => $item_id],
            ['%d']
        );
        
        jet_injector_debug_log('Cleared existing relations', [
            'item_id' => $item_id,
            'relation_id' => $relation_id,
            'is_parent' => $is_parent,
        ]);
    }
}

