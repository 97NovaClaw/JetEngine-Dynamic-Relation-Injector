<?php
/**
 * CCT Configuration Card Template
 *
 * @package JetRelationInjector
 * @var array $config Configuration data
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$cct_slug = $config['cct_slug'];
$is_enabled = $config['is_enabled'];
$config_data = $config['config'];

// Get CCT name
$discovery = Jet_Injector_Plugin::instance()->get_discovery();
$cct = $discovery->get_cct($cct_slug);
$cct_name = $cct ? $cct['name'] : $cct_slug;
?>

<div class="cct-config-card" data-cct-slug="<?php echo esc_attr($cct_slug); ?>">
    <div class="card-header">
        <h3><?php echo esc_html($cct_name); ?></h3>
        <div class="card-actions">
            <label class="toggle-switch">
                <input type="checkbox" 
                       class="toggle-config" 
                       data-cct-slug="<?php echo esc_attr($cct_slug); ?>"
                       <?php checked($is_enabled); ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>
    
    <div class="card-body">
        <div class="config-meta">
            <div class="meta-item">
                <span class="meta-label"><?php _e('CCT Slug:', 'jet-relation-injector'); ?></span>
                <code><?php echo esc_html($cct_slug); ?></code>
            </div>
            
            <div class="meta-item">
                <span class="meta-label"><?php _e('Injection Point:', 'jet-relation-injector'); ?></span>
                <span><?php 
                    echo isset($config_data['injection_point']) && $config_data['injection_point'] === 'after_fields' 
                        ? __('After CCT Fields', 'jet-relation-injector')
                        : __('Before Save Button', 'jet-relation-injector');
                ?></span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label"><?php _e('Enabled Relations:', 'jet-relation-injector'); ?></span>
                <span>
                    <?php 
                    $relation_count = isset($config_data['enabled_relations']) ? count($config_data['enabled_relations']) : 0;
                    printf(_n('%d relation', '%d relations', $relation_count, 'jet-relation-injector'), $relation_count);
                    ?>
                </span>
            </div>
        </div>
        
        <?php if (!empty($config_data['enabled_relations'])): ?>
            <div class="config-relations">
                <strong><?php _e('Relations:', 'jet-relation-injector'); ?></strong>
                <ul>
                    <?php 
                    $all_relations = $discovery->get_all_relations();
                    foreach ($config_data['enabled_relations'] as $relation_id): 
                        $relation = null;
                        foreach ($all_relations as $rel) {
                            if ($rel['id'] == $relation_id) {
                                $relation = $rel;
                                break;
                            }
                        }
                        if ($relation):
                    ?>
                        <li><?php echo esc_html($relation['name']); ?></li>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card-footer">
        <button type="button" class="button button-secondary edit-config" data-cct-slug="<?php echo esc_attr($cct_slug); ?>">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('Edit', 'jet-relation-injector'); ?>
        </button>
        
        <button type="button" class="button button-link-delete delete-config" data-cct-slug="<?php echo esc_attr($cct_slug); ?>">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Delete', 'jet-relation-injector'); ?>
        </button>
    </div>
</div>

<style>
.cct-config-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.2s;
}

.cct-config-card:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.cct-config-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dcdcde;
    background: #f9f9f9;
}

.cct-config-card .card-header h3 {
    margin: 0;
    font-size: 16px;
}

.cct-config-card .card-body {
    padding: 20px;
}

.cct-config-card .config-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.cct-config-card .meta-item {
    display: flex;
    gap: 10px;
}

.cct-config-card .meta-label {
    font-weight: 600;
    min-width: 120px;
}

.cct-config-card .config-relations {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
}

.cct-config-card .config-relations ul {
    margin: 5px 0 0 20px;
    list-style: disc;
}

.cct-config-card .card-footer {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid #dcdcde;
    background: #f9f9f9;
}

.cct-config-card .card-footer .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: #2271b1;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(26px);
}
</style>

