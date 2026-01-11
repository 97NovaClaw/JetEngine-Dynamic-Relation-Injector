<?php
/**
 * Configuration Database Handler
 *
 * Manages the custom database table for CCT configurations
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Configuration Database Class
 */
class Jet_Injector_Config_DB {
    
    /**
     * Get table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . JET_INJECTOR_TABLE;
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type varchar(50) NOT NULL DEFAULT 'cct',
            object_slug varchar(255) NOT NULL,
            config_data longtext NOT NULL,
            is_enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_object (object_type, object_slug),
            KEY object_type_idx (object_type),
            KEY object_slug_idx (object_slug),
            KEY is_enabled_idx (is_enabled)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Update plugin version
        update_option('jet_injector_version', JET_INJECTOR_VERSION);
        
        jet_injector_debug_log('Database table created/updated', ['table' => $table_name]);
    }
    
    /**
     * Insert a new configuration
     *
     * @param array $data Configuration data
     * @return int|false Insert ID or false on failure
     */
    public static function insert($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = [
            'object_type' => 'cct',
            'object_slug' => '',
            'config_data' => '',
            'is_enabled' => 1,
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Encode config_data if it's an array
        if (is_array($data['config_data'])) {
            $data['config_data'] = wp_json_encode($data['config_data']);
        }
        
        $result = $wpdb->insert(
            $table_name,
            [
                'object_type' => sanitize_text_field($data['object_type']),
                'object_slug' => sanitize_text_field($data['object_slug']),
                'config_data' => $data['config_data'],
                'is_enabled' => (int) $data['is_enabled'],
            ],
            ['%s', '%s', '%s', '%d']
        );
        
        if ($result === false) {
            jet_injector_log_error('Failed to insert config', ['error' => $wpdb->last_error]);
            return false;
        }
        
        jet_injector_debug_log('Config inserted', [
            'id' => $wpdb->insert_id,
            'object_type' => $data['object_type'],
            'object_slug' => $data['object_slug'],
        ]);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing configuration
     *
     * @param int   $id   Configuration ID
     * @param array $data Configuration data
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Encode config_data if it's an array
        if (isset($data['config_data']) && is_array($data['config_data'])) {
            $data['config_data'] = wp_json_encode($data['config_data']);
        }
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['object_type'])) {
            $update_data['object_type'] = sanitize_text_field($data['object_type']);
            $formats[] = '%s';
        }
        
        if (isset($data['object_slug'])) {
            $update_data['object_slug'] = sanitize_text_field($data['object_slug']);
            $formats[] = '%s';
        }
        
        if (isset($data['config_data'])) {
            $update_data['config_data'] = $data['config_data'];
            $formats[] = '%s';
        }
        
        if (isset($data['is_enabled'])) {
            $update_data['is_enabled'] = (int) $data['is_enabled'];
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        if ($result === false) {
            jet_injector_log_error('Failed to update config', ['id' => $id, 'error' => $wpdb->last_error]);
            return false;
        }
        
        jet_injector_debug_log('Config updated', ['id' => $id]);
        
        return true;
    }
    
    /**
     * Delete a configuration
     *
     * @param int $id Configuration ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            jet_injector_log_error('Failed to delete config', ['id' => $id, 'error' => $wpdb->last_error]);
            return false;
        }
        
        jet_injector_debug_log('Config deleted', ['id' => $id]);
        
        return true;
    }
    
    /**
     * Get configuration by ID
     *
     * @param int $id Configuration ID
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id)
        );
        
        if ($config && !empty($config->config_data)) {
            $config->config_data = json_decode($config->config_data, true);
        }
        
        return $config;
    }
    
    /**
     * Get configuration by object
     *
     * @param string $object_type Object type (cct, taxonomy, post_type)
     * @param string $object_slug Object slug
     * @return object|null
     */
    public static function get_by_object($object_type, $object_slug) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE object_type = %s AND object_slug = %s",
                $object_type,
                $object_slug
            )
        );
        
        if ($config && !empty($config->config_data)) {
            $config->config_data = json_decode($config->config_data, true);
        }
        
        return $config;
    }
    
    /**
     * Get all configurations
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all($args = []) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = [
            'object_type' => null,
            'is_enabled' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = [];
        $where_values = [];
        
        if (!empty($args['object_type'])) {
            $where[] = 'object_type = %s';
            $where_values[] = $args['object_type'];
        }
        
        if ($args['is_enabled'] !== null) {
            $where[] = 'is_enabled = %d';
            $where_values[] = (int) $args['is_enabled'];
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $order_sql = $orderby ? "ORDER BY {$orderby}" : '';
        
        $query = "SELECT * FROM {$table_name} {$where_sql} {$order_sql}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $configs = $wpdb->get_results($query);
        
        // Decode config_data for each
        foreach ($configs as $config) {
            if (!empty($config->config_data)) {
                $config->config_data = json_decode($config->config_data, true);
            }
        }
        
        return $configs;
    }
    
    /**
     * Get enabled configurations for specific object type
     *
     * @param string $object_type Object type
     * @return array
     */
    public static function get_enabled($object_type = 'cct') {
        return self::get_all([
            'object_type' => $object_type,
            'is_enabled' => 1,
        ]);
    }
    
    /**
     * Check if configuration exists
     *
     * @param string $object_type Object type
     * @param string $object_slug Object slug
     * @return bool
     */
    public static function exists($object_type, $object_slug) {
        $config = self::get_by_object($object_type, $object_slug);
        return !empty($config);
    }
    
    /**
     * Upsert (insert or update) configuration
     *
     * @param array $data Configuration data
     * @return int|bool Config ID or false on failure
     */
    public static function upsert($data) {
        $existing = self::get_by_object($data['object_type'], $data['object_slug']);
        
        if ($existing) {
            $success = self::update($existing->id, $data);
            return $success ? $existing->id : false;
        } else {
            return self::insert($data);
        }
    }
}

