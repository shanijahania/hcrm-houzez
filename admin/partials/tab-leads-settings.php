<?php
/**
 * Leads Settings tab template.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get hooks enabled settings
$hcrm_hooks_enabled = $hcrm_leads_settings['hooks_enabled'] ?? [];

// Define available form hooks
$hcrm_form_hooks = [
    'houzez_ele_inquiry_form' => [
        'label' => __('Elementor Inquiry Form', 'hcrm-houzez'),
        'description' => __('Property inquiry forms built with Elementor.', 'hcrm-houzez'),
    ],
    'houzez_ele_contact_form' => [
        'label' => __('Elementor Contact Form', 'hcrm-houzez'),
        'description' => __('General contact forms built with Elementor.', 'hcrm-houzez'),
    ],
    'houzez_contact_realtor' => [
        'label' => __('Agent/Agency Detail Form', 'hcrm-houzez'),
        'description' => __('Contact forms on agent and agency profile pages.', 'hcrm-houzez'),
    ],
    'houzez_schedule_send_message' => [
        'label' => __('Schedule Tour Form', 'hcrm-houzez'),
        'description' => __('Property viewing/tour scheduling forms.', 'hcrm-houzez'),
    ],
    'houzez_property_agent_contact' => [
        'label' => __('Property Detail Contact Form', 'hcrm-houzez'),
        'description' => __('Contact forms on property detail pages.', 'hcrm-houzez'),
    ],
];

// Get lead counts (if table exists)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
$hcrm_synced_leads = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
        'lead'
    )
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
$hcrm_synced_contacts = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
        'contact'
    )
);
?>

<div class="hcrm-settings-form">
    <!-- Lead Sync Overview Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Lead Sync', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Capture leads from Houzez forms and sync them to your CRM.', 'hcrm-houzez'); ?>
        </p>

        <!-- Stats Grid -->
        <div class="hcrm-stats-grid">
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-icon dashicons dashicons-megaphone"></span>
                <div class="hcrm-stat-content">
                    <span class="hcrm-stat-number" id="stat-leads-synced"><?php echo esc_html( $hcrm_synced_leads ?: 0 ); ?></span>
                    <span class="hcrm-stat-label"><?php esc_html_e('Leads Synced', 'hcrm-houzez'); ?></span>
                </div>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-icon dashicons dashicons-id"></span>
                <div class="hcrm-stat-content">
                    <span class="hcrm-stat-number" id="stat-contacts-created"><?php echo esc_html( $hcrm_synced_contacts ?: 0 ); ?></span>
                    <span class="hcrm-stat-label"><?php esc_html_e('Contacts Created', 'hcrm-houzez'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Sync Settings Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Sync Settings', 'hcrm-houzez'); ?></h2>

        <!-- Enable Lead Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_leads" id="sync_leads"
                           <?php checked( ! empty( $hcrm_leads_settings['sync_leads'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Enable Lead Sync', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Capture form submissions and sync them to CRM as leads.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <!-- Background Queue -->
        <div class="hcrm-sync-row hcrm-sync-row-sub">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="use_background_queue" id="use_background_queue"
                           <?php checked( ! empty( $hcrm_leads_settings['use_background_queue'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Background Queue', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Process leads in the background using Action Scheduler. Recommended for better form performance.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Hooks Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Form Hooks', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Select which Houzez form submissions to capture as leads.', 'hcrm-houzez'); ?>
        </p>

        <?php foreach ( $hcrm_form_hooks as $hcrm_hook => $hcrm_config ) :
            $hcrm_is_enabled = ! empty( $hcrm_hooks_enabled[ $hcrm_hook ] );
        ?>
        <div class="hcrm-sync-row hcrm-hook-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox"
                           name="hooks_enabled[<?php echo esc_attr( $hcrm_hook ); ?>]"
                           id="hook_<?php echo esc_attr( $hcrm_hook ); ?>"
                           value="1"
                           <?php checked( $hcrm_is_enabled ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php echo esc_html( $hcrm_config['label'] ); ?></h4>
                    <p><?php echo esc_html( $hcrm_config['description'] ); ?></p>
                    <code class="hcrm-hook-name"><?php echo esc_html( $hcrm_hook ); ?></code>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Contact Types Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Contact Types', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Leads will be created with contacts. The following contact types will be created in CRM if they don\'t exist.', 'hcrm-houzez'); ?>
        </p>

        <div class="hcrm-contact-types">
            <div class="hcrm-contact-type-badge">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e('Buyer', 'hcrm-houzez'); ?>
            </div>
            <div class="hcrm-contact-type-badge">
                <span class="dashicons dashicons-admin-home"></span>
                <?php esc_html_e('Seller', 'hcrm-houzez'); ?>
            </div>
            <div class="hcrm-contact-type-badge">
                <span class="dashicons dashicons-businessman"></span>
                <?php esc_html_e('Owner', 'hcrm-houzez'); ?>
            </div>
        </div>

        <p class="hcrm-info-text">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('Property-related leads will automatically be linked to the corresponding property in CRM.', 'hcrm-houzez'); ?>
        </p>
    </div>

    <!-- Save Button -->
    <div class="hcrm-form-section">
        <div class="hcrm-form-actions">
            <button type="button" id="save-leads-settings" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'hcrm-houzez'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>
</div>
