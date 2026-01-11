# Glossary & Terminology

## JetEngine Terms

### CCT (Custom Content Type)
A JetEngine feature that creates custom database tables for storing structured data, separate from the WordPress `wp_posts` table. Each CCT has its own dedicated table (`wp_jet_cct_{slug}`).

### CCT Factory
The PHP class (`\Jet_Engine\Modules\Custom_Content_Types\Factory`) that represents a registered CCT. Provides methods for accessing fields, database, and item operations.

### CCT Item
A single record/row in a CCT database table. Equivalent to a "post" in WordPress but stored in the CCT's custom table.

### Relation
A connection between two objects (posts, terms, users, or CCTs). JetEngine Relations create a separate database table to store these connections.

### Relation Type
The cardinality of a relation:
- **One to One**: Each parent can have one child, each child can have one parent
- **One to Many**: Each parent can have many children, each child has one parent
- **Many to Many**: Each parent can have many children, each child can have many parents

### Parent Object / Child Object
In a relation, the "parent" is typically the "one" side, and the "child" is the "many" side. However, this is just naming convention - both sides are equal in many-to-many relations.

### Object Type String
JetEngine uses format `type::subtype` to identify objects:
- `posts::post` - WordPress posts
- `posts::page` - WordPress pages
- `posts::my-cpt` - Custom post type
- `terms::category` - Categories
- `cct::my-content-type` - CCT with slug "my-content-type"
- `users::user` - Users

### Parent Relation (Hierarchy)
A relation can have a "parent relation" (`parent_rel`), creating a hierarchical chain. This enables grandparent-grandchild relationships.

Example:
- Relation A: Brand → Vehicle (parent relation)
- Relation B: Vehicle → Service Guide (child relation, has `parent_rel` = A)
- Result: Brand is grandparent of Service Guide

### Grandparent Relation
When editing the ultimate **child** in a hierarchy, the plugin detects and offers a cascading selector for the grandparent.

**Example:** Editing Service Guide → select Brand (grandparent) → then select Vehicle (parent)

### Grandchild Relation
When editing the ultimate **parent** in a hierarchy, the plugin detects and offers a cascading selector for the grandchild.

**Example:** Editing Brand → select Vehicle (child) → then select Service Guide (grandchild)

### Meta Fields (Relation)
Additional data fields that can be stored on the relationship itself (not on either object). Useful for things like "quantity" in a product-order relationship.

---

## Plugin-Specific Terms

### Injection
The act of dynamically inserting HTML/UI elements into an existing page (the CCT edit screen).

### Trojan Horse Method
Our strategy for capturing relation data during CCT save:
1. Inject hidden input fields into the CCT form
2. When form submits, our hidden fields travel with the POST data
3. After CCT saves, our hook extracts and processes the hidden field data

### Discovery Engine
Module A - scans JetEngine's internal registry to find available CCTs, fields, and relations.

### Configuration Store
Module B - stores per-CCT injection settings in our database table.

### Runtime Engine
Module C - the JavaScript that renders the relation selector UI on CCT edit pages.

### Transaction Processor
Module D - the PHP code that intercepts CCT save and creates actual relations from hidden input data.

### Data Broker
Module E - AJAX API for searching CCT items and creating new items. Supports CCTs, Taxonomies, and Post Types.

### Utilities Module
Module F - Maintenance and diagnostic tools for cache management, bulk operations, and relation configuration diagnostics.

### Cascading Selector / Cascading Modal
A 2-step UI for hierarchical relations that filters results based on parent selection:
- **Step 1:** Select grandparent/parent item
- **Step 2:** Shows only children/grandchildren related to the step 1 selection
- **Supports both directions:** Grandparent (up the chain) and Grandchild (down the chain)
- **Example:** When editing Service Guide, first select Brand (step 1), then see only Vehicles for that Brand (step 2)

### Display Field / Title Field
The CCT field whose value is shown in search results and selected item chips. Example: showing "model_name" instead of the numeric `_ID`. This is configured in JetEngine Relations settings as "Title Field for [CCT Name]".

### Validation System
Built-in checking system that prevents saving configurations for relations without database tables or other critical issues. Shows detailed error messages in the admin UI.

### Relation Table
The database table (`wp_jet_rel_XX`) that stores relation connections. JetEngine creates this when the relation has "Store in separate database table" enabled.

### rel_id
A column in JetEngine relation tables that stores the relation ID. Required for JetEngine to properly track and display relations.

### parent_rel
A column in JetEngine relation tables that stores the parent relation ID for hierarchical relations. Used for grandparent-grandchild chains.

### Bulk Re-save
A utility operation that updates all items in a CCT, triggering JetEngine's update hooks and refreshing any cached display names or relation data.

---

## WordPress/PHP Terms

### Nonce
"Number used once" - a security token to verify that requests come from legitimate admin pages, not from external attackers.

### Capability
WordPress permission check. Common ones:
- `manage_options` - Administrator level
- `edit_posts` - Editor level
- `edit_others_posts` - Can edit other users' content

### Hook
WordPress mechanism for extending functionality:
- **Action**: Do something at a specific point (`add_action`, `do_action`)
- **Filter**: Modify data passing through (`add_filter`, `apply_filters`)

### dbDelta
WordPress function for creating/updating database tables safely. Handles schema changes gracefully.

### Transient
Cached data stored in `wp_options` with an expiration time. Useful for caching expensive operations.

---

## JavaScript Terms

### Localized Script
Using `wp_localize_script()` to pass PHP data to JavaScript as a global variable.

### Debounce
Delaying a function call until user stops typing. Prevents excessive AJAX calls on every keystroke.

### AJAX
Asynchronous JavaScript and XML - making HTTP requests without page reload. We use `wp.ajax` or `fetch()` to call WordPress AJAX handlers.

---

## Database Terms

### WPDB
The WordPress database abstraction class (`$wpdb`). Provides methods like `get_results()`, `prepare()`, `insert()`, `update()`.

### Prefix
The WordPress table prefix (usually `wp_`). Always use `$wpdb->prefix` instead of hardcoding.

### Charset Collate
Database character set and collation settings. Always use `$wpdb->get_charset_collate()` when creating tables.

---

## Abbreviations

| Abbreviation | Full Term |
|--------------|-----------|
| CCT | Custom Content Type |
| CPT | Custom Post Type |
| AJAX | Asynchronous JavaScript and XML |
| CRUD | Create, Read, Update, Delete |
| API | Application Programming Interface |
| UI | User Interface |
| UX | User Experience |
| DOM | Document Object Model |
| JSON | JavaScript Object Notation |
| SQL | Structured Query Language |
| REST | Representational State Transfer |
| i18n | Internationalization |
| l10n | Localization |

