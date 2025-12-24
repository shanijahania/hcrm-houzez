<?php
/**
 * Sync Settings tab template.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="hcrm-settings-form">
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Sync Options', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Configure what data to sync between WordPress and the CRM.', 'hcrm-houzez'); ?>
        </p>

        <!-- Properties Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_properties" id="sync_properties"
                           <?php checked(!empty($sync_settings['sync_properties'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Properties', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync property listings between WordPress and CRM. This includes all property details, images, and taxonomy assignments.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-btn" data-entity="properties">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync Now', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <!-- Taxonomies Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_taxonomies" id="sync_taxonomies"
                           <?php checked(!empty($sync_settings['sync_taxonomies'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Taxonomies', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync property types, statuses, cities, areas, and features.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-btn" data-entity="taxonomies">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync Now', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <!-- Taxonomy Auto Sync -->
        <div class="hcrm-sync-row hcrm-sync-row-sub">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="taxonomy_auto_sync" id="taxonomy_auto_sync"
                           <?php checked(!empty($sync_settings['taxonomy_auto_sync'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Auto Sync Taxonomies', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Automatically sync when property types, statuses, labels, or features are created/updated.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <!-- Users Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_users" id="sync_users"
                           <?php checked(!empty($sync_settings['sync_users'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Users', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync agents and agencies with CRM users and contacts.', 'hcrm-houzez'); ?></p>
                    <span class="hcrm-badge hcrm-badge-info"><?php esc_html_e('Coming Soon', 'hcrm-houzez'); ?></span>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-btn" data-entity="users" disabled>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync Now', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <!-- Leads Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_leads" id="sync_leads"
                           <?php checked(!empty($sync_settings['sync_leads'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Leads', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync inquiry leads to CRM contacts for follow-up.', 'hcrm-houzez'); ?></p>
                    <span class="hcrm-badge hcrm-badge-info"><?php esc_html_e('Coming Soon', 'hcrm-houzez'); ?></span>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-btn" data-entity="leads" disabled>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync Now', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <!-- Auto Sync Setting -->
        <div class="hcrm-sync-row hcrm-sync-row-auto">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="auto_sync" id="auto_sync"
                           <?php checked(!empty($sync_settings['auto_sync'])); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Auto Sync', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Automatically sync properties when they are created or updated in WordPress.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <div class="hcrm-form-actions">
            <button type="button" id="save-sync-settings" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'hcrm-houzez'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>

    <!-- Sync Stats -->
    <div class="hcrm-form-section hcrm-sync-stats">
        <h2><?php esc_html_e('Sync Statistics', 'hcrm-houzez'); ?></h2>

        <div class="hcrm-stats-grid">
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number" id="stat-properties-synced">--</span>
                <span class="hcrm-stat-label"><?php esc_html_e('Properties Synced', 'hcrm-houzez'); ?></span>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number" id="stat-last-sync">--</span>
                <span class="hcrm-stat-label"><?php esc_html_e('Last Sync', 'hcrm-houzez'); ?></span>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number" id="stat-errors">--</span>
                <span class="hcrm-stat-label"><?php esc_html_e('Errors (24h)', 'hcrm-houzez'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Sync Progress Modal -->
<div id="sync-progress-modal" class="hcrm-modal" style="display: none;">
    <div class="hcrm-modal-overlay"></div>
    <div class="hcrm-modal-content">
        <h3>
            <span class="dashicons dashicons-update hcrm-spin"></span>
            <?php esc_html_e('Syncing...', 'hcrm-houzez'); ?>
        </h3>
        <div class="hcrm-progress-bar">
            <div class="hcrm-progress" style="width: 0%"></div>
        </div>
        <p class="hcrm-progress-text"><?php esc_html_e('Please wait while we sync your data...', 'hcrm-houzez'); ?></p>
    </div>
</div>
