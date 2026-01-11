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
     * Constructor - Register hooks on init
     */
    public function __construct() {
        // Defer hook registration to avoid circular dependency
        add_action('init', [$this, 'register_hooks'], 20);
    }
    
    /**
     * Register WordPress hooks for all configured CCTs
     */
    public function register_hooks() {
        // Get all enabled configurations directly from database
        $enabled_configs = Jet_Injector_Config_DB::get_enabled('cct');
        
        if (empty($enabled_configs)) {
            return;
        }
        
        foreach ($enabled_configs as $config) {
            $cct_slug = $config->object_slug;
            
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
        
        // Verify nonce - matches the nonce created in runtime-loader.php
        if (empty($_POST['jet_injector_nonce']) || !wp_verify_nonce($_POST['jet_injector_nonce'], 'jet_injector_nonce')) {
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
        
        // Get args and current CCT from the instance (passed by JetEngine hook)
        $args = $relation->get_args();
        
        // The $instance parameter from the hook gives us the CCT slug directly!
        $current_cct = $instance->get_arg('slug');
        
        if (!$current_cct) {
            jet_injector_log_error('Could not determine CCT slug from instance', ['item_id' => $item_id]);
            return;
        }
        
        // Determine position (parent or child)
        // Check if current CCT matches parent or child object
        $parent_parsed = Jet_Injector_Plugin::instance()->get_discovery()->parse_relation_object($args['parent_object']);
        $child_parsed = Jet_Injector_Plugin::instance()->get_discovery()->parse_relation_object($args['child_object']);
        
        // Current CCT should be in parent_object (since it's the one being saved)
        $is_parent = ($parent_parsed['type'] === 'cct' && $parent_parsed['slug'] === $current_cct);
        
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
     * @param int    $parent_id Parent item ID (or term ID for taxonomy relations)
     * @param int    $child_id  Child item ID (or term ID for taxonomy relations)
     * @return bool Success
     */
    private function create_relation($relation, $parent_id, $child_id) {
        global $wpdb;
        
        $relation_id = $relation->get_id();
        $table = $wpdb->prefix . 'jet_rel_' . $relation_id;
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if (!$table_exists) {
            jet_injector_log_error('Relation table does not exist', [
                'table' => $table,
                'relation_id' => $relation_id,
            ]);
            return false;
        }
        
        // Validate IDs
        if (empty($parent_id) || empty($child_id)) {
            jet_injector_log_error('Invalid parent or child ID', [
                'parent_id' => $parent_id,
                'child_id' => $child_id,
            ]);
            return false;
        }
        
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
        
        if ($result === false) {
            jet_injector_log_error('Failed to insert relation', [
                'table' => $table,
                'parent_id' => $parent_id,
                'child_id' => $child_id,
                'error' => $wpdb->last_error,
            ]);
            return false;
        }
        
        jet_injector_debug_log('Relation created successfully', [
            'parent_id' => $parent_id,
            'child_id' => $child_id,
            'table' => $table,
        ]);
        
        return true;
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
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if (!$table_exists) {
            jet_injector_log_warning('Cannot clear relations - table does not exist', [
                'table' => $table,
                'relation_id' => $relation_id,
            ]);
            return;
        }
        
        $column = $is_parent ? 'parent_object_id' : 'child_object_id';
        
        $deleted = $wpdb->delete(
            $table,
            [$column => $item_id],
            ['%d']
        );
        
        jet_injector_debug_log('Cleared existing relations', [
            'item_id' => $item_id,
            'relation_id' => $relation_id,
            'is_parent' => $is_parent,
            'deleted_count' => $deleted,
        ]);
    }
}

