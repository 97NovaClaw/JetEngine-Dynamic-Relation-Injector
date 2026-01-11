# Design Rules

## Overview

This document defines the UI/UX patterns for the JetEngine Dynamic Relation Injector. The goal is to make our UI feel like a **native extension of JetEngine**, not a third-party add-on.

---

## Visual Language

### Color Palette

Match JetEngine's admin color scheme:

```css
:root {
  /* Primary Actions */
  --jet-primary: #007cba;           /* WordPress blue - primary buttons */
  --jet-primary-hover: #006ba1;     /* Hover state */
  --jet-primary-dark: #005a87;      /* Active state */
  
  /* Secondary / Neutral */
  --jet-secondary: #f0f0f1;         /* Light gray backgrounds */
  --jet-border: #c3c4c7;            /* Default borders */
  --jet-border-focus: #2271b1;      /* Focus state borders */
  
  /* Status Colors */
  --jet-success: #00a32a;           /* Success green */
  --jet-warning: #dba617;           /* Warning yellow */
  --jet-error: #d63638;             /* Error red */
  --jet-info: #72aee6;              /* Info blue */
  
  /* Text */
  --jet-text-primary: #1e1e1e;      /* Main text */
  --jet-text-secondary: #50575e;    /* Secondary/muted text */
  --jet-text-link: #2271b1;         /* Link color */
  
  /* Backgrounds */
  --jet-bg-white: #ffffff;
  --jet-bg-light: #f6f7f7;
  --jet-bg-alt: #f0f0f1;
}
```

### Typography

```css
/* Match WordPress admin typography */
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
  font-size: 13px;
  line-height: 1.4em;
  color: var(--jet-text-primary);
}

/* Headings */
h2 { font-size: 1.3em; font-weight: 600; }
h3 { font-size: 1.1em; font-weight: 600; }
h4 { font-size: 1em; font-weight: 600; }

/* Labels */
label {
  font-weight: 600;
  display: block;
  margin-bottom: 5px;
}

/* Descriptions */
.description {
  font-size: 12px;
  color: var(--jet-text-secondary);
  font-style: italic;
  margin-top: 4px;
}
```

---

## Component Patterns

### 1. Relation Selector Box

The main container for relation controls on the CCT edit page.

**Placement:** Below CCT fields, above the Save button area (or in its own meta-box if rendering issues occur)

```html
<div class="jet-injector-relations-box">
  <h3 class="jet-injector-box-title">
    <span class="dashicons dashicons-networking"></span>
    Related Items
  </h3>
  <div class="jet-injector-box-content">
    <!-- Relation selectors go here -->
  </div>
</div>
```

```css
.jet-injector-relations-box {
  background: var(--jet-bg-white);
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  margin: 20px 0;
  box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.jet-injector-box-title {
  background: var(--jet-bg-light);
  border-bottom: 1px solid var(--jet-border);
  padding: 12px 15px;
  margin: 0;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.jet-injector-box-title .dashicons {
  color: var(--jet-primary);
}

.jet-injector-box-content {
  padding: 15px;
}
```

### 2. Individual Relation Selector

Each relation gets its own selector row.

```html
<div class="jet-injector-relation" data-relation-slug="guide_to_vehicle">
  <div class="jet-injector-relation-header">
    <label class="jet-injector-relation-label">Vehicle Model</label>
    <span class="jet-injector-relation-type">One to Many</span>
  </div>
  
  <div class="jet-injector-relation-control">
    <!-- Cascading dropdowns or single search -->
    <div class="jet-injector-search-wrap">
      <input type="text" class="jet-injector-search" placeholder="Search vehicle models...">
      <div class="jet-injector-dropdown"></div>
    </div>
    
    <button type="button" class="jet-injector-add-new">
      <span class="dashicons dashicons-plus-alt2"></span>
      Add New
    </button>
  </div>
  
  <div class="jet-injector-selected-items">
    <!-- Selected items displayed here -->
  </div>
  
  <input type="hidden" name="_injector_rel_guide_to_vehicle" value="">
</div>
```

```css
.jet-injector-relation {
  padding: 15px 0;
  border-bottom: 1px solid var(--jet-border);
}

.jet-injector-relation:last-child {
  border-bottom: none;
}

.jet-injector-relation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.jet-injector-relation-label {
  font-weight: 600;
  font-size: 13px;
}

.jet-injector-relation-type {
  font-size: 11px;
  color: var(--jet-text-secondary);
  background: var(--jet-bg-alt);
  padding: 2px 8px;
  border-radius: 3px;
}

.jet-injector-relation-control {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.jet-injector-search-wrap {
  flex: 1;
  position: relative;
}

.jet-injector-search {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  font-size: 13px;
}

.jet-injector-search:focus {
  border-color: var(--jet-border-focus);
  box-shadow: 0 0 0 1px var(--jet-border-focus);
  outline: none;
}
```

### 3. Search Dropdown

Appears below the search input when typing.

```css
.jet-injector-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--jet-bg-white);
  border: 1px solid var(--jet-border);
  border-top: none;
  border-radius: 0 0 4px 4px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  max-height: 200px;
  overflow-y: auto;
  z-index: 100;
  display: none;
}

.jet-injector-dropdown.is-open {
  display: block;
}

.jet-injector-dropdown-item {
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid var(--jet-bg-alt);
  transition: background 0.15s;
}

.jet-injector-dropdown-item:hover,
.jet-injector-dropdown-item.is-focused {
  background: var(--jet-bg-light);
}

.jet-injector-dropdown-item:last-child {
  border-bottom: none;
}

.jet-injector-dropdown-empty {
  padding: 15px;
  text-align: center;
  color: var(--jet-text-secondary);
}

.jet-injector-dropdown-loading {
  padding: 15px;
  text-align: center;
}

.jet-injector-dropdown-loading .spinner {
  display: inline-block;
  visibility: visible;
}
```

### 4. Selected Items Display

Shows selected related items as removable chips/tags.

```html
<div class="jet-injector-selected-items">
  <div class="jet-injector-selected-item" data-id="505">
    <span class="jet-injector-item-label">Toyota Camry 2024</span>
    <button type="button" class="jet-injector-remove-item" title="Remove">
      <span class="dashicons dashicons-no-alt"></span>
    </button>
  </div>
</div>
```

```css
.jet-injector-selected-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.jet-injector-selected-item {
  display: inline-flex;
  align-items: center;
  background: var(--jet-bg-light);
  border: 1px solid var(--jet-border);
  border-radius: 3px;
  padding: 5px 8px 5px 10px;
  font-size: 12px;
  gap: 6px;
}

.jet-injector-item-label {
  color: var(--jet-text-primary);
}

.jet-injector-remove-item {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: var(--jet-text-secondary);
  line-height: 1;
}

.jet-injector-remove-item:hover {
  color: var(--jet-error);
}

.jet-injector-remove-item .dashicons {
  font-size: 16px;
  width: 16px;
  height: 16px;
}
```

### 5. Add New Button

```css
.jet-injector-add-new {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  background: var(--jet-bg-white);
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  color: var(--jet-text-primary);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.jet-injector-add-new:hover {
  border-color: var(--jet-primary);
  color: var(--jet-primary);
}

.jet-injector-add-new .dashicons {
  font-size: 16px;
  width: 16px;
  height: 16px;
}
```

### 6. Cascading Selectors (Grandparent Relations)

For hierarchical relations, show parent selector above child.

```html
<div class="jet-injector-cascade">
  <div class="jet-injector-cascade-level" data-level="0">
    <label>Brand</label>
    <select class="jet-injector-cascade-select">
      <option value="">Select Brand...</option>
      <option value="1">Toyota</option>
      <option value="2">Ford</option>
    </select>
  </div>
  
  <div class="jet-injector-cascade-arrow">
    <span class="dashicons dashicons-arrow-down-alt"></span>
  </div>
  
  <div class="jet-injector-cascade-level" data-level="1">
    <label>Vehicle Model</label>
    <div class="jet-injector-search-wrap">
      <input type="text" class="jet-injector-search" placeholder="Search models..." disabled>
    </div>
  </div>
</div>
```

```css
.jet-injector-cascade {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.jet-injector-cascade-level {
  flex: 1;
}

.jet-injector-cascade-level[data-level="0"] {
  padding-bottom: 10px;
  border-bottom: 1px dashed var(--jet-border);
}

.jet-injector-cascade-arrow {
  text-align: center;
  color: var(--jet-text-secondary);
}

.jet-injector-cascade-select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  font-size: 13px;
  background: var(--jet-bg-white);
}

.jet-injector-cascade-select:disabled,
.jet-injector-search:disabled {
  background: var(--jet-bg-alt);
  cursor: not-allowed;
}
```

### 7. Modal (Create New Item)

```css
.jet-injector-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.jet-injector-modal {
  background: var(--jet-bg-white);
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.jet-injector-modal-header {
  background: var(--jet-bg-light);
  padding: 15px 20px;
  border-bottom: 1px solid var(--jet-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.jet-injector-modal-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
}

.jet-injector-modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--jet-text-secondary);
  padding: 5px;
}

.jet-injector-modal-close:hover {
  color: var(--jet-text-primary);
}

.jet-injector-modal-body {
  padding: 20px;
  overflow-y: auto;
  max-height: calc(80vh - 130px);
}

.jet-injector-modal-footer {
  background: var(--jet-bg-light);
  padding: 15px 20px;
  border-top: 1px solid var(--jet-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Form fields in modal */
.jet-injector-form-row {
  margin-bottom: 15px;
}

.jet-injector-form-row:last-child {
  margin-bottom: 0;
}

.jet-injector-form-row label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
}

.jet-injector-form-row input[type="text"],
.jet-injector-form-row textarea,
.jet-injector-form-row select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  font-size: 13px;
}
```

### 8. Buttons

```css
/* Primary button */
.jet-injector-btn-primary {
  background: var(--jet-primary);
  color: #fff;
  border: 1px solid var(--jet-primary);
  padding: 8px 16px;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.15s;
}

.jet-injector-btn-primary:hover {
  background: var(--jet-primary-hover);
}

.jet-injector-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Secondary button */
.jet-injector-btn-secondary {
  background: var(--jet-bg-white);
  color: var(--jet-text-primary);
  border: 1px solid var(--jet-border);
  padding: 8px 16px;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}

.jet-injector-btn-secondary:hover {
  border-color: var(--jet-primary);
  color: var(--jet-primary);
}
```

### 9. Notices/Warnings

```html
<div class="jet-injector-notice jet-injector-notice-warning">
  <span class="dashicons dashicons-warning"></span>
  <span>This relation type only allows one connection. Selecting a new item will replace the existing one.</span>
</div>
```

```css
.jet-injector-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 15px;
  border-radius: 4px;
  font-size: 12px;
  margin: 10px 0;
}

.jet-injector-notice-warning {
  background: #fcf9e8;
  border-left: 4px solid var(--jet-warning);
  color: #6e5b00;
}

.jet-injector-notice-error {
  background: #fcf0f1;
  border-left: 4px solid var(--jet-error);
  color: #8a1f1f;
}

.jet-injector-notice-info {
  background: #f0f6fc;
  border-left: 4px solid var(--jet-info);
  color: #1e4276;
}

.jet-injector-notice .dashicons {
  flex-shrink: 0;
  margin-top: 2px;
}
```

---

## Admin Settings Page Patterns

### Page Header

Match WordPress/JetEngine admin headers.

```css
.jet-injector-admin-wrap {
  max-width: 1200px;
  margin: 20px 20px 20px 0;
}

.jet-injector-admin-header {
  background: var(--jet-bg-white);
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  padding: 20px;
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.jet-injector-admin-title {
  font-size: 23px;
  font-weight: 400;
  margin: 0;
  color: var(--jet-text-primary);
}
```

### Tab Navigation

```css
.jet-injector-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--jet-border);
  margin-bottom: 20px;
}

.jet-injector-tab {
  padding: 12px 20px;
  background: var(--jet-bg-alt);
  border: 1px solid var(--jet-border);
  border-bottom: none;
  border-radius: 4px 4px 0 0;
  margin-right: -1px;
  cursor: pointer;
  font-size: 13px;
  color: var(--jet-text-secondary);
  transition: all 0.15s;
}

.jet-injector-tab:hover {
  color: var(--jet-text-primary);
}

.jet-injector-tab.is-active {
  background: var(--jet-bg-white);
  color: var(--jet-primary);
  border-bottom-color: var(--jet-bg-white);
  margin-bottom: -1px;
}
```

### CCT Configuration Cards

```css
.jet-injector-cct-card {
  background: var(--jet-bg-white);
  border: 1px solid var(--jet-border);
  border-radius: 4px;
  margin-bottom: 15px;
  overflow: hidden;
}

.jet-injector-cct-header {
  background: var(--jet-bg-light);
  padding: 15px 20px;
  border-bottom: 1px solid var(--jet-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.jet-injector-cct-title {
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}

.jet-injector-cct-toggle {
  /* WordPress-style toggle switch */
}

.jet-injector-cct-body {
  padding: 20px;
  display: none;
}

.jet-injector-cct-card.is-expanded .jet-injector-cct-body {
  display: block;
}
```

---

## Responsive Behavior

```css
@media screen and (max-width: 782px) {
  .jet-injector-relation-control {
    flex-direction: column;
  }
  
  .jet-injector-add-new {
    width: 100%;
    justify-content: center;
  }
  
  .jet-injector-cascade {
    gap: 15px;
  }
}
```

---

## Animation Guidelines

- **Duration:** 150-200ms for micro-interactions
- **Easing:** `ease-out` for expanding, `ease-in` for collapsing
- **Avoid:** Excessive animation that slows down workflow

```css
/* Standard transition */
.jet-injector-transition {
  transition: all 0.15s ease-out;
}

/* Fade in */
@keyframes jet-injector-fade-in {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.jet-injector-fade-in {
  animation: jet-injector-fade-in 0.2s ease-out;
}
```

---

## Accessibility

1. **Focus states:** All interactive elements must have visible focus indicators
2. **Keyboard navigation:** Dropdowns navigable with arrow keys
3. **Screen readers:** Use proper ARIA labels
4. **Color contrast:** Maintain WCAG AA compliance

```css
/* Focus visible */
.jet-injector-search:focus-visible,
.jet-injector-btn-primary:focus-visible,
.jet-injector-btn-secondary:focus-visible {
  outline: 2px solid var(--jet-primary);
  outline-offset: 2px;
}

/* Skip focus ring for mouse users */
.jet-injector-search:focus:not(:focus-visible) {
  outline: none;
}
```

---

## Icons

Use WordPress Dashicons where possible:

| Purpose | Dashicon |
|---------|----------|
| Relations | `dashicons-networking` |
| Add New | `dashicons-plus-alt2` |
| Remove | `dashicons-no-alt` |
| Expand | `dashicons-arrow-down-alt2` |
| Collapse | `dashicons-arrow-up-alt2` |
| Warning | `dashicons-warning` |
| Error | `dashicons-dismiss` |
| Success | `dashicons-yes-alt` |
| Loading | `.spinner` (WordPress native) |

