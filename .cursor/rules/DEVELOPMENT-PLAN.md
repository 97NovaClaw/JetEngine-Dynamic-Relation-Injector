# Development Plan

## Overview

This document outlines the phased development approach for the JetEngine Dynamic Relation Injector plugin.

---

## Phase 1: Foundation (The Skeleton)

**Goal:** Establish plugin structure and verify JetEngine integration

### Tasks

- [ ] **1.1** Create plugin folder structure
- [ ] **1.2** Create main plugin file with activation/deactivation hooks
- [ ] **1.3** Build Discovery Engine class (Module A)
  - [ ] `get_all_ccts()` - Returns all registered CCTs with slug, name, table
  - [ ] `get_cct_fields($cct_slug)` - Returns field definitions for a CCT
  - [ ] `get_active_relations()` - Returns all JetEngine relations with types
  - [ ] `get_relations_for_cct($cct_slug)` - Returns relations where CCT is parent OR child
- [ ] **1.4** Build Configuration Database class
  - [ ] Create database table on activation
  - [ ] CRUD methods for configuration storage
- [ ] **1.5** Verify Discovery Engine with debug output

### Deliverables
- Working plugin that can read JetEngine CCT and Relation data
- Database table created on activation
- Debug page showing discovered data

---

## Phase 2: The Data Broker (Module E)

**Goal:** Create AJAX API for querying and creating CCT items

### Tasks

- [ ] **2.1** Create Data Broker class with AJAX endpoints
- [ ] **2.2** Implement `query_items` action
  - [ ] Input: `cct_slug`, `search_term`, `display_field`, `filters`
  - [ ] Output: JSON array `[{ _ID: 1, label: "..." }]`
  - [ ] Support pagination for large datasets
- [ ] **2.3** Implement `create_item` action
  - [ ] Input: `cct_slug`, `field_values`
  - [ ] Output: `{ success: true, item_id: 123 }`
- [ ] **2.4** Security: nonce validation, capability checks, input sanitization
- [ ] **2.5** Test via Postman/cURL

### Deliverables
- Working AJAX endpoints for CCT item operations
- Tested with manual requests

---

## Phase 3: The Transaction Processor (Module D)

**Goal:** Intercept CCT save and process injected relation data

### Tasks

- [ ] **3.1** Create Transaction Processor class
- [ ] **3.2** Hook into `jet-engine/custom-content-types/updated-item/{slug}`
- [ ] **3.3** Hook into `jet-engine/custom-content-types/created-item/{slug}`
- [ ] **3.4** Scan $_POST for `_injector_rel_*` keys
- [ ] **3.5** Extract relation slug and related item ID
- [ ] **3.6** Call JetEngine relations API to create/update relation
- [ ] **3.7** Handle relation type constraints (one_to_one, one_to_many, many_to_many)
- [ ] **3.8** Manual testing: inject hidden input via DevTools, verify relation created

### Deliverables
- Relations are created when hidden fields present in form
- Tested manually with browser DevTools

---

## Phase 4: The Runtime Engine (Module C)

**Goal:** JavaScript that renders the relation selector UI

### Tasks

- [ ] **4.1** Create Runtime Loader class (PHP)
  - [ ] Detect CCT edit screen
  - [ ] Load configuration for current CCT
  - [ ] Enqueue JS/CSS with localized config data
- [ ] **4.2** Create injector.js
  - [ ] Read `window.jetInjectorConfig`
  - [ ] Locate form: `$('form[action*="jet-cct-save-item"]')`
  - [ ] Render relation selector container
  - [ ] Append hidden inputs inside form
- [ ] **4.3** Implement search-as-you-type
  - [ ] Debounced AJAX calls to Data Broker
  - [ ] Dropdown results display
  - [ ] Item selection updates hidden input
- [ ] **4.4** Implement cascading selectors for grandparent relations
  - [ ] Detect if relation has `parent_rel`
  - [ ] Show parent dropdown first
  - [ ] Filter child dropdown based on parent selection
- [ ] **4.5** Implement "Add New" modal
  - [ ] Toggle form for creating new related item
  - [ ] Submit to Data Broker `create_item`
  - [ ] Auto-select newly created item
- [ ] **4.6** Display existing relations if editing existing CCT item
- [ ] **4.7** Support removing relations (many_to_many)

### Deliverables
- Full UI rendered on CCT edit pages
- Search, select, create workflow functional
- Hidden inputs properly injected into form

---

## Phase 5: Admin Configuration UI

**Goal:** Settings page for configuring which CCTs get relation injection

### Tasks

- [ ] **5.1** Create Admin Page class
- [ ] **5.2** Register top-level menu: "Relation Injector"
- [ ] **5.3** Build main settings template
  - [ ] List all CCTs with toggle to enable/disable
  - [ ] For each enabled CCT, show discovered relations
  - [ ] Per-relation config: display field, allow create new
- [ ] **5.4** Build Debug tab
  - [ ] Toggle: Enable PHP Logging
  - [ ] Toggle: Enable JS Console Logging
  - [ ] Toggle: Enable Admin Notices
  - [ ] View/Clear debug log
- [ ] **5.5** AJAX save configuration
- [ ] **5.6** Style to match JetEngine admin UI

### Deliverables
- Complete admin interface for configuration
- Debug controls for development

---

## Phase 6: Polish & Edge Cases

**Goal:** Handle edge cases, improve UX, optimize performance

### Tasks

- [ ] **6.1** Handle CCT with no relations gracefully
- [ ] **6.2** Handle disabled/inactive relations
- [ ] **6.3** Validation messages for required relations
- [ ] **6.4** Loading states and error handling
- [ ] **6.5** Performance optimization for large item sets
- [ ] **6.6** Localization (i18n) support
- [ ] **6.7** Uninstall cleanup
- [ ] **6.8** Documentation and inline comments

### Deliverables
- Production-ready plugin
- No console errors or PHP warnings
- Graceful degradation

---

## Phase 7: Future Enhancements (Post-1.0)

These are deferred for future versions:

- [ ] Support for Post Types (not just CCTs)
- [ ] Support for Taxonomies
- [ ] Bulk relation management
- [ ] Import/export configurations
- [ ] REST API for headless setups
- [ ] Gutenberg block for relation display

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
| 0.1.0 | TBD | Phase 1 complete - Foundation |
| 0.2.0 | TBD | Phase 2 complete - Data Broker |
| 0.3.0 | TBD | Phase 3 complete - Transaction Processor |
| 0.4.0 | TBD | Phase 4 complete - Runtime Engine |
| 0.5.0 | TBD | Phase 5 complete - Admin UI |
| 1.0.0 | TBD | Phase 6 complete - Production Ready |

