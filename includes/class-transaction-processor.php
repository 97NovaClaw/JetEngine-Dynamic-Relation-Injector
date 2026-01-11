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
            $slug = $config->object_slug; // Use different variable name to avoid closure issues
            
            // CREATED hook: ($item, $item_id, $handler)
            add_action(
                "jet-engine/custom-content-types/created-item/{$slug}",
                function($item, $item_id, $handler) use ($slug) {
                    $this->process_created_item($item, $item_id, $handler, $slug);
                },
                10,
                3
            );
            
            // UPDATED hook: ($item, $prev_item, $handler) - DIFFERENT SIGNATURE!
            add_action(
                "jet-engine/custom-content-types/updated-item/{$slug}",
                function($item, $prev_item, $handler) use ($slug) {
                    // Extract item_id from $item array
                    $item_id = isset($item['_ID']) ? $item['_ID'] : 0;
                    $this->process_created_item($item, $item_id, $handler, $slug);
                },
                10,
                3
            );
            
            jet_injector_debug_log("Registered hooks for CCT: {$slug}");
        }
    }
    
    /**
     * Process relation save from injected form data
     *
     * Created hook: do_action('created-item/{slug}', $item, $item_id, $handler)
     * Updated hook: do_action('updated-item/{slug}', $item, $prev_item, $handler)
     *
     * @param array  $item      CCT item data array
     * @param int    $item_id   CCT item ID (integer)
     * @param object $handler   Item_Handler instance
     * @param string $cct_slug  CCT slug (passed via closure)
     */
    public function process_created_item($item, $item_id, $handler, $cct_slug) {
        // Wrap in try-catch to prevent crashes
        try {
            jet_injector_debug_log('🔥 HOOK FIRED: process_created_item called', [
                'cct_slug' => $cct_slug,
                'item_id' => $item_id,
                'item_data_keys' => array_keys($item),
                'handler_type' => get_class($handler),
                'has_post_data' => !empty($_POST),
                'has_injector_data' => !empty($_POST['jet_injector_relations']),
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
            
            jet_injector_debug_log('✅ Nonce verified, parsing relation data...');
            
            // Parse the relation data (it's JSON-encoded)
            $relations_data = json_decode(stripslashes($_POST['jet_injector_relations']), true);
            
            if (!is_array($relations_data)) {
                jet_injector_log_error('Invalid relations data format', ['raw' => $_POST['jet_injector_relations']]);
                return;
            }
            
            jet_injector_debug_log('Parsed relation data', $relations_data);
            
            // Process each relation - PASS cct_slug!
            foreach ($relations_data as $relation_id => $relation_items) {
                jet_injector_debug_log('Processing relation ID: ' . $relation_id, ['cct_slug' => $cct_slug]);
                $this->save_relation_items($item_id, $relation_id, $relation_items, $cct_slug);
            }
            
            jet_injector_debug_log('✅ Relation processing complete', ['item_id' => $item_id]);
            
        } catch (Throwable $e) {
            jet_injector_log_error('FATAL in process_relation_save: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't re-throw - let the CCT save complete
        }
    }
    
    /**
     * Save relation items for a specific relation
     *
     * @param int    $item_id        CCT item ID
     * @param int    $relation_id    Relation ID
     * @param array  $relation_items Array of related item IDs
     * @param string $cct_slug       CCT slug from hook registration
     */
    private function save_relation_items($item_id, $relation_id, $relation_items, $cct_slug) {
        try {
            if (!function_exists('jet_engine') || !jet_engine()->relations) {
                jet_injector_log_error('JetEngine relations not available');
                return;
            }
            
            jet_injector_debug_log('Getting relation object for ID: ' . $relation_id);
            
            // Get the relation object
            $relations = jet_engine()->relations->get_active_relations();
            
            if (!isset($relations[$relation_id])) {
                jet_injector_log_error('Relation not found', ['relation_id' => $relation_id]);
                return;
            }
            
            $relation = $relations[$relation_id];
            $args = $relation->get_args();
            
            jet_injector_debug_log('Relation found', [
                'relation_id' => $relation_id,
                'parent_object' => $args['parent_object'],
                'child_object' => $args['child_object'],
                'type' => $args['type'],
            ]);
            
            // We already have the CCT slug from the closure! No need to dig it out of objects.
            jet_injector_debug_log('✅ Using CCT slug from hook registration', ['cct_slug' => $cct_slug]);
            
            // Parse parent and child objects to determine types
            $discovery = Jet_Injector_Plugin::instance()->get_discovery();
            $parent_parsed = $discovery->parse_relation_object($args['parent_object']);
            $child_parsed = $discovery->parse_relation_object($args['child_object']);
            
            jet_injector_debug_log('Relation objects parsed', [
                'parent' => $parent_parsed,
                'child' => $child_parsed,
            ]);
            
            // Determine if current CCT is parent or child
            $is_parent = ($parent_parsed['type'] === 'cct' && $parent_parsed['slug'] === $cct_slug);
        
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
                jet_injector_debug_log('✅ Relation created', [
                    'parent_id' => $is_parent ? $item_id : $related_item_id,
                    'child_id' => $is_parent ? $related_item_id : $item_id,
                ]);
            } else {
                jet_injector_log_error('❌ Failed to create relation', [
                    'relation_id' => $relation_id,
                    'parent_id' => $is_parent ? $item_id : $related_item_id,
                    'child_id' => $is_parent ? $related_item_id : $item_id,
                ]);
            }
        }
        
        } catch (Throwable $e) {
            jet_injector_log_error('💥 FATAL in save_relation_items: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'item_id' => $item_id,
                'relation_id' => $relation_id,
            ]);
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
        
        // Get relation args for parent_rel (grandparent support)
        $args = $relation->get_args();
        $parent_rel = isset($args['parent_rel']) ? absint($args['parent_rel']) : null;
        
        // Insert relation - MUST include rel_id for JetEngine to recognize it!
        $result = $wpdb->insert(
            $table,
            [
                'rel_id'           => $relation_id,  // Required by JetEngine!
                'parent_rel'       => $parent_rel,   // For grandparent relations
                'parent_object_id' => $parent_id,
                'child_object_id'  => $child_id,
            ],
            ['%s', '%d', '%d', '%d']  // rel_id is text type
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

