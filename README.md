# JetEngine Dynamic Relation Injector

A WordPress plugin that extends JetEngine's Custom Content Types (CCT) module by injecting relation selectors directly into CCT edit screens, enabling relation management before the initial save.

## Problem Solved

**Before:** Users had to save a new CCT item before being able to establish relations with other items ("save first, relate later" limitation).

**After:** Users can select/create related items directly on the CCT edit screen before the initial save, creating a seamless workflow.

## Features

✅ **Pre-Save Relation Management** - Manage relations before saving new CCT items
✅ **Search & Select** - Ajax-powered search for related items
✅ **Create on the Fly** - Create new related items without leaving the page
✅ **Grandparent Support** - Handle hierarchical relations (e.g., Brand → Vehicle → Service Guide)
✅ **Multiple CCT Configurations** - Configure different settings for each CCT
✅ **Flexible Injection Points** - Choose where to inject the UI (before save button or after fields)
✅ **Debug System** - In-plugin debug logging with admin UI for troubleshooting

## Requirements

- **WordPress:** 6.7+
- **PHP:** 7.4+
- **JetEngine:** 3.3.1+
  - Custom Content Types module enabled
  - Relations module enabled

## Installation

1. **Upload the Plugin**
   - Download or clone this repository
   - Upload to `/wp-content/plugins/jet-engine-relation-injector/`
   - Or zip and install via WordPress admin

2. **Activate**
   - Go to Plugins → Installed Plugins
   - Activate "JetEngine Dynamic Relation Injector"
   - The plugin will verify JetEngine dependencies

3. **Configure**
   - Navigate to **Relation Injector** in the WordPress admin menu
   - Add a CCT configuration
   - Select which relations to enable
   - Save and test on a CCT edit page!

## Quick Start

### 1. Add a CCT Configuration

1. Go to **Relation Injector** → **CCT Configurations**
2. Click **"Add CCT Configuration"**
3. Select your CCT (e.g., "Service Guides")
4. Choose injection point:
   - **Before Save Button** - Best for most use cases
   - **After CCT Fields** - If you need it integrated with fields
5. Select which relations to enable
6. Save

### 2. Edit a CCT Item

1. Go to the CCT edit page (e.g., edit a Service Guide)
2. You'll see the new **"Relations"** section injected
3. Click **"Select"** to search for existing items
4. Or click **"Add New"** to create a new related item
5. Save the CCT item - relations are saved automatically!

## How It Works

### The "Trojan Horse" Method

JetEngine CCT edit screens use standard HTML form POST submissions (not AJAX). Our plugin:

1. **Injects** a relation selector UI into the CCT edit form
2. **Adds** hidden `<input>` fields containing selected relation data
3. **Intercepts** the save action using JetEngine's `created-item` and `updated-item` hooks
4. **Processes** the hidden field data to create relations in the database

This approach is reliable, doesn't interfere with JetEngine's core functionality, and works with all CCT configurations.

## Architecture

```
jet-engine-relation-injector/
├── includes/
│   ├── class-discovery.php          # Discovers CCTs, fields, and relations
│   ├── class-config-manager.php     # CRUD for configurations
│   ├── class-config-db.php          # Database table management
│   ├── class-transaction-processor.php  # Processes relation saves
│   ├── class-data-broker.php        # AJAX API for search/create
│   ├── class-runtime-loader.php     # Detects CCT pages and loads assets
│   ├── class-admin-page.php         # Admin UI handler
│   └── helpers/
│       └── debug.php                # Debug logging functions
├── assets/
│   ├── css/
│   │   ├── admin.css                # Admin page styles
│   │   └── injector.css             # Runtime injection styles
│   └── js/
│       ├── admin.js                 # Admin page logic
│       └── injector.js              # Runtime injection engine
└── templates/
    └── admin/
        ├── settings-page.php        # Main settings template
        ├── cct-config-card.php      # Configuration card
        └── debug-tab.php            # Debug tab
```

## Configuration Schema

Each CCT configuration is stored in the database with this structure:

```json
{
  "injection_point": "before_save",
  "enabled_relations": [12, 15, 18],
  "display_fields": {
    "12": ["name", "model"],
    "15": ["title", "category"]
  },
  "ui_settings": {
    "show_labels": true,
    "show_create_button": true,
    "modal_width": "medium"
  }
}
```

## Debug System

The plugin includes a comprehensive debug system:

### Enable Debugging

1. Go to **Relation Injector** → **Debug**
2. Enable one or more debug options:
   - **PHP Debug Logging** - Writes to `debug.txt` in plugin root
   - **JavaScript Console** - Outputs to browser console
   - **Admin Notices** - Shows WordPress admin notices
3. Save settings

### View Debug Log

1. Ensure "PHP Debug Logging" is enabled
2. Click **"View Log"** to see contents
3. Use **"Refresh"** to update
4. Use **"Clear Log"** to empty the file

### Debug File Location

```
wp-content/plugins/jet-engine-relation-injector/debug.txt
```

## Development

For development documentation, see:

- **`.cursor/rules/`** - AI agent reference documentation
- **`DEVELOPMENT-PLAN.md`** - Phased development roadmap
- **`ARCHITECTURE.md`** - Technical architecture details
- **`DEBUG-SYSTEM.md`** - Debug implementation guide
- **`JETENGINE-API-REFERENCE.md`** - JetEngine hooks and methods

## Troubleshooting

### Relations Not Saving

1. Enable **PHP Debug Logging** in Debug tab
2. Try saving a CCT item with relations
3. View the debug log to see processing details
4. Check for nonce verification errors or relation ID mismatches

### UI Not Appearing

1. Verify the CCT configuration is **enabled** (toggle switch)
2. Check that at least one relation is selected
3. Enable **JavaScript Console** debug mode
4. Look for errors in browser console (F12)

### "Save First" Warning Still Appears

This plugin doesn't remove JetEngine's native relation UI—it adds a new one. The injected UI appears as configured (before save button or after fields). You can use both, but our injected UI works before the first save.

## Roadmap

### Phase 2 (Next Steps)
- Advanced display field selection per relation
- Custom field mapping for "Add New" modals
- Bulk relation management
- Import/Export configurations

### Phase 3 (Future)
- Support for Post Types and Taxonomies
- Visual relation graph
- Conditional logic for relation visibility
- Relation templates

## Support

For issues, feature requests, or questions:
- Check the documentation in `.cursor/rules/`
- Enable debug logging and check `debug.txt`
- Review `TROUBLESHOOTING.md`

## License

GPL v2 or later

## Credits

Developed as an extension for [JetEngine](https://crocoblock.com/plugins/jetengine/) by Crocoblock.

---

**Note:** This plugin is designed to work alongside JetEngine, not replace it. It extends JetEngine's functionality while respecting its architecture and workflows.

