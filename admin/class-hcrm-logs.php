<?php
/**
 * Logs admin page for HCRM.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Logs
 *
 * Handles the logs admin page.
 *
 * @since 1.0.0
 */
class HCRM_Logs {

    /**
     * Render the logs page.
     */
    public static function render_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle download action
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in the same conditional
        if ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'download' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'hcrm_download_logs' ) ) {
            HCRM_Logger::download_logs();
        }

        $log_size = HCRM_Logger::get_formatted_log_size();
        $logs = HCRM_Logger::get_logs(500); // Last 500 lines
        ?>
        <div class="wrap hcrm-logs-page">
            <h1><?php esc_html_e('Sync Logs', 'hcrm-houzez'); ?></h1>

            <div class="hcrm-logs-header">
                <div class="hcrm-logs-info">
                    <span class="hcrm-log-size">
                        <?php
                        echo esc_html( sprintf(
                            /* translators: %s: file size */
                            __( 'Log file size: %s', 'hcrm-houzez' ),
                            $log_size
                        ) );
                        ?>
                    </span>
                </div>
                <div class="hcrm-logs-actions">
                    <button type="button" class="button" id="hcrm-refresh-logs">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'hcrm-houzez'); ?>
                    </button>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hcrm-logs&action=download'), 'hcrm_download_logs')); ?>" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download', 'hcrm-houzez'); ?>
                    </a>
                    <button type="button" class="button button-secondary" id="hcrm-clear-logs">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear Logs', 'hcrm-houzez'); ?>
                    </button>
                </div>
            </div>

            <div class="hcrm-logs-filters">
                <label>
                    <input type="checkbox" id="hcrm-filter-errors" checked>
                    <?php esc_html_e('Errors', 'hcrm-houzez'); ?>
                </label>
                <label>
                    <input type="checkbox" id="hcrm-filter-warnings" checked>
                    <?php esc_html_e('Warnings', 'hcrm-houzez'); ?>
                </label>
                <label>
                    <input type="checkbox" id="hcrm-filter-info" checked>
                    <?php esc_html_e('Info', 'hcrm-houzez'); ?>
                </label>
                <label>
                    <input type="checkbox" id="hcrm-filter-debug">
                    <?php esc_html_e('Debug', 'hcrm-houzez'); ?>
                </label>
                <input type="text" id="hcrm-logs-search" placeholder="<?php esc_attr_e('Search logs...', 'hcrm-houzez'); ?>">
            </div>

            <div class="hcrm-logs-container">
                <pre id="hcrm-logs-content"><?php echo esc_html($logs); ?></pre>
            </div>

            <?php if (empty(trim($logs))): ?>
            <div class="hcrm-logs-empty">
                <span class="dashicons dashicons-info-outline"></span>
                <p><?php esc_html_e('No sync logs available yet. Logs will appear here when you run a sync.', 'hcrm-houzez'); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <style>
            .hcrm-logs-page {
                max-width: 1400px;
            }
            .hcrm-logs-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .hcrm-logs-info {
                color: #666;
            }
            .hcrm-logs-actions {
                display: flex;
                gap: 10px;
            }
            .hcrm-logs-actions .button {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .hcrm-logs-actions .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                line-height: 1;
            }
            .hcrm-logs-filters {
                display: flex;
                gap: 20px;
                align-items: center;
                margin-bottom: 15px;
                padding: 10px 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .hcrm-logs-filters label {
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
            }
            #hcrm-logs-search {
                margin-left: auto;
                min-width: 250px;
            }
            .hcrm-logs-container {
                background: #1e1e1e;
                border-radius: 4px;
                max-height: 600px;
                overflow: auto;
            }
            #hcrm-logs-content {
                margin: 0;
                padding: 15px;
                font-family: 'Monaco', 'Consolas', 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.6;
                color: #d4d4d4;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .hcrm-logs-empty {
                text-align: center;
                padding: 40px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-top: 20px;
            }
            .hcrm-logs-empty .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #999;
            }
            .hcrm-logs-empty p {
                color: #666;
                margin-top: 10px;
            }
            /* Log level colors */
            .log-line-error { color: #f44336; }
            .log-line-warning { color: #ff9800; }
            .log-line-info { color: #4caf50; }
            .log-line-debug { color: #9e9e9e; }
            .log-line-hidden { display: none; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Refresh logs
            $('#hcrm-refresh-logs').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');

                $.post(ajaxurl, {
                    action: 'hcrm_get_logs',
                    nonce: '<?php echo esc_attr( wp_create_nonce( 'hcrm_logs_nonce' ) ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#hcrm-logs-content').text(response.data.logs);
                        $('.hcrm-log-size').text('<?php esc_html_e('Log file size:', 'hcrm-houzez'); ?> ' + response.data.size);
                        applyFilters();
                    }
                    $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                });
            });

            // Clear logs
            $('#hcrm-clear-logs').on('click', function() {
                if (!confirm('<?php esc_html_e('Are you sure you want to clear all logs?', 'hcrm-houzez'); ?>')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'hcrm_clear_logs',
                    nonce: '<?php echo esc_attr( wp_create_nonce( 'hcrm_logs_nonce' ) ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#hcrm-logs-content').text('');
                        $('.hcrm-log-size').text('<?php esc_html_e('Log file size:', 'hcrm-houzez'); ?> 0 B');
                        $('.hcrm-logs-empty').show();
                    }
                    $btn.prop('disabled', false);
                });
            });

            // Filter and search
            function applyFilters() {
                var content = $('#hcrm-logs-content').text();
                var lines = content.split('\n');
                var showErrors = $('#hcrm-filter-errors').is(':checked');
                var showWarnings = $('#hcrm-filter-warnings').is(':checked');
                var showInfo = $('#hcrm-filter-info').is(':checked');
                var showDebug = $('#hcrm-filter-debug').is(':checked');
                var search = $('#hcrm-logs-search').val().toLowerCase();

                var html = lines.map(function(line) {
                    if (!line.trim()) return '';

                    var isError = line.indexOf('[ERROR]') !== -1;
                    var isWarning = line.indexOf('[WARNING]') !== -1;
                    var isDebug = line.indexOf('[DEBUG]') !== -1;
                    var isInfo = line.indexOf('[INFO]') !== -1;

                    var show = (isError && showErrors) ||
                               (isWarning && showWarnings) ||
                               (isInfo && showInfo) ||
                               (isDebug && showDebug) ||
                               (!isError && !isWarning && !isInfo && !isDebug && showInfo);

                    if (search && line.toLowerCase().indexOf(search) === -1) {
                        show = false;
                    }

                    var className = 'log-line';
                    if (isError) className += ' log-line-error';
                    else if (isWarning) className += ' log-line-warning';
                    else if (isDebug) className += ' log-line-debug';
                    else className += ' log-line-info';

                    if (!show) className += ' log-line-hidden';

                    return '<span class="' + className + '">' + escapeHtml(line) + '</span>';
                }).join('\n');

                $('#hcrm-logs-content').html(html);
            }

            function escapeHtml(text) {
                return text
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            $('.hcrm-logs-filters input').on('change keyup', function() {
                applyFilters();
            });

            // Initial filter application
            applyFilters();

            // Scroll to bottom
            var container = $('.hcrm-logs-container');
            container.scrollTop(container[0].scrollHeight);

            // Spin animation
            $('<style>.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');
        });
        </script>
        <?php
    }
}
