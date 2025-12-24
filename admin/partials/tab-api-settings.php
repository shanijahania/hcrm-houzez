<?php
/**
 * API Settings tab template.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<form id="hcrm-api-settings-form" class="hcrm-settings-form">
    <?php wp_nonce_field('hcrm_admin_nonce', 'hcrm_nonce'); ?>

    <div class="hcrm-form-section">
        <h2><?php esc_html_e('CRM Connection', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Configure the connection to your Laravel CRM API.', 'hcrm-houzez'); ?>
        </p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_base_url"><?php esc_html_e('API Base URL', 'hcrm-houzez'); ?></label>
                </th>
                <td>
                    <input
                        type="url"
                        id="api_base_url"
                        name="api_base_url"
                        class="regular-text"
                        placeholder="https://your-crm.com/api/v1"
                        value="<?php echo esc_attr( $hcrm_api_settings['api_base_url'] ?? '' ); ?>"
                    >
                    <p class="description">
                        <?php esc_html_e('Enter the base URL of your Laravel CRM API (e.g., https://crm.example.com/api/v1)', 'hcrm-houzez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="api_token"><?php esc_html_e('API Token', 'hcrm-houzez'); ?></label>
                </th>
                <td>
                    <div class="hcrm-password-field">
                        <input
                            type="password"
                            id="api_token"
                            name="api_token"
                            class="regular-text"
                            placeholder="<?php echo esc_attr( ! empty( $hcrm_api_settings['api_token'] ) ? '********' : '' ); ?>"
                            value=""
                            autocomplete="new-password"
                        >
                        <button type="button" class="button hcrm-toggle-password" title="<?php esc_attr_e('Toggle visibility', 'hcrm-houzez'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Your Laravel Sanctum API token. Leave blank to keep existing token.', 'hcrm-houzez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Webhook URL', 'hcrm-houzez'); ?></label>
                </th>
                <td>
                    <div class="hcrm-readonly-field">
                        <input
                            type="text"
                            id="webhook-url"
                            readonly
                            class="regular-text code"
                            value="<?php echo esc_url( $hcrm_webhook_url ); ?>"
                        >
                        <button type="button" class="button hcrm-copy-url" title="<?php esc_attr_e('Copy to clipboard', 'hcrm-houzez'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Configure this URL in your CRM webhook settings to receive updates.', 'hcrm-houzez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="webhook_secret"><?php esc_html_e('Webhook Secret', 'hcrm-houzez'); ?></label>
                </th>
                <td>
                    <div class="hcrm-password-field">
                        <input
                            type="password"
                            id="webhook_secret"
                            name="webhook_secret"
                            class="regular-text code"
                            placeholder="<?php echo esc_attr( !empty(HCRM_Webhook_Handler::get_webhook_secret()) ? '********' : '' ); ?>"
                            value=""
                            autocomplete="new-password"
                        >
                        <button type="button" class="button hcrm-toggle-password" title="<?php esc_attr_e('Toggle visibility', 'hcrm-houzez'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Enter the webhook secret provided by your CRM. Leave blank to keep existing secret.', 'hcrm-houzez'); ?>
                    </p>
                </td>
            </tr>
        </table>

    </div>

    <div class="hcrm-form-section">
        <h2><?php esc_html_e('Auto Sync', 'hcrm-houzez'); ?></h2>
        <p class="hcrm-section-desc">
            <?php esc_html_e('Enable automatic synchronization when data changes.', 'hcrm-houzez'); ?>
        </p>

        <div class="hcrm-sync-row hcrm-sync-row-auto">
            <div class="hcrm-sync-toggle">
                <label class="hcrm-switch">
                    <input type="checkbox" name="auto_sync" id="auto_sync"
                           <?php checked( ! empty( $hcrm_api_settings['auto_sync'] ) ); ?>>
                    <span class="hcrm-slider"></span>
                </label>
                <div class="hcrm-sync-info">
                    <h4><?php esc_html_e('Enable Auto Sync', 'hcrm-houzez'); ?></h4>
                    <p><?php esc_html_e('Automatically sync properties, agents, and agencies when they are created or updated in WordPress.', 'hcrm-houzez'); ?></p>
                </div>
            </div>
        </div>

        <div class="hcrm-form-actions">
            <button type="button" id="test-connection" class="button button-secondary">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Test Connection', 'hcrm-houzez'); ?>
            </button>
            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'hcrm-houzez'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>

    <!-- Connection Test Result -->
    <div id="connection-result" class="hcrm-notice" style="display: none;"></div>
</form>
