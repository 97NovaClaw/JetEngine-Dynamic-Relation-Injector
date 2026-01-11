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
     * AJAX: Create a new item (CCT, taxonomy term, or post)
     */
    public function ajax_create_item() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $object_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        // Sanitize item_data array
        $item_data = isset($_POST['item_data']) && is_array($_POST['item_data']) 
            ? array_map('sanitize_text_field', $_POST['item_data']) 
            : [];
        
        if (empty($object_slug)) {
            wp_send_json_error(['message' => __('Object slug is required', 'jet-relation-injector')]);
            return;
        }
        
        jet_injector_debug_log('Creating item', [
            'object_slug' => $object_slug,
            'item_data' => $item_data,
        ]);
        
        // Parse object type
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $parsed = $discovery->parse_relation_object($object_slug);
        
        // Route to appropriate create method
        switch ($parsed['type']) {
            case 'terms':
                $result = $this->create_taxonomy_term($parsed['slug'], $item_data);
                break;
                
            case 'posts':
                $result = $this->create_post_item($parsed['slug'], $item_data);
                break;
                
            case 'cct':
            default:
                $result = $this->create_cct_item($parsed['slug'], $item_data);
                break;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
            return;
        }
        
        wp_send_json_success([
            'item_id' => $result,
            'message' => __('Item created successfully', 'jet-relation-injector'),
        ]);
    }
    
    /**
     * Create a new taxonomy term
     *
     * @param string $taxonomy  Taxonomy slug
     * @param array  $item_data Term data
     * @return int|WP_Error Term ID or error
     */
    private function create_taxonomy_term($taxonomy, $item_data) {
        // Check if user can edit terms
        if (!current_user_can('manage_categories')) {
            return new WP_Error('unauthorized', __('Unauthorized to create terms', 'jet-relation-injector'));
        }
        
        $term_name = isset($item_data['title']) ? $item_data['title'] : '';
        
        if (empty($term_name)) {
            return new WP_Error('missing_name', __('Term name is required', 'jet-relation-injector'));
        }
        
        $args = [
            'slug' => isset($item_data['slug']) ? sanitize_title($item_data['slug']) : sanitize_title($term_name),
        ];
        
        if (isset($item_data['description'])) {
            $args['description'] = sanitize_textarea_field($item_data['description']);
        }
        
        $result = wp_insert_term($term_name, $taxonomy, $args);
        
        if (is_wp_error($result)) {
            jet_injector_log_error('Failed to create term', [
                'taxonomy' => $taxonomy,
                'term_name' => $term_name,
                'error' => $result->get_error_message(),
            ]);
            return $result;
        }
        
        $term_id = $result['term_id'];
        
        jet_injector_debug_log('Taxonomy term created', [
            'taxonomy' => $taxonomy,
            'term_id' => $term_id,
            'term_name' => $term_name,
        ]);
        
        return $term_id;
    }
    
    /**
     * Create a new post
     *
     * @param string $post_type Post type slug
     * @param array  $item_data Post data
     * @return int|WP_Error Post ID or error
     */
    private function create_post_item($post_type, $item_data) {
        $post_data = [
            'post_type' => $post_type,
            'post_title' => isset($item_data['title']) ? $item_data['title'] : __('New Item', 'jet-relation-injector'),
            'post_status' => 'publish',
        ];
        
        if (isset($item_data['content'])) {
            $post_data['post_content'] = wp_kses_post($item_data['content']);
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            jet_injector_log_error('Failed to create post', [
                'post_type' => $post_type,
                'error' => $post_id->get_error_message(),
            ]);
            return $post_id;
        }
        
        jet_injector_debug_log('Post created', [
            'post_type' => $post_type,
            'post_id' => $post_id,
        ]);
        
        return $post_id;
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
        
        $object_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (empty($object_slug) || empty($item_id)) {
            wp_send_json_error(['message' => __('Object slug and item ID are required', 'jet-relation-injector')]);
            return;
        }
        
        // Parse object type
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $parsed = $discovery->parse_relation_object($object_slug);
        
        // Route to appropriate get method
        switch ($parsed['type']) {
            case 'terms':
                $item = $this->get_taxonomy_term($parsed['slug'], $item_id);
                break;
                
            case 'posts':
                $item = $this->get_post_item($parsed['slug'], $item_id);
                break;
                
            case 'cct':
            default:
                $item = $this->get_cct_item($parsed['slug'], $item_id);
                break;
        }
        
        if (!$item) {
            wp_send_json_error(['message' => __('Item not found', 'jet-relation-injector')]);
            return;
        }
        
        wp_send_json_success(['item' => $item]);
    }
    
    /**
     * Get a single taxonomy term
     *
     * @param string $taxonomy Taxonomy slug
     * @param int    $term_id  Term ID
     * @return array|null
     */
    private function get_taxonomy_term($taxonomy, $term_id) {
        $term = get_term($term_id, $taxonomy);
        
        if (is_wp_error($term) || !$term) {
            return null;
        }
        
        return [
            'id' => $term->term_id,
            'title' => $term->name,
            'fields' => [
                'name' => [
                    'name' => 'name',
                    'title' => __('Name', 'jet-relation-injector'),
                    'value' => $term->name,
                    'type' => 'text',
                ],
                'slug' => [
                    'name' => 'slug',
                    'title' => __('Slug', 'jet-relation-injector'),
                    'value' => $term->slug,
                    'type' => 'text',
                ],
            ],
        ];
    }
    
    /**
     * Get a single post
     *
     * @param string $post_type Post type slug
     * @param int    $post_id   Post ID
     * @return array|null
     */
    private function get_post_item($post_type, $post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== $post_type) {
            return null;
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'fields' => [
                'title' => [
                    'name' => 'title',
                    'title' => __('Title', 'jet-relation-injector'),
                    'value' => $post->post_title,
                    'type' => 'text',
                ],
                'status' => [
                    'name' => 'status',
                    'title' => __('Status', 'jet-relation-injector'),
                    'value' => $post->post_status,
                    'type' => 'text',
                ],
            ],
        ];
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
     * Search items (supports CCTs, Taxonomies, and Post Types)
     *
     * @param string $object_slug Object slug (may include prefix like "terms::taxonomy_name")
     * @param string $search_term Search term
     * @param int    $parent_id   Parent item ID (for filtering)
     * @param int    $relation_id Relation ID (for filtering)
     * @return array
     */
    private function search_cct_items($object_slug, $search_term = '', $parent_id = 0, $relation_id = 0) {
        // Parse object type
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $parsed = $discovery->parse_relation_object($object_slug);
        
        jet_injector_debug_log('Searching items with parsed object', [
            'object_slug' => $object_slug,
            'parsed_type' => $parsed['type'],
            'parsed_slug' => $parsed['slug'],
            'search_term' => $search_term,
        ]);
        
        // Route to appropriate search method based on type
        switch ($parsed['type']) {
            case 'terms':
                return $this->search_taxonomy_terms($parsed['slug'], $search_term);
                
            case 'posts':
                return $this->search_post_type_items($parsed['slug'], $search_term);
                
            case 'cct':
            default:
                return $this->search_cct_items_internal($parsed['slug'], $search_term, $parent_id, $relation_id);
        }
    }
    
    /**
     * Search taxonomy terms
     *
     * @param string $taxonomy    Taxonomy slug
     * @param string $search_term Search term
     * @return array
     */
    private function search_taxonomy_terms($taxonomy, $search_term = '') {
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 20,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        if (!empty($search_term)) {
            $args['search'] = $search_term;
        }
        
        $terms = get_terms($args);
        
        if (is_wp_error($terms)) {
            jet_injector_log_error('Failed to get taxonomy terms', [
                'taxonomy' => $taxonomy,
                'error' => $terms->get_error_message(),
            ]);
            return [];
        }
        
        $items = [];
        foreach ($terms as $term) {
            $items[] = [
                'id' => $term->term_id,
                'title' => $term->name,
                'fields' => [
                    'name' => [
                        'name' => 'name',
                        'title' => __('Name', 'jet-relation-injector'),
                        'value' => $term->name,
                        'type' => 'text',
                    ],
                    'slug' => [
                        'name' => 'slug',
                        'title' => __('Slug', 'jet-relation-injector'),
                        'value' => $term->slug,
                        'type' => 'text',
                    ],
                    'count' => [
                        'name' => 'count',
                        'title' => __('Count', 'jet-relation-injector'),
                        'value' => $term->count,
                        'type' => 'number',
                    ],
                ],
            ];
        }
        
        jet_injector_debug_log('Found taxonomy terms', [
            'taxonomy' => $taxonomy,
            'count' => count($items),
        ]);
        
        return $items;
    }
    
    /**
     * Search post type items
     *
     * @param string $post_type   Post type slug
     * @param string $search_term Search term
     * @return array
     */
    private function search_post_type_items($post_type, $search_term = '') {
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        if (!empty($search_term)) {
            $args['s'] = $search_term;
        }
        
        $query = new \WP_Query($args);
        $items = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $items[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'fields' => [
                        'title' => [
                            'name' => 'title',
                            'title' => __('Title', 'jet-relation-injector'),
                            'value' => get_the_title(),
                            'type' => 'text',
                        ],
                        'status' => [
                            'name' => 'status',
                            'title' => __('Status', 'jet-relation-injector'),
                            'value' => get_post_status(),
                            'type' => 'text',
                        ],
                    ],
                ];
            }
            wp_reset_postdata();
        }
        
        jet_injector_debug_log('Found post type items', [
            'post_type' => $post_type,
            'count' => count($items),
        ]);
        
        return $items;
    }
    
    /**
     * Search CCT items (internal method)
     *
     * @param string $cct_slug    CCT slug (without prefix)
     * @param string $search_term Search term
     * @param int    $parent_id   Parent item ID (for filtering)
     * @param int    $relation_id Relation ID (for filtering)
     * @return array
     */
    private function search_cct_items_internal($cct_slug, $search_term = '', $parent_id = 0, $relation_id = 0) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            jet_injector_log_error('CCT Module class not found');
            return [];
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        // Get content type (Factory object) - NOT item handler!
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type) {
            jet_injector_log_error('CCT content type not found', ['cct_slug' => $cct_slug]);
            return [];
        }
        
        // Build query args - NOTE: db->query() signature is: query($args, $limit, $offset, $order, $rel)
        $args = [];
        $limit = 20;
        $offset = 0;
        $order = ['_ID' => 'DESC'];
        
        // Add search filter if provided - uses _cct_search with keyword and fields
        if (!empty($search_term)) {
            $args['_cct_search'] = [
                'keyword' => $search_term,
                'fields' => [], // Empty = search all fields
            ];
        }
        
        $raw_items = $content_type->db->query($args, $limit, $offset, $order);
        
        jet_injector_debug_log('CCT query executed', [
            'cct_slug' => $cct_slug,
            'search_term' => $search_term,
            'result_count' => is_array($raw_items) ? count($raw_items) : 0,
        ]);
        
        if (empty($raw_items)) {
            jet_injector_debug_log('No CCT items found', ['cct_slug' => $cct_slug]);
            return [];
        }
        
        // Format items for response
        $formatted_items = [];
        foreach ($raw_items as $item) {
            // Determine title - try common title fields
            $title = '#' . $item['_ID'];
            if (!empty($item['title'])) {
                $title = $item['title'];
            } elseif (!empty($item['name'])) {
                $title = $item['name'];
            }
            
            $formatted_items[] = [
                'id' => $item['_ID'],
                'title' => $title,
                'fields' => $item, // Pass raw item data for fields display
            ];
        }
        
        jet_injector_debug_log('Found CCT items', [
            'cct_slug' => $cct_slug,
            'count' => count($formatted_items),
        ]);
        
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
            jet_injector_log_error('CCT Module class not found');
            return new \WP_Error('module_not_found', __('CCT module not found', 'jet-relation-injector'));
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        // Get content type (Factory) - NOT item handler
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type) {
            jet_injector_log_error('CCT content type not found', ['cct_slug' => $cct_slug]);
            return new \WP_Error('cct_not_found', __('CCT not found', 'jet-relation-injector'));
        }
        
        // Get the CCT's actual fields
        $cct_fields = $content_type->get_formatted_fields();
        
        jet_injector_debug_log('CCT fields schema', [
            'cct_slug' => $cct_slug,
            'field_names' => array_keys($cct_fields),
        ]);
        
        // Build item data using ONLY valid CCT fields
        $item = [];
        
        // If we have a 'title' in item_data, try to find the first text field to use it
        $title_value = isset($item_data['title']) ? sanitize_text_field($item_data['title']) : '';
        $title_assigned = false;
        
        foreach ($cct_fields as $field_name => $field_data) {
            // Skip system fields
            if (in_array($field_name, ['_ID', 'cct_status', 'cct_author_id', 'cct_created', 'cct_modified', 'cct_single_post_id'])) {
                continue;
            }
            
            // If user provided this specific field, use it
            if (isset($item_data[$field_name])) {
                $item[$field_name] = sanitize_text_field($item_data[$field_name]);
            }
            // Assign title to first text field if not already assigned
            elseif (!$title_assigned && $title_value && isset($field_data['type']) && in_array($field_data['type'], ['text', 'textarea'])) {
                $item[$field_name] = $title_value;
                $title_assigned = true;
                jet_injector_debug_log('Assigned title to field', [
                    'field_name' => $field_name,
                    'value' => $title_value,
                ]);
            }
            // Default empty for required fields
            elseif (!empty($field_data['is_required'])) {
                $item[$field_name] = '';
            }
        }
        
        // Set required system fields for CCT insert
        $item['_ID'] = null; // Required for new item
        $item['cct_author_id'] = get_current_user_id();
        $item['cct_created'] = current_time('mysql');
        $item['cct_modified'] = current_time('mysql');
        $item['cct_status'] = 'publish';
        
        jet_injector_debug_log('Inserting CCT item', [
            'cct_slug' => $cct_slug,
            'item' => $item,
        ]);
        
        // Insert via the database object
        $item_id = $content_type->db->insert($item);
        
        if (!$item_id) {
            $error = $content_type->db->get_errors();
            jet_injector_log_error('Failed to create CCT item', [
                'cct_slug' => $cct_slug,
                'item' => $item,
                'db_error' => $error,
            ]);
            return new \WP_Error('create_failed', __('Failed to create item: ', 'jet-relation-injector') . $error);
        }
        
        jet_injector_debug_log('CCT item created successfully', [
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

