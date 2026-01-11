# Troubleshooting Guide

## Common Issues & Solutions

---

## ⚡ Critical Issues (Real-World)

### Relations Show #ID Instead of Titles

**Symptoms:**
- Relation selectors show "#2", "#15" instead of readable names
- Users can't identify items
- Persists even after updating items with titles

**Root Cause:**
JetEngine relations require a `title_field` configuration per CCT to display meaningful names.

**Solution:**

1. **Use the Utilities Tab → Diagnose Relations:**
   - Go to Admin → Relation Injector → Utilities
   - Click "Run Diagnosis"
   - Look for "⚠️ Issues" status
   - Check which relations are missing `title_field`

2. **Fix in JetEngine:**
   - Go to JetEngine → Relations
   - Edit the relation showing issues
   - Find "Title Field for [CCT Name]" setting
   - Select the field that should display (e.g., `model`, `name`, `title`)
   - Save the relation

3. **Refresh Cached Data (Optional):**
   - Go to Utilities → Bulk Re-save CCT Items
   - Select the CCT
   - Click "Re-save All Items"
   - This triggers JetEngine to refresh cached display names

---

### Relation Table Does Not Exist

**Symptoms:**
- Error: "Relation table does not exist: wp_jet_rel_XX"
- Relations fail to save
- Config validation fails

**Root Cause:**
JetEngine relation not configured to use a separate database table.

**Solution:**

1. **Check Table Existence:**
```sql
SHOW TABLES LIKE 'wp_jet_rel_%';
```

2. **Enable Table Storage:**
   - Go to JetEngine → Relations
   - Edit the relation
   - Enable ☑️ "Store in separate database table"
   - Save (this creates the `wp_jet_rel_XX` table)

3. **Verify:**
```sql
SHOW TABLES LIKE 'wp_jet_rel_XX';  -- Should return the table
```

**Note:** Our plugin REQUIRES this setting. Meta-based relations are not supported.

---

### Cannot Use Object of Type Factory as Array

**Symptoms:**
- Fatal error: "Cannot use object of type Jet_Engine\Modules\Custom_Content_Types\Factory as array"
- Admin page crashes
- Occurs in `class-discovery.php`

**Root Cause:**
JetEngine's `get_content_types()` returns Factory objects, not arrays.

**Solution:**
This was fixed in v1.0.0. Update to latest version:
```php
// BEFORE (BROKEN):
$cct_data = $content_type['labels']['name'];

// AFTER (FIXED):
$cct_data = $content_type->get_arg('labels')['name'];
```

---

### Relations Not Saving (Hook Parameter Order)

**Symptoms:**
- Debug shows hook firing
- Item ID is wrong or NULL
- Parent/child reversed in database

**Root Cause:**
JetEngine's `created-item` and `updated-item` hooks have DIFFERENT signatures:
- `created-item/{slug}`: `($item_data, $item_id, $handler)`
- `updated-item/{slug}`: `($item_data, $prev_item_data, $handler)`

**Solution:**
This was fixed in v1.0.0. Separate callbacks handle each hook correctly.

---

### Missing rel_id and parent_rel Columns

**Symptoms:**
- Relations save to database
- JetEngine's native UI doesn't show them
- Relation appears "orphaned"

**Root Cause:**
JetEngine requires `rel_id` and `parent_rel` columns for relation tracking.

**Solution:**
This was fixed in v1.0.0:
```php
$wpdb->insert($table, [
    'parent_object_id' => $parent_id,
    'child_object_id' => $child_id,
    'rel_id' => $relation_id,     // REQUIRED
    'parent_rel' => $parent_rel,  // REQUIRED for hierarchies
]);
```

---

### Taxonomy/Post Type Relations Not Working

**Symptoms:**
- CCT-to-Taxonomy relations show "Select undefined"
- CCT-to-Post Type relations fail
- Only CCT-to-CCT works

**Root Cause:**
Originally only CCT relations were implemented.

**Solution:**
Updated in v1.0.0 to support:
- `cct::slug` - Custom Content Types
- `terms::taxonomy` - Taxonomy Terms
- `posts::post_type` - Post Types

The plugin now parses the object notation and uses appropriate WordPress APIs (`get_terms()`, `WP_Query`, etc.).

---

## Development Issues

### JetEngine Not Detected

**Symptoms:**
- Plugin shows "JetEngine not found" error
- Discovery Engine returns empty arrays

**Solutions:**

1. **Check JetEngine is active:**
```php
if (!function_exists('jet_engine')) {
    // JetEngine not loaded
}
```

2. **Check hook timing:**
```php
// Our code must run after JetEngine initializes
add_action('init', 'our_init_function', 20); // Priority > 11
```

3. **Check module is enabled:**
```php
$modules = jet_engine()->modules->get_modules_for_js();
if (!isset($modules['custom-content-types'])) {
    // CCT module not enabled in JetEngine settings
}
```

---

### CCT Items Not Saving Relations

**Symptoms:**
- Hidden inputs are in the form
- CCT item saves successfully
- Relations are not created

**Possible Causes:**

1. **Hook not registered correctly:**
```php
// Ensure hook name matches CCT slug exactly
add_action(
    'jet-engine/custom-content-types/updated-item/my-cct-slug',
    [$this, 'process_update'],
    10, 3
);
```

2. **Wrong relation direction:**
```php
// Check if CCT is parent or child in this relation
$is_parent = $relation->is_parent('cct', $cct_slug);
if ($is_parent) {
    // CCT item is parent, related items are children
    $relation->update($cct_item_id, $related_item_id);
} else {
    // CCT item is child, related items are parents
    $relation->update($related_item_id, $cct_item_id);
}
```

3. **Relation type constraint:**
```php
// For one_to_one, existing relation may block new one
// Need to delete existing first
$relation->delete_rows($parent_id, null); // Clear existing
$relation->update($parent_id, $new_child_id);
```

---

### AJAX Requests Failing

**Symptoms:**
- Search returns no results or errors
- "Invalid security token" errors
- 403 or 500 errors

**Solutions:**

1. **Check nonce:**
```php
// Generating
wp_create_nonce('jet_injector_nonce');

// Verifying
if (!wp_verify_nonce($_POST['nonce'], 'jet_injector_nonce')) {
    wp_send_json_error(['message' => 'Invalid nonce']);
}
```

2. **Check AJAX action registration:**
```php
// Must use both for logged-in users
add_action('wp_ajax_jet_injector_broker', [$this, 'handle_request']);
```

3. **Check JavaScript is sending correctly:**
```javascript
fetch(jetInjectorConfig.ajaxUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
        action: 'jet_injector_broker',
        nonce: jetInjectorConfig.nonce,
        // ... other data
    })
});
```

---

### UI Not Rendering

**Symptoms:**
- Relations box doesn't appear on CCT edit page
- JavaScript errors in console

**Solutions:**

1. **Check page detection:**
```php
function detect_cct_edit_screen() {
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    $action = isset($_GET['cct_action']) ? $_GET['cct_action'] : '';
    
    error_log("Page: {$page}, Action: {$action}");
    
    if (strpos($page, 'jet-cct-') !== 0) {
        return false;
    }
    // ...
}
```

2. **Check form selector:**
```javascript
const form = document.querySelector('form[action*="jet-cct-save-item"]');
console.log('Form found:', form);
if (!form) {
    console.error('CCT form not found - selector may have changed');
}
```

3. **Check script enqueue:**
```php
add_action('admin_enqueue_scripts', function($hook) {
    error_log("Current hook: {$hook}");
    // CCT pages don't have a standard hook - check $_GET
});
```

---

### Database Table Not Created

**Symptoms:**
- Plugin activates without error
- Configuration not saving
- "Table doesn't exist" errors

**Solutions:**

1. **Check dbDelta syntax:**
```php
// dbDelta is VERY picky about syntax
// - Two spaces after PRIMARY KEY
// - Specific formatting required
$sql = "CREATE TABLE {$table_name} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (id)
) {$charset_collate};"; // Note: PRIMARY KEY  (id) - TWO SPACES
```

2. **Check for errors:**
```php
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$result = dbDelta($sql);
error_log('dbDelta result: ' . print_r($result, true));
```

3. **Manually verify:**
```php
global $wpdb;
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
error_log("Table exists: " . ($exists ? 'yes' : 'no'));
```

---

## Performance Issues

### Slow Search Results

**Symptoms:**
- Long delay when typing in search
- AJAX requests taking several seconds

**Solutions:**

1. **Add database indexes:**
```sql
ALTER TABLE wp_jet_cct_vehicles 
ADD INDEX idx_model_name (model_name(50));
```

2. **Limit results:**
```php
$wpdb->get_results($wpdb->prepare(
    "SELECT _ID, model_name FROM {$table} 
     WHERE model_name LIKE %s 
     LIMIT 20",  // Always limit!
    $search
));
```

3. **Increase debounce delay:**
```javascript
// Debounce search to reduce AJAX calls
let searchTimeout;
input.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        doSearch(e.target.value);
    }, 300); // 300ms delay
});
```

---

### Memory Issues with Large CCTs

**Symptoms:**
- PHP memory errors
- Page crashes on CCTs with many items

**Solutions:**

1. **Never load all items:**
```php
// BAD
$all_items = $cct->db->query([]);

// GOOD
$items = $cct->db->query([
    'limit' => 20,
    'offset' => ($page - 1) * 20
]);
```

2. **Use streaming for exports:**
```php
// If ever needed for export, stream instead of buffering
```

---

## Debugging Techniques

### Enable Debug Logging

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// In our plugin
function jet_injector_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[JetInjector] ' . print_r($message, true));
    }
}
```

### JavaScript Console Logging

```javascript
// Enable in debug settings, then:
if (window.jetInjectorConfig?.debug) {
    console.log('[JetInjector]', 'Message here', data);
}
```

### Inspect Hidden Inputs

Before submitting the CCT form, check hidden inputs in browser DevTools:

```javascript
document.querySelectorAll('input[name^="_injector_rel"]').forEach(input => {
    console.log(input.name, input.value);
});
```

### Trace Relation Creation

```php
add_action('jet-engine/custom-content-types/updated-item/my-cct', function($item, $prev, $handler) {
    jet_injector_log('=== RELATION PROCESSING ===');
    jet_injector_log('Item ID: ' . $item['_ID']);
    jet_injector_log('POST data: ' . print_r($_POST, true));
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_injector_rel_') === 0) {
            jet_injector_log("Found relation key: {$key} = {$value}");
        }
    }
}, 10, 3);
```

---

## Error Messages Reference

| Error | Cause | Solution |
|-------|-------|----------|
| "JetEngine not active" | JetEngine plugin not installed/active | Activate JetEngine |
| "CCT module not enabled" | CCT feature disabled in JetEngine | Enable in JetEngine > Modules |
| "Invalid security token" | Nonce expired or wrong | Check nonce generation/verification |
| "You do not have permission" | User capability check failed | Check user role |
| "Relation not found" | Relation deleted or ID wrong | Verify relation still exists |
| "Table doesn't exist" | Database table not created | Check activation hook, run dbDelta |
| "Cannot read property of undefined" (JS) | Config not localized properly | Check wp_localize_script |

---

## Getting Help

### Information to Gather

When reporting issues, include:

1. **WordPress version:** `get_bloginfo('version')`
2. **JetEngine version:** Check in Plugins page
3. **Plugin version:** Our version constant
4. **PHP version:** `phpversion()`
5. **Error logs:** Last 50 lines of debug.log
6. **Browser console:** Any JavaScript errors
7. **Steps to reproduce:** Exact actions that cause the issue

### Debug Info Dump

```php
function jet_injector_debug_info() {
    return [
        'wordpress' => get_bloginfo('version'),
        'php' => phpversion(),
        'jetengine' => defined('JET_ENGINE_VERSION') ? JET_ENGINE_VERSION : 'N/A',
        'plugin' => JET_INJECTOR_VERSION,
        'cct_module' => function_exists('jet_engine') && jet_engine()->modules->is_module_active('custom-content-types'),
        'relations_count' => function_exists('jet_engine') ? count(jet_engine()->relations->get_active_relations()) : 0,
        'table_exists' => (new Jet_Injector_Config_DB())->table_exists(),
    ];
}
```

