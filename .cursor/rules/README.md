# JetEngine Dynamic Relation Injector

## Project Overview

**Version:** 1.0.0  
**Status:** In Development  
**Target Platform:** WordPress 6.0+, JetEngine 3.3.1+

### What This Plugin Does

The JetEngine Dynamic Relation Injector is a bridge plugin that solves a critical UX limitation in JetEngine's Custom Content Types (CCT) module:

> **The Problem:** Users must save a CCT item *before* they can create relations to other items. This breaks the natural workflow and causes confusion.

> **The Solution:** This plugin injects a relation selector UI directly into the CCT edit screen, allowing users to select or create related items *before* saving. The relation data is stored in hidden form fields and processed after the CCT save completes.

### Core Features

1. **Discovery Engine** - Scans JetEngine to find available CCTs, Fields, and Relations
2. **Configuration Store** - Stores injection rules per CCT in a dedicated database table
3. **Runtime Injection** - Dynamically injects relation selector UI on CCT edit screens
4. **Deferred Saving** - Piggybacks on CCT form submission to save relations
5. **Cascading Selectors** - Supports grandparent relations with cascading dropdowns
6. **Create New Items** - Allows creating related items inline via modal
7. **Auto-Detection** - Automatically finds all relations where a CCT is parent or child

### Technical Approach: The "Trojan Horse" Method

JetEngine CCTs use standard HTML Form POST requests (not AJAX). Our strategy:

1. Locate the CCT form: `form[action*="jet-cct-save-item"]`
2. Inject hidden inputs: `<input type="hidden" name="_injector_rel_[SLUG]" value="[ID]">`
3. Intercept after save via WordPress hook
4. Process hidden field data to create actual relations

---

## Documentation Index

| Document | Description |
|----------|-------------|
| [project-rules.mdc](./project-rules.mdc) | **Main Cursor rules file** - Quick reference for AI agents |
| [DEVELOPMENT-PLAN.md](./DEVELOPMENT-PLAN.md) | Phased development roadmap |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Technical architecture & modules |
| [DESIGN-RULES.md](./DESIGN-RULES.md) | UI/UX patterns matching JetEngine |
| [JETENGINE-API-REFERENCE.md](./JETENGINE-API-REFERENCE.md) | JetEngine hooks, methods, APIs |
| [DATABASE-SCHEMA.md](./DATABASE-SCHEMA.md) | Database tables & data structures |
| [CONFIGURATION-SCHEMA.md](./CONFIGURATION-SCHEMA.md) | JSON configuration format |
| [DEBUG-SYSTEM.md](./DEBUG-SYSTEM.md) | In-plugin debug logging implementation |
| [GLOSSARY.md](./GLOSSARY.md) | Terminology reference |
| [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) | Common issues and solutions |

---

## Quick Start (For Development)

```bash
# 1. Clone/setup in WordPress plugins directory
wp-content/plugins/jet-engine-relation-injector/

# 2. Activate plugin (requires JetEngine to be active)

# 3. Access settings: Admin → Relation Injector

# 4. Select a CCT to configure injection rules
```

---

## Dependencies

- **WordPress** 6.7+
- **JetEngine** 3.3.1+ with:
  - Custom Content Types module enabled
  - Relations module enabled

---

## License

GPL v2 or later

