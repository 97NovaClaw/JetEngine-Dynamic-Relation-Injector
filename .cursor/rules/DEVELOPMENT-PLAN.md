# Development Plan

## Overview

This document outlines the phased development approach for the JetEngine Dynamic Relation Injector plugin.

**Current Status:** ✅ v1.0.0 Production Ready (Beta Testing)  
**Completion Date:** January 11, 2026

---

## Phase 1: Foundation (The Skeleton) ✅ COMPLETE

**Goal:** Establish plugin structure and verify JetEngine integration

### Tasks

- [x] **1.1** Create plugin folder structure
- [x] **1.2** Create main plugin file with activation/deactivation hooks
- [x] **1.3** Build Discovery Engine class (Module A)
  - [x] `get_all_ccts()` - Returns all registered CCTs with slug, name, table
  - [x] `get_cct_fields($cct_slug)` - Returns field definitions for a CCT
  - [x] `get_active_relations()` - Returns all JetEngine relations with types
  - [x] `get_relations_for_cct($cct_slug)` - Returns relations where CCT is parent OR child
  - [x] **BONUS:** Added support for Taxonomy and Post Type objects
  - [x] **BONUS:** Added `relation_table_exists()` validation
- [x] **1.4** Build Configuration Database class
  - [x] Create database table on activation
  - [x] CRUD methods for configuration storage
- [x] **1.5** Verify Discovery Engine with debug output

### Deliverables ✅
- ✅ Working plugin that can read JetEngine CCT and Relation data
- ✅ Database table created on activation
- ✅ Debug page showing discovered data

---

## Phase 2: The Data Broker (Module E) ✅ COMPLETE

**Goal:** Create AJAX API for querying and creating CCT items

### Tasks

- [x] **2.1** Create Data Broker class with AJAX endpoints
- [x] **2.2** Implement `query_items` action
  - [x] Input: `cct_slug`, `search_term`, `display_field`, `filters`
  - [x] Output: JSON array `[{ _ID: 1, label: "..." }]`
  - [x] Support pagination for large datasets
  - [x] **BONUS:** Support for CCT, Taxonomy, and Post Type searches
- [x] **2.3** Implement `create_item` action
  - [x] Input: `cct_slug`, `field_values`
  - [x] Output: `{ success: true, item_id: 123 }`
  - [x] **BONUS:** Support for creating CCT items, taxonomy terms, and posts
- [x] **2.4** Security: nonce validation, capability checks, input sanitization
- [x] **2.5** Test via Postman/cURL

### Deliverables ✅
- ✅ Working AJAX endpoints for CCT item operations
- ✅ Tested with manual requests and browser integration

---

## Phase 3: The Transaction Processor (Module D) ✅ COMPLETE

**Goal:** Intercept CCT save and process injected relation data

### Tasks

- [x] **3.1** Create Transaction Processor class
- [x] **3.2** Hook into `jet-engine/custom-content-types/updated-item/{slug}`
- [x] **3.3** Hook into `jet-engine/custom-content-types/created-item/{slug}`
- [x] **3.4** Scan $_POST for `jet_injector_relations_data` JSON
- [x] **3.5** Extract relation ID and related item IDs
- [x] **3.6** Call JetEngine relations API to create/update relation
- [x] **3.7** Handle relation type constraints (one_to_one, one_to_many, many_to_many)
- [x] **3.8** Manual testing: inject hidden input via DevTools, verify relation created
- [x] **BONUS:** Support for Taxonomy and Post Type relations
- [x] **BONUS:** Correct `rel_id` and `parent_rel` insertion for JetEngine compatibility
- [x] **BONUS:** Fixed hook parameter order issues between created/updated hooks

### Deliverables ✅
- ✅ Relations are created when hidden fields present in form
- ✅ Tested manually with browser DevTools
- ✅ Extensive debug logging for troubleshooting

---

## Phase 4: The Runtime Engine (Module C) ✅ COMPLETE

**Goal:** JavaScript that renders the relation selector UI

### Tasks

- [x] **4.1** Create Runtime Loader class (PHP)
  - [x] Detect CCT edit screen
  - [x] Load configuration for current CCT
  - [x] Enqueue JS/CSS with localized config data
- [x] **4.2** Create injector.js
  - [x] Read `window.jetInjectorConfig`
  - [x] Locate form: `$('form[action*="jet-cct-save-item"]')`
  - [x] Render relation selector container
  - [x] Append hidden JSON data field inside form
- [x] **4.3** Implement search-as-you-type
  - [x] Debounced AJAX calls to Data Broker
  - [x] Dropdown results display
  - [x] Item selection updates hidden input
  - [x] Auto-detect title field from CCT schema
- [x] **4.4** Implement cascading selectors for grandparent/grandchild relations
  - [x] Detect if relation has `parent_rel` or is hierarchical
  - [x] Show 2-step cascading modal for hierarchical relations
  - [x] Filter step 2 dropdown based on step 1 selection
  - [x] Support both grandparent (up) and grandchild (down) directions
  - [x] AJAX endpoint for cascade filtering (`ajax_search_cascade_items`)
  - [x] CSS styling with visual step indicators
- [x] **4.5** Implement "Add New" modal
  - [x] Toggle form for creating new related item
  - [x] Submit to Data Broker `create_item`
  - [x] Auto-select newly created item
  - [x] Support for CCT, Taxonomy, and Post Type creation
- [x] **4.6** Display existing relations if editing existing CCT item
- [x] **4.7** Support removing relations (many_to_many)

### Deliverables ✅
- ✅ Full UI rendered on CCT edit pages
- ✅ Search, select, create workflow functional
- ✅ Hidden inputs properly injected into form
- ✅ Comprehensive console logging for debugging

---

## Phase 5: Admin Configuration UI ✅ COMPLETE

**Goal:** Settings page for configuring which CCTs get relation injection

### Tasks

- [x] **5.1** Create Admin Page class
- [x] **5.2** Register top-level menu: "Relation Injector"
- [x] **5.3** Build main settings template
  - [x] List all CCTs with toggle to enable/disable
  - [x] For each enabled CCT, show discovered relations
  - [x] Per-relation config: display field, allow create new
  - [x] **BONUS:** Visual warnings for missing DB tables
  - [x] **BONUS:** Clickable links to fix issues in JetEngine
- [x] **5.4** Build Debug tab
  - [x] Toggle: Enable PHP Logging
  - [x] Toggle: Enable JS Console Logging
  - [x] Toggle: Enable Admin Notices
  - [x] View/Clear debug log
- [x] **5.5** AJAX save configuration
  - [x] **BONUS:** Validation with detailed error messages
  - [x] **BONUS:** Table existence checking
- [x] **5.6** Style to match JetEngine admin UI
- [x] **BONUS:** Added Utilities tab (Phase 7 feature implemented early!)

### Deliverables ✅
- ✅ Complete admin interface for configuration
- ✅ Debug controls for development
- ✅ Validation system prevents invalid configurations

---

## Phase 6: Polish & Edge Cases ✅ COMPLETE

**Goal:** Handle edge cases, improve UX, optimize performance

### Tasks

- [x] **6.1** Handle CCT with no relations gracefully
- [x] **6.2** Handle disabled/inactive relations
- [x] **6.3** Validation messages for required relations
  - [x] Config validation with detailed error messages
  - [x] UI warnings in admin for missing tables/title_fields
- [x] **6.4** Loading states and error handling
  - [x] Spinners and loading indicators
  - [x] Error messages in modal and AJAX responses
- [x] **6.5** Performance optimization for large item sets
  - [x] Query limits (20 items default)
  - [x] Debounced search inputs
  - [x] Caching in Discovery module
- [x] **6.6** Localization (i18n) support
  - [x] All strings wrapped in `__()` functions
  - [x] Text domain: `jet-relation-injector`
- [x] **6.7** Uninstall cleanup
  - [x] Database table cleanup in `uninstall.php`
  - [ ] **PARTIAL:** Relation data cleanup (not implemented)
- [x] **6.8** Documentation and inline comments
  - [x] Comprehensive `.cursor/rules/` documentation
  - [x] PHPDoc blocks for all methods
  - [x] Inline comments for complex logic

### Deliverables ✅
- ✅ Production-ready plugin
- ✅ No console errors or PHP warnings (with proper error handling)
- ✅ Graceful degradation for missing dependencies

---

## Phase 7: Advanced Features ✅ PARTIALLY COMPLETE

Originally planned for post-1.0, several were implemented in v1.0.0:

- [x] **Support for Post Types** - ✅ Implemented in v1.0.0
- [x] **Support for Taxonomies** - ✅ Implemented in v1.0.0
- [x] **Bulk relation management** - ✅ Implemented via Utilities tab (bulk re-save)
- [x] **Diagnostic tools** - ✅ Implemented via Utilities tab
- [ ] **Import/export configurations** - Deferred to v1.1+
- [ ] **REST API for headless setups** - Deferred to v1.1+
- [ ] **Gutenberg block for relation display** - Deferred to v2.0+

## Phase 8: Maintenance & Utilities ✅ COMPLETE

**New phase added during development**

- [x] **Cache clearing tools**
  - [x] Clear JetEngine transients
  - [x] Flush WordPress object cache
- [x] **Bulk operations**
  - [x] Bulk re-save CCT items to refresh cached data
  - [x] Progress tracking
- [x] **Diagnostics**
  - [x] Relation configuration checker
  - [x] Database table validator
  - [x] Title field configuration checker
  - [x] Detailed issue reporting with solutions

---

## Testing Checklist

### Manual Testing Scenarios

1. **New CCT Item + New Relation**
   - Create new CCT item
   - Select existing related item
   - Save → Verify relation exists in database

2. **New CCT Item + Create New Related Item**
   - Create new CCT item
   - Click "Add New" for related item
   - Fill minimal fields, create
   - Save parent → Both items exist with relation

3. **Edit Existing CCT Item**
   - Edit item that has existing relations
   - Verify relations pre-populated
   - Add/remove relations
   - Save → Verify changes persisted

4. **Cascading Grandparent Relations**
   - CCT with hierarchical relations (Brand → Vehicle → Service)
   - Select Brand first
   - Vehicle dropdown filters accordingly
   - Save → Verify full chain

5. **Relation Type Constraints**
   - `one_to_one`: Warning when selecting second item
   - `one_to_many`: Multiple children allowed
   - `many_to_many`: Multiple on both sides

6. **Error Scenarios**
   - JetEngine deactivated → Graceful notice
   - Relation deleted → Handle missing relation
   - Network error during AJAX → User feedback

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 0.1.0 | Jan 10, 2026 | Phase 1 complete - Foundation & Discovery |
| 0.2.0 | Jan 10, 2026 | Phase 2 complete - Data Broker |
| 0.3.0 | Jan 10, 2026 | Phase 3 complete - Transaction Processor |
| 0.4.0 | Jan 10, 2026 | Phase 4 complete - Runtime Engine |
| 0.5.0 | Jan 10, 2026 | Phase 5 complete - Admin UI |
| 0.9.0 | Jan 11, 2026 | Critical bug fixes (Factory object handling, hook signatures) |
| 0.9.5 | Jan 11, 2026 | Added Taxonomy and Post Type support |
| 1.0.0 | Jan 11, 2026 | ✅ Production Ready - All phases complete + Utilities module |

### v1.0.0 Highlights
- ✅ CCT-to-CCT, CCT-to-Taxonomy, CCT-to-Post Type relations
- ✅ Validation system with UI warnings
- ✅ Utilities tab (cache clearing, bulk re-save, diagnostics)
- ✅ WordPress 6.0+ compatibility (tested on 6.2+)
- ✅ Extensive debug logging system
- ✅ All critical bugs resolved

