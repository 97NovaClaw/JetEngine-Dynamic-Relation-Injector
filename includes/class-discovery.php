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
     * Get all Custom Content Types from JetEngine
     *
     * @return array Array of CCT objects with slug, name, and fields
     */
    public function get_all_ccts() {
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
        
        foreach ($raw_ccts as $slug => $cct_data) {
            $ccts[] = [
                'slug' => $slug,
                'name' => isset($cct_data['labels']['name']) ? $cct_data['labels']['name'] : $slug,
                'singular_name' => isset($cct_data['labels']['singular_name']) ? $cct_data['labels']['singular_name'] : $slug,
                'fields' => $this->get_cct_fields($slug, $cct_data),
                'raw_data' => $cct_data,
            ];
        }
        
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
        $all_ccts = $this->get_all_ccts();
        
        foreach ($all_ccts as $cct) {
            if ($cct['slug'] === $cct_slug) {
                return $cct;
            }
        }
        
        return null;
    }
    
    /**
     * Get fields for a specific CCT
     *
     * @param string $cct_slug CCT slug
     * @param array  $cct_data CCT raw data
     * @return array Array of field objects
     */
    private function get_cct_fields($cct_slug, $cct_data) {
        if (empty($cct_data['fields'])) {
            return [];
        }
        
        $fields = [];
        
        foreach ($cct_data['fields'] as $field) {
            $fields[] = [
                'name' => isset($field['name']) ? $field['name'] : '',
                'title' => isset($field['title']) ? $field['title'] : $field['name'],
                'type' => isset($field['type']) ? $field['type'] : 'text',
                'options' => isset($field['options']) ? $field['options'] : [],
                'raw_data' => $field,
            ];
        }
        
        return $fields;
    }
    
    /**
     * Get all JetEngine relations
     *
     * @return array Array of relation objects
     */
    public function get_all_relations() {
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
            $args = $relation_obj->get_args();
            
            $relations[] = [
                'id' => $relation_id,
                'name' => isset($args['name']) ? $args['name'] : 'Relation ' . $relation_id,
                'parent_object' => isset($args['parent_object']) ? $args['parent_object'] : '',
                'parent_relation' => isset($args['parent_rel']) ? $args['parent_rel'] : '',
                'child_object' => isset($args['child_object']) ? $args['child_object'] : '',
                'child_relation' => isset($args['child_rel']) ? $args['child_rel'] : '',
                'type' => isset($args['type']) ? $args['type'] : 'one_to_many',
                'parent_rel' => isset($args['parent_rel']) ? $args['parent_rel'] : null, // For hierarchy
                'is_hierarchy' => !empty($args['parent_rel']),
                'raw_args' => $args,
                'relation_object' => $relation_obj,
            ];
        }
        
        jet_injector_debug_log('Discovered relations', [
            'count' => count($relations),
            'relation_names' => wp_list_pluck($relations, 'name')
        ]);
        
        return $relations;
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
     * Example: If "Service Guide" is child of "Vehicle", and "Vehicle" is child of "Brand"
     * This returns the "Brand -> Vehicle" relation
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
            // For each parent relation, find relations where the parent is a child
            $parent_object = $parent_rel['parent_object'];
            
            foreach ($all_relations as $relation) {
                // Check if this relation has the parent as a child
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
        
        if (!empty($grandparent_relations)) {
            jet_injector_debug_log("Found grandparent relations for {$cct_slug}", [
                'count' => count($grandparent_relations),
            ]);
        }
        
        return $grandparent_relations;
    }
    
    /**
     * Check if a CCT slug matches a relation object string
     *
     * Relation objects can be in format "cct::slug" or just "slug"
     *
     * @param string $cct_slug      CCT slug to check
     * @param string $relation_obj  Relation object string from JetEngine
     * @return bool
     */
    private function is_cct_in_relation($cct_slug, $relation_obj) {
        // Handle "cct::slug" format
        if (strpos($relation_obj, 'cct::') === 0) {
            $rel_cct_slug = str_replace('cct::', '', $relation_obj);
            return $rel_cct_slug === $cct_slug;
        }
        
        // Direct match
        return $relation_obj === $cct_slug;
    }
    
    /**
     * Get relation types (one_to_one, one_to_many, many_to_many)
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
     * Get displayable fields for a CCT (for search/display in modals)
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
        
        // Add all text-like fields
        foreach ($cct['fields'] as $field) {
            if (in_array($field['type'], ['text', 'textarea', 'wysiwyg', 'select', 'radio'])) {
                $displayable[$field['name']] = $field['title'];
            }
        }
        
        return $displayable;
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

