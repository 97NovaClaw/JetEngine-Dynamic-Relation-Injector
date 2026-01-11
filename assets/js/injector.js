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
        
        /**
         * Initialize
         */
        init: function() {
            if (typeof jetInjectorConfig === 'undefined') {
                console.error('[Jet Injector] Configuration not found');
                return;
            }
            
            this.config = jetInjectorConfig;
            this.log('Initializing on CCT:', this.config.cct_slug);
            this.log('Relations:', this.config.relations);
            
            // Initialize selected items
            this.config.relations.forEach(relation => {
                this.selectedItems[relation.id] = [];
            });
            
            // Wait for DOM ready and JetEngine form to load
            this.waitForForm();
        },
        
        /**
         * Wait for CCT form to be available
         */
        waitForForm: function() {
            const checkForm = setInterval(() => {
                const $form = $('form[action*="jet-cct-save-item"]');
                if ($form.length) {
                    clearInterval(checkForm);
                    this.log('CCT form found');
                    this.injectUI($form);
                }
            }, 100);
            
            // Timeout after 5 seconds
            setTimeout(() => clearInterval(checkForm), 5000);
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
            
            this.log('UI injected successfully');
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
            
            const $modal = this.createSearchModal(relation);
            $('body').append($modal);
            
            // Show modal
            setTimeout(() => $modal.addClass('active'), 10);
            
            // Load initial results
            this.searchItems(relation, '');
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
            // Prepare relations data
            const relationsData = {};
            
            Object.keys(this.selectedItems).forEach(relationId => {
                const items = this.selectedItems[relationId];
                if (items.length > 0) {
                    relationsData[relationId] = items.map(item => item.id);
                }
            });
            
            // Set hidden input value
            $('#jet-injector-relations-data').val(JSON.stringify(relationsData));
            
            this.log('Form submitting with relations data:', relationsData);
            
            // Let form submit normally
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

