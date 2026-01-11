# Configuration Schema

## Overview

This document defines the JSON configuration schema used to control how relation injectors behave for each CCT.

---

## Configuration Storage

Configurations are stored in the `jet_injector_configs` database table. The `config_data` column contains a JSON object following this schema.

---

## Full Schema Definition

```jsonc
{
  // Schema version for migration support
  "version": "1.0",
  
  // Relations configuration - keyed by relation ID or slug
  "relations": {
    "[relation_id]": {
      // Whether this relation selector is enabled
      "enabled": true,
      
      // Field from target CCT to display in search results
      "display_field": "model_name",
      
      // Allow creating new related items inline
      "allow_create_new": true,
      
      // Fields to show in "create new" modal
      // Empty array = only display_field
      "create_fields": ["model_name", "year", "vin"],
      
      // Fields to search within
      // Empty array = only display_field
      "search_fields": ["model_name", "vin"],
      
      // Custom label for this selector
      // Falls back to relation name if not set
      "ui_label": "Vehicle Model",
      
      // Placeholder text for search input
      "placeholder": "Search vehicles...",
      
      // Sort order (lower = higher in list)
      "position": 1,
      
      // For cascading relations: parent relation config
      "cascade": {
        "parent_relation_id": "8",
        "parent_display_field": "brand_name",
        "parent_label": "Select Brand First"
      }
    }
  },
  
  // Global UI settings for the relations box
  "ui_settings": {
    // Custom title for the relations container
    "box_title": "Related Items",
    
    // Where to position the box
    // Options: "after_fields", "before_save", "meta_box"
    "box_position": "after_fields",
    
    // Show relation type badges (e.g., "One to Many")
    "show_relation_type_badge": true,
    
    // Collapse the box by default
    "collapsed_by_default": false
  }
}
```

---

## Minimal Configuration

The simplest valid configuration (auto-detects everything):

```json
{
  "version": "1.0",
  "relations": {}
}
```

When `relations` is empty, the system will:
1. Auto-detect all relations where this CCT is parent or child
2. Use default settings for each relation
3. Use the first text-type field as `display_field`

---

## Field Definitions

### `version` (required)
- **Type:** string
- **Format:** Semantic version (e.g., "1.0", "1.1.0")
- **Purpose:** Enables schema migrations in future versions

### `relations` (required)
- **Type:** object
- **Keys:** Relation identifiers (ID as string)
- **Values:** Relation configuration objects

---

## Relation Configuration Object

### `enabled`
- **Type:** boolean
- **Default:** `true`
- **Description:** When `false`, this relation will not be shown even if detected

### `display_field`
- **Type:** string
- **Default:** First text-type field in target CCT, or `_ID`
- **Description:** The CCT field to display in search results and selected items

### `allow_create_new`
- **Type:** boolean
- **Default:** `true`
- **Description:** Show "Add New" button to create related items inline

### `create_fields`
- **Type:** array of strings
- **Default:** `[]` (uses `display_field` only)
- **Description:** Which fields to show in the create modal
- **Example:** `["title", "description", "price"]`

### `search_fields`
- **Type:** array of strings
- **Default:** `[]` (uses `display_field` only)
- **Description:** Which fields to search when user types
- **Example:** `["title", "sku", "description"]`

### `ui_label`
- **Type:** string
- **Default:** Relation's configured name
- **Description:** Custom label shown above the selector

### `placeholder`
- **Type:** string
- **Default:** `"Search [relation_name]..."`
- **Description:** Placeholder text for the search input

### `position`
- **Type:** integer
- **Default:** `0`
- **Description:** Sort order for multiple relations (lower = first)

### `cascade`
- **Type:** object | null
- **Default:** Auto-detected from relation hierarchy
- **Description:** Configuration for cascading parent selectors

#### Cascade Sub-fields

| Field | Type | Description |
|-------|------|-------------|
| `parent_relation_id` | string | ID of the parent relation |
| `parent_display_field` | string | Field to show for parent items |
| `parent_label` | string | Label for parent dropdown |

---

## UI Settings Object

### `box_title`
- **Type:** string
- **Default:** `"Related Items"`
- **Description:** Heading text for the relations container box

### `box_position`
- **Type:** string
- **Default:** `"after_fields"`
- **Options:**
  - `"after_fields"` - After all CCT fields, before save button area
  - `"before_save"` - Immediately before the save button
  - `"meta_box"` - Render as a separate WordPress-style meta box
- **Description:** Where to inject the relations UI on the page

### `show_relation_type_badge`
- **Type:** boolean
- **Default:** `true`
- **Description:** Display badges like "One to Many" next to relation labels

### `collapsed_by_default`
- **Type:** boolean
- **Default:** `false`
- **Description:** Start with the relations box collapsed

---

## Examples

### Example 1: Vehicle Service Guide

CCT "service-guides" relates to CCT "vehicles" and taxonomy "service-categories":

```json
{
  "version": "1.0",
  "relations": {
    "12": {
      "enabled": true,
      "display_field": "model_name",
      "allow_create_new": true,
      "create_fields": ["model_name", "year", "make"],
      "search_fields": ["model_name", "vin", "plate_number"],
      "ui_label": "Vehicle",
      "placeholder": "Search by model name, VIN, or plate...",
      "position": 1
    },
    "15": {
      "enabled": true,
      "display_field": "name",
      "allow_create_new": false,
      "ui_label": "Service Category",
      "position": 2
    }
  },
  "ui_settings": {
    "box_title": "Link This Guide To:",
    "box_position": "after_fields",
    "show_relation_type_badge": true
  }
}
```

### Example 2: With Cascading (Grandparent)

CCT "parts" relates to CCT "vehicles" which relates to CCT "brands":

```json
{
  "version": "1.0",
  "relations": {
    "20": {
      "enabled": true,
      "display_field": "part_number",
      "ui_label": "Compatible Vehicle",
      "cascade": {
        "parent_relation_id": "18",
        "parent_display_field": "brand_name",
        "parent_label": "Filter by Brand"
      }
    }
  }
}
```

### Example 3: Minimal (Auto-Detect)

Let the system auto-detect and use defaults:

```json
{
  "version": "1.0",
  "relations": {}
}
```

### Example 4: Disable Specific Relation

Enable injection but hide one specific relation:

```json
{
  "version": "1.0",
  "relations": {
    "12": {
      "enabled": true,
      "display_field": "name"
    },
    "15": {
      "enabled": false
    }
  }
}
```

---

## Validation Rules

When saving configuration, validate:

1. **Version** must be present and non-empty
2. **Relation IDs** in `relations` object must be numeric strings or valid slugs
3. **Field names** in `display_field`, `create_fields`, `search_fields` must exist in target CCT
4. **Position** values must be integers
5. **Cascade** configuration must reference valid parent relations

```php
function validate_config(array $config): array {
    $errors = [];
    
    if (empty($config['version'])) {
        $errors[] = 'Missing version field';
    }
    
    if (!isset($config['relations']) || !is_array($config['relations'])) {
        $errors[] = 'Relations must be an object';
    }
    
    foreach ($config['relations'] as $rel_id => $rel_config) {
        if (!is_numeric($rel_id) && !preg_match('/^[a-z0-9_-]+$/', $rel_id)) {
            $errors[] = "Invalid relation identifier: {$rel_id}";
        }
        
        if (isset($rel_config['position']) && !is_int($rel_config['position'])) {
            $errors[] = "Position must be integer for relation {$rel_id}";
        }
    }
    
    return $errors;
}
```

---

## Default Values Reference

Complete defaults applied when values are not specified:

```php
$defaults = [
    'version' => '1.0',
    'relations' => [],
    'ui_settings' => [
        'box_title' => __('Related Items', 'jet-relation-injector'),
        'box_position' => 'after_fields',
        'show_relation_type_badge' => true,
        'collapsed_by_default' => false,
    ],
];

$relation_defaults = [
    'enabled' => true,
    'display_field' => null,  // Auto-detected
    'allow_create_new' => true,
    'create_fields' => [],
    'search_fields' => [],
    'ui_label' => null,  // Uses relation name
    'placeholder' => null,  // Auto-generated
    'position' => 0,
    'cascade' => null,  // Auto-detected from parent_rel
];
```

---

## JavaScript Config Format

When passed to JavaScript via `wp_localize_script`, the configuration is transformed:

```javascript
window.jetInjectorConfig = {
    nonce: "abc123...",
    ajaxUrl: "/wp-admin/admin-ajax.php",
    cctSlug: "service-guides",
    itemId: 123,  // or null for new items
    
    relations: [
        {
            id: "12",
            slug: "guide_to_vehicle",
            name: "Guide to Vehicle",
            type: "one_to_many",
            targetCct: "vehicles",
            role: "parent",  // or "child"
            
            // Config merged with defaults
            enabled: true,
            displayField: "model_name",
            allowCreateNew: true,
            createFields: ["model_name", "year"],
            searchFields: ["model_name", "vin"],
            uiLabel: "Vehicle",
            placeholder: "Search vehicles...",
            position: 1,
            
            // Cascade info (if applicable)
            cascade: {
                parentRelationId: "8",
                parentDisplayField: "brand_name",
                parentLabel: "Select Brand First"
            },
            
            // Current values (when editing existing item)
            currentValues: [
                { id: 505, label: "Toyota Camry 2024" }
            ]
        }
    ],
    
    uiSettings: {
        boxTitle: "Link This Guide To:",
        boxPosition: "after_fields",
        showTypeBadge: true
    },
    
    // Translations
    i18n: {
        searchPlaceholder: "Search...",
        addNew: "Add New",
        noResults: "No items found",
        loading: "Loading...",
        remove: "Remove",
        oneToOneWarning: "This relation only allows one item. Selecting will replace the current."
    }
};
```

