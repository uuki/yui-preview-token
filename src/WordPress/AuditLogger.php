<?php

declare(strict_types=1);

namespace WPT\WordPress;

/**
 * Writes audit entries for token lifecycle events.
 *
 * Default output: PHP's system error log (same destination as WP_DEBUG_LOG).
 * Each line is prefixed with [wpt] to distinguish WPT entries from other PHP errors.
 *
 * Custom output: define WPT_LOG_FILE in wp-config.php to write to a dedicated file.
 *   define('WPT_LOG_FILE', '/var/log/wp-preview-token.log');
 *
 * Logged events: token issued, token used.
 */
class AuditLogger
{
    public function register(): void
    {
        add_action('wpt_token_issued', [$this, 'on_token_issued'], 10, 2);
        add_action('wpt_token_used',   [$this, 'on_token_used'],   10, 2);
    }

    public function on_token_issued(int $post_id, int $user_id): void
    {
        $this->write('issued', $post_id, $user_id);
    }

    public function on_token_used(int $post_id, int $user_id): void
    {
        $this->write('used', $post_id, $user_id);
    }

    private function write(string $event, int $post_id, int $user_id): void
    {
        $message = sprintf(
            '[wpt] event=%s post_id=%d user_id=%d ip=%s',
            $event,
            $post_id,
            $user_id,
            $this->get_ip()
        );

        if (defined('WPT_LOG_FILE') && is_string(WPT_LOG_FILE) && WPT_LOG_FILE !== '') {
            // Write to the application-specific file. Prepend a timestamp since
            // error_log type 3 does not add one automatically.
            $line = sprintf("[%s] %s\n", gmdate('Y-m-d\TH:i:s\Z'), $message);
            $this->write_to_file(WPT_LOG_FILE, $line);
        } else {
            // Delegate to PHP's system error log. PHP prepends its own timestamp,
            // and WordPress routes this to WP_DEBUG_LOG when it is configured.
            error_log($message);
        }
    }

    private function write_to_file(string $path, string $line): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        // Silently discard if the file is not writable.
        @error_log($line, 3, $path);
    }

    private function get_ip(): string
    {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
