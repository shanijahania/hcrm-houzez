<?php
/**
 * Users Settings tab template.
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

// Count agencies (houzez_agency CPT - syncs to CRM agencies)
$hcrm_total_agencies = wp_count_posts('houzez_agency');
$hcrm_total_agencies_published = $hcrm_total_agencies->publish ?? 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
$hcrm_synced_agencies = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = %s",
        'agency'
    )
);

// Count WordPress users with syncable roles (syncs to CRM users)
$hcrm_syncable_wp_roles = ['houzez_agent', 'houzez_agency', 'houzez_manager', 'administrator'];
$hcrm_wp_users_with_syncable_roles = get_users([
    'role__in' => $hcrm_syncable_wp_roles,
    'fields'   => 'ID',
]);
$hcrm_total_wp_users = count($hcrm_wp_users_with_syncable_roles);

// Count only synced users that still have syncable roles
$hcrm_synced_wp_users = 0;
if (!empty($hcrm_wp_users_with_syncable_roles)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query
    $hcrm_synced_wp_users = $wpdb->get_var($wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause with safe %d placeholders
        "SELECT COUNT(*) FROM {$wpdb->prefix}hcrm_entity_map WHERE entity_type = 'wp_user' AND wp_id IN (" . implode(',', array_fill(0, count($hcrm_wp_users_with_syncable_roles), '%d')) . ")",
        $hcrm_wp_users_with_syncable_roles
    ));
}

// Get role mapping
$hcrm_role_mapping = $hcrm_users_settings['role_mapping'] ?? [];

// Define available WP roles and CRM roles
$hcrm_wp_roles_to_sync = [
    'houzez_agent'   => __('Agent', 'hcrm-houzez'),
    'houzez_agency'  => __('Agency', 'hcrm-houzez'),
    'houzez_manager' => __('Manager', 'hcrm-houzez'),
    'administrator'  => __('Administrator', 'hcrm-houzez'),
];

$hcrm_crm_roles = [
    'agent'   => __('Agent', 'hcrm-houzez'),
    'agency'  => __('Agency', 'hcrm-houzez'),
    'manager' => __('Manager', 'hcrm-houzez'),
    'admin'   => __('Admin', 'hcrm-houzez'),
];
?>

<div class="hcrm-settings-form">
    <!-- Users Stats Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Users & Agencies', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Sync WordPress users to CRM users and agency profiles to CRM agencies.', 'hcrm-houzez'); ?>
        </p>

        <!-- Stats Grid -->
        <div class="hcrm-stats-grid">
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-icon dashicons dashicons-admin-users"></span>
                <div class="hcrm-stat-content">
                    <span class="hcrm-stat-number"><?php echo esc_html( $hcrm_total_wp_users ); ?></span>
                    <span class="hcrm-stat-label"><?php esc_html_e('WP Users', 'hcrm-houzez'); ?></span>
                    <?php /* translators: %d: number of synced users */ ?>
                    <span class="hcrm-stat-synced"><?php echo esc_html( sprintf( __( '%d synced', 'hcrm-houzez' ), $hcrm_synced_wp_users ?: 0 ) ); ?></span>
                </div>
            </div>
            <div class="hcrm-stat-box">
                <span class="hcrm-stat-icon dashicons dashicons-building"></span>
                <div class="hcrm-stat-content">
                    <span class="hcrm-stat-number"><?php echo esc_html( $hcrm_total_agencies_published ); ?></span>
                    <span class="hcrm-stat-label"><?php esc_html_e('Agencies', 'hcrm-houzez'); ?></span>
                    <?php /* translators: %d: number of synced agencies */ ?>
                    <span class="hcrm-stat-synced"><?php echo esc_html( sprintf( __( '%d synced', 'hcrm-houzez' ), $hcrm_synced_agencies ?: 0 ) ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Settings Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Sync Settings', 'hcrm-houzez'); ?></h2>

        <!-- Enable User Sync -->
        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_users" id="sync_users"
                           <?php checked( ! empty( $hcrm_users_settings['sync_users'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Enable User Sync', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Sync WordPress users and agencies to CRM.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <!-- Sync Avatars -->
        <div class="hcrm-sync-row hcrm-sync-row-sub">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="sync_avatars" id="sync_avatars"
                           <?php checked( ! empty( $hcrm_users_settings['sync_avatars'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Sync Avatars & Logos', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Include profile images for users and logos for agencies.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <!-- Auto Sync on Save -->
        <div class="hcrm-sync-row hcrm-sync-row-sub">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="auto_sync" id="auto_sync_users"
                           <?php checked( ! empty( $hcrm_users_settings['auto_sync'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Auto Sync on Save', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Automatically sync WordPress users (Agent/Agency/Manager/Admin roles) and agency profiles when created or updated.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Mapping Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Role Mapping', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Map WordPress user roles to CRM roles.', 'hcrm-houzez'); ?>
        </p>

        <table class="form-table hcrm-role-mapping-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('WordPress Role', 'hcrm-houzez'); ?></th>
                    <th><?php esc_html_e('CRM Role', 'hcrm-houzez'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $hcrm_wp_roles_to_sync as $hcrm_wp_role => $hcrm_wp_label ) :
                    $hcrm_current_crm_role = $hcrm_role_mapping[ $hcrm_wp_role ] ?? '';
                ?>
                <tr>
                    <td>
                        <label for="role_mapping_<?php echo esc_attr( $hcrm_wp_role ); ?>">
                            <?php echo esc_html( $hcrm_wp_label ); ?>
                        </label>
                    </td>
                    <td>
                        <select name="role_mapping[<?php echo esc_attr( $hcrm_wp_role ); ?>]"
                                id="role_mapping_<?php echo esc_attr( $hcrm_wp_role ); ?>"
                                class="regular-text">
                            <option value=""><?php esc_html_e('-- Select --', 'hcrm-houzez'); ?></option>
                            <?php foreach ( $hcrm_crm_roles as $hcrm_crm_role => $hcrm_crm_label ) : ?>
                            <option value="<?php echo esc_attr( $hcrm_crm_role ); ?>"
                                    <?php selected( $hcrm_current_crm_role, $hcrm_crm_role ); ?>>
                                <?php echo esc_html( $hcrm_crm_label ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Sync Section -->
    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Sync', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Manage user and agency synchronization.', 'hcrm-houzez'); ?>
        </p>

        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <div class="hcrm-sync-info" style="margin-left: 0;">
                    <h4><?php esc_html_e('WordPress Users', 'hcrm-houzez'); ?></h4>
                    <p><?php
                        echo esc_html( sprintf(
                            /* translators: 1: total users, 2: synced users, 3: pending users */
                            __( '%1$d total, %2$d synced, %3$d pending (Agent/Agency/Manager/Admin roles)', 'hcrm-houzez' ),
                            $hcrm_total_wp_users,
                            $hcrm_synced_wp_users ?: 0,
                            max( 0, $hcrm_total_wp_users - ( $hcrm_synced_wp_users ?: 0 ) )
                        ) );
                    ?></p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-users-btn" data-type="wp_users">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync WordPress Users', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>

        <div class="hcrm-sync-row">
            <div class="hcrm-sync-toggle">
                <div class="hcrm-sync-info" style="margin-left: 0;">
                    <h4><?php esc_html_e('Agency Profiles (houzez_agency)', 'hcrm-houzez'); ?></h4>
                    <p><?php
                        echo esc_html( sprintf(
                            /* translators: 1: total agencies, 2: synced agencies, 3: pending agencies */
                            __( '%1$d total, %2$d synced, %3$d pending', 'hcrm-houzez' ),
                            $hcrm_total_agencies_published,
                            $hcrm_synced_agencies ?: 0,
                            max( 0, $hcrm_total_agencies_published - ( $hcrm_synced_agencies ?: 0 ) )
                        ) );
                    ?></p>
                </div>
            </div>
            <div class="hcrm-sync-actions">
                <button type="button" class="button hcrm-sync-users-btn" data-type="agencies">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync Agencies', 'hcrm-houzez'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="hcrm-form-section">
        <div class="hcrm-form-actions">
            <button type="button" id="save-users-settings" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'hcrm-houzez'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>
</div>
