# Architecture Overview

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           WordPress Admin                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────┐        ┌─────────────────────────────────────┐ │
│  │   CCT Edit Screen   │        │      Admin Settings Page            │ │
│  │   (JetEngine)       │        │      (Relation Injector)            │ │
│  │                     │        │                                     │ │
│  │  ┌───────────────┐  │        │  ┌─────────────────────────────┐   │ │
│  │  │ CCT Fields    │  │        │  │  CCT Configuration Cards    │   │ │
│  │  └───────────────┘  │        │  │  - Enable/Disable per CCT   │   │ │
│  │                     │        │  │  - Relation display options │   │ │
│  │  ┌───────────────┐  │        │  └─────────────────────────────┘   │ │
│  │  │ INJECTED:     │  │        │                                     │ │
│  │  │ Relation Box  │◄─┼────────┼─── Configuration loaded             │ │
│  │  │ - Selectors   │  │        │                                     │ │
│  │  │ - Hidden Inp  │  │        │  ┌─────────────────────────────┐   │ │
│  │  └───────────────┘  │        │  │  Debug Settings Tab         │   │ │
│  │                     │        │  └─────────────────────────────┘   │ │
│  │  ┌───────────────┐  │        └─────────────────────────────────────┘ │
│  │  │ Save Button   │  │                                                │
│  │  └───────┬───────┘  │                                                │
│  └──────────┼──────────┘                                                │
│             │                                                            │
│             │ Form POST (includes hidden inputs)                         │
│             ▼                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    Transaction Processor                          │   │
│  │  1. JetEngine saves CCT item (normal flow)                       │   │
│  │  2. Our hook fires: 'updated-item/{slug}'                        │   │
│  │  3. Extract _injector_rel_* from $_POST                          │   │
│  │  4. Call JetEngine Relations API                                 │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            Data Layer                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────┐    ┌─────────────────────┐                     │
│  │ Plugin Config Table │    │  JetEngine Tables   │                     │
│  │ jet_injector_config │    │  - CCT item tables  │                     │
│  │                     │    │  - Relations tables │                     │
│  └─────────────────────┘    └─────────────────────┘                     │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Module Architecture

### Module A: Discovery Engine

**Class:** `Jet_Injector_Discovery`  
**File:** `includes/class-discovery.php`

**Purpose:** Read JetEngine's internal registry to discover CCTs, fields, and relations.

```php
class Jet_Injector_Discovery {
    
    /**
     * Get all registered CCTs
     * @return array [{slug, name, db_table, fields_count}, ...]
     */
    public function get_all_ccts(): array;
    
    /**
     * Get fields for a specific CCT
     * @param string $cct_slug
     * @return array [{name, title, type, options}, ...]
     */
    public function get_cct_fields(string $cct_slug): array;
    
    /**
     * Get all active JetEngine relations
     * @return array [{id, name, parent_object, child_object, type, parent_rel}, ...]
     */
    public function get_active_relations(): array;
    
    /**
     * Get relations where a specific CCT is parent or child
     * @param string $cct_slug
     * @return array [{relation_id, role, relation_data}, ...]
     */
    public function get_relations_for_cct(string $cct_slug): array;
    
    /**
     * Check if a relation has parent relations (for cascading)
     * @param int $relation_id
     * @return array|false Parent relation chain or false
     */
    public function get_relation_hierarchy(int $relation_id): array|false;
}
```

---

### Module B: Configuration Store

**Class:** `Jet_Injector_Config_Manager`  
**File:** `includes/class-config-manager.php`

**Database Class:** `Jet_Injector_Config_DB`  
**File:** `includes/class-config-db.php`

**Purpose:** Store and retrieve injection configurations per CCT.

```php
class Jet_Injector_Config_DB {
    
    const TABLE_NAME = 'jet_injector_configs';
    
    /**
     * Create database table on plugin activation
     */
    public function create_table(): void;
    
    /**
     * Drop table on uninstall
     */
    public function drop_table(): void;
}

class Jet_Injector_Config_Manager {
    
    /**
     * Get configuration for a specific CCT
     * @param string $object_type 'cct', 'post_type', 'taxonomy'
     * @param string $object_slug
     * @return array|null Configuration data or null
     */
    public function get_config(string $object_type, string $object_slug): ?array;
    
    /**
     * Save/update configuration
     * @param string $object_type
     * @param string $object_slug
     * @param array $config_data
     * @return bool Success
     */
    public function save_config(string $object_type, string $object_slug, array $config_data): bool;
    
    /**
     * Delete configuration
     * @param string $object_type
     * @param string $object_slug
     * @return bool Success
     */
    public function delete_config(string $object_type, string $object_slug): bool;
    
    /**
     * Get all active configurations
     * @return array All configs with is_active = 1
     */
    public function get_all_active_configs(): array;
    
    /**
     * Check if a CCT has active injection config
     * @param string $cct_slug
     * @return bool
     */
    public function is_cct_configured(string $cct_slug): bool;
}
```

---

### Module C: Runtime Engine (JavaScript)

**File:** `assets/js/injector.js`

**Purpose:** Client-side rendering of relation selectors and form manipulation.

```javascript
window.JetInjector = {
    
    // Configuration passed from PHP
    config: window.jetInjectorConfig || {},
    
    /**
     * Initialize the injector
     */
    init: function() {
        this.form = document.querySelector('form[action*="jet-cct-save-item"]');
        if (!this.form || !this.config.relations) return;
        
        this.renderRelationsBox();
        this.bindEvents();
    },
    
    /**
     * Render the relations container box
     */
    renderRelationsBox: function() {},
    
    /**
     * Render individual relation selector
     * @param {Object} relation Relation configuration
     */
    renderRelationSelector: function(relation) {},
    
    /**
     * Render cascading selectors for hierarchical relations
     * @param {Object} relation Relation with parent_rel
     */
    renderCascadeSelector: function(relation) {},
    
    /**
     * Search for items via AJAX
     * @param {string} cctSlug Target CCT slug
     * @param {string} searchTerm Search query
     * @param {Object} filters Optional filters (e.g., parent ID)
     */
    searchItems: async function(cctSlug, searchTerm, filters = {}) {},
    
    /**
     * Handle item selection
     * @param {string} relationSlug
     * @param {number} itemId
     * @param {string} itemLabel
     */
    selectItem: function(relationSlug, itemId, itemLabel) {},
    
    /**
     * Remove selected item
     * @param {string} relationSlug
     * @param {number} itemId
     */
    removeItem: function(relationSlug, itemId) {},
    
    /**
     * Update hidden input value
     * @param {string} relationSlug
     */
    updateHiddenInput: function(relationSlug) {},
    
    /**
     * Open create new item modal
     * @param {string} cctSlug
     * @param {string} relationSlug
     */
    openCreateModal: function(cctSlug, relationSlug) {},
    
    /**
     * Submit new item creation
     * @param {string} cctSlug
     * @param {Object} fieldValues
     */
    createItem: async function(cctSlug, fieldValues) {}
};

document.addEventListener('DOMContentLoaded', () => JetInjector.init());
```

---

### Module D: Transaction Processor

**Class:** `Jet_Injector_Transaction_Processor`  
**File:** `includes/class-transaction-processor.php`

**Purpose:** Hook into CCT save process and create relations from hidden inputs.

```php
class Jet_Injector_Transaction_Processor {
    
    /**
     * Register hooks for all configured CCTs
     */
    public function register_hooks(): void;
    
    /**
     * Process relation data after CCT item update
     * @param array $item Updated item data
     * @param array $prev_item Previous item data
     * @param object $handler Item handler instance
     */
    public function process_update(array $item, array $prev_item, $handler): void;
    
    /**
     * Process relation data after CCT item creation
     * @param array $item Created item data
     * @param int $item_id New item ID
     * @param object $handler Item handler instance
     */
    public function process_create(array $item, int $item_id, $handler): void;
    
    /**
     * Extract relation data from POST
     * @return array [{relation_slug, item_ids}, ...]
     */
    private function extract_relation_data(): array;
    
    /**
     * Create or update relations via JetEngine API
     * @param int $current_item_id The CCT item being saved
     * @param string $cct_slug
     * @param array $relation_data Extracted relation data
     */
    private function save_relations(int $current_item_id, string $cct_slug, array $relation_data): void;
    
    /**
     * Handle relation type constraints
     * @param object $relation JetEngine Relation object
     * @param int $current_id
     * @param array $related_ids
     */
    private function apply_relation_constraints($relation, int $current_id, array $related_ids): void;
}
```

---

### Module E: Data Broker (AJAX API)

**Class:** `Jet_Injector_Data_Broker`  
**File:** `includes/class-data-broker.php`

**Purpose:** Single AJAX endpoint for querying and creating CCT items.

```php
class Jet_Injector_Data_Broker {
    
    const AJAX_ACTION = 'jet_injector_broker';
    
    /**
     * Register AJAX handlers
     */
    public function register_handlers(): void;
    
    /**
     * Main AJAX router
     */
    public function handle_request(): void;
    
    /**
     * Query items from a CCT
     * Expects: cct_slug, search_term, display_field, filters, page, per_page
     * Returns: [{_ID, label, parent_label?}, ...]
     */
    private function query_items(): void;
    
    /**
     * Create new item in a CCT
     * Expects: cct_slug, field_values
     * Returns: {success, item_id, item_label}
     */
    private function create_item(): void;
    
    /**
     * Get existing relations for an item
     * Expects: cct_slug, item_id, relation_slug
     * Returns: [{related_id, label}, ...]
     */
    private function get_existing_relations(): void;
    
    /**
     * Validate request nonce
     */
    private function verify_nonce(): bool;
    
    /**
     * Check user capabilities
     */
    private function check_capabilities(): bool;
    
    /**
     * Sanitize input data
     */
    private function sanitize_input(array $data): array;
}
```

---

### Module F: Utilities (NEW in v1.0.0)

**Class:** `Jet_Injector_Utilities`  
**File:** `includes/class-utilities.php`

**Purpose:** Maintenance and diagnostic tools for JetEngine cache and CCT data.

```php
class Jet_Injector_Utilities {
    
    /**
     * Clear all JetEngine-related caches
     * - Deletes JetEngine transients
     * - Flushes object cache
     * - Clears listings cache
     * @return array {transients_deleted, object_cache_flushed}
     */
    public function clear_jetengine_caches(): array;
    
    /**
     * Bulk re-save all items in a CCT
     * - Updates cct_modified timestamp
     * - Triggers JetEngine update hooks
     * - Refreshes cached display names
     * @param string $cct_slug CCT slug
     * @return array|WP_Error {processed, total, errors}
     */
    public function bulk_resave_cct_items(string $cct_slug);
    
    /**
     * Diagnose all relations for configuration issues
     * - Checks if title_field is set for CCT relations
     * - Verifies database tables exist
     * - Returns detailed status for each relation
     * @return array {relations, issues_count, ok_count}
     */
    public function diagnose_relations(): array;
    
    /**
     * Diagnose a single relation
     * @param array $relation Relation data
     * @return array Diagnosis result with issues array
     */
    private function diagnose_single_relation(array $relation): array;
    
    /**
     * AJAX handler: Clear caches
     */
    public function ajax_clear_cache(): void;
    
    /**
     * AJAX handler: Bulk re-save CCT
     */
    public function ajax_bulk_resave(): void;
    
    /**
     * AJAX handler: Diagnose relations
     */
    public function ajax_diagnose_relations(): void;
}
```

**Key Features:**
1. **Cache Management** - Resolves stale relation data issues
2. **Bulk Operations** - Re-saves all CCT items to refresh cached titles
3. **Diagnostics** - Identifies configuration issues (missing title_field, missing tables)

---

### Admin Page Handler

**Class:** `Jet_Injector_Admin_Page`  
**File:** `includes/class-admin-page.php`

```php
class Jet_Injector_Admin_Page {
    
    const MENU_SLUG = 'jet-relation-injector';
    
    /**
     * Register admin menu
     */
    public function register_menu(): void;
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_assets(string $hook): void;
    
    /**
     * Render main settings page
     */
    public function render_settings_page(): void;
    
    /**
     * AJAX: Save CCT configuration
     */
    public function ajax_save_config(): void;
    
    /**
     * AJAX: Get CCT details for UI
     */
    public function ajax_get_cct_details(): void;
    
    /**
     * AJAX: Save debug settings
     */
    public function ajax_save_debug_settings(): void;
}
```

---

### Runtime Loader

**Class:** `Jet_Injector_Runtime_Loader`  
**File:** `includes/class-runtime-loader.php`

**Purpose:** Detect CCT edit screens and load the runtime engine.

```php
class Jet_Injector_Runtime_Loader {
    
    /**
     * Check if current screen is a CCT edit page
     * @return string|false CCT slug or false
     */
    public function detect_cct_edit_screen(): string|false;
    
    /**
     * Enqueue runtime assets for CCT edit page
     * @param string $cct_slug
     */
    public function enqueue_runtime(string $cct_slug): void;
    
    /**
     * Prepare configuration for JavaScript
     * @param string $cct_slug
     * @return array Config to localize
     */
    private function prepare_js_config(string $cct_slug): array;
    
    /**
     * Get display-ready relation data
     * @param string $cct_slug
     * @return array Relations with UI-ready data
     */
    private function get_relations_for_ui(string $cct_slug): array;
    
    /**
     * Get existing relation values if editing existing item
     * @param string $cct_slug
     * @param int $item_id
     * @return array Current relation values
     */
    private function get_existing_values(string $cct_slug, int $item_id): array;
}
```

---

## Data Flow

### 1. Page Load (CCT Edit Screen)

```
1. WordPress loads CCT edit page
2. Runtime Loader detects CCT edit screen
3. Check if CCT has active injection config
4. If yes: Load configuration from database
5. Enqueue injector.js and injector.css
6. Pass configuration via wp_localize_script
7. JS initializes and renders relation box
8. If editing existing item: fetch current relations via AJAX
```

### 2. User Searches for Related Item

```
1. User types in search field
2. JS debounces input (300ms)
3. AJAX request to Data Broker: action=query_items
4. PHP queries JetEngine CCT database
5. Returns JSON array of matching items
6. JS renders dropdown with results
7. User clicks item to select
8. JS updates UI and hidden input value
```

### 3. User Creates New Related Item

```
1. User clicks "Add New" button
2. JS opens modal with form fields
3. User fills required fields
4. User clicks "Create"
5. AJAX request to Data Broker: action=create_item
6. PHP creates item via JetEngine API
7. Returns new item ID and label
8. JS auto-selects the new item
9. Modal closes
```

### 4. Form Submission

```
1. User clicks "Save" on CCT form
2. Form submits with hidden inputs included
3. JetEngine processes CCT save (normal flow)
4. Our hook fires: jet-engine/custom-content-types/updated-item/{slug}
5. Transaction Processor extracts _injector_rel_* from $_POST
6. For each relation:
   a. Parse relation slug and item IDs
   b. Determine if CCT is parent or child
   c. Call JetEngine relation->update() method
7. Relations saved to JetEngine relation tables
```

---

## File Structure

```
jet-engine-relation-injector/
├── jet-engine-relation-injector.php    # Main plugin file, constants, bootstrap
├── uninstall.php                        # Cleanup on uninstall
├── debug.txt                            # IN-PLUGIN debug log file (auto-created)
│
├── assets/
│   ├── css/
│   │   ├── admin.css                    # Admin settings page styles
│   │   └── injector.css                 # CCT edit page injection styles
│   │
│   └── js/
│       ├── admin.js                     # Admin settings page logic
│       └── injector.js                  # Runtime injection engine
│
├── includes/
│   ├── class-plugin.php                 # Main plugin singleton
│   ├── class-discovery.php              # Module A: Discovery Engine
│   ├── class-config-manager.php         # Module B: Config CRUD
│   ├── class-config-db.php              # Database table management
│   ├── class-transaction-processor.php  # Module D: Save hook handler
│   ├── class-data-broker.php            # Module E: AJAX API
│   ├── class-admin-page.php             # Admin UI handler
│   ├── class-runtime-loader.php         # CCT page detection & loading
│   │
│   └── helpers/
│       └── debug.php                    # Debug logging functions
│
├── templates/
│   ├── admin/
│   │   ├── settings-page.php            # Main settings template
│   │   ├── cct-config-card.php          # CCT configuration card
│   │   └── debug-tab.php                # Debug settings tab
│   │
│   └── modals/
│       └── create-item-modal.php        # Inline item creation form
│
└── .cursor/
    └── rules/                            # AI Agent Reference Documentation
        ├── project-rules.mdc             # Main Cursor rules file
        ├── README.md
        ├── DEVELOPMENT-PLAN.md
        ├── ARCHITECTURE.md
        ├── DESIGN-RULES.md
        ├── JETENGINE-API-REFERENCE.md
        ├── DATABASE-SCHEMA.md
        ├── CONFIGURATION-SCHEMA.md
        ├── DEBUG-SYSTEM.md
        ├── GLOSSARY.md
        └── TROUBLESHOOTING.md
```

---

## Security Considerations

### Nonce Verification

Every AJAX request must include and verify a nonce:

```php
// In PHP - create nonce
wp_create_nonce('jet_injector_nonce');

// In PHP - verify
if (!wp_verify_nonce($_POST['nonce'], 'jet_injector_nonce')) {
    wp_send_json_error('Invalid security token');
}

// Hidden inputs in form also protected by CCT's own nonce
```

### Capability Checks

```php
// For admin operations
if (!current_user_can('manage_options')) {
    wp_send_json_error('Unauthorized');
}

// For CCT operations - check CCT-specific caps
if (!$cct_factory->user_has_access()) {
    wp_send_json_error('You do not have permission');
}
```

### Input Sanitization

```php
// All user input must be sanitized
$cct_slug = sanitize_key($_POST['cct_slug']);
$search_term = sanitize_text_field($_POST['search_term']);
$item_ids = array_map('absint', (array) $_POST['item_ids']);
```

### Output Escaping

```php
// In templates
echo esc_html($label);
echo esc_attr($value);
echo esc_url($url);

// In JavaScript (localized data is auto-escaped)
wp_localize_script('jet-injector', 'jetInjectorConfig', $config);
```

