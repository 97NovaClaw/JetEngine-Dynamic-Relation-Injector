<?php
/**
 * Utilities Class
 *
 * Provides utility functions for JetEngine cache management and CCT maintenance
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Utilities Class
 */
class Jet_Injector_Utilities {
    
    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        add_action('wp_ajax_jet_injector_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_jet_injector_bulk_resave', [$this, 'ajax_bulk_resave']);
        add_action('wp_ajax_jet_injector_diagnose_relations', [$this, 'ajax_diagnose_relations']);
    }
    
    /**
     * AJAX: Clear JetEngine caches
     */
    public function ajax_clear_cache() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $cleared = $this->clear_jetengine_caches();
        
        jet_injector_debug_log('JetEngine caches cleared', $cleared);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Cleared %d transients and flushed object cache.', 'jet-relation-injector'),
                $cleared['transients_deleted']
            ),
            'details' => $cleared,
        ]);
    }
    
    /**
     * Clear all JetEngine-related caches
     *
     * @return array Results
     */
    public function clear_jetengine_caches() {
        global $wpdb;
        
        $results = [
            'transients_deleted' => 0,
            'object_cache_flushed' => false,
        ];
        
        // Delete JetEngine transients
        $transient_patterns = [
            '_transient_jet_engine%',
            '_transient_timeout_jet_engine%',
            '_transient_jet-engine%',
            '_transient_timeout_jet-engine%',
            '_transient_jet_cct%',
            '_transient_timeout_jet_cct%',
        ];
        
        foreach ($transient_patterns as $pattern) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));
            $results['transients_deleted'] += $deleted;
        }
        
        // Also delete any transients stored in sitemeta for multisite
        if (is_multisite()) {
            foreach ($transient_patterns as $pattern) {
                $site_pattern = str_replace('_transient', '_site_transient', $pattern);
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                    $site_pattern
                ));
                $results['transients_deleted'] += $deleted;
            }
        }
        
        // Flush object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $results['object_cache_flushed'] = true;
        }
        
        // Clear any JetEngine specific caches
        if (function_exists('jet_engine')) {
            // Clear listings cache
            if (isset(jet_engine()->listings) && method_exists(jet_engine()->listings, 'flush_cache')) {
                jet_engine()->listings->flush_cache();
            }
        }
        
        return $results;
    }
    
    /**
     * AJAX: Bulk re-save CCT items
     */
    public function ajax_bulk_resave() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug is required', 'jet-relation-injector')]);
            return;
        }
        
        $result = $this->bulk_resave_cct_items($cct_slug);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        jet_injector_debug_log('Bulk re-save completed', [
            'cct_slug' => $cct_slug,
            'items_processed' => $result['processed'],
        ]);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Successfully re-saved %d items in %s.', 'jet-relation-injector'),
                $result['processed'],
                $cct_slug
            ),
            'details' => $result,
        ]);
    }
    
    /**
     * Bulk re-save all items in a CCT
     *
     * @param string $cct_slug CCT slug
     * @return array|WP_Error Results or error
     */
    public function bulk_resave_cct_items($cct_slug) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return new \WP_Error('module_not_found', __('CCT module not found', 'jet-relation-injector'));
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type) {
            return new \WP_Error('cct_not_found', __('CCT not found', 'jet-relation-injector'));
        }
        
        // Get all items
        $items = $content_type->db->query([], 0, 0, ['_ID' => 'ASC']);
        
        if (empty($items)) {
            return [
                'processed' => 0,
                'total' => 0,
                'message' => __('No items found in this CCT', 'jet-relation-injector'),
            ];
        }
        
        $processed = 0;
        $errors = [];
        
        foreach ($items as $item) {
            $item_id = $item['_ID'];
            
            // Update the modified timestamp to trigger any caching updates
            $update_data = [
                'cct_modified' => current_time('mysql'),
            ];
            
            $result = $content_type->db->update($update_data, ['_ID' => $item_id]);
            
            if ($result !== false) {
                $processed++;
                
                // Trigger JetEngine's update hook to refresh any cached data
                do_action('jet-engine/custom-content-types/updated-item/' . $cct_slug, $item, $item, null);
            } else {
                $errors[] = $item_id;
            }
        }
        
        return [
            'processed' => $processed,
            'total' => count($items),
            'errors' => $errors,
        ];
    }
    
    /**
     * AJAX: Diagnose relation settings
     */
    public function ajax_diagnose_relations() {
        check_ajax_referer('jet_injector_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'jet-relation-injector')]);
            return;
        }
        
        $diagnosis = $this->diagnose_relations();
        
        jet_injector_debug_log('Relations diagnosed', [
            'total' => count($diagnosis['relations']),
            'issues' => $diagnosis['issues_count'],
        ]);
        
        wp_send_json_success($diagnosis);
    }
    
    /**
     * Diagnose all relations for configuration issues
     *
     * @return array Diagnosis results
     */
    public function diagnose_relations() {
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        $relations = $discovery->get_all_relations();
        
        $diagnosis = [
            'relations' => [],
            'issues_count' => 0,
            'ok_count' => 0,
        ];
        
        foreach ($relations as $relation) {
            $rel_diagnosis = $this->diagnose_single_relation($relation);
            $diagnosis['relations'][] = $rel_diagnosis;
            
            if ($rel_diagnosis['has_issues']) {
                $diagnosis['issues_count']++;
            } else {
                $diagnosis['ok_count']++;
            }
        }
        
        return $diagnosis;
    }
    
    /**
     * Diagnose a single relation
     *
     * @param array $relation Relation data
     * @return array Diagnosis result
     */
    private function diagnose_single_relation($relation) {
        $result = [
            'id' => $relation['id'],
            'name' => $relation['name'],
            'parent_object' => $relation['parent_object'],
            'child_object' => $relation['child_object'],
            'parent_title_field' => null,
            'child_title_field' => null,
            'table_exists' => isset($relation['table_exists']) ? $relation['table_exists'] : false,
            'table_name' => isset($relation['table_name']) ? $relation['table_name'] : 'wp_jet_rel_' . $relation['id'],
            'has_issues' => false,
            'issues' => [],
        ];
        
        // Get the actual relation object to check title_field settings
        if (function_exists('jet_engine') && jet_engine()->relations) {
            $rel_obj = jet_engine()->relations->get_active_relations($relation['id']);
            
            if ($rel_obj && method_exists($rel_obj, 'get_args')) {
                $args = $rel_obj->get_args();
                
                // Check CCT title fields
                if (isset($args['cct']) && is_array($args['cct'])) {
                    foreach ($args['cct'] as $cct_key => $cct_config) {
                        if (!empty($cct_config['title_field'])) {
                            if (strpos($cct_key, $this->extract_slug($relation['parent_object'])) !== false) {
                                $result['parent_title_field'] = $cct_config['title_field'];
                            }
                            if (strpos($cct_key, $this->extract_slug($relation['child_object'])) !== false) {
                                $result['child_title_field'] = $cct_config['title_field'];
                            }
                        }
                    }
                }
            }
        }
        
        // Check for issues
        $discovery = Jet_Injector_Plugin::instance()->get_discovery();
        
        // Check if parent is CCT and missing title_field
        $parent_parsed = $discovery->parse_relation_object($relation['parent_object']);
        if ($parent_parsed['type'] === 'cct' && empty($result['parent_title_field'])) {
            $result['has_issues'] = true;
            $result['issues'][] = sprintf(
                __('Parent CCT "%s" has no title_field configured - will show #ID only', 'jet-relation-injector'),
                $parent_parsed['slug']
            );
        }
        
        // Check if child is CCT and missing title_field
        $child_parsed = $discovery->parse_relation_object($relation['child_object']);
        if ($child_parsed['type'] === 'cct' && empty($result['child_title_field'])) {
            $result['has_issues'] = true;
            $result['issues'][] = sprintf(
                __('Child CCT "%s" has no title_field configured - will show #ID only', 'jet-relation-injector'),
                $child_parsed['slug']
            );
        }
        
        // Check if table exists
        if (!$result['table_exists']) {
            $result['has_issues'] = true;
            $result['issues'][] = __('Database table does not exist', 'jet-relation-injector');
        }
        
        return $result;
    }
    
    /**
     * Extract slug from object notation (e.g., "cct::vehicles" => "vehicles")
     *
     * @param string $object Object string
     * @return string Slug
     */
    private function extract_slug($object) {
        if (strpos($object, '::') !== false) {
            $parts = explode('::', $object);
            return isset($parts[1]) ? $parts[1] : $object;
        }
        return $object;
    }
}

