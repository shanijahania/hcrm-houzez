<?php
/**
 * Settings page template.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$hcrm_api_settings        = HCRM_Settings::get_api_settings();
$hcrm_properties_settings = HCRM_Settings::get_properties_settings();
$hcrm_taxonomy_settings   = HCRM_Settings::get_taxonomy_settings();
$hcrm_users_settings      = HCRM_Settings::get_users_settings();
$hcrm_leads_settings      = HCRM_Settings::get_leads_settings();
$hcrm_webhook_url         = HCRM_Webhook_Handler::get_webhook_url();
$hcrm_is_configured       = HCRM_Settings::is_api_configured();
$hcrm_api_base_url        = $hcrm_api_settings['api_base_url'] ?? '';
?>

<div class="wrap hcrm-settings-wrap">
    <h1 class="hcrm-page-title">
        <span class="dashicons dashicons-admin-generic"></span>
        <?php esc_html_e( 'HCRM Houzez', 'hcrm-houzez' ); ?>
        <span class="hcrm-version"><?php
            /* translators: %s: version number */
            echo esc_html( sprintf( __( 'v%s', 'hcrm-houzez' ), HCRM_VERSION ) );
        ?></span>
        <span class="hcrm-connection-indicator" id="connection-indicator" title="<?php esc_attr_e( 'API Connection Status', 'hcrm-houzez' ); ?>">
            <span class="indicator-dot"></span>
            <span class="indicator-text" id="connection-text"><?php echo $hcrm_is_configured ? esc_html__( 'Checking...', 'hcrm-houzez' ) : esc_html__( 'Not configured', 'hcrm-houzez' ); ?></span>
        </span>
    </h1>

    <!-- Notices Container -->
    <div id="hcrm-notices"></div>

    <!-- Stuck Syncs Warning Banner -->
    <div id="hcrm-stuck-syncs-banner" class="hcrm-stuck-syncs-banner" style="display: none;">
        <div class="hcrm-stuck-syncs-content">
            <span class="dashicons dashicons-warning"></span>
            <span class="hcrm-stuck-syncs-text">
                <?php esc_html_e('There are sync operations in progress.', 'hcrm-houzez'); ?>
                <span id="hcrm-stuck-syncs-count"></span>
            </span>
        </div>
        <button type="button" class="button hcrm-clear-stuck-syncs-btn" id="hcrm-clear-stuck-syncs">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e('Clear Stuck Syncs', 'hcrm-houzez'); ?>
        </button>
    </div>

    <div class="hcrm-settings-container">
        <!-- Tabs Navigation -->
        <nav class="hcrm-tabs-nav">
            <a href="#api-settings" class="hcrm-tab-link active" data-tab="api-settings">
                <span class="dashicons dashicons-rest-api"></span>
                <?php esc_html_e('API Settings', 'hcrm-houzez'); ?>
            </a>
            <a href="#properties-settings" class="hcrm-tab-link" data-tab="properties-settings">
                <span class="dashicons dashicons-building"></span>
                <?php esc_html_e('Properties', 'hcrm-houzez'); ?>
            </a>
            <a href="#users-settings" class="hcrm-tab-link" data-tab="users-settings">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e('Users', 'hcrm-houzez'); ?>
            </a>
            <a href="#leads-settings" class="hcrm-tab-link" data-tab="leads-settings">
                <span class="dashicons dashicons-megaphone"></span>
                <?php esc_html_e('Leads', 'hcrm-houzez'); ?>
            </a>
        </nav>

        <!-- Tab Panels -->
        <div class="hcrm-tabs-content">
            <!-- API Settings Tab -->
            <div id="api-settings" class="hcrm-tab-panel active">
                <?php include 'tab-api-settings.php'; ?>
            </div>

            <!-- Properties Settings Tab -->
            <div id="properties-settings" class="hcrm-tab-panel">
                <?php include 'tab-properties-settings.php'; ?>
            </div>

            <!-- Users Settings Tab -->
            <div id="users-settings" class="hcrm-tab-panel">
                <?php include 'tab-users-settings.php'; ?>
            </div>

            <!-- Leads Settings Tab -->
            <div id="leads-settings" class="hcrm-tab-panel">
                <?php include 'tab-leads-settings.php'; ?>
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

<!-- Custom Fields Mapping Modal -->
<div id="custom-fields-mapping-modal" class="hcrm-modal hcrm-modal-large" style="display: none;">
    <div class="hcrm-modal-overlay"></div>
    <div class="hcrm-modal-content">
        <div class="hcrm-modal-header">
            <h3>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'Custom Fields Mapping', 'hcrm-houzez' ); ?>
            </h3>
            <button type="button" class="hcrm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'hcrm-houzez' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="hcrm-modal-body">
            <!-- Loading State -->
            <div class="hcrm-mapping-loading" id="mapping-loading">
                <span class="dashicons dashicons-update hcrm-spin"></span>
                <p><?php esc_html_e( 'Loading custom fields...', 'hcrm-houzez' ); ?></p>
            </div>

            <!-- Empty State -->
            <div class="hcrm-mapping-empty" id="mapping-empty" style="display: none;">
                <span class="dashicons dashicons-info-outline"></span>
                <p><?php esc_html_e( 'No Houzez custom fields found. Add custom fields in Houzez > Custom Fields Builder first.', 'hcrm-houzez' ); ?></p>
            </div>

            <!-- Error State -->
            <div class="hcrm-mapping-error" id="mapping-error" style="display: none;">
                <span class="dashicons dashicons-warning"></span>
                <p id="mapping-error-message"><?php esc_html_e( 'Failed to load custom fields.', 'hcrm-houzez' ); ?></p>
            </div>

            <!-- Mapping Table -->
            <div class="hcrm-mapping-table-wrapper" id="mapping-table-wrapper" style="display: none;">
                <p class="hcrm-mapping-description">
                    <?php esc_html_e( 'Map each Houzez custom field to the corresponding CRM custom field. Fields that are not mapped will not be synced.', 'hcrm-houzez' ); ?>
                </p>
                <table class="hcrm-mapping-table" id="mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Houzez Field', 'hcrm-houzez' ); ?></th>
                            <th><?php esc_html_e( 'CRM Field', 'hcrm-houzez' ); ?></th>
                            <th class="hcrm-mapping-actions-col"><?php esc_html_e( 'Actions', 'hcrm-houzez' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="mapping-rows">
                        <!-- Dynamic rows will be inserted here -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="hcrm-modal-footer">
            <button type="button" class="button hcrm-modal-cancel">
                <?php esc_html_e( 'Cancel', 'hcrm-houzez' ); ?>
            </button>
            <button type="button" class="button button-primary" id="save-custom-fields-mapping">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Save Mapping', 'hcrm-houzez' ); ?>
            </button>
        </div>
    </div>
</div>
