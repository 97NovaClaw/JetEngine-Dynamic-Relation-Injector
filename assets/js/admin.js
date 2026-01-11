/**
 * Admin Page JavaScript
 *
 * @package JetRelationInjector
 */

(function($) {
    'use strict';
    
    const JetInjectorAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.log('Admin initialized');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.switchTab.bind(this));
            
            // Add new config
            $('#add-cct-config').on('click', this.openConfigModal.bind(this));
            
            // Edit config
            $(document).on('click', '.edit-config', this.editConfig.bind(this));
            
            // Delete config
            $(document).on('click', '.delete-config', this.deleteConfig.bind(this));
            
            // Toggle config
            $(document).on('change', '.toggle-config', this.toggleConfig.bind(this));
            
            // CCT selection change
            $('#config-cct-slug').on('change', this.loadCCTRelations.bind(this));
            
            // Save config
            $('#save-cct-config').on('click', this.saveConfig.bind(this));
            
            // Modal close
            $('.modal-close, .modal-overlay').on('click', this.closeModal.bind(this));
            
            // Debug settings
            $('#save-debug-settings').on('click', this.saveDebugSettings.bind(this));
            $('#view-log, #refresh-log').on('click', this.viewLog.bind(this));
            $('#clear-log').on('click', this.clearLog.bind(this));
            
            // Prevent modal close when clicking inside
            $('.modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const tabName = $tab.data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show corresponding content
            $('.tab-content').removeClass('active');
            $('#tab-' + tabName).addClass('active');
            
            // Update URL hash
            window.location.hash = tabName;
            
            this.log('Switched to tab:', tabName);
        },
        
        /**
         * Open config modal
         */
        openConfigModal: function() {
            $('#modal-title').text(jetInjectorAdmin.i18n.add_config || 'Add CCT Configuration');
            $('#cct-config-form')[0].reset();
            $('#config-id').val('');
            $('#relations-selection-row').hide();
            $('#relations-list').empty();
            $('#cct-config-modal').fadeIn(200);
            this.log('Config modal opened');
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('#cct-config-modal').fadeOut(200);
            this.log('Modal closed');
        },
        
        /**
         * Edit config
         */
        editConfig: function(e) {
            const cctSlug = $(e.currentTarget).data('cct-slug');
            
            this.log('Editing config for:', cctSlug);
            
            // Load config data via AJAX
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_get_config',
                    nonce: jetInjectorAdmin.nonce,
                    cct_slug: cctSlug
                },
                success: (response) => {
                    if (response.success) {
                        this.populateConfigForm(response.data);
                        $('#cct-config-modal').fadeIn(200);
                    } else {
                        alert(response.data.message || 'Failed to load configuration');
                    }
                },
                error: () => {
                    alert('Failed to load configuration');
                }
            });
        },
        
        /**
         * Populate config form with data
         */
        populateConfigForm: function(config) {
            $('#modal-title').text('Edit CCT Configuration');
            $('#config-id').val(config.id);
            $('#config-cct-slug').val(config.cct_slug).trigger('change');
            $('#config-is-enabled').prop('checked', config.is_enabled);
            
            if (config.config) {
                $('#config-injection-point').val(config.config.injection_point || 'before_save');
                // Enabled relations will be populated after CCT relations load
                this.pendingEnabledRelations = config.config.enabled_relations || [];
                this.pendingDisplayFields = config.config.display_fields || {};
            }
        },
        
        /**
         * Load CCT relations
         */
        loadCCTRelations: function(e) {
            const cctSlug = $('#config-cct-slug').val();
            
            if (!cctSlug) {
                $('#relations-selection-row').hide();
                return;
            }
            
            this.log('Loading relations for CCT:', cctSlug);
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_get_cct_relations',
                    nonce: jetInjectorAdmin.nonce,
                    cct_slug: cctSlug
                },
                beforeSend: () => {
                    $('#relations-list').html('<p class="description">Loading relations...</p>');
                },
                success: (response) => {
                    if (response.success) {
                        this.renderRelations(response.data.relations);
                        $('#relations-selection-row').show();
                    } else {
                        $('#relations-list').html('<p class="description error">' + response.data.message + '</p>');
                    }
                },
                error: () => {
                    $('#relations-list').html('<p class="description error">Failed to load relations</p>');
                }
            });
        },
        
        /**
         * Render relations list
         */
        renderRelations: function(relations) {
            const $list = $('#relations-list').empty();
            
            if (!relations || relations.length === 0) {
                $list.html('<p class="description">No relations found for this CCT.</p>');
                return;
            }
            
            relations.forEach((relation) => {
                const isEnabled = this.pendingEnabledRelations && 
                                this.pendingEnabledRelations.includes(relation.id);
                
                const $item = $('<div class="relation-item"></div>');
                
                // Header
                const $header = $('<div class="relation-item-header"></div>');
                $header.append(
                    $('<input type="checkbox">')
                        .attr('name', 'enabled_relations[]')
                        .val(relation.id)
                        .prop('checked', isEnabled)
                        .attr('id', 'relation-' + relation.id)
                );
                $header.append(
                    $('<label>').attr('for', 'relation-' + relation.id).text(relation.name)
                );
                
                // Badge
                if (relation.cct_position === 'grandparent') {
                    $header.append('<span class="relation-badge grandparent">Grandparent</span>');
                } else {
                    $header.append('<span class="relation-badge">' + relation.cct_position + '</span>');
                }
                
                // Table missing warning
                if (relation.table_exists === false) {
                    $header.append('<span class="relation-badge error" title="Database table missing">⚠️ No DB Table</span>');
                }
                
                $item.append($header);
                
                // Meta info
                const $meta = $('<div class="relation-meta"></div>');
                $meta.append('<div><strong>Type:</strong> ' + relation.type + '</div>');
                $meta.append('<div><strong>Parent:</strong> ' + relation.parent_object + '</div>');
                $meta.append('<div><strong>Child:</strong> ' + relation.child_object + '</div>');
                
                // Show warning if table doesn't exist
                if (relation.table_exists === false) {
                    $meta.append(
                        '<div class="relation-warning" style="color: #d63638; margin-top: 8px;">' +
                        '<strong>⚠️ Warning:</strong> Table <code>' + relation.table_name + '</code> does not exist. ' +
                        '<a href="' + jetInjectorAdmin.jetengine_relations_url + '" target="_blank">Edit this relation in JetEngine</a> ' +
                        'and enable "Store in separate database table", then save.' +
                        '</div>'
                    );
                }
                
                $item.append($meta);
                
                // Display fields (will be populated via AJAX when needed)
                // For MVP, we'll skip this and implement in Phase 2
                
                $list.append($item);
            });
        },
        
        /**
         * Save config
         */
        saveConfig: function(e) {
            e.preventDefault();
            
            const $form = $('#cct-config-form');
            const cctSlug = $('#config-cct-slug').val();
            
            if (!cctSlug) {
                alert('Please select a CCT');
                return;
            }
            
            // Gather enabled relations
            const enabledRelations = [];
            $('input[name="enabled_relations[]"]:checked').each(function() {
                enabledRelations.push(parseInt($(this).val()));
            });
            
            const configData = {
                injection_point: $('#config-injection-point').val(),
                enabled_relations: enabledRelations,
                display_fields: {},
                ui_settings: {
                    show_labels: true,
                    show_create_button: true,
                    modal_width: 'medium'
                }
            };
            
            this.log('Saving config:', {cctSlug, configData});
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_save_config',
                    nonce: jetInjectorAdmin.nonce,
                    cct_slug: cctSlug,
                    config: configData,
                    is_enabled: $('#config-is-enabled').is(':checked')
                },
                beforeSend: () => {
                    $('#modal-spinner').addClass('is-active');
                    $('#save-cct-config').prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        $('#modal-message').text(response.data.message).addClass('success');
                        setTimeout(() => {
                            this.closeModal();
                            location.reload();
                        }, 1000);
                    } else {
                        $('#modal-message').text(response.data.message).addClass('error');
                    }
                },
                error: () => {
                    $('#modal-message').text('Failed to save configuration').addClass('error');
                },
                complete: () => {
                    $('#modal-spinner').removeClass('is-active');
                    $('#save-cct-config').prop('disabled', false);
                }
            });
        },
        
        /**
         * Delete config
         */
        deleteConfig: function(e) {
            const cctSlug = $(e.currentTarget).data('cct-slug');
            
            if (!confirm(jetInjectorAdmin.i18n.confirm_delete)) {
                return;
            }
            
            this.log('Deleting config for:', cctSlug);
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_delete_config',
                    nonce: jetInjectorAdmin.nonce,
                    cct_slug: cctSlug
                },
                success: (response) => {
                    if (response.success) {
                        $(`.cct-config-card[data-cct-slug="${cctSlug}"]`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('.cct-config-card').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message || 'Failed to delete configuration');
                    }
                }
            });
        },
        
        /**
         * Toggle config
         */
        toggleConfig: function(e) {
            const $toggle = $(e.currentTarget);
            const cctSlug = $toggle.data('cct-slug');
            const isEnabled = $toggle.is(':checked');
            
            this.log('Toggling config:', cctSlug, isEnabled);
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_toggle_config',
                    nonce: jetInjectorAdmin.nonce,
                    cct_slug: cctSlug,
                    is_enabled: isEnabled
                },
                success: (response) => {
                    if (!response.success) {
                        $toggle.prop('checked', !isEnabled);
                        alert(response.data.message || 'Failed to toggle configuration');
                    } else {
                        $(`.cct-config-card[data-cct-slug="${cctSlug}"]`).toggleClass('disabled', !isEnabled);
                    }
                }
            });
        },
        
        /**
         * Save debug settings
         */
        saveDebugSettings: function(e) {
            e.preventDefault();
            
            const $form = $('#debug-settings-form');
            const formData = $form.serialize();
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_save_debug_settings',
                    nonce: jetInjectorAdmin.nonce,
                    ...Object.fromEntries(new URLSearchParams(formData))
                },
                beforeSend: () => {
                    $('#debug-spinner').addClass('is-active');
                },
                success: (response) => {
                    if (response.success) {
                        $('#debug-message').text(response.data.message).addClass('success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        $('#debug-message').text(response.data.message).addClass('error');
                    }
                },
                complete: () => {
                    $('#debug-spinner').removeClass('is-active');
                }
            });
        },
        
        /**
         * View log
         */
        viewLog: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_view_log',
                    nonce: jetInjectorAdmin.nonce
                },
                beforeSend: () => {
                    $('#log-spinner').addClass('is-active');
                },
                success: (response) => {
                    if (response.success) {
                        $('#log-contents').text(response.data.contents);
                        $('#log-size').text(response.data.size);
                        $('#log-viewer').slideDown();
                    }
                },
                complete: () => {
                    $('#log-spinner').removeClass('is-active');
                }
            });
        },
        
        /**
         * Clear log
         */
        clearLog: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the debug log?')) {
                return;
            }
            
            $.ajax({
                url: jetInjectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_clear_log',
                    nonce: jetInjectorAdmin.nonce
                },
                beforeSend: () => {
                    $('#log-spinner').addClass('is-active');
                },
                success: (response) => {
                    if (response.success) {
                        $('#log-contents').text('');
                        $('#log-viewer').slideUp();
                        $('#log-message').text(response.data.message).addClass('success');
                        setTimeout(() => {
                            $('#log-message').removeClass('success').text('');
                        }, 3000);
                    }
                },
                complete: () => {
                    $('#log-spinner').removeClass('is-active');
                }
            });
        },
        
        /**
         * Log to console if debug enabled
         */
        log: function(...args) {
            if (jetInjectorAdmin.debug) {
                console.log('[Jet Injector Admin]', ...args);
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        JetInjectorAdmin.init();
        
        // Load tab from hash
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            $(`.nav-tab[data-tab="${hash}"]`).trigger('click');
        }
    });
    
})(jQuery);

