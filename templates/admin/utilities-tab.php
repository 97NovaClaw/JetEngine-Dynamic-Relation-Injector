<?php
/**
 * Utilities Tab Template
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get discovery instance for CCT list
$discovery = Jet_Injector_Plugin::instance()->get_discovery();
$ccts = $discovery->get_all_ccts();
$relations = $discovery->get_all_relations();
?>

<div class="utilities-section">
    <h3><?php _e('JetEngine Cache Utilities', 'jet-relation-injector'); ?></h3>
    <p class="description">
        <?php _e('These utilities help resolve common issues with JetEngine relations, particularly when relation items show IDs instead of titles.', 'jet-relation-injector'); ?>
    </p>
    
    <!-- Cache Clearing -->
    <div class="utility-card">
        <div class="utility-header">
            <span class="dashicons dashicons-trash"></span>
            <h4><?php _e('Clear JetEngine Caches', 'jet-relation-injector'); ?></h4>
        </div>
        <p class="description">
            <?php _e('Clears all JetEngine-related transients and object caches. Use this if relation data appears stale.', 'jet-relation-injector'); ?>
        </p>
        <button type="button" id="clear-jetengine-cache" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Clear Caches', 'jet-relation-injector'); ?>
        </button>
        <span class="utility-result" id="cache-clear-result"></span>
    </div>
    
    <!-- Bulk Re-save CCT Items -->
    <div class="utility-card">
        <div class="utility-header">
            <span class="dashicons dashicons-database-import"></span>
            <h4><?php _e('Bulk Re-save CCT Items', 'jet-relation-injector'); ?></h4>
        </div>
        <p class="description">
            <?php _e('Re-saves all items in a CCT, triggering update hooks and refreshing any cached display names. Useful when items created without titles now have them.', 'jet-relation-injector'); ?>
        </p>
        <div class="utility-form">
            <select id="resave-cct-slug" class="regular-text">
                <option value=""><?php _e('Select a CCT...', 'jet-relation-injector'); ?></option>
                <?php foreach ($ccts as $cct): ?>
                    <option value="<?php echo esc_attr($cct['slug']); ?>">
                        <?php echo esc_html($cct['name'] . ' (' . $cct['slug'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="bulk-resave-cct" class="button button-secondary" disabled>
                <span class="dashicons dashicons-update"></span>
                <?php _e('Re-save All Items', 'jet-relation-injector'); ?>
            </button>
            <span class="spinner" id="resave-spinner"></span>
        </div>
        <div class="utility-result" id="resave-result"></div>
        <div class="utility-progress" id="resave-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill" id="resave-progress-fill"></div>
            </div>
            <span class="progress-text" id="resave-progress-text">0 / 0</span>
        </div>
    </div>
    
    <!-- Diagnose Relations -->
    <div class="utility-card">
        <div class="utility-header">
            <span class="dashicons dashicons-stethoscope"></span>
            <h4><?php _e('Diagnose Relation Settings', 'jet-relation-injector'); ?></h4>
        </div>
        <p class="description">
            <?php _e('Checks if your JetEngine relations have the "Title Field" configured. If not set, relations will only show item IDs.', 'jet-relation-injector'); ?>
        </p>
        <button type="button" id="diagnose-relations" class="button button-secondary">
            <span class="dashicons dashicons-search"></span>
            <?php _e('Run Diagnosis', 'jet-relation-injector'); ?>
        </button>
        <div class="utility-result" id="diagnose-result"></div>
        <div id="diagnose-details" class="diagnose-details" style="display: none;">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Relation', 'jet-relation-injector'); ?></th>
                        <th><?php _e('Parent Object', 'jet-relation-injector'); ?></th>
                        <th><?php _e('Child Object', 'jet-relation-injector'); ?></th>
                        <th><?php _e('Parent Title Field', 'jet-relation-injector'); ?></th>
                        <th><?php _e('Child Title Field', 'jet-relation-injector'); ?></th>
                        <th><?php _e('DB Table', 'jet-relation-injector'); ?></th>
                        <th><?php _e('Status', 'jet-relation-injector'); ?></th>
                    </tr>
                </thead>
                <tbody id="diagnose-table-body">
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.utilities-section {
    max-width: 800px;
}

.utility-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.utility-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.utility-header .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: #2271b1;
}

.utility-header h4 {
    margin: 0;
    font-size: 16px;
}

.utility-form {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
}

.utility-form select {
    min-width: 250px;
}

.utility-result {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 3px;
    display: none;
}

.utility-result.success {
    display: block;
    background: #d7f1e3;
    color: #1e8656;
    border-left: 3px solid #1e8656;
}

.utility-result.error {
    display: block;
    background: #fcf0f1;
    color: #d63638;
    border-left: 3px solid #d63638;
}

.utility-result.warning {
    display: block;
    background: #fcf9e8;
    color: #996800;
    border-left: 3px solid #dba617;
}

.utility-progress {
    margin-top: 15px;
}

.progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 12px;
    color: #666;
}

.diagnose-details {
    margin-top: 20px;
}

.diagnose-details .status-ok {
    color: #1e8656;
    font-weight: 600;
}

.diagnose-details .status-warning {
    color: #996800;
    font-weight: 600;
}

.diagnose-details .status-error {
    color: #d63638;
    font-weight: 600;
}

.diagnose-details code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

