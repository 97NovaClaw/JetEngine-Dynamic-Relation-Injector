<?php
/**
 * Admin Settings Page Template
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap jet-injector-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p>
            <strong><?php _e('About This Plugin:', 'jet-relation-injector'); ?></strong>
            <?php _e('This plugin injects JetEngine relation selectors into CCT edit screens, allowing you to manage relations before the initial save—eliminating the "save first, relate later" limitation.', 'jet-relation-injector'); ?>
        </p>
    </div>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="#cct-configs" class="nav-tab nav-tab-active" data-tab="cct-configs">
            <?php _e('CCT Configurations', 'jet-relation-injector'); ?>
        </a>
        <a href="#debug" class="nav-tab" data-tab="debug">
            <?php _e('Debug', 'jet-relation-injector'); ?>
        </a>
    </h2>
    
    <!-- Tab Content -->
    <div class="tab-contents">
        
        <!-- CCT Configurations Tab -->
        <div id="tab-cct-configs" class="tab-content active">
            <div class="tab-header">
                <p class="description">
                    <?php _e('Configure which CCTs should have relation injection enabled and customize the behavior for each.', 'jet-relation-injector'); ?>
                </p>
                <button type="button" id="add-cct-config" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                    <?php _e('Add CCT Configuration', 'jet-relation-injector'); ?>
                </button>
            </div>
            
            <div id="cct-configs-list" class="cct-configs-list">
                <?php if (empty($configs)): ?>
                    <div class="no-configs">
                        <p><?php _e('No configurations yet. Click "Add CCT Configuration" to get started.', 'jet-relation-injector'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($configs as $config): ?>
                        <?php include JET_INJECTOR_PLUGIN_DIR . 'templates/admin/cct-config-card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Debug Tab -->
        <div id="tab-debug" class="tab-content">
            <?php include JET_INJECTOR_PLUGIN_DIR . 'templates/admin/debug-tab.php'; ?>
        </div>
        
    </div>
</div>

<!-- Modal Template for Adding/Editing CCT Config -->
<div id="cct-config-modal" class="jet-injector-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title"><?php _e('Add CCT Configuration', 'jet-relation-injector'); ?></h2>
            <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'jet-relation-injector'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="modal-body">
            <form id="cct-config-form">
                <input type="hidden" name="config_id" id="config-id" value="">
                
                <table class="form-table">
                    <tbody>
                        <!-- CCT Selection -->
                        <tr>
                            <th scope="row">
                                <label for="config-cct-slug">
                                    <?php _e('Custom Content Type', 'jet-relation-injector'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <select name="cct_slug" id="config-cct-slug" class="regular-text" required>
                                    <option value=""><?php _e('-- Select CCT --', 'jet-relation-injector'); ?></option>
                                    <?php foreach ($ccts as $cct): ?>
                                        <option value="<?php echo esc_attr($cct['slug']); ?>">
                                            <?php echo esc_html($cct['name']); ?> (<?php echo esc_html($cct['slug']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Select the CCT where relation injection should be enabled.', 'jet-relation-injector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Enabled Toggle -->
                        <tr>
                            <th scope="row">
                                <label for="config-is-enabled">
                                    <?php _e('Enable Injection', 'jet-relation-injector'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_enabled" id="config-is-enabled" value="1" checked>
                                    <?php _e('Enable relation injection for this CCT', 'jet-relation-injector'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <!-- Injection Point -->
                        <tr>
                            <th scope="row">
                                <label for="config-injection-point">
                                    <?php _e('Injection Point', 'jet-relation-injector'); ?>
                                </label>
                            </th>
                            <td>
                                <select name="injection_point" id="config-injection-point" class="regular-text">
                                    <option value="before_save"><?php _e('Before Save Button', 'jet-relation-injector'); ?></option>
                                    <option value="after_fields"><?php _e('After CCT Fields', 'jet-relation-injector'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Where to inject the relation selector UI.', 'jet-relation-injector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Relations Selection -->
                        <tr id="relations-selection-row" style="display: none;">
                            <th scope="row">
                                <label>
                                    <?php _e('Enabled Relations', 'jet-relation-injector'); ?>
                                </label>
                            </th>
                            <td>
                                <div id="relations-list" class="relations-list">
                                    <p class="description"><?php _e('Select a CCT above to see available relations.', 'jet-relation-injector'); ?></p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="button button-secondary modal-close">
                <?php _e('Cancel', 'jet-relation-injector'); ?>
            </button>
            <button type="button" id="save-cct-config" class="button button-primary">
                <span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
                <?php _e('Save Configuration', 'jet-relation-injector'); ?>
            </button>
            <span class="spinner" id="modal-spinner" style="float: none;"></span>
            <span id="modal-message"></span>
        </div>
    </div>
</div>

<style>
.jet-injector-settings .nav-tab-wrapper {
    margin-bottom: 0;
}

.jet-injector-settings .tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: 0;
}

.jet-injector-settings .tab-content.active {
    display: block;
}

.jet-injector-settings .tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.jet-injector-settings .no-configs {
    text-align: center;
    padding: 60px 20px;
    color: #646970;
}

.jet-injector-settings .cct-configs-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.jet-injector-settings .required {
    color: #dc3232;
}

/* Modal Styles */
.jet-injector-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
}

.jet-injector-modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
}

.jet-injector-modal .modal-content {
    position: relative;
    max-width: 800px;
    margin: 50px auto;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    max-height: calc(100vh - 100px);
    display: flex;
    flex-direction: column;
}

.jet-injector-modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dcdcde;
}

.jet-injector-modal .modal-header h2 {
    margin: 0;
}

.jet-injector-modal .modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #646970;
    padding: 0;
}

.jet-injector-modal .modal-close:hover {
    color: #000;
}

.jet-injector-modal .modal-body {
    padding: 20px;
    overflow-y: auto;
}

.jet-injector-modal .modal-footer {
    padding: 20px;
    border-top: 1px solid #dcdcde;
    text-align: right;
}

.jet-injector-modal .modal-footer .button {
    margin-left: 10px;
}

#modal-message.success {
    color: #46b450;
    font-weight: 600;
}

#modal-message.error {
    color: #dc3232;
    font-weight: 600;
}

.relations-list {
    border: 1px solid #dcdcde;
    padding: 15px;
    border-radius: 4px;
    background: #f9f9f9;
}

.relation-item {
    display: flex;
    align-items: flex-start;
    padding: 10px;
    border-bottom: 1px solid #e5e5e5;
}

.relation-item:last-child {
    border-bottom: none;
}

.relation-item label {
    flex: 1;
    cursor: pointer;
}

.relation-item .display-fields {
    margin-top: 10px;
    padding-left: 25px;
}
</style>

