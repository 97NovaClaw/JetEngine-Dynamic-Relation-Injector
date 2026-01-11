/**
 * Runtime Injector JavaScript
 *
 * Handles relation selector UI injection on CCT edit pages
 *
 * @package JetRelationInjector
 */

(function($) {
    'use strict';
    
    const JetInjector = {
        
        config: null,
        selectedItems: {},
        formFound: false,
        
        /**
         * Initialize
         */
        init: function() {
            console.log('[Jet Injector] 🔥 INIT CALLED');
            
            if (typeof jetInjectorConfig === 'undefined') {
                console.error('[Jet Injector] ❌ Configuration not found - script not localized!');
                return;
            }
            
            this.config = jetInjectorConfig;
            console.log('[Jet Injector] ✅ Config loaded:', this.config);
            console.log('[Jet Injector] CCT Slug:', this.config.cct_slug);
            console.log('[Jet Injector] Relations:', this.config.relations);
            console.log('[Jet Injector] Injection Point:', this.config.injection_point);
            
            // Initialize selected items
            this.config.relations.forEach(relation => {
                this.selectedItems[relation.id] = [];
            });
            
            console.log('[Jet Injector] 🔍 Starting form search...');
            
            // Wait for DOM ready and JetEngine form to load
            this.waitForForm();
        },
        
        /**
         * Wait for CCT form to be available
         */
        waitForForm: function() {
            let attempts = 0;
            const maxAttempts = 50;
            
            const checkForm = setInterval(() => {
                attempts++;
                
                // Try multiple selectors
                let $form = $('form[action*="jet-cct-save-item"]');
                
                if (!$form.length) {
                    // Try alternative selectors
                    $form = $('form[method="post"]').filter(function() {
                        return $(this).find('[name="cct_action"]').length > 0;
                    });
                }
                
                console.log(`[Jet Injector] Attempt ${attempts}/${maxAttempts} - Forms found: ${$form.length}`);
                
                if ($form.length) {
                    clearInterval(checkForm);
                    this.formFound = true;
                    console.log('[Jet Injector] ✅ FORM FOUND!', $form[0]);
                    console.log('[Jet Injector] Form action:', $form.attr('action'));
                    console.log('[Jet Injector] Form method:', $form.attr('method'));
                    this.injectUI($form);
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkForm);
                    console.error('[Jet Injector] ❌ FORM NOT FOUND after', attempts, 'attempts');
                    console.log('[Jet Injector] All forms on page:', $('form').length);
                    $('form').each(function(i) {
                        console.log(`  Form ${i}:`, {
                            action: $(this).attr('action'),
                            method: $(this).attr('method'),
                            id: $(this).attr('id'),
                            class: $(this).attr('class')
                        });
                    });
                }
            }, 100);
        },
        
        /**
         * Inject relation selector UI
         */
        injectUI: function($form) {
            const injectionPoint = this.config.injection_point || 'before_save';
            const $container = this.createContainer();
            
            if (injectionPoint === 'before_save') {
                // Inject before submit button
                const $submitBtn = $form.find('[type="submit"]').last();
                if ($submitBtn.length) {
                    $submitBtn.before($container);
                } else {
                    $form.append($container);
                }
            } else {
                // Inject after CCT fields
                $form.append($container);
            }
            
            // Create hidden inputs container
            const $hiddenContainer = $('<div class="jet-injector-hidden-inputs"></div>');
            $form.append($hiddenContainer);
            
            // Add nonce field
            $hiddenContainer.append(
                $('<input>').attr({
                    type: 'hidden',
                    name: 'jet_injector_nonce',
                    value: this.config.nonce
                })
            );
            
            // Add relations data field (will be populated on submit)
            $hiddenContainer.append(
                $('<input>').attr({
                    type: 'hidden',
                    name: 'jet_injector_relations',
                    id: 'jet-injector-relations-data'
                })
            );
            
            // Hook into form submit
            $form.on('submit', this.onFormSubmit.bind(this));
            
            console.log('[Jet Injector] ✅ UI INJECTION COMPLETE');
            console.log('[Jet Injector] Hidden inputs added:', $hiddenContainer.find('input').length);
            console.log('[Jet Injector] Submit handler attached');
        },
        
        /**
         * Create relations container
         */
        createContainer: function() {
            const $container = $('<div class="jet-injector-container"></div>');
            
            $container.append('<h3>' + (this.config.i18n.relations_title || 'Relations') + '</h3>');
            
            this.config.relations.forEach(relation => {
                $container.append(this.createRelationItem(relation));
            });
            
            return $container;
        },
        
        /**
         * Create relation item UI
         */
        createRelationItem: function(relation) {
            const $item = $('<div class="jet-injector-relation-item"></div>');
            
            // Header
            const $header = $('<div class="jet-injector-relation-header"></div>');
            $header.append('<div class="jet-injector-relation-label">' + relation.name + '</div>');
            
            // Type badge
            const badgeClass = relation.cct_position === 'grandparent' ? 'grandparent' : '';
            $header.append(
                '<span class="jet-injector-relation-type ' + badgeClass + '">' + 
                relation.cct_position + 
                '</span>'
            );
            
            $item.append($header);
            
            // Selected items
            const $selectedItems = $('<div class="jet-injector-selected-items" data-relation-id="' + relation.id + '"></div>');
            $item.append($selectedItems);
            
            // Actions
            const $actions = $('<div class="jet-injector-actions"></div>');
            
            $actions.append(
                $('<button type="button" class="jet-injector-btn">')
                    .html('<span class="dashicons dashicons-search"></span> ' + this.config.i18n.select)
                    .data('relation', relation)
                    .on('click', () => this.openSearchModal(relation))
            );
            
            if (this.config.ui_settings.show_create_button) {
                $actions.append(
                    $('<button type="button" class="jet-injector-btn secondary">')
                        .html('<span class="dashicons dashicons-plus-alt"></span> ' + this.config.i18n.add_new)
                        .data('relation', relation)
                        .on('click', () => this.openCreateModal(relation))
                );
            }
            
            $item.append($actions);
            
            // Warning for one-to-one relations
            if (relation.type === 'one_to_one') {
                $item.append(
                    '<div class="jet-injector-warning">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    this.config.i18n.one_to_one_warning +
                    '</div>'
                );
            }
            
            return $item;
        },
        
        /**
         * Open search modal
         */
        openSearchModal: function(relation) {
            this.log('Opening search modal for relation:', relation.name);
            
            // Check if this is a hierarchical relation
            if (relation.hierarchy_meta) {
                this.log('Detected hierarchical relation, opening cascading modal');
                this.openCascadingModal(relation);
                return;
            }
            
            const $modal = this.createSearchModal(relation);
            $('body').append($modal);
            
            // Show modal
            setTimeout(() => $modal.addClass('active'), 10);
            
            // Load initial results
            this.searchItems(relation, '');
        },
        
        /**
         * Open cascading modal for hierarchical relations
         */
        openCascadingModal: function(relation) {
            this.log('Opening cascading modal', {
                relation: relation.name,
                hierarchy_type: relation.hierarchy_meta.type,
                parent_relation_id: relation.hierarchy_meta.parent_relation_id
            });
            
            const $modal = this.createCascadingModal(relation);
            $('body').append($modal);
            
            // Show modal
            setTimeout(() => $modal.addClass('active'), 10);
            
            // Load parent/grandparent items first
            this.loadCascadeStep1(relation, $modal);
        },
        
        /**
         * Create cascading modal for hierarchical relations
         */
        createCascadingModal: function(relation) {
            const $modal = $('<div class="jet-injector-modal cascading"></div>');
            const $overlay = $('<div class="jet-injector-modal-overlay"></div>');
            const $content = $('<div class="jet-injector-modal-content"></div>');
            
            // Header
            const $header = $('<div class="jet-injector-modal-header"></div>');
            $header.append('<h3>Select ' + relation.related_cct_name + '</h3>');
            $header.append(
                $('<button type="button" class="jet-injector-modal-close">')
                    .html('&times;')
                    .on('click', () => this.closeModal($modal))
            );
            
            // Body
            const $body = $('<div class="jet-injector-modal-body"></div>');
            
            // Determine labels based on hierarchy type
            let step1Label, step2Label;
            if (relation.hierarchy_meta.type === 'grandparent') {
                // Current CCT is grandchild
                // Step 1: Select grandparent
                // Step 2: Select parent (filtered by grandparent)
                step1Label = 'Select ' + relation.related_cct_name + ' (Grandparent)';
                step2Label = 'Select ' + relation.hierarchy_meta.parent_object_name + ' (Parent)';
            } else if (relation.hierarchy_meta.type === 'grandchild') {
                // Current CCT is grandparent
                // Step 1: Select parent
                // Step 2: Select grandchild (filtered by parent)
                step1Label = 'Select ' + relation.hierarchy_meta.parent_object_name + ' (Child)';
                step2Label = 'Select ' + relation.related_cct_name + ' (Grandchild)';
            }
            
            // Step 1: Parent/Grandparent selector
            const $step1 = $('<div class="cascade-step" data-step="1"></div>');
            $step1.append('<div class="cascade-step-label">' + step1Label + '</div>');
            
            const $search1 = $('<div class="jet-injector-search"></div>');
            const $searchInput1 = $('<input type="text" class="cascade-search-1" placeholder="' + this.config.i18n.search_placeholder + '">')
                .on('input', (e) => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.searchCascadeStep1(relation, $(e.currentTarget).val(), $modal);
                    }, 300);
                });
            $search1.append($searchInput1);
            $step1.append($search1);
            $step1.append('<div class="cascade-results-1" data-relation-id="' + relation.id + '"></div>');
            
            // Step 2: Child/Grandchild selector (initially hidden/disabled)
            const $step2 = $('<div class="cascade-step" data-step="2" style="opacity:0.5;"></div>');
            $step2.append('<div class="cascade-step-label">' + step2Label + '</div>');
            $step2.append('<p class="cascade-step-instruction">Please select an item from step 1 first</p>');
            
            const $search2 = $('<div class="jet-injector-search" style="display:none;"></div>');
            const $searchInput2 = $('<input type="text" class="cascade-search-2" placeholder="' + this.config.i18n.search_placeholder + '" disabled>')
                .on('input', (e) => {
                    clearTimeout(this.searchTimeout2);
                    this.searchTimeout2 = setTimeout(() => {
                        const step1SelectedId = $modal.data('step1-selected-id');
                        if (step1SelectedId) {
                            this.searchCascadeStep2(relation, step1SelectedId, $(e.currentTarget).val(), $modal);
                        }
                    }, 300);
                });
            $search2.append($searchInput2);
            $step2.append($search2);
            $step2.append('<div class="cascade-results-2" data-relation-id="' + relation.id + '"></div>');
            
            $body.append($step1, $step2);
            
            // Footer
            const $footer = $('<div class="jet-injector-modal-footer"></div>');
            $footer.append(
                $('<button type="button" class="jet-injector-btn secondary">')
                    .text(this.config.i18n.cancel)
                    .on('click', () => this.closeModal($modal))
            );
            
            $content.append($header, $body, $footer);
            $modal.append($overlay, $content);
            
            // Close on overlay click
            $overlay.on('click', () => this.closeModal($modal));
            
            return $modal;
        },
        
        /**
         * Load cascade step 1 items
         */
        loadCascadeStep1: function(relation, $modal) {
            this.searchCascadeStep1(relation, '', $modal);
        },
        
        /**
         * Search cascade step 1 items
         */
        searchCascadeStep1: function(relation, searchTerm, $modal) {
            const $results = $modal.find('.cascade-results-1');
            $results.html('<div class="jet-injector-loading"><div class="spinner is-active"></div><p>' + this.config.i18n.loading + '</p></div>');
            
            // Determine which object to search for in step 1
            let searchObject;
            if (relation.hierarchy_meta.type === 'grandparent') {
                // Search for grandparent
                searchObject = relation.related_cct_slug;
            } else {
                // Search for parent (child of current CCT)
                searchObject = relation.hierarchy_meta.parent_object;
            }
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_search_items',
                    nonce: this.config.nonce,
                    cct_slug: searchObject,
                    search: searchTerm,
                    relation_id: relation.id
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCascadeStep1Results(relation, response.data.items, $results, $modal);
                    } else {
                        $results.html('<div class="jet-injector-empty"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: () => {
                    $results.html('<div class="jet-injector-empty"><p>' + this.config.i18n.error + '</p></div>');
                }
            });
        },
        
        /**
         * Render cascade step 1 results
         */
        renderCascadeStep1Results: function(relation, items, $container, $modal) {
            $container.empty();
            
            if (!items || items.length === 0) {
                $container.html('<div class="jet-injector-empty"><p>' + this.config.i18n.no_results + '</p></div>');
                return;
            }
            
            items.forEach(item => {
                const $resultItem = $('<div class="jet-injector-result-item cascade-step-1-item"></div>');
                
                const $info = $('<div class="jet-injector-result-info"></div>');
                $info.append('<div class="jet-injector-result-title">' + item.title + '</div>');
                $info.append('<div class="jet-injector-result-meta">ID: ' + item.id + '</div>');
                
                const $selectBtn = $('<button type="button" class="jet-injector-result-btn">')
                    .text('Select')
                    .on('click', () => {
                        // Store selection and enable step 2
                        $modal.data('step1-selected-id', item.id);
                        $modal.data('step1-selected-item', item);
                        
                        // Highlight selected item
                        $modal.find('.cascade-step-1-item').removeClass('selected');
                        $resultItem.addClass('selected');
                        
                        // Enable step 2
                        this.enableCascadeStep2(relation, item.id, $modal);
                    });
                
                $resultItem.append($info, $selectBtn);
                $container.append($resultItem);
            });
        },
        
        /**
         * Enable cascade step 2 after step 1 selection
         */
        enableCascadeStep2: function(relation, step1ItemId, $modal) {
            this.log('Enabling cascade step 2', { step1ItemId });
            
            const $step2 = $modal.find('[data-step="2"]');
            $step2.css('opacity', '1');
            $step2.find('.cascade-step-instruction').hide();
            $step2.find('.jet-injector-search').show();
            $step2.find('.cascade-search-2').prop('disabled', false).focus();
            
            // Load step 2 items filtered by step 1 selection
            this.searchCascadeStep2(relation, step1ItemId, '', $modal);
        },
        
        /**
         * Search cascade step 2 items (filtered by step 1 selection)
         */
        searchCascadeStep2: function(relation, step1ItemId, searchTerm, $modal) {
            const $results = $modal.find('.cascade-results-2');
            $results.html('<div class="jet-injector-loading"><div class="spinner is-active"></div><p>' + this.config.i18n.loading + '</p></div>');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_search_cascade_items',
                    nonce: this.config.nonce,
                    relation_id: relation.id,
                    parent_relation_id: relation.hierarchy_meta.parent_relation_id,
                    parent_item_id: step1ItemId,
                    search: searchTerm,
                    hierarchy_type: relation.hierarchy_meta.type
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCascadeStep2Results(relation, response.data.items, $results, $modal);
                    } else {
                        $results.html('<div class="jet-injector-empty"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: () => {
                    $results.html('<div class="jet-injector-empty"><p>' + this.config.i18n.error + '</p></div>');
                }
            });
        },
        
        /**
         * Render cascade step 2 results
         */
        renderCascadeStep2Results: function(relation, items, $container, $modal) {
            $container.empty();
            
            if (!items || items.length === 0) {
                $container.html('<div class="jet-injector-empty"><p>' + this.config.i18n.no_results + '</p></div>');
                return;
            }
            
            items.forEach(item => {
                const $resultItem = $('<div class="jet-injector-result-item"></div>');
                
                const $info = $('<div class="jet-injector-result-info"></div>');
                $info.append('<div class="jet-injector-result-title">' + item.title + '</div>');
                $info.append('<div class="jet-injector-result-meta">ID: ' + item.id + '</div>');
                
                const $selectBtn = $('<button type="button" class="jet-injector-result-btn">')
                    .text(this.config.i18n.select)
                    .on('click', () => {
                        // For hierarchical relations, we need to add the final item
                        // (which could be parent or grandchild depending on direction)
                        this.addSelectedItem(relation, item);
                        this.closeModal($modal);
                    });
                
                $resultItem.append($info, $selectBtn);
                $container.append($resultItem);
            });
        },
        
        /**
         * Create search modal
         */
        createSearchModal: function(relation) {
            const $modal = $('<div class="jet-injector-modal"></div>');
            const $overlay = $('<div class="jet-injector-modal-overlay"></div>');
            const $content = $('<div class="jet-injector-modal-content"></div>');
            
            // Header
            const $header = $('<div class="jet-injector-modal-header"></div>');
            $header.append('<h3>Select ' + relation.related_cct_name + '</h3>');
            $header.append(
                $('<button type="button" class="jet-injector-modal-close">')
                    .html('&times;')
                    .on('click', () => this.closeModal($modal))
            );
            
            // Body
            const $body = $('<div class="jet-injector-modal-body"></div>');
            
            // Search input
            const $search = $('<div class="jet-injector-search"></div>');
            const $searchInput = $('<input type="text" placeholder="' + this.config.i18n.search_placeholder + '">')
                .on('input', (e) => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.searchItems(relation, $(e.currentTarget).val());
                    }, 300);
                });
            $search.append($searchInput);
            $body.append($search);
            
            // Results container
            $body.append('<div class="jet-injector-results" data-relation-id="' + relation.id + '"></div>');
            
            // Footer
            const $footer = $('<div class="jet-injector-modal-footer"></div>');
            $footer.append(
                $('<button type="button" class="jet-injector-btn secondary">')
                    .text(this.config.i18n.cancel)
                    .on('click', () => this.closeModal($modal))
            );
            
            $content.append($header, $body, $footer);
            $modal.append($overlay, $content);
            
            // Close on overlay click
            $overlay.on('click', () => this.closeModal($modal));
            
            return $modal;
        },
        
        /**
         * Search items via AJAX
         */
        searchItems: function(relation, searchTerm) {
            const $results = $('.jet-injector-results[data-relation-id="' + relation.id + '"]');
            
            $results.html('<div class="jet-injector-loading"><div class="spinner is-active"></div><p>' + this.config.i18n.loading + '</p></div>');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_search_items',
                    nonce: this.config.nonce,
                    cct_slug: relation.related_cct_slug,
                    search: searchTerm,
                    relation_id: relation.id
                },
                success: (response) => {
                    if (response.success) {
                        this.renderSearchResults(relation, response.data.items, $results);
                    } else {
                        $results.html('<div class="jet-injector-empty"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: () => {
                    $results.html('<div class="jet-injector-empty"><p>' + this.config.i18n.error + '</p></div>');
                }
            });
        },
        
        /**
         * Render search results
         */
        renderSearchResults: function(relation, items, $container) {
            $container.empty();
            
            if (!items || items.length === 0) {
                $container.html('<div class="jet-injector-empty"><p>' + this.config.i18n.no_results + '</p></div>');
                return;
            }
            
            items.forEach(item => {
                const $resultItem = $('<div class="jet-injector-result-item"></div>');
                
                const $info = $('<div class="jet-injector-result-info"></div>');
                $info.append('<div class="jet-injector-result-title">' + item.title + '</div>');
                $info.append('<div class="jet-injector-result-meta">ID: ' + item.id + '</div>');
                
                const $selectBtn = $('<button type="button" class="jet-injector-result-btn">')
                    .text(this.config.i18n.select)
                    .on('click', () => {
                        this.addSelectedItem(relation, item);
                        this.closeModal($resultItem.closest('.jet-injector-modal'));
                    });
                
                $resultItem.append($info, $selectBtn);
                $container.append($resultItem);
            });
        },
        
        /**
         * Open create modal
         */
        openCreateModal: function(relation) {
            this.log('Opening create modal for relation:', relation.name);
            
            const $modal = this.createCreateModal(relation);
            $('body').append($modal);
            
            // Show modal
            setTimeout(() => $modal.addClass('active'), 10);
        },
        
        /**
         * Create "Add New" modal
         */
        createCreateModal: function(relation) {
            const $modal = $('<div class="jet-injector-modal"></div>');
            const $overlay = $('<div class="jet-injector-modal-overlay"></div>');
            const $content = $('<div class="jet-injector-modal-content"></div>');
            
            // Header
            const $header = $('<div class="jet-injector-modal-header"></div>');
            $header.append('<h3>Create New ' + relation.related_cct_name + '</h3>');
            $header.append(
                $('<button type="button" class="jet-injector-modal-close">')
                    .html('&times;')
                    .on('click', () => this.closeModal($modal))
            );
            
            // Body
            const $body = $('<div class="jet-injector-modal-body"></div>');
            
            // Create form (simplified for MVP)
            const $form = $('<div class="jet-injector-create-form"></div>');
            
            // Add basic fields (title/name field)
            $form.append(
                '<div class="form-field">' +
                '<label>Title <span class="required">*</span></label>' +
                '<input type="text" name="item_title" required>' +
                '</div>'
            );
            
            $body.append($form);
            
            // Footer
            const $footer = $('<div class="jet-injector-modal-footer"></div>');
            $footer.append(
                $('<button type="button" class="jet-injector-btn secondary">')
                    .text(this.config.i18n.cancel)
                    .on('click', () => this.closeModal($modal))
            );
            $footer.append(
                $('<button type="button" class="jet-injector-btn">')
                    .text(this.config.i18n.create)
                    .on('click', () => this.createItem(relation, $form, $modal))
            );
            
            $content.append($header, $body, $footer);
            $modal.append($overlay, $content);
            
            // Close on overlay click
            $overlay.on('click', () => this.closeModal($modal));
            
            return $modal;
        },
        
        /**
         * Create new item via AJAX
         */
        createItem: function(relation, $form, $modal) {
            const itemData = {
                title: $form.find('[name="item_title"]').val()
            };
            
            if (!itemData.title) {
                alert('Please enter a title');
                return;
            }
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'jet_injector_create_item',
                    nonce: this.config.nonce,
                    cct_slug: relation.related_cct_slug,
                    item_data: itemData
                },
                success: (response) => {
                    if (response.success) {
                        this.addSelectedItem(relation, {
                            id: response.data.item_id,
                            title: itemData.title
                        });
                        this.closeModal($modal);
                    } else {
                        alert(response.data.message || this.config.i18n.error);
                    }
                }
            });
        },
        
        /**
         * Add selected item
         */
        addSelectedItem: function(relation, item) {
            // Check if one-to-one and clear existing
            if (relation.type === 'one_to_one') {
                this.selectedItems[relation.id] = [];
            }
            
            // Add item if not already selected
            if (!this.selectedItems[relation.id].find(i => i.id === item.id)) {
                this.selectedItems[relation.id].push(item);
            }
            
            this.renderSelectedItems(relation);
            this.log('Item selected:', item, 'for relation:', relation.name);
        },
        
        /**
         * Remove selected item
         */
        removeSelectedItem: function(relation, itemId) {
            this.selectedItems[relation.id] = this.selectedItems[relation.id].filter(i => i.id !== itemId);
            this.renderSelectedItems(relation);
            this.log('Item removed:', itemId, 'from relation:', relation.name);
        },
        
        /**
         * Render selected items
         */
        renderSelectedItems: function(relation) {
            const $container = $('.jet-injector-selected-items[data-relation-id="' + relation.id + '"]');
            $container.empty();
            
            this.selectedItems[relation.id].forEach(item => {
                const $tag = $('<div class="jet-injector-selected-item"></div>');
                $tag.append('<span>' + item.title + '</span>');
                $tag.append(
                    $('<button type="button" class="remove-item">')
                        .html('&times;')
                        .on('click', () => this.removeSelectedItem(relation, item.id))
                );
                $container.append($tag);
            });
        },
        
        /**
         * Close modal
         */
        closeModal: function($modal) {
            $modal.removeClass('active');
            setTimeout(() => $modal.remove(), 300);
        },
        
        /**
         * Handle form submit
         */
        onFormSubmit: function(e) {
            console.log('[Jet Injector] 🚀 FORM SUBMIT EVENT TRIGGERED');
            console.log('[Jet Injector] Selected items:', this.selectedItems);
            
            // Prepare relations data
            const relationsData = {};
            
            Object.keys(this.selectedItems).forEach(relationId => {
                const items = this.selectedItems[relationId];
                if (items.length > 0) {
                    relationsData[relationId] = items.map(item => item.id);
                }
            });
            
            console.log('[Jet Injector] Relations data to save:', relationsData);
            
            // Set hidden input value
            const $input = $('#jet-injector-relations-data');
            console.log('[Jet Injector] Hidden input found:', $input.length);
            
            if ($input.length) {
                $input.val(JSON.stringify(relationsData));
                console.log('[Jet Injector] ✅ Hidden input value SET:', $input.val());
            } else {
                console.error('[Jet Injector] ❌ Hidden input NOT FOUND!');
            }
            
            // Verify nonce field exists
            const $nonce = $('[name="jet_injector_nonce"]');
            console.log('[Jet Injector] Nonce field found:', $nonce.length, 'value:', $nonce.val());
            
            // Let form submit normally
            console.log('[Jet Injector] ✅ Allowing form submission to continue');
            return true;
        },
        
        /**
         * Log to console if debug enabled
         */
        log: function(...args) {
            if (this.config.debug) {
                console.log('[Jet Injector]', ...args);
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        JetInjector.init();
    });
    
})(jQuery);

