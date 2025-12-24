/**
 * HCRM Houzez Admin JavaScript - Modern Redesign
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * HCRM Admin Handler
     */
    const HCRM_Admin = {

        // Auto-refresh interval
        connectionInterval: null,

        // Active sync polling intervals
        activeSyncs: {},

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.checkConnectionStatus();
            this.loadSyncStats();
            this.startConnectionMonitor();
            this.checkActiveSyncs();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.hcrm-tab-link', this.switchTab.bind(this));

            // Test connection
            $(document).on('click', '#test-connection', this.testConnection.bind(this));

            // Save API settings
            $(document).on('submit', '#hcrm-api-settings-form', this.saveApiSettings.bind(this));

            // Save sync settings
            $(document).on('click', '#save-sync-settings', this.saveSyncSettings.bind(this));

            // Sync buttons
            $(document).on('click', '.hcrm-sync-btn:not([disabled])', this.handleSync.bind(this));

            // Toggle password visibility
            $(document).on('click', '.hcrm-toggle-password', this.togglePassword.bind(this));

            // Copy to clipboard
            $(document).on('click', '.hcrm-copy-url', this.copyToClipboard.bind(this));

            // Save properties settings
            $(document).on('click', '#save-properties-settings', this.savePropertiesSettings.bind(this));

            // Save users settings
            $(document).on('click', '#save-users-settings', this.saveUsersSettings.bind(this));

            // Save leads settings
            $(document).on('click', '#save-leads-settings', this.saveLeadsSettings.bind(this));

            // Sync taxonomy buttons
            $(document).on('click', '.hcrm-sync-taxonomy-btn:not([disabled])', this.handleTaxonomySync.bind(this));

            // Sync users buttons
            $(document).on('click', '.hcrm-sync-users-btn:not([disabled])', this.handleUsersSync.bind(this));

            // Cancel sync buttons
            $(document).on('click', '.hcrm-cancel-sync:not([disabled])', this.handleCancelSync.bind(this));

            // Clear stuck syncs button
            $(document).on('click', '#hcrm-clear-stuck-syncs', this.handleClearStuckSyncs.bind(this));

        },

        /**
         * Initialize tabs from URL hash
         */
        initTabs: function() {
            const hash = window.location.hash || '#api-settings';
            this.showTab(hash.replace('#', ''));
        },

        /**
         * Switch tab handler
         */
        switchTab: function(e) {
            e.preventDefault();
            const tabId = $(e.currentTarget).data('tab');
            this.showTab(tabId);
            window.location.hash = tabId;
        },

        /**
         * Show a specific tab
         */
        showTab: function(tabId) {
            $('.hcrm-tab-link').removeClass('active');
            $('.hcrm-tab-panel').removeClass('active');

            $('[data-tab="' + tabId + '"]').addClass('active');
            $('#' + tabId).addClass('active');
        },

        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $result = $('#connection-result');
            const $spinner = $btn.siblings('.spinner');

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');
            $result.hide();

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_test_connection',
                    nonce: hcrm_admin.nonce,
                    api_base_url: $('#api_base_url').val(),
                    api_token: $('#api_token').val()
                },
                success: function(response) {
                    $result.show();
                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
                        HCRM_Admin.updateConnectionStatus(true);
                        HCRM_Admin.setButtonSuccess($btn);
                    } else {
                        $result.removeClass('success').addClass('error')
                            .html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                        HCRM_Admin.updateConnectionStatus(false);
                    }
                },
                error: function(xhr, status, error) {
                    $result.show().removeClass('success').addClass('error')
                        .html('<span class="dashicons dashicons-warning"></span> ' + (error || hcrm_admin.i18n.error));
                    HCRM_Admin.updateConnectionStatus(false);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Save API settings
         */
        saveApiSettings: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $btn = $form.find('[type="submit"]');
            const $spinner = $btn.siblings('.spinner');

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_save_settings',
                    nonce: hcrm_admin.nonce,
                    settings: $form.serialize()
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        HCRM_Admin.setButtonSuccess($btn);
                        // Update endpoint display
                        const apiUrl = $('#api_base_url').val();
                        if (apiUrl) {
                            try {
                                const hostname = new URL(apiUrl).hostname;
                                $('#api-endpoint-display').text(hostname);
                            } catch (e) {
                                // Invalid URL, ignore
                            }
                        }
                        // Check connection after save
                        setTimeout(function() {
                            HCRM_Admin.checkConnectionStatus();
                        }, 500);
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Save sync settings
         */
        saveSyncSettings: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $spinner = $btn.siblings('.spinner');

            // Gather sync settings
            const settings = {
                sync_properties: $('#sync_properties').is(':checked') ? 1 : 0,
                sync_taxonomies: $('#sync_taxonomies').is(':checked') ? 1 : 0,
                sync_users: $('#sync_users').is(':checked') ? 1 : 0,
                sync_leads: $('#sync_leads').is(':checked') ? 1 : 0,
                auto_sync: $('#auto_sync').is(':checked') ? 1 : 0
            };

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_save_settings',
                    nonce: hcrm_admin.nonce,
                    settings: $.param(settings)
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        HCRM_Admin.setButtonSuccess($btn);
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Save properties settings
         */
        savePropertiesSettings: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $spinner = $btn.siblings('.spinner');

            // Gather properties settings
            const settings = {
                sync_properties: $('#sync_properties').is(':checked') ? 1 : 0,
                sync_on_save: $('#sync_on_save').is(':checked') ? 1 : 0,
                sync_property_type: $('#sync_property_type').is(':checked') ? 1 : 0,
                sync_property_status: $('#sync_property_status').is(':checked') ? 1 : 0,
                sync_property_label: $('#sync_property_label').is(':checked') ? 1 : 0,
                sync_property_feature: $('#sync_property_feature').is(':checked') ? 1 : 0
            };

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_save_properties_settings',
                    nonce: hcrm_admin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        HCRM_Admin.setButtonSuccess($btn);
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Save users settings
         */
        saveUsersSettings: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $spinner = $btn.siblings('.spinner');

            // Gather users settings
            const settings = {
                sync_users: $('#sync_users').is(':checked') ? 1 : 0,
                sync_avatars: $('#sync_avatars').is(':checked') ? 1 : 0,
                auto_sync: $('#auto_sync_users').is(':checked') ? 1 : 0
            };

            // Gather role mappings
            const role_mapping = {};
            $('select[name^="role_mapping"]').each(function() {
                const name = $(this).attr('name');
                const matches = name.match(/role_mapping\[(.+)\]/);
                if (matches) {
                    role_mapping[matches[1]] = $(this).val();
                }
            });
            settings.role_mapping = role_mapping;

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_save_users_settings',
                    nonce: hcrm_admin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        HCRM_Admin.setButtonSuccess($btn);
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Save leads settings
         */
        saveLeadsSettings: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $spinner = $btn.siblings('.spinner');

            // Gather leads settings
            const settings = {
                sync_leads: $('#sync_leads').is(':checked') ? 1 : 0,
                use_background_queue: $('#use_background_queue').is(':checked') ? 1 : 0
            };

            // Gather enabled hooks
            const hooks_enabled = {};
            $('input[name^="hooks_enabled"]').each(function() {
                const name = $(this).attr('name');
                const matches = name.match(/hooks_enabled\[(.+)\]/);
                if (matches) {
                    hooks_enabled[matches[1]] = $(this).is(':checked') ? 1 : 0;
                }
            });
            settings.hooks_enabled = hooks_enabled;

            this.setButtonLoading($btn, true);
            $spinner.addClass('is-active');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_save_leads_settings',
                    nonce: hcrm_admin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        HCRM_Admin.setButtonSuccess($btn);
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                },
                complete: function() {
                    HCRM_Admin.setButtonLoading($btn, false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Handle taxonomy sync button click (background mode)
         */
        handleTaxonomySync: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const taxonomy = $btn.data('taxonomy');
            const $card = $btn.closest('.hcrm-sync-row');
            const progressId = 'sync-progress-' + taxonomy;

            if (!confirm(hcrm_admin.i18n.confirm_sync || 'Are you sure you want to sync?')) {
                return;
            }

            this.setButtonLoading($btn, true);

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_sync_taxonomy',
                    nonce: hcrm_admin.nonce,
                    taxonomy: taxonomy
                },
                success: function(response) {
                    if (response.success && response.data.sync_id) {
                        // Show progress bar and start polling
                        HCRM_Admin.showInlineProgress($card, progressId, response.data.sync_id);
                        HCRM_Admin.startPolling(response.data.sync_id, progressId, 'taxonomy', $btn);
                    } else {
                        HCRM_Admin.setButtonLoading($btn, false);
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.setButtonLoading($btn, false);
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                }
            });
        },

        /**
         * Handle users sync button click (background mode)
         */
        handleUsersSync: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const type = $btn.data('type'); // 'wp_users', 'agencies', or 'all'
            const $syncRow = $btn.closest('.hcrm-sync-row');
            const progressId = 'sync-progress-users-' + type;

            if (!confirm(hcrm_admin.i18n.confirm_sync || 'Are you sure you want to sync?')) {
                return;
            }

            this.setButtonLoading($btn, true);

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_sync_users',
                    nonce: hcrm_admin.nonce,
                    type: type
                },
                success: function(response) {
                    if (response.success && response.data.sync_id) {
                        // Show progress bar and start polling
                        HCRM_Admin.showInlineProgress($syncRow, progressId, response.data.sync_id);
                        HCRM_Admin.startPolling(response.data.sync_id, progressId, 'users', $btn);
                    } else {
                        HCRM_Admin.setButtonLoading($btn, false);
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.setButtonLoading($btn, false);
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                }
            });
        },

        /**
         * Load users stats
         */
        loadUsersStats: function() {
            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_get_users_stats',
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        if (data.agencies) {
                            $('#agencies-total').text(data.agencies.total || 0);
                            $('#agencies-synced').text(data.agencies.synced || 0);
                            $('#agencies-pending').text(data.agencies.pending || 0);
                        }
                        if (data.wp_users) {
                            $('#wp-users-total').text(data.wp_users.total || 0);
                            $('#wp-users-synced').text(data.wp_users.synced || 0);
                            $('#wp-users-pending').text(data.wp_users.pending || 0);
                        }
                    }
                }
            });
        },

        /**
         * Handle sync button click (background mode)
         */
        handleSync: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const entity = $btn.data('entity');
            const $syncRow = $btn.closest('.hcrm-sync-row');
            const progressId = 'sync-progress-' + entity;

            if (!confirm(hcrm_admin.i18n.confirm_sync)) {
                return;
            }

            this.setButtonLoading($btn, true);

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_sync_' + entity,
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.sync_id) {
                        // Show progress bar and start polling
                        HCRM_Admin.showInlineProgress($syncRow, progressId, response.data.sync_id);
                        HCRM_Admin.startPolling(response.data.sync_id, progressId, entity, $btn);
                    } else {
                        HCRM_Admin.setButtonLoading($btn, false);
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.setButtonLoading($btn, false);
                    HCRM_Admin.showNotice('error', error || hcrm_admin.i18n.error);
                }
            });
        },

        /**
         * Toggle password visibility
         */
        togglePassword: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $input = $btn.siblings('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';

            $input.attr('type', type);
            $btn.find('.dashicons')
                .toggleClass('dashicons-visibility', type === 'password')
                .toggleClass('dashicons-hidden', type === 'text');
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $input = $btn.siblings('input');
            const originalType = $input.attr('type');

            // Temporarily show the value for copying
            $input.attr('type', 'text');
            $input[0].select();

            try {
                document.execCommand('copy');
                HCRM_Admin.showNotice('success', hcrm_admin.i18n.copied);
            } catch (err) {
                HCRM_Admin.showNotice('error', 'Failed to copy');
            }

            // Restore original type
            $input.attr('type', originalType);
        },

        /**
         * Check connection status on page load
         */
        checkConnectionStatus: function(silent, callback) {
            const $indicator = $('#connection-indicator');

            // Set to checking state
            if (!silent) {
                $indicator.removeClass('connected disconnected').addClass('checking');
                $('#connection-text').text(hcrm_admin.i18n.checking || 'Checking...');
            }

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_get_sync_status',
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.updateConnectionStatus(response.data.connected);
                    } else {
                        HCRM_Admin.updateConnectionStatus(false);
                    }
                },
                error: function() {
                    HCRM_Admin.updateConnectionStatus(false);
                },
                complete: function() {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },

        /**
         * Update connection status indicator
         */
        updateConnectionStatus: function(connected) {
            const $indicator = $('#connection-indicator');
            const $text = $('#connection-text');

            $indicator.removeClass('connected disconnected checking');

            if (connected) {
                $indicator.addClass('connected');
                $text.text(hcrm_admin.i18n.connected || 'Connected');
            } else {
                $indicator.addClass('disconnected');
                $text.text(hcrm_admin.i18n.not_connected || 'Disconnected');
            }
        },

        /**
         * Start auto-refresh for connection status
         */
        startConnectionMonitor: function() {
            // Check every 60 seconds (silent check)
            this.connectionInterval = setInterval(function() {
                HCRM_Admin.checkConnectionStatus(true);
            }, 60000);
        },

        /**
         * Load sync statistics
         */
        loadSyncStats: function() {
            // Show skeleton loaders
            $('#stat-properties-synced, #stat-last-sync, #stat-errors').addClass('loading').text('...');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_get_sync_status',
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Animate stat updates
                        HCRM_Admin.animateStat('#stat-properties-synced', response.data.properties_synced || 0);
                        $('#stat-last-sync').removeClass('loading').text(response.data.last_sync || '--');
                        HCRM_Admin.animateStat('#stat-errors', response.data.errors_24h || 0);
                        $('#last-sync-time').text(response.data.last_sync || '--');
                    }
                },
                error: function() {
                    $('#stat-properties-synced, #stat-last-sync, #stat-errors').removeClass('loading').text('--');
                }
            });
        },

        /**
         * Animate a stat number counting up
         */
        animateStat: function(selector, targetValue) {
            const $element = $(selector);
            $element.removeClass('loading');

            const currentValue = parseInt($element.text()) || 0;
            const duration = 600;
            const steps = 20;
            const increment = (targetValue - currentValue) / steps;
            const stepDuration = duration / steps;

            if (increment === 0) {
                $element.text(targetValue);
                return;
            }

            let current = currentValue;
            let step = 0;

            const timer = setInterval(function() {
                step++;
                current += increment;

                if (step >= steps) {
                    current = targetValue;
                    clearInterval(timer);
                }

                $element.text(Math.round(current));
            }, stepDuration);
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function($btn, loading) {
            if (loading) {
                $btn.prop('disabled', true).addClass('loading');
            } else {
                $btn.prop('disabled', false).removeClass('loading');
            }
        },

        /**
         * Set button success state (temporary)
         */
        setButtonSuccess: function($btn) {
            $btn.addClass('success');
            setTimeout(function() {
                $btn.removeClass('success');
            }, 2000);
        },

        /**
         * Show sync progress modal
         */
        showSyncProgress: function() {
            $('#sync-progress-modal').show();
            // Animate progress bar
            $('.hcrm-progress').css('width', '0%');
            setTimeout(function() {
                $('.hcrm-progress').css('width', '100%');
            }, 100);
        },

        /**
         * Hide sync progress modal
         */
        hideSyncProgress: function() {
            $('#sync-progress-modal').hide();
            $('.hcrm-progress').css('width', '0%');
        },

        /**
         * Show inline progress bar
         */
        showInlineProgress: function($container, progressId, syncId) {
            // Check if progress bar already exists
            let $progress = $container.find('.hcrm-sync-progress');
            if ($progress.length === 0) {
                // Create progress bar HTML
                const progressHtml = `
                    <div class="hcrm-sync-progress" id="${progressId}" data-sync-id="${syncId}">
                        <div class="hcrm-progress-bar-container">
                            <div class="hcrm-progress-bar" style="width: 0%"></div>
                        </div>
                        <div class="hcrm-progress-info">
                            <span class="hcrm-progress-text">Starting sync...</span>
                            <span class="hcrm-progress-count">0 of 0</span>
                        </div>
                        <div class="hcrm-progress-actions">
                            <button type="button" class="button hcrm-cancel-sync" data-sync-id="${syncId}">
                                <span class="dashicons dashicons-no"></span> Cancel
                            </button>
                        </div>
                    </div>
                `;
                $container.append(progressHtml);
                $progress = $container.find('.hcrm-sync-progress');
            } else {
                // Update sync ID
                $progress.attr('data-sync-id', syncId);
                $progress.find('.hcrm-cancel-sync').attr('data-sync-id', syncId);
            }

            $progress.slideDown(200);
        },

        /**
         * Start polling for sync progress
         */
        startPolling: function(syncId, progressId, entity, $btn) {
            // Store reference
            this.activeSyncs[syncId] = {
                interval: null,
                progressId: progressId,
                entity: entity,
                $btn: $btn
            };

            const poll = setInterval(function() {
                HCRM_Admin.pollProgress(syncId);
            }, 500); // Poll every 500ms for smoother progress updates

            this.activeSyncs[syncId].interval = poll;
        },

        /**
         * Poll for sync progress
         */
        pollProgress: function(syncId) {
            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_get_sync_progress',
                    nonce: hcrm_admin.nonce,
                    sync_id: syncId
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.updateProgressBar(syncId, response.data);

                        // Check if sync is done
                        if (response.data.status === 'completed' ||
                            response.data.status === 'failed' ||
                            response.data.status === 'cancelled') {
                            HCRM_Admin.onSyncComplete(syncId, response.data);
                        }
                    } else {
                        // Sync not found or error
                        HCRM_Admin.onSyncComplete(syncId, { status: 'not_found' });
                    }
                },
                error: function() {
                    // Don't stop polling on network errors
                    console.error('Polling error for sync:', syncId);
                }
            });
        },

        /**
         * Update progress bar with current data
         */
        updateProgressBar: function(syncId, data) {
            const syncInfo = this.activeSyncs[syncId];
            if (!syncInfo) return;

            const $progress = $('#' + syncInfo.progressId);
            if ($progress.length === 0) return;

            const percentage = data.percentage || 0;
            const processed = data.processed || 0;
            const total = data.total || 0;
            const currentItem = data.current_item || '';

            // Update progress bar width
            $progress.find('.hcrm-progress-bar').css('width', percentage + '%');

            // Update text
            let statusText = '';
            if (data.status === 'running') {
                statusText = currentItem ? 'Syncing: ' + currentItem : 'Syncing...';
            } else if (data.status === 'pending') {
                statusText = 'Starting sync...';
            }
            $progress.find('.hcrm-progress-text').text(statusText);
            $progress.find('.hcrm-progress-count').text(processed + ' of ' + total);

            // Add time estimate if available
            if (data.estimated_remaining_formatted) {
                $progress.find('.hcrm-progress-count').text(
                    processed + ' of ' + total + ' (~' + data.estimated_remaining_formatted + ' remaining)'
                );
            }
        },

        /**
         * Handle sync completion
         */
        onSyncComplete: function(syncId, data) {
            const syncInfo = this.activeSyncs[syncId];
            if (!syncInfo) return;

            // Stop polling
            if (syncInfo.interval) {
                clearInterval(syncInfo.interval);
            }

            const $progress = $('#' + syncInfo.progressId);
            const $btn = syncInfo.$btn;

            // Update UI based on status
            if (data.status === 'completed') {
                $progress.find('.hcrm-progress-bar').css('width', '100%').addClass('completed');
                $progress.find('.hcrm-progress-text').text('Sync completed!');
                this.showNotice('success', 'Sync completed: ' + (data.success || 0) + ' items synced, ' + (data.failed || 0) + ' failed.');
                this.setButtonSuccess($btn);

                // Reload stats
                if (syncInfo.entity === 'properties') {
                    this.loadSyncStats();
                } else if (syncInfo.entity === 'users') {
                    this.loadUsersStats();
                }
            } else if (data.status === 'failed') {
                $progress.find('.hcrm-progress-bar').addClass('failed');
                $progress.find('.hcrm-progress-text').text('Sync failed');
                this.showNotice('error', 'Sync failed: ' + (data.errors && data.errors.length > 0 ? data.errors[data.errors.length - 1].message : 'Unknown error'));
            } else if (data.status === 'cancelled') {
                $progress.find('.hcrm-progress-bar').addClass('cancelled');
                $progress.find('.hcrm-progress-text').text('Sync cancelled');
                this.showNotice('info', 'Sync was cancelled.');
            }

            // Hide cancel button
            $progress.find('.hcrm-progress-actions').hide();

            // Re-enable button
            this.setButtonLoading($btn, false);

            // Hide progress bar after delay
            setTimeout(function() {
                $progress.slideUp(300, function() {
                    $(this).remove();
                });
            }, 3000);

            // Remove from active syncs
            delete this.activeSyncs[syncId];
        },

        /**
         * Handle cancel sync button click
         */
        handleCancelSync: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const syncId = $btn.data('sync-id');

            if (!syncId) return;

            if (!confirm('Are you sure you want to cancel this sync?')) {
                return;
            }

            $btn.prop('disabled', true).text('Cancelling...');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_cancel_sync',
                    nonce: hcrm_admin.nonce,
                    sync_id: syncId
                },
                success: function(response) {
                    if (response.success) {
                        // Polling will pick up the cancelled status
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Cancel');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Cancel');
                }
            });
        },

        /**
         * Check for active syncs on page load
         */
        checkActiveSyncs: function() {
            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_get_active_syncs',
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.syncs) {
                        const syncs = response.data.syncs;

                        // Show/hide stuck syncs banner
                        if (syncs.length > 0) {
                            $('#hcrm-stuck-syncs-count').text('(' + syncs.length + ' active)');
                            $('#hcrm-stuck-syncs-banner').slideDown(200);
                        } else {
                            $('#hcrm-stuck-syncs-banner').slideUp(200);
                        }

                        // Resume polling for active syncs
                        syncs.forEach(function(sync) {
                            HCRM_Admin.resumeSync(sync);
                        });
                    } else {
                        $('#hcrm-stuck-syncs-banner').slideUp(200);
                    }
                }
            });
        },

        /**
         * Handle clear stuck syncs button click
         */
        handleClearStuckSyncs: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all stuck syncs? This will cancel any in-progress operations.')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update hcrm-spin"></span> Clearing...');

            $.ajax({
                url: hcrm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hcrm_clear_stuck_syncs',
                    nonce: hcrm_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        HCRM_Admin.showNotice('success', response.data.message);
                        $('#hcrm-stuck-syncs-banner').slideUp(200);

                        // Stop all active polling
                        Object.keys(HCRM_Admin.activeSyncs).forEach(function(syncId) {
                            if (HCRM_Admin.activeSyncs[syncId].interval) {
                                clearInterval(HCRM_Admin.activeSyncs[syncId].interval);
                            }
                        });
                        HCRM_Admin.activeSyncs = {};

                        // Remove all progress bars
                        $('.hcrm-sync-progress').slideUp(200, function() {
                            $(this).remove();
                        });

                        // Re-enable all sync buttons
                        $('.hcrm-sync-btn, .hcrm-sync-taxonomy-btn, .hcrm-sync-users-btn').prop('disabled', false).removeClass('loading');
                    } else {
                        HCRM_Admin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    HCRM_Admin.showNotice('error', 'Failed to clear syncs: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Resume sync progress display
         */
        resumeSync: function(syncData) {
            const type = syncData.type;
            let $container, progressId, entity, $btn;

            // Find the appropriate container based on sync type
            if (type === 'properties') {
                $container = $('.hcrm-sync-btn[data-entity="properties"]').closest('.hcrm-sync-row');
                progressId = 'sync-progress-properties';
                entity = 'properties';
                $btn = $('.hcrm-sync-btn[data-entity="properties"]');
            } else if (type === 'wp_users') {
                $container = $('.hcrm-sync-users-btn[data-type="wp_users"]').closest('.hcrm-sync-row');
                progressId = 'sync-progress-users-wp_users';
                entity = 'users';
                $btn = $('.hcrm-sync-users-btn[data-type="wp_users"]');
            } else if (type === 'agencies') {
                $container = $('.hcrm-sync-users-btn[data-type="agencies"]').closest('.hcrm-sync-row');
                progressId = 'sync-progress-users-agencies';
                entity = 'users';
                $btn = $('.hcrm-sync-users-btn[data-type="agencies"]');
            } else if (type.startsWith('taxonomy_')) {
                const taxonomy = type.replace('taxonomy_', '');
                $container = $('.hcrm-sync-taxonomy-btn[data-taxonomy="' + taxonomy + '"]').closest('.hcrm-sync-row');
                progressId = 'sync-progress-' + taxonomy;
                entity = 'taxonomy';
                $btn = $('.hcrm-sync-taxonomy-btn[data-taxonomy="' + taxonomy + '"]');
            }

            if ($container && $container.length > 0) {
                this.setButtonLoading($btn, true);
                this.showInlineProgress($container, progressId, syncData.sync_id);
                this.updateProgressBar(syncData.sync_id, syncData);
                this.startPolling(syncData.sync_id, progressId, entity, $btn);
            }
        },

        /**
         * Show a notice message
         */
        showNotice: function(type, message) {
            // Map type to appropriate icon
            const iconMap = {
                'success': 'dashicons-yes-alt',
                'error': 'dashicons-dismiss',
                'warning': 'dashicons-warning',
                'info': 'dashicons-info'
            };
            const iconClass = iconMap[type] || 'dashicons-info';

            const $notice = $(
                '<div class="hcrm-notice ' + type + '">' +
                '<span class="dashicons ' + iconClass + '"></span>' +
                '<span class="hcrm-notice-text">' + message + '</span>' +
                '<button type="button" class="hcrm-notice-dismiss">' +
                '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
                '</div>'
            );

            // Bind dismiss button
            $notice.find('.hcrm-notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Remove existing notices
            $('#hcrm-notices').empty().append($notice);

            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);

            // Auto-hide after 5 seconds (except errors)
            if (type !== 'error') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        HCRM_Admin.init();
    });

    /**
     * Expose clearStuckSyncs globally for console access
     */
    window.HCRM_ClearStuckSyncs = function() {
        jQuery.ajax({
            url: hcrm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hcrm_clear_stuck_syncs',
                nonce: hcrm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('✓ ' + response.data.message);
                    alert(response.data.message);
                    location.reload();
                } else {
                    console.error('✗ ' + response.data.message);
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('✗ AJAX Error: ' + error);
                alert('AJAX Error: ' + error);
            }
        });
    };

})(jQuery);
