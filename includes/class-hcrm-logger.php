<?php
/**
 * Logger class for HCRM sync operations.
 *
 * @package HCRM_Houzez
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HCRM_Logger
 *
 * Handles logging for sync operations to a custom log file.
 *
 * @since 1.0.0
 */
class HCRM_Logger {

    /**
     * Log file path.
     *
     * @var string
     */
    private static $log_file;

    /**
     * Maximum log file size in bytes (5MB).
     *
     * @var int
     */
    const MAX_FILE_SIZE = 5242880;

    /**
     * Get the log file path.
     *
     * @return string Log file path.
     */
    public static function get_log_file() {
        if (self::$log_file === null) {
            $log_dir = HCRM_PLUGIN_PATH . 'logs';

            // Create logs directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }

            // Add .htaccess to protect logs directory
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating security file
                file_put_contents($htaccess, 'Deny from all');
            }

            // Add index.php to prevent directory listing
            $index = $log_dir . '/index.php';
            if (!file_exists($index)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating security file
                file_put_contents($index, '<?php // Silence is golden');
            }

            self::$log_file = $log_dir . '/sync.log';
        }

        return self::$log_file;
    }

    /**
     * Log a message.
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, warning, error, debug).
     * @param array  $context Additional context data.
     */
    public static function log($message, $level = 'info', $context = []) {
        $log_file = self::get_log_file();

        // Rotate log if too large
        self::maybe_rotate_log();

        // Format timestamp
        $timestamp = current_time('Y-m-d H:i:s');

        // Format level
        $level = strtoupper($level);

        // Format context if provided
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' ' . wp_json_encode($context);
        }

        // Build log entry
        $log_entry = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $context_str);

        // Write to file
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Logging to custom log file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log an info message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context data.
     */
    public static function info($message, $context = []) {
        self::log($message, 'info', $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context data.
     */
    public static function warning($message, $context = []) {
        self::log($message, 'warning', $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context data.
     */
    public static function error($message, $context = []) {
        self::log($message, 'error', $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context data.
     */
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, 'debug', $context);
        }
    }

    /**
     * Log sync start.
     *
     * @param string $type    Sync type.
     * @param int    $total   Total items.
     * @param string $sync_id Sync ID.
     */
    public static function sync_start($type, $total, $sync_id) {
        self::info("===== SYNC STARTED =====");
        self::info("Type: {$type}, Total items: {$total}, Sync ID: {$sync_id}");
    }

    /**
     * Log sync progress.
     *
     * @param string $sync_id   Sync ID.
     * @param int    $processed Items processed.
     * @param int    $total     Total items.
     * @param string $item      Current item name.
     */
    public static function sync_progress($sync_id, $processed, $total, $item = '') {
        $percentage = $total > 0 ? round(($processed / $total) * 100) : 0;
        $item_info = $item ? " - {$item}" : '';
        self::info("Progress: {$processed}/{$total} ({$percentage}%){$item_info}");
    }

    /**
     * Log sync completion.
     *
     * @param string $sync_id Sync ID.
     * @param int    $success Successful items.
     * @param int    $failed  Failed items.
     * @param int    $elapsed Elapsed time in seconds.
     */
    public static function sync_complete($sync_id, $success, $failed, $elapsed = 0) {
        $time_str = $elapsed > 0 ? " in {$elapsed}s" : '';
        self::info("===== SYNC COMPLETED{$time_str} =====");
        self::info("Results: {$success} succeeded, {$failed} failed");
    }

    /**
     * Log sync item.
     *
     * @param int    $item_id Item ID.
     * @param string $title   Item title.
     * @param bool   $success Whether sync was successful.
     * @param string $message Optional message.
     */
    public static function sync_item($item_id, $title, $success, $message = '') {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $msg = $message ? ": {$message}" : '';
        self::log("Item #{$item_id} ({$title}) - {$status}{$msg}", $success ? 'info' : 'error');
    }

    /**
     * Log API call.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint API endpoint.
     * @param int    $status   Response status code.
     */
    public static function api_call($method, $endpoint, $status) {
        $level = $status >= 400 ? 'error' : 'info';
        self::log("API {$method} {$endpoint} - Status: {$status}", $level);
    }

    /**
     * Get log contents.
     *
     * @param int $lines Number of lines to return (0 for all).
     * @return string Log contents.
     */
    public static function get_logs($lines = 0) {
        $log_file = self::get_log_file();

        if (!file_exists($log_file)) {
            return '';
        }

        if ($lines <= 0) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading custom log file
            return file_get_contents($log_file);
        }

        // Read last N lines
        $file = new SplFileObject($log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();

        $start = max(0, $total_lines - $lines);
        $output = [];

        $file->seek($start);
        while (!$file->eof()) {
            $line = $file->fgets();
            if (trim($line) !== '') {
                $output[] = $line;
            }
        }

        return implode('', $output);
    }

    /**
     * Clear the log file.
     *
     * @return bool Success.
     */
    public static function clear_logs() {
        $log_file = self::get_log_file();

        if (file_exists($log_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Clearing custom log file
            return file_put_contents($log_file, '') !== false;
        }

        return true;
    }

    /**
     * Get log file size.
     *
     * @return int File size in bytes.
     */
    public static function get_log_size() {
        $log_file = self::get_log_file();

        if (!file_exists($log_file)) {
            return 0;
        }

        return filesize($log_file);
    }

    /**
     * Get formatted log file size.
     *
     * @return string Formatted file size.
     */
    public static function get_formatted_log_size() {
        $size = self::get_log_size();

        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / 1048576, 2) . ' MB';
        }
    }

    /**
     * Rotate log file if too large.
     */
    private static function maybe_rotate_log() {
        $log_file = self::get_log_file();

        if (!file_exists($log_file)) {
            return;
        }

        if (filesize($log_file) > self::MAX_FILE_SIZE) {
            $backup = $log_file . '.' . gmdate('Y-m-d-His') . '.bak';
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Renaming log file for rotation
            rename($log_file, $backup);

            // Keep only last 3 backup files
            $log_dir = dirname($log_file);
            $backups = glob($log_dir . '/sync.log.*.bak');
            if (count($backups) > 3) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                $to_delete = array_slice($backups, 0, count($backups) - 3);
                foreach ($to_delete as $file) {
                    wp_delete_file( $file );
                }
            }
        }
    }

    /**
     * Download log file.
     */
    public static function download_logs() {
        $log_file = self::get_log_file();

        if (!file_exists($log_file)) {
            wp_die( esc_html__( 'No logs available', 'hcrm-houzez' ) );
        }

        $filename = 'hcrm-sync-' . gmdate('Y-m-d-His') . '.log';

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($log_file));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming file download
        readfile($log_file);
        exit;
    }
}
