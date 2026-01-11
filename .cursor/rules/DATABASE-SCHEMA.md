# Database Schema

## Overview

The JetEngine Dynamic Relation Injector uses a single custom database table for storing configuration, plus WordPress options for debug settings.

---

## Custom Table: `{prefix}jet_injector_configs`

Stores injection configuration for each content type (CCT, and future support for post types/taxonomies).

### Table Definition

```sql
CREATE TABLE {$wpdb->prefix}jet_injector_configs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    object_type VARCHAR(50) NOT NULL DEFAULT 'cct',
    object_slug VARCHAR(200) NOT NULL,
    config_data LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY object_key (object_type, object_slug),
    KEY is_active (is_active)
) {$charset_collate};
```

### Column Definitions

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Auto-increment primary key |
| `object_type` | VARCHAR(50) | Type of object: `cct`, `post_type`, `taxonomy` |
| `object_slug` | VARCHAR(200) | The slug/name of the specific type (e.g., `service-guides`) |
| `config_data` | LONGTEXT | JSON-encoded configuration (see schema below) |
| `is_active` | TINYINT(1) | Whether injection is enabled (1) or disabled (0) |
| `created_at` | DATETIME | Record creation timestamp |
| `updated_at` | DATETIME | Last modification timestamp |

### Indexes

- **PRIMARY KEY** on `id` - standard auto-increment
- **UNIQUE KEY** on `(object_type, object_slug)` - ensures one config per object
- **KEY** on `is_active` - for efficient filtering of active configs

---

## PHP Table Management

```php
class Jet_Injector_Config_DB {
    
    const TABLE_NAME = 'jet_injector_configs';
    
    /**
     * Get the full table name with prefix
     */
    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
    
    /**
     * Create table on plugin activation
     */
    public function create_table(): void {
        global $wpdb;
        
        $table_name = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(50) NOT NULL DEFAULT 'cct',
            object_slug VARCHAR(200) NOT NULL,
            config_data LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY object_key (object_type, object_slug),
            KEY is_active (is_active)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Store schema version
        update_option('jet_injector_db_version', '1.0.0');
    }
    
    /**
     * Check if table exists
     */
    public function table_exists(): bool {
        global $wpdb;
        $table_name = $this->table_name();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Drop table on uninstall
     */
    public function drop_table(): void {
        global $wpdb;
        $table_name = $this->table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        delete_option('jet_injector_db_version');
    }
}
```

---

## Configuration Data Schema (JSON)

The `config_data` column stores a JSON object with this structure:

```json
{
  "version": "1.0",
  "relations": {
    "relation_slug_or_id": {
      "enabled": true,
      "display_field": "title_field",
      "allow_create_new": true,
      "create_fields": ["title_field", "another_field"],
      "search_fields": ["title_field", "description"],
      "ui_label": "Select Vehicle",
      "placeholder": "Search for a vehicle...",
      "position": 1
    }
  },
  "ui_settings": {
    "box_title": "Related Items",
    "box_position": "after_fields",
    "show_relation_type_badge": true
  }
}
```

### Schema Field Definitions

#### Root Level

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `version` | string | Yes | Schema version for migrations |
| `relations` | object | Yes | Map of relation configs keyed by slug/ID |
| `ui_settings` | object | No | Global UI customization |

#### Relations Object

Each key is a relation identifier (slug or numeric ID as string).

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `enabled` | boolean | true | Whether to show this relation |
| `display_field` | string | `_ID` | Field to show in search results |
| `allow_create_new` | boolean | true | Show "Add New" button |
| `create_fields` | array | [] | Fields to show in create modal (empty = display_field only) |
| `search_fields` | array | [] | Fields to search in (empty = display_field only) |
| `ui_label` | string | Relation name | Custom label for the selector |
| `placeholder` | string | Auto-generated | Search input placeholder |
| `position` | integer | 0 | Sort order within the box |

#### UI Settings Object

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `box_title` | string | "Related Items" | Heading for the relations box |
| `box_position` | string | "after_fields" | Where to render: `after_fields`, `before_save` |
| `show_relation_type_badge` | boolean | true | Show "One to Many" etc badge |

---

## Example Records

### CCT Configuration

```sql
INSERT INTO wp_jet_injector_configs 
(object_type, object_slug, config_data, is_active) 
VALUES 
('cct', 'service-guides', '{
  "version": "1.0",
  "relations": {
    "12": {
      "enabled": true,
      "display_field": "model_name",
      "allow_create_new": true,
      "create_fields": ["model_name", "year"],
      "search_fields": ["model_name", "vin"],
      "ui_label": "Vehicle Model",
      "placeholder": "Search vehicles by name or VIN..."
    },
    "15": {
      "enabled": true,
      "display_field": "name",
      "allow_create_new": false,
      "ui_label": "Service Category"
    }
  },
  "ui_settings": {
    "box_title": "Link to Vehicles & Categories",
    "show_relation_type_badge": true
  }
}', 1);
```

### Disabled Configuration

```sql
INSERT INTO wp_jet_injector_configs 
(object_type, object_slug, config_data, is_active) 
VALUES 
('cct', 'another-cct', '{"version":"1.0","relations":{}}', 0);
```

---

## WordPress Options

### Debug Settings

**Option Name:** `jet_injector_debug_options`

```php
$default_debug_options = [
    'enable_php_logging' => false,     // Write to debug.log
    'enable_js_console' => false,      // Console.log in browser
    'enable_admin_notices' => false,   // Show admin notices
];

// Get options
$debug_options = get_option('jet_injector_debug_options', $default_debug_options);

// Update options
update_option('jet_injector_debug_options', [
    'enable_php_logging' => true,
    'enable_js_console' => true,
    'enable_admin_notices' => false,
]);
```

### Database Version

**Option Name:** `jet_injector_db_version`

```php
// Set on table creation
update_option('jet_injector_db_version', '1.0.0');

// Check for migrations
$current_version = get_option('jet_injector_db_version', '0.0.0');
if (version_compare($current_version, '1.1.0', '<')) {
    // Run migration
}
```

---

## CRUD Operations

### Get Configuration

```php
public function get_config(string $object_type, string $object_slug): ?array {
    global $wpdb;
    
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$this->table_name()} 
         WHERE object_type = %s AND object_slug = %s",
        $object_type,
        $object_slug
    ));
    
    if (!$row) {
        return null;
    }
    
    return [
        'id' => (int) $row->id,
        'object_type' => $row->object_type,
        'object_slug' => $row->object_slug,
        'config_data' => json_decode($row->config_data, true),
        'is_active' => (bool) $row->is_active,
        'created_at' => $row->created_at,
        'updated_at' => $row->updated_at,
    ];
}
```

### Save Configuration

```php
public function save_config(string $object_type, string $object_slug, array $config_data): bool {
    global $wpdb;
    
    $existing = $this->get_config($object_type, $object_slug);
    
    $data = [
        'object_type' => $object_type,
        'object_slug' => $object_slug,
        'config_data' => wp_json_encode($config_data),
        'is_active' => 1,
    ];
    
    if ($existing) {
        // Update
        $result = $wpdb->update(
            $this->table_name(),
            $data,
            ['id' => $existing['id']],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );
    } else {
        // Insert
        $result = $wpdb->insert(
            $this->table_name(),
            $data,
            ['%s', '%s', '%s', '%d']
        );
    }
    
    return $result !== false;
}
```

### Delete Configuration

```php
public function delete_config(string $object_type, string $object_slug): bool {
    global $wpdb;
    
    $result = $wpdb->delete(
        $this->table_name(),
        [
            'object_type' => $object_type,
            'object_slug' => $object_slug,
        ],
        ['%s', '%s']
    );
    
    return $result !== false;
}
```

### Get All Active Configurations

```php
public function get_all_active_configs(): array {
    global $wpdb;
    
    $rows = $wpdb->get_results(
        "SELECT * FROM {$this->table_name()} WHERE is_active = 1",
        ARRAY_A
    );
    
    return array_map(function($row) {
        return [
            'id' => (int) $row['id'],
            'object_type' => $row['object_type'],
            'object_slug' => $row['object_slug'],
            'config_data' => json_decode($row['config_data'], true),
            'is_active' => true,
        ];
    }, $rows);
}
```

---

## Uninstall Cleanup

When the plugin is uninstalled (not just deactivated), clean up all data:

```php
// uninstall.php

// Security check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop custom table
$table_name = $wpdb->prefix . 'jet_injector_configs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete options
delete_option('jet_injector_db_version');
delete_option('jet_injector_debug_options');

// Optional: Delete any transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_jet_injector_%' 
     OR option_name LIKE '_transient_timeout_jet_injector_%'"
);
```

---

## JetEngine Relation Tables (External - NOT Created by Our Plugin)

### Table Pattern: `{prefix}jet_rel_{relation_id}`

JetEngine creates these tables when a relation has "Store in separate database table" enabled. Our plugin writes to these tables but does NOT create them.

### Standard Schema

```sql
CREATE TABLE wp_jet_rel_47 (
    _ID BIGINT(20) NOT NULL AUTO_INCREMENT,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rel_id TEXT,                    -- CRITICAL: Relation ID (required by JetEngine)
    parent_rel INT(11),             -- CRITICAL: Parent relation ID for hierarchies
    parent_object_id BIGINT(20),    -- Parent item ID
    child_object_id BIGINT(20),     -- Child item ID
    PRIMARY KEY (_ID)
);
```

### Critical Columns for Our Plugin

| Column | Required? | Description |
|--------|-----------|-------------|
| `_ID` | Always | Auto-increment primary key |
| `parent_object_id` | Always | ID of parent item (CCT `_ID`, term `term_id`, or post `ID`) |
| `child_object_id` | Always | ID of child item |
| `rel_id` | **YES!** | **MUST be set to relation ID or JetEngine won't recognize it** |
| `parent_rel` | For hierarchies | Set to parent relation ID if this is a child relation |
| `created` | Optional | Timestamp (auto-set by MySQL) |

### How Our Plugin Inserts Relations

```php
global $wpdb;

$table = $wpdb->prefix . 'jet_rel_' . absint($relation_id);

// CRITICAL: Must include rel_id and parent_rel!
$wpdb->insert($table, [
    'parent_object_id' => $parent_id,
    'child_object_id' => $child_id,
    'rel_id' => $relation_id,           // REQUIRED!
    'parent_rel' => $parent_rel_id,     // NULL if not hierarchical
], ['%d', '%d', '%d', '%d']);
```

### Why rel_id is Required

Without `rel_id`, JetEngine's native relation UI won't display the connection, even though it exists in the database. The relation appears "orphaned".

**Symptoms of Missing rel_id:**
- Relation exists in database (`SELECT *` shows the row)
- JetEngine's relation panel shows nothing
- Frontend queries don't find the relation

**Fix:**
Always include `rel_id` in inserts (implemented in v1.0.0).

### Validation: Check Table Exists

Before writing to a relation table:

```php
$discovery = Jet_Injector_Plugin::instance()->get_discovery();

if (!$discovery->relation_table_exists($relation_id)) {
    // Table doesn't exist - user needs to:
    // 1. Edit relation in JetEngine
    // 2. Enable "Store in separate database table"
    // 3. Save
    return new WP_Error('table_missing', 'Relation table does not exist');
}
```

---

## Future Migration Support

For schema updates in future versions:

```php
public function maybe_upgrade(): void {
    $current_version = get_option('jet_injector_db_version', '0.0.0');
    
    // Version 1.1.0: Add new column example
    if (version_compare($current_version, '1.1.0', '<')) {
        $this->upgrade_to_1_1_0();
    }
    
    // Update version after all migrations
    update_option('jet_injector_db_version', JET_INJECTOR_VERSION);
}

private function upgrade_to_1_1_0(): void {
    global $wpdb;
    
    // Example: Add a new column
    $wpdb->query(
        "ALTER TABLE {$this->table_name()} 
         ADD COLUMN priority INT DEFAULT 0 AFTER is_active"
    );
}
```

