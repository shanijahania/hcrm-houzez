<?php
/**
 * Properties Settings tab template.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get counts for display
global $wpdb;
$hcrm_total_properties   = wp_count_posts( 'property' );
$hcrm_total_published    = $hcrm_total_properties->publish ?? 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
$hcrm_synced_properties  = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
        'property'
    )
);

// Taxonomy counts
$hcrm_taxonomies = [
    'property_type' => [
        'label' => __('Property Types', 'hcrm-houzez'),
        'setting_key' => 'sync_property_type',
        'crm_endpoint' => 'listing-types',
    ],
    'property_status' => [
        'label' => __('Property Status', 'hcrm-houzez'),
        'setting_key' => 'sync_property_status',
        'crm_endpoint' => 'listing-statuses',
    ],
    'property_label' => [
        'label' => __('Property Labels', 'hcrm-houzez'),
        'setting_key' => 'sync_property_label',
        'crm_endpoint' => 'listing-labels',
    ],
    'property_feature' => [
        'label' => __('Property Features', 'hcrm-houzez'),
        'setting_key' => 'sync_property_feature',
        'crm_endpoint' => 'facilities',
    ],
];
?>

<div class="hcrm-settings-form">
    <!-- Property Sync Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Property Sync', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Manage property synchronization between WordPress and CRM.', 'hcrm-houzez'); ?>
        </p>

        <!-- Property Stats -->
        <div class="hcrm-stats-grid hcrm-stats-grid-compact">
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number"><?php echo esc_html( $hcrm_total_published ); ?></span>
                <span class="hcrm-stat-label"><?php esc_html_e( 'Total Properties', 'hcrm-houzez' ); ?></span>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number" id="stat-properties-synced"><?php echo esc_html( $hcrm_synced_properties ); ?></span>
                <span class="hcrm-stat-label"><?php esc_html_e( 'Synced to CRM', 'hcrm-houzez' ); ?></span>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-number"><?php echo esc_html( max( 0, $hcrm_total_published - $hcrm_synced_properties ) ); ?></span>
                <span class="hcrm-stat-label"><?php esc_html_e( 'Pending Sync', 'hcrm-houzez' ); ?></span>
            </div>
        </div>

        <!-- Property Sync Toggle -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_properties" id="sync_properties"
                           <?php checked( ! empty( $hcrm_properties_settings['sync_properties'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Enable Property Sync', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync property listings between WordPress and CRM including all details, images, and taxonomy assignments.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-btn" data-entity="properties" data-action="hcrm_sync_all_properties">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync All', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <!-- Sync on Save Toggle -->
        <div class="hcrm-sync-row hcrm-sync-row-sub">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_on_save" id="sync_on_save"
                           <?php checked( ! empty( $hcrm_properties_settings['sync_on_save'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Sync on Save', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Automatically sync properties to CRM when they are saved in WordPress.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Taxonomy Sync Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Taxonomy Sync', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Manage taxonomy synchronization for property types, statuses, labels, and features.', 'hcrm-houzez'); ?>
        </p>

        <?php foreach ( $hcrm_taxonomies as $hcrm_taxonomy => $hcrm_config ) :
            $hcrm_term_count = wp_count_terms( [ 'taxonomy' => $hcrm_taxonomy, 'hide_empty' => false ] );
            // Count only mappings that have the correct taxonomy field set
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
            $hcrm_synced_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s AND taxonomy = %s",
                    'taxonomy',
                    $hcrm_taxonomy
                )
            );
        ?>
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="<?php echo esc_attr( $hcrm_config['setting_key'] ); ?>"
                           id="<?php echo esc_attr( $hcrm_config['setting_key'] ); ?>"
                           <?php checked( ! empty( $hcrm_taxonomy_settings[ $hcrm_config['setting_key'] ] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php echo esc_html( $hcrm_config['label'] ); ?></h4>
                    <p class="hcrm-taxonomy-stats">
                        <?php /* translators: %d: number of terms in WordPress */ ?>
                        <span class="hcrm-count-total"><?php printf( esc_html__( '%d in WordPress', 'hcrm-houzez' ), (int) $hcrm_term_count ); ?></span>
                        <span class="hcrm-separator">|</span>
                        <?php /* translators: %d: number of synced terms */ ?>
                        <span class="hcrm-count-synced"><?php printf( esc_html__( '%d synced', 'hcrm-houzez' ), (int) $hcrm_synced_count ); ?></span>
                    </p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-taxonomy-btn"
                        data-taxonomy="<?php echo esc_attr( $hcrm_taxonomy ); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Sync', 'hcrm-houzez' ); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Save Button -->
    <div class="hcrm-form-section">
        <div class="hcrm-form-actions">
            <button type="button" id="save-properties-settings" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'hcrm-houzez'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>
</div>
