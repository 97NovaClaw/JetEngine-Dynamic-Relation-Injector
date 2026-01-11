<?php
/**
 * Configuration Manager - Module B
 *
 * Handles CRUD operations for CCT configurations
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Configuration Manager Class
 */
class Jet_Injector_Config_Manager {
    
    /**
     * Save CCT configuration
     *
     * @param string $cct_slug      CCT slug
     * @param array  $config        Configuration data
     * @param bool   $is_enabled    Enable/disable injection
     * @return int|false Config ID or false on failure
     */
    public function save_config($cct_slug, $config, $is_enabled = true) {
        // Merge with defaults to ensure all required fields exist
        $config = $this->merge_with_defaults($config);
        
        // Validate configuration
        $validated = $this->validate_config($config);
        
        if (is_wp_error($validated)) {
            jet_injector_log_error('Config validation failed', [
                'cct_slug' => $cct_slug,
                'errors' => $validated->get_error_messages(),
            ]);
            return false;
        }
        
        $data = [
            'object_type' => 'cct',
            'object_slug' => $cct_slug,
            'config_data' => $config,
            'is_enabled' => $is_enabled ? 1 : 0,
        ];
        
        $result = Jet_Injector_Config_DB::upsert($data);
        
        if ($result) {
            jet_injector_debug_log('Config saved successfully', [
                'cct_slug' => $cct_slug,
                'config_id' => $result,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get CCT configuration
     *
     * @param string $cct_slug CCT slug
     * @return array|null Configuration data or null if not found
     */
    public function get_config($cct_slug) {
        $config_obj = Jet_Injector_Config_DB::get_by_object('cct', $cct_slug);
        
        if (!$config_obj) {
            return null;
        }
        
        return $this->format_config($config_obj);
    }
    
    /**
     * Get all CCT configurations
     *
     * @param bool $enabled_only Return only enabled configs
     * @return array
     */
    public function get_all_configs($enabled_only = false) {
        $args = ['object_type' => 'cct'];
        
        if ($enabled_only) {
            $args['is_enabled'] = 1;
        }
        
        $configs = Jet_Injector_Config_DB::get_all($args);
        
        $formatted = [];
        foreach ($configs as $config) {
            $formatted[] = $this->format_config($config);
        }
        
        return $formatted;
    }
    
    /**
     * Delete CCT configuration
     *
     * @param string $cct_slug CCT slug
     * @return bool Success
     */
    public function delete_config($cct_slug) {
        $config = Jet_Injector_Config_DB::get_by_object('cct', $cct_slug);
        
        if (!$config) {
            return false;
        }
        
        $result = Jet_Injector_Config_DB::delete($config->id);
        
        if ($result) {
            jet_injector_debug_log('Config deleted', ['cct_slug' => $cct_slug]);
        }
        
        return $result;
    }
    
    /**
     * Enable/disable configuration
     *
     * @param string $cct_slug   CCT slug
     * @param bool   $is_enabled Enable state
     * @return bool Success
     */
    public function toggle_config($cct_slug, $is_enabled) {
        $config = Jet_Injector_Config_DB::get_by_object('cct', $cct_slug);
        
        if (!$config) {
            return false;
        }
        
        $result = Jet_Injector_Config_DB::update($config->id, [
            'is_enabled' => $is_enabled ? 1 : 0,
        ]);
        
        if ($result) {
            jet_injector_debug_log('Config toggled', [
                'cct_slug' => $cct_slug,
                'is_enabled' => $is_enabled,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Format configuration object
     *
     * @param object $config_obj Database config object
     * @return array Formatted configuration
     */
    private function format_config($config_obj) {
        return [
            'id' => $config_obj->id,
            'cct_slug' => $config_obj->object_slug,
            'is_enabled' => (bool) $config_obj->is_enabled,
            'config' => $config_obj->config_data,
            'created_at' => $config_obj->created_at,
            'updated_at' => $config_obj->updated_at,
        ];
    }
    
    /**
     * Validate configuration data
     *
     * @param array $config Configuration to validate
     * @return true|WP_Error
     */
    private function validate_config($config) {
        $errors = new WP_Error();
        
        // Check if config is an array
        if (!is_array($config)) {
            $errors->add('invalid_format', __('Configuration must be an array.', 'jet-relation-injector'));
            return $errors;
        }
        
        // Validate injection_point
        if (!isset($config['injection_point'])) {
            $errors->add('missing_injection_point', __('Injection point is required.', 'jet-relation-injector'));
        } elseif (!in_array($config['injection_point'], ['before_save', 'after_fields'])) {
            $errors->add('invalid_injection_point', __('Invalid injection point.', 'jet-relation-injector'));
        }
        
        // Validate enabled_relations
        if (!isset($config['enabled_relations'])) {
            $errors->add('missing_relations', __('Enabled relations are required.', 'jet-relation-injector'));
        } elseif (!is_array($config['enabled_relations'])) {
            $errors->add('invalid_relations', __('Enabled relations must be an array.', 'jet-relation-injector'));
        }
        
        // Validate display_fields for each relation (optional - will use defaults if not set)
        if (isset($config['enabled_relations']) && is_array($config['enabled_relations'])) {
            // Ensure display_fields array exists
            if (!isset($config['display_fields']) || !is_array($config['display_fields'])) {
                $config['display_fields'] = [];
            }
            
            // Validate that relation tables exist
            $discovery = Jet_Injector_Plugin::instance()->get_discovery();
            $missing_tables = [];
            
            foreach ($config['enabled_relations'] as $relation_id) {
                // Set empty array for relations without display fields (they'll show all fields)
                if (!isset($config['display_fields'][$relation_id])) {
                    $config['display_fields'][$relation_id] = []; // Empty = show all
                }
                
                // Check if relation table exists
                if (!$discovery->relation_table_exists($relation_id)) {
                    $missing_tables[] = $relation_id;
                }
            }
            
            // If any relation tables are missing, add error with helpful message
            if (!empty($missing_tables)) {
                $table_names = array_map(function($id) {
                    return 'wp_jet_rel_' . $id;
                }, $missing_tables);
                
                $errors->add(
                    'missing_relation_tables',
                    sprintf(
                        __('The following relation tables do not exist: %s. Please edit these relations in JetEngine and enable "Store in separate database table", then save the relation.', 'jet-relation-injector'),
                        implode(', ', $table_names)
                    )
                );
            }
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return true;
    }
    
    /**
     * Get default configuration structure
     *
     * @return array
     */
    public function get_default_config() {
        return [
            'injection_point' => 'before_save',
            'enabled_relations' => [],
            'display_fields' => [],
            'ui_settings' => [
                'show_labels' => true,
                'show_create_button' => true,
                'modal_width' => 'medium',
            ],
        ];
    }
    
    /**
     * Merge with defaults
     *
     * @param array $config User configuration
     * @return array Complete configuration
     */
    public function merge_with_defaults($config) {
        return array_replace_recursive($this->get_default_config(), $config);
    }
    
    /**
     * Export configuration as JSON
     *
     * @param string $cct_slug CCT slug
     * @return string|false JSON string or false on failure
     */
    public function export_config($cct_slug) {
        $config = $this->get_config($cct_slug);
        
        if (!$config) {
            return false;
        }
        
        return wp_json_encode($config, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import configuration from JSON
     *
     * @param string $cct_slug CCT slug
     * @param string $json     JSON configuration
     * @return int|false Config ID or false on failure
     */
    public function import_config($cct_slug, $json) {
        $config = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jet_injector_log_error('Failed to parse JSON config', ['error' => json_last_error_msg()]);
            return false;
        }
        
        // Extract just the config data
        $config_data = isset($config['config']) ? $config['config'] : $config;
        $is_enabled = isset($config['is_enabled']) ? $config['is_enabled'] : true;
        
        return $this->save_config($cct_slug, $config_data, $is_enabled);
    }
}

