# JetEngine API Reference

## Overview

This document catalogs the JetEngine internal APIs, hooks, and methods that the Relation Injector plugin will use. All code references are from JetEngine 3.3.1.

---

## Accessing JetEngine Instance

```php
// Main JetEngine instance
$jet_engine = jet_engine();

// Check if JetEngine is active
if (!function_exists('jet_engine')) {
    // JetEngine not available
    return;
}
```

---

## Custom Content Types Module

### Accessing the CCT Module

```php
// Get the CCT module instance
$cct_module = jet_engine()->modules->get_module('custom-content-types');

// Get the CCT manager
$cct_manager = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager;

// Alternative via jet_engine()
$cct_module_data = jet_engine()->modules->get_modules_for_js()['custom-content-types'];
```

### Get All Registered CCTs

```php
/**
 * Returns array of CCT Factory instances keyed by slug
 * @return array<string, \Jet_Engine\Modules\Custom_Content_Types\Factory>
 */
$content_types = $cct_manager->get_content_types();

foreach ($content_types as $slug => $factory) {
    $name = $factory->get_arg('name');        // Display name
    $slug = $factory->get_arg('slug');        // URL-safe slug
    $db_table = $factory->db->table();        // Database table name
}
```

### Get CCT by Slug

```php
/**
 * @param string $slug CCT slug
 * @return \Jet_Engine\Modules\Custom_Content_Types\Factory|false
 */
$cct = $cct_manager->get_content_types('my-cct-slug');

if ($cct) {
    // Work with CCT
}
```

### Get CCT Fields

```php
/**
 * Get formatted fields array for a CCT
 * @return array
 */
$fields = $cct->get_formatted_fields();

/**
 * Get fields list for UI (dropdowns, etc)
 * @param string $context 'all', 'custom', 'service'
 * @param string $format 'blocks', 'elementor', etc
 * @return array
 */
$fields_list = $cct->get_fields_list('custom', 'blocks');

// Field structure:
// [
//     'name' => 'field_slug',
//     'title' => 'Field Label',
//     'type' => 'text|number|select|etc',
//     'options' => [...],  // For select fields
//     'default_val' => '',
// ]
```

### CCT Item Operations

```php
// Get Item Handler
$item_handler = $cct->get_item_handler();

// Query items from CCT database
$items = $cct->db->query([
    'orderby' => 'title_field',
    'order' => 'ASC',
    'limit' => 20,
]);

// Get single item by ID
$item = $cct->db->get_item($item_id);

// Search items (basic LIKE query)
global $wpdb;
$table = $cct->db->table();
$search = '%' . $wpdb->esc_like($search_term) . '%';
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE display_field LIKE %s LIMIT 20",
    $search
));
```

### Create/Update CCT Item

```php
// Prepare item data array
$itemarr = [
    'field_slug_1' => 'value1',
    'field_slug_2' => 'value2',
    // ...
];

// For update, include _ID
$itemarr['_ID'] = $existing_item_id;

// Use item handler to save
$new_id = $item_handler->update_item($itemarr);

if (is_wp_error($new_id)) {
    // Handle error
    $error_message = $new_id->get_error_message();
}
```

---

## CCT Hooks (Save Process)

### Before Update (Existing Item)

```php
/**
 * Fires before updating an existing CCT item
 * 
 * @param array $item New item data to be saved
 * @param array $prev_item Previous item data
 * @param \Jet_Engine\Modules\Custom_Content_Types\Item_Handler $handler
 */
add_action(
    'jet-engine/custom-content-types/update-item/{cct_slug}',
    function($item, $prev_item, $handler) {
        // Modify $item before save
    },
    10, 3
);
```

### After Update (Existing Item)

```php
/**
 * Fires after updating an existing CCT item
 * 
 * @param array $item Saved item data (includes _ID)
 * @param array $prev_item Previous item data
 * @param \Jet_Engine\Modules\Custom_Content_Types\Item_Handler $handler
 */
add_action(
    'jet-engine/custom-content-types/updated-item/{cct_slug}',
    function($item, $prev_item, $handler) {
        $item_id = $item['_ID'];
        $cct_slug = $handler->get_factory()->get_arg('slug');
        
        // Process relations after save
    },
    10, 3
);
```

### Before Create (New Item)

```php
/**
 * Fires before creating a new CCT item
 * 
 * @param array $item Item data to be inserted
 * @param \Jet_Engine\Modules\Custom_Content_Types\Item_Handler $handler
 */
add_action(
    'jet-engine/custom-content-types/create-item/{cct_slug}',
    function($item, $handler) {
        // Modify $item before insert
    },
    10, 2
);
```

### After Create (New Item)

```php
/**
 * Fires after creating a new CCT item
 * 
 * @param array $item Created item data
 * @param int $item_id New item ID
 * @param \Jet_Engine\Modules\Custom_Content_Types\Item_Handler $handler
 */
add_action(
    'jet-engine/custom-content-types/created-item/{cct_slug}',
    function($item, $item_id, $handler) {
        // $item_id is the newly created ID
        // Process relations after creation
    },
    10, 3
);
```

### Delete Item

```php
/**
 * Fires after deleting a CCT item
 */
add_action(
    'jet-engine/custom-content-types/delete-item/{cct_slug}',
    function($item_id, $item, $handler) {
        // Cleanup related data
    },
    10, 3
);
```

---

## Relations Module

### Accessing Relations Manager

```php
// Get relations manager
$relations_manager = jet_engine()->relations;

// Check if relations module exists
if (!$relations_manager) {
    // Relations module not available
    return;
}
```

### Get All Active Relations

```php
/**
 * Get all registered relation objects
 * @param int|false $rel_id Specific relation ID or false for all
 * @return array|\Jet_Engine\Relations\Relation
 */
$all_relations = jet_engine()->relations->get_active_relations();

foreach ($all_relations as $rel_id => $relation) {
    $name = $relation->get_relation_name();
    $parent = $relation->get_args('parent_object');  // e.g., 'cct::vehicles'
    $child = $relation->get_args('child_object');    // e.g., 'cct::service-guides'
    $type = $relation->get_args('type');             // one_to_one, one_to_many, many_to_many
    $parent_rel = $relation->get_args('parent_rel'); // Parent relation ID (for hierarchy)
}
```

### Get Single Relation

```php
/**
 * @param int $relation_id
 * @return \Jet_Engine\Relations\Relation|false
 */
$relation = jet_engine()->relations->get_active_relations($relation_id);
```

### Relation Object Properties

```php
// Get relation ID
$id = $relation->get_id();

// Get relation name (for display)
$name = $relation->get_relation_name();

// Get relation arguments
$parent_object = $relation->get_args('parent_object');  // e.g., 'posts::post', 'cct::my-cct'
$child_object = $relation->get_args('child_object');
$type = $relation->get_args('type');                    // one_to_one, one_to_many, many_to_many
$parent_rel = $relation->get_args('parent_rel');        // Parent relation ID for hierarchy
$parent_control = $relation->get_args('parent_control'); // Show control on parent edit page
$child_control = $relation->get_args('child_control');   // Show control on child edit page
$parent_manager = $relation->get_args('parent_manager'); // Allow create from parent page
$child_manager = $relation->get_args('child_manager');   // Allow create from child page

// Check if current object is parent or child
$is_parent = $relation->is_parent($object_type, $object_name);

// Get meta fields defined for this relation
$meta_fields = $relation->get_meta_fields();
```

### Parsing Object Type

```php
// Object types are in format 'type::subtype'
// Examples:
//   'posts::post'
//   'posts::page'
//   'terms::category'
//   'users::user'
//   'cct::my-content-type'

// Parse type string
$parts = jet_engine()->relations->types_helper->type_parts_by_name('cct::vehicles');
// Returns: ['cct', 'vehicles']

// Build type string
$type_name = jet_engine()->relations->types_helper->type_name_by_parts('cct', 'vehicles');
// Returns: 'cct::vehicles'
```

### Creating/Updating Relations

```php
/**
 * Update relation (create or replace depending on type)
 * 
 * @param int $parent_object_id Parent item ID
 * @param int $child_object_id Child item ID
 * @return int|false Relation row ID or false on failure
 */
$relation->update($parent_object_id, $child_object_id);

// For context-aware updates (handles type constraints)
$relation->set_update_context('parent'); // or 'child'
$relation->update($parent_id, $child_id);
```

### Getting Related Items

```php
// Get children for a parent
$children = $relation->get_children($parent_object_id);
// Returns array of relation rows with child_object_id

// Get parents for a child
$parents = $relation->get_parents($child_object_id);
// Returns array of relation rows with parent_object_id

// Get raw related IDs
$child_ids = $relation->get_related_items($parent_id, 'child');
$parent_ids = $relation->get_related_items($child_id, 'parent');
```

### Deleting Relations

```php
/**
 * Delete specific relation row
 * 
 * @param int $parent_object_id
 * @param int $child_object_id
 */
$relation->delete_rows($parent_object_id, $child_object_id);

// Delete all relations for an object
$relation->cleanup_relation($object_id, 'parent'); // or 'child'
```

---

## Hierarchy (Grandparent Relations)

### Check if Relation is Hierarchical

```php
$parent_rel_id = $relation->get_args('parent_rel');

if ($parent_rel_id) {
    // This relation has a parent relation
    $parent_relation = jet_engine()->relations->get_active_relations($parent_rel_id);
}
```

### Get Grandparents/Grandchildren

```php
// Only available if hierarchy module is loaded
if (jet_engine()->relations->hierachy) {
    
    // Get grandparents for a grandchild
    $grandparent_ids = jet_engine()->relations->hierachy->get_grandparents(
        $child_relation_id,
        $grandchild_object_id
    );
    
    // Get grandchildren for a grandparent
    $grandchild_ids = jet_engine()->relations->hierachy->get_grandchildren(
        $child_relation_id,
        $grandparent_object_id
    );
}
```

---

## Types Helper

Utility class for working with different object types (posts, terms, users, CCT).

```php
$types_helper = jet_engine()->relations->types_helper;

// Get all items of a type for dropdown
$items = $types_helper->get_type_items($type, $subtype, $relation, $exclude_ids);
// Returns: [['value' => id, 'label' => 'Title'], ...]

// Get item title
$title = $types_helper->get_type_item_title($type_name, $item_id, $relation);

// Get edit URL
$edit_url = $types_helper->get_type_item_edit_url($type_name, $item_id, $relation);

// Get view URL
$view_url = $types_helper->get_type_item_view_url($type_name, $item_id, $relation);

// Create new item
$new_id = $types_helper->create_item($type_name, $item_data);

// Delete item
$types_helper->delete_item($type_name, $item_id);

// Check user capabilities
$can_edit = $types_helper->current_user_can('edit', $type_name, $item_id, $subtype);
$can_delete = $types_helper->current_user_can('delete', $type_name, $item_id, $subtype);
```

---

## AJAX Endpoints (JetEngine Internal)

JetEngine uses these AJAX actions for relations. Reference only - we'll create our own.

```php
// Get items of a type
wp_ajax_jet_engine_relations_get_type_items

// Get related items list
wp_ajax_jet_engine_relations_get_related_items

// Update relation (connect items)
wp_ajax_jet_engine_relations_update_relation_items

// Disconnect relation
wp_ajax_jet_engine_relations_disconnect_relation_items

// Create new item
wp_ajax_jet_engine_relations_create_item_of_type

// Save relation meta
wp_ajax_jet_engine_relations_save_relation_meta
```

---

## CCT Edit Page Detection

Detect if we're on a CCT edit screen:

```php
// Check current admin page
function is_cct_edit_screen() {
    if (!is_admin()) {
        return false;
    }
    
    // CCT edit pages use these query params
    // page=jet-cct-{slug}&cct_action=edit|add
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    $action = isset($_GET['cct_action']) ? $_GET['cct_action'] : '';
    
    if (strpos($page, 'jet-cct-') === 0 && in_array($action, ['edit', 'add'])) {
        // Extract CCT slug from page param
        $cct_slug = str_replace('jet-cct-', '', $page);
        return $cct_slug;
    }
    
    return false;
}

// Get current item ID if editing
function get_current_cct_item_id() {
    if (isset($_GET['item_id'])) {
        return absint($_GET['item_id']);
    }
    return false;
}
```

---

## Form Selector

The CCT edit form can be located with:

```javascript
// JavaScript
const form = document.querySelector('form[action*="jet-cct-save-item"]');

// jQuery
const $form = $('form[action*="jet-cct-save-item"]');
```

Form structure:

```html
<form method="post" action="...?cct_action=save-item&...">
    <input type="hidden" name="cct_nonce" value="...">
    
    <!-- CCT field inputs -->
    <input name="field_slug" value="...">
    
    <!-- Our injected hidden inputs go here -->
    <input type="hidden" name="_injector_rel_relation_slug" value="123,456">
    
    <!-- Submit buttons -->
    <button type="submit">Save</button>
</form>
```

---

## Nonce Names

```php
// CCT form nonce
$nonce_action = 'jet-cct-nonce';
$nonce_field = 'cct_nonce';

// Relations control nonce
$relations_nonce_action = 'jet-engine-relations-control';
```

---

## JavaScript Globals (JetEngine)

When on relation control pages, JetEngine provides:

```javascript
window.JetEngineRelationsCommon = {
    _nonce: 'abc123...',
    // ... other config
};
```

We should not rely on these - create our own config object.

