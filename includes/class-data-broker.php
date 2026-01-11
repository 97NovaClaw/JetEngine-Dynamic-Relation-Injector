<?php
/**
 * Data Broker - Module E
 *
 * AJAX API for searching and creating related CCT items
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Data Broker Class
 */
class Jet_Injector_Data_Broker {
    
    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Search for related items
        add_action('wp_ajax_jet_injector_search_items', [$this, 'ajax_search_items']);
        
        // Create new related item
        add_action('wp_ajax_jet_injector_create_item', [$this, 'ajax_create_item']);
        
        // Get item details
        add_action('wp_ajax_jet_injector_get_item', [$this, 'ajax_get_item']);
        
        // Get grandparent items (for hierarchical relations)
        add_action('wp_ajax_jet_injector_get_grandparent_items', [$this, 'ajax_get_grandparent_items']);
    }
    
    /**
     * AJAX: Search for items in a CCT
     */
    public function ajax_search_items() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return; // Safety return
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $relation_id = isset($_POST['relation_id']) ? absint($_POST['relation_id']) : 0;
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
            return; // Safety return
        }
        
        jet_injector_debug_log('Searching items', [
            'cct_slug' => $cct_slug,
            'search_term' => $search_term,
            'parent_id' => $parent_id,
        ]);
        
        $items = $this->search_cct_items($cct_slug, $search_term, $parent_id, $relation_id);
        
        wp_send_json_success([
            'items' => $items,
            'count' => count($items),
        ]);
    }
    
    /**
     * AJAX: Create a new CCT item
     */
    public function ajax_create_item() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        // Sanitize item_data array
        $item_data = isset($_POST['item_data']) && is_array($_POST['item_data']) 
            ? array_map('sanitize_text_field', $_POST['item_data']) 
            : [];
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
            return;
        }
        
        jet_injector_debug_log('Creating item', [
            'cct_slug' => $cct_slug,
            'item_data' => $item_data,
        ]);
        
        $result = $this->create_cct_item($cct_slug, $item_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }
        
        wp_send_json_success([
            'item_id' => $result,
            'message' => __('Item created successfully', 'jet-relation-injector'),
        ]);
    }
    
    /**
     * AJAX: Get item details
     */
    public function ajax_get_item() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (empty($cct_slug) || empty($item_id)) {
            wp_send_json_error(['message' => __('CCT slug and item ID are required', 'jet-relation-injector')]);
            return;
        }
        
        $item = $this->get_cct_item($cct_slug, $item_id);
        
        if (!$item) {
            wp_send_json_error(['message' => __('Item not found', 'jet-relation-injector')]);
            return;
        }
        
        wp_send_json_success(['item' => $item]);
    }
    
    /**
     * AJAX: Get grandparent items for hierarchical relations
     */
    public function ajax_get_grandparent_items() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
        }
        
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $grandparent_relation_id = isset($_POST['grandparent_relation_id']) ? intval($_POST['grandparent_relation_id']) : 0;
        
        if (empty($parent_id) || empty($grandparent_relation_id)) {
            wp_send_json_error(['message' => __('Parent ID and grandparent relation ID are required', 'jet-relation-injector')]);
        }
        
        $items = $this->get_grandparent_items($parent_id, $grandparent_relation_id);
        
        wp_send_json_success([
            'items' => $items,
            'count' => count($items),
        ]);
    }
    
    /**
     * Search CCT items
     *
     * @param string $cct_slug    CCT slug
     * @param string $search_term Search term
     * @param int    $parent_id   Parent item ID (for filtering)
     * @param int    $relation_id Relation ID (for filtering)
     * @return array
     */
    private function search_cct_items($cct_slug, $search_term = '', $parent_id = 0, $relation_id = 0) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return [];
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $handler = $module->manager->get_item_handler($cct_slug);
        
        if (!$handler) {
            jet_injector_log_error('CCT handler not found', ['cct_slug' => $cct_slug]);
            return [];
        }
        
        $args = [
            'limit' => 20,
            'offset' => 0,
            'order' => ['_ID' => 'DESC'],
        ];
        
        // Add search filter if provided
        if (!empty($search_term)) {
            // Get displayable fields
            $discovery = Jet_Injector_Plugin::instance()->get_discovery();
            $cct_data = $discovery->get_cct($cct_slug);
            
            if ($cct_data && !empty($cct_data['fields'])) {
                // Build OR search across text fields
                $search_fields = [];
                foreach ($cct_data['fields'] as $field) {
                    if (in_array($field['type'], ['text', 'textarea'])) {
                        $search_fields[$field['name']] = $search_term;
                    }
                }
                
                if (!empty($search_fields)) {
                    $args['_search'] = $search_term; // JetEngine's built-in search
                }
            }
        }
        
        // Filter by parent relation if provided
        if ($parent_id && $relation_id) {
            $args['_rel'] = [
                'relation_id' => $relation_id,
                'parent_id' => $parent_id,
            ];
        }
        
        $items = $handler->query_items($args);
        
        if (empty($items)) {
            return [];
        }
        
        // Format items for response
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = $this->format_item($cct_slug, $item);
        }
        
        return $formatted_items;
    }
    
    /**
     * Create a new CCT item
     *
     * @param string $cct_slug  CCT slug
     * @param array  $item_data Item data
     * @return int|WP_Error Item ID or error
     */
    private function create_cct_item($cct_slug, $item_data) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return new WP_Error('module_not_found', __('CCT module not found', 'jet-relation-injector'));
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $handler = $module->manager->get_item_handler($cct_slug);
        
        if (!$handler) {
            return new WP_Error('handler_not_found', __('CCT handler not found', 'jet-relation-injector'));
        }
        
        // Sanitize item data
        $sanitized_data = [];
        foreach ($item_data as $field => $value) {
            $sanitized_data[sanitize_key($field)] = sanitize_text_field($value);
        }
        
        // Set status as publish
        $sanitized_data['cct_status'] = 'publish';
        
        // Create the item
        $item_id = $handler->update_item($sanitized_data);
        
        if (!$item_id) {
            jet_injector_log_error('Failed to create CCT item', [
                'cct_slug' => $cct_slug,
                'item_data' => $sanitized_data,
            ]);
            return new WP_Error('create_failed', __('Failed to create item', 'jet-relation-injector'));
        }
        
        jet_injector_debug_log('CCT item created', [
            'cct_slug' => $cct_slug,
            'item_id' => $item_id,
        ]);
        
        return $item_id;
    }
    
    /**
     * Get a single CCT item
     *
     * @param string $cct_slug CCT slug
     * @param int    $item_id  Item ID
     * @return array|null
     */
    private function get_cct_item($cct_slug, $item_id) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return null;
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $handler = $module->manager->get_item_handler($cct_slug);
        
        if (!$handler) {
            return null;
        }
        
        $item = $handler->get_item($item_id);
        
        if (!$item) {
            return null;
        }
        
        return $this->format_item($cct_slug, $item);
    }
    
    /**
     * Get grandparent items
     *
     * @param int $parent_id             Parent item ID
     * @param int $grandparent_relation_id Grandparent relation ID
     * @return array
     */
    private function get_grandparent_items($parent_id, $grandparent_relation_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'jet_rel_' . $grandparent_relation_id;
        
        // Get grandparent IDs
        $grandparent_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d",
                $parent_id
            )
        );
        
        if (empty($grandparent_ids)) {
            return [];
        }
        
        // Get relation details to find CCT slug
        $relations = jet_engine()->relations->get_active_relations();
        if (!isset($relations[$grandparent_relation_id])) {
            return [];
        }
        
        $relation = $relations[$grandparent_relation_id];
        $args = $relation->get_args();
        
        // Extract CCT slug from parent_object
        $cct_slug = str_replace('cct::', '', $args['parent_object']);
        
        // Get items
        $items = [];
        foreach ($grandparent_ids as $id) {
            $item = $this->get_cct_item($cct_slug, $id);
            if ($item) {
                $items[] = $item;
            }
        }
        
        return $items;
    }
    
    /**
     * Format item for API response
     *
     * @param string $cct_slug CCT slug
     * @param array  $item     Item data
     * @return array
     */
    private function format_item($cct_slug, $item) {
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $cct_data = $discovery->get_cct($cct_slug);
        
        $formatted = [
            'id' => isset($item['_ID']) ? $item['_ID'] : 0,
            'title' => '',
            'fields' => [],
        ];
        
        // Try to determine a title
        if (isset($item['_ID'])) {
            $formatted['title'] = '#' . $item['_ID'];
        }
        
        // Add field data
        if ($cct_data && !empty($cct_data['fields'])) {
            foreach ($cct_data['fields'] as $field) {
                $field_name = $field['name'];
                if (isset($item[$field_name])) {
                    $formatted['fields'][$field_name] = [
                        'name' => $field_name,
                        'title' => $field['title'],
                        'value' => $item[$field_name],
                        'type' => $field['type'],
                    ];
                    
                    // Use first text field as title if available
                    if (empty($formatted['title']) && in_array($field['type'], ['text', 'textarea'])) {
                        $formatted['title'] = $item[$field_name];
                    }
                }
            }
        }
        
        return $formatted;
    }
}

