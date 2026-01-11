<?php
/**
 * Discovery Engine - Module A
 *
 * Discovers CCTs, Fields, and Relations from JetEngine
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Discovery Engine Class
 */
class Jet_Injector_Discovery {
    
    /**
     * Cache for CCTs to avoid repeated API calls
     *
     * @var array|null
     */
    private $ccts_cache = null;
    
    /**
     * Cache for relations
     *
     * @var array|null
     */
    private $relations_cache = null;
    
    /**
     * Get all Custom Content Types from JetEngine
     *
     * @return array Array of CCT objects with slug, name, and fields
     */
    public function get_all_ccts() {
        // Return cache if available
        if ($this->ccts_cache !== null) {
            return $this->ccts_cache;
        }
        
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            jet_injector_log_error('CCT Module not found');
            return [];
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        if (!$module || !isset($module->manager)) {
            jet_injector_log_error('CCT Manager not available');
            return [];
        }
        
        $raw_ccts = $module->manager->get_content_types();
        
        if (empty($raw_ccts)) {
            jet_injector_debug_log('No CCTs found');
            return [];
        }
        
        $ccts = [];
        
        // FIXED: get_content_types() returns Factory OBJECTS, not arrays!
        foreach ($raw_ccts as $slug => $cct_instance) {
            // Safety check - ensure we have a valid object
            if (!is_object($cct_instance)) {
                jet_injector_log_warning('Invalid CCT instance', ['slug' => $slug, 'type' => gettype($cct_instance)]);
                continue;
            }
            
            // Use object methods to get data
            $ccts[] = [
                'slug' => $slug,
                'name' => $cct_instance->get_arg('name') ?: $slug,
                'singular_name' => $cct_instance->get_arg('name') ?: $slug,
                'fields' => $this->get_cct_fields_from_instance($cct_instance),
                'type_id' => property_exists($cct_instance, 'type_id') ? $cct_instance->type_id : null,
            ];
        }
        
        // Cache the results
        $this->ccts_cache = $ccts;
        
        jet_injector_debug_log('Discovered CCTs', ['count' => count($ccts), 'slugs' => wp_list_pluck($ccts, 'slug')]);
        
        return $ccts;
    }
    
    /**
     * Get a single CCT by slug
     *
     * @param string $cct_slug CCT slug
     * @return array|null CCT data or null if not found
     */
    public function get_cct($cct_slug) {
        // Try direct lookup first (more efficient)
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return null;
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        if (!$module || !isset($module->manager)) {
            return null;
        }
        
        // get_content_types with a slug returns a single CCT or false
        $cct_instance = $module->manager->get_content_types($cct_slug);
        
        if (!$cct_instance || !is_object($cct_instance)) {
            return null;
        }
        
        return [
            'slug' => $cct_slug,
            'name' => $cct_instance->get_arg('name') ?: $cct_slug,
            'singular_name' => $cct_instance->get_arg('name') ?: $cct_slug,
            'fields' => $this->get_cct_fields_from_instance($cct_instance),
            'type_id' => property_exists($cct_instance, 'type_id') ? $cct_instance->type_id : null,
        ];
    }
    
    /**
     * Get fields from a CCT Factory instance
     *
     * @param object $cct_instance CCT Factory instance
     * @return array Array of field objects
     */
    private function get_cct_fields_from_instance($cct_instance) {
        $fields = [];
        
        // Use the get_fields_list method if available
        if (method_exists($cct_instance, 'get_fields_list')) {
            $field_list = $cct_instance->get_fields_list();
            
            if (!empty($field_list)) {
                foreach ($field_list as $field_name => $field_label) {
                    $fields[] = [
                        'name' => $field_name,
                        'title' => $field_label,
                        'type' => 'text', // Default, we don't have type info from get_fields_list
                        'options' => [],
                    ];
                }
            }
        }
        
        // Try to get more detailed field info if available
        $args = $cct_instance->get_arg('fields');
        if (!empty($args) && is_array($args)) {
            $fields = [];
            foreach ($args as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $fields[] = [
                    'name' => isset($field['name']) ? $field['name'] : '',
                    'title' => isset($field['title']) ? $field['title'] : (isset($field['name']) ? $field['name'] : ''),
                    'type' => isset($field['type']) ? $field['type'] : 'text',
                    'options' => isset($field['options']) ? $field['options'] : [],
                ];
            }
        }
        
        return $fields;
    }
    
    /**
     * Get all JetEngine relations
     *
     * @return array Array of relation objects
     */
    public function get_all_relations() {
        // Return cache if available
        if ($this->relations_cache !== null) {
            return $this->relations_cache;
        }
        
        if (!function_exists('jet_engine') || !jet_engine()->relations) {
            jet_injector_log_error('JetEngine Relations not available');
            return [];
        }
        
        $raw_relations = jet_engine()->relations->get_active_relations();
        
        if (empty($raw_relations)) {
            jet_injector_debug_log('No relations found');
            return [];
        }
        
        $relations = [];
        
        foreach ($raw_relations as $relation_id => $relation_obj) {
            // Safety check
            if (!is_object($relation_obj) || !method_exists($relation_obj, 'get_args')) {
                continue;
            }
            
            $args = $relation_obj->get_args();
            
            // Generate a readable name if not set
            $name = '';
            if (!empty($args['name'])) {
                $name = $args['name'];
            } elseif (!empty($args['labels']['name'])) {
                $name = $args['labels']['name'];
            } else {
                // Generate from parent/child using helper method
                $parent_name = $this->get_relation_object_name($args['parent_object']);
                $child_name = $this->get_relation_object_name($args['child_object']);
                $name = $parent_name . ' → ' . $child_name;
            }
            
            // Check if relation table exists
            $table_exists = $this->relation_table_exists($relation_id);
            
            $relations[] = [
                'id' => $relation_id,
                'name' => $name,
                'parent_object' => isset($args['parent_object']) ? $args['parent_object'] : '',
                'child_object' => isset($args['child_object']) ? $args['child_object'] : '',
                'type' => isset($args['type']) ? $args['type'] : 'one_to_many',
                'parent_rel' => isset($args['parent_rel']) ? $args['parent_rel'] : null,
                'is_hierarchy' => !empty($args['parent_rel']),
                'table_exists' => $table_exists,
                'table_name' => 'wp_jet_rel_' . $relation_id,
                'raw_args' => $args,
                // Don't pass the full object to JS - just pass what's needed
            ];
        }
        
        // Cache the results
        $this->relations_cache = $relations;
        
        jet_injector_debug_log('Discovered relations', [
            'count' => count($relations),
            'relation_names' => wp_list_pluck($relations, 'name')
        ]);
        
        return $relations;
    }
    
    /**
     * Clear caches (useful for testing or after updates)
     */
    public function clear_cache() {
        $this->ccts_cache = null;
        $this->relations_cache = null;
    }
    
    /**
     * Get relations for a specific CCT
     *
     * @param string $cct_slug         CCT slug
     * @param string $position         Position filter: 'parent', 'child', or 'both'
     * @param bool   $include_hierarchy Include grandparent relations
     * @return array Array of relations where this CCT is involved
     */
    public function get_relations_for_cct($cct_slug, $position = 'both', $include_hierarchy = true) {
        $all_relations = $this->get_all_relations();
        $cct_relations = [];
        
        foreach ($all_relations as $relation) {
            $is_parent = $this->is_cct_in_relation($cct_slug, $relation['parent_object']);
            $is_child = $this->is_cct_in_relation($cct_slug, $relation['child_object']);
            
            $should_include = false;
            
            switch ($position) {
                case 'parent':
                    $should_include = $is_parent;
                    break;
                case 'child':
                    $should_include = $is_child;
                    break;
                case 'both':
                default:
                    $should_include = $is_parent || $is_child;
                    break;
            }
            
            if ($should_include) {
                // Add position info
                $relation['cct_position'] = $is_parent ? 'parent' : 'child';
                $cct_relations[] = $relation;
            }
        }
        
        // Include grandparent relations if enabled
        if ($include_hierarchy) {
            $grandparent_relations = $this->get_grandparent_relations($cct_slug);
            $cct_relations = array_merge($cct_relations, $grandparent_relations);
        }
        
        jet_injector_debug_log("Relations for CCT: {$cct_slug}", [
            'position' => $position,
            'count' => count($cct_relations),
            'relations' => wp_list_pluck($cct_relations, 'name')
        ]);
        
        return $cct_relations;
    }
    
    /**
     * Get grandparent relations for a CCT
     *
     * @param string $cct_slug CCT slug
     * @return array Grandparent relations
     */
    public function get_grandparent_relations($cct_slug) {
        $all_relations = $this->get_all_relations();
        $grandparent_relations = [];
        
        // Find relations where this CCT is the child
        $parent_relations = $this->get_relations_for_cct($cct_slug, 'child', false);
        
        foreach ($parent_relations as $parent_rel) {
            $parent_object = $parent_rel['parent_object'];
            
            foreach ($all_relations as $relation) {
                if ($this->is_cct_in_relation($parent_object, $relation['child_object'])) {
                    $relation['cct_position'] = 'grandparent';
                    $relation['grandparent_path'] = [
                        'grandparent' => $relation['parent_object'],
                        'parent' => $parent_object,
                        'child' => $cct_slug,
                    ];
                    $grandparent_relations[] = $relation;
                }
            }
        }
        
        return $grandparent_relations;
    }
    
    /**
     * Check if a CCT slug matches a relation object string
     *
     * @param string $cct_slug      CCT slug to check
     * @param string $relation_obj  Relation object string from JetEngine
     * @return bool
     */
    private function is_cct_in_relation($cct_slug, $relation_obj) {
        if (!is_string($relation_obj)) {
            return false;
        }
        
        // Handle "cct::slug" format
        if (strpos($relation_obj, 'cct::') === 0) {
            $rel_cct_slug = str_replace('cct::', '', $relation_obj);
            return $rel_cct_slug === $cct_slug;
        }
        
        // Handle "terms::" and "posts::" - these are NOT CCT relations
        if (strpos($relation_obj, 'terms::') === 0 || strpos($relation_obj, 'posts::') === 0) {
            return false; // Not a CCT
        }
        
        // Direct match (legacy format without prefix)
        return $relation_obj === $cct_slug;
    }
    
    /**
     * Parse relation object string into type and slug
     *
     * @param string $relation_obj Relation object string (e.g., "cct::vehicles", "terms::category")
     * @return array ['type' => 'cct|terms|posts', 'slug' => 'slug_name']
     */
    public function parse_relation_object($relation_obj) {
        if (!is_string($relation_obj)) {
            return ['type' => 'unknown', 'slug' => ''];
        }
        
        // Check for type delimiter
        if (strpos($relation_obj, '::') !== false) {
            list($type, $slug) = explode('::', $relation_obj, 2);
            return [
                'type' => $type, // cct, terms, posts
                'slug' => $slug,
            ];
        }
        
        // No delimiter - assume legacy CCT format
        return [
            'type' => 'cct',
            'slug' => $relation_obj,
        ];
    }
    
    /**
     * Get human-readable name for relation object
     *
     * @param string $relation_obj Relation object string
     * @return string Readable name
     */
    public function get_relation_object_name($relation_obj) {
        $parsed = $this->parse_relation_object($relation_obj);
        
        switch ($parsed['type']) {
            case 'cct':
                $cct = $this->get_cct($parsed['slug']);
                return $cct ? $cct['name'] : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            case 'terms':
                $taxonomy = get_taxonomy($parsed['slug']);
                return $taxonomy ? $taxonomy->label : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            case 'posts':
                $post_type = get_post_type_object($parsed['slug']);
                return $post_type ? $post_type->label : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            default:
                return ucfirst(str_replace('_', ' ', $parsed['slug']));
        }
    }
    
    /**
     * Get relation types
     *
     * @return array Relation type options
     */
    public function get_relation_types() {
        return [
            'one_to_one' => __('One to One', 'jet-relation-injector'),
            'one_to_many' => __('One to Many', 'jet-relation-injector'),
            'many_to_many' => __('Many to Many', 'jet-relation-injector'),
        ];
    }
    
    /**
     * Get displayable fields for a CCT
     *
     * @param string $cct_slug CCT slug
     * @return array Field options suitable for display
     */
    public function get_displayable_fields($cct_slug) {
        $cct = $this->get_cct($cct_slug);
        
        if (!$cct) {
            return [];
        }
        
        $displayable = [
            '_ID' => __('Item ID', 'jet-relation-injector'),
        ];
        
        foreach ($cct['fields'] as $field) {
            if (in_array($field['type'], ['text', 'textarea', 'wysiwyg', 'select', 'radio'])) {
                $displayable[$field['name']] = $field['title'];
            }
        }
        
        return $displayable;
    }
    
    /**
     * Check if a relation database table exists
     *
     * @param int $relation_id Relation ID
     * @return bool True if table exists
     */
    public function relation_table_exists($relation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jet_rel_' . absint($relation_id);
        
        // Check if table exists using SHOW TABLES
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return !empty($table_exists);
    }
    
    /**
     * Verify JetEngine dependencies
     *
     * @return array Status of dependencies
     */
    public function verify_dependencies() {
        $status = [
            'jetengine' => class_exists('Jet_Engine'),
            'cct_module' => class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module'),
            'relations_module' => function_exists('jet_engine') && isset(jet_engine()->relations),
            'all_ok' => false,
        ];
        
        $status['all_ok'] = $status['jetengine'] && $status['cct_module'] && $status['relations_module'];
        
        if (!$status['all_ok']) {
            jet_injector_log_warning('Dependency check failed', $status);
        }
        
        return $status;
    }
}
