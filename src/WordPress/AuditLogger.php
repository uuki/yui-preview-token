<?php

declare(strict_types=1);

namespace WPT\WordPress;

class AuditLogger
{
    private string $log_dir;

    public function __construct()
    {
        // Default: wp-content/wpt-logs/. Override with WPT_LOG_DIR in wp-config.php.
        $this->log_dir = defined('WPT_LOG_DIR')
            ? rtrim((string) WPT_LOG_DIR, '/\\')
            : rtrim(WP_CONTENT_DIR, '/\\') . '/wpt-logs';
    }

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
        if (!$this->ensure_log_dir()) {
            return;
        }

        $line = sprintf(
            "[%s] event=%s post_id=%d user_id=%d ip=%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $event,
            $post_id,
            $user_id,
            $this->get_ip()
        );

        error_log($line, 3, $this->log_dir . '/' . gmdate('Y-m-d') . '.log');
    }

    private function ensure_log_dir(): bool
    {
        if (is_dir($this->log_dir)) {
            return true;
        }

        if (!wp_mkdir_p($this->log_dir)) {
            return false;
        }

        // Block direct web access (Apache). Nginx requires server-level config.
        file_put_contents($this->log_dir . '/.htaccess', "deny from all\n");
        file_put_contents($this->log_dir . '/index.php', "<?php // silence\n");

        return true;
    }

    private function get_ip(): string
    {
        return sanitize_text_field(
            wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
    }
}
