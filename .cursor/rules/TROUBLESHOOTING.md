# Troubleshooting Guide

## Common Issues & Solutions

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

