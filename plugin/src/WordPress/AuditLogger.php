<?php

declare(strict_types=1);

namespace PVT\WordPress;

use PVT\Token\TokenIssuer;

/**
 * Writes audit entries for token lifecycle events.
 *
 * Default output: PHP's system error log (same destination as WP_DEBUG_LOG).
 * Each line is prefixed with LOG_PREFIX to distinguish plugin entries from other PHP errors.
 *
 * Custom output: define the constant named by Constants::DEFINE_LOG_FILE in wp-config.php.
 *   define('PVT_LOG_FILE', '/var/log/pvt.log');
 *
 * Logged events:
 *   - token issued / used                  (lifecycle)
 *   - invalid_token / rate_limit_exceeded  (security: brute-force / abuse detection)
 *   - capability_denied                    (security: privilege escalation attempts)
 */
class AuditLogger
{
    public function register(): void
    {
        // Token lifecycle
        add_action(TokenIssuer::HOOK_TOKEN_ISSUED,      [$this, 'on_token_issued'],        10, 2);
        add_action(Constants::HOOK_TOKEN_USED,          [$this, 'on_token_used'],          10, 2);
        // Security events
        add_action(Constants::HOOK_INVALID_TOKEN,       [$this, 'on_invalid_token'],       10, 1);
        add_action(Constants::HOOK_RATE_LIMIT_EXCEEDED, [$this, 'on_rate_limit_exceeded'], 10, 2);
        add_action(Constants::HOOK_CAPABILITY_DENIED,   [$this, 'on_capability_denied'],   10, 2);
    }

    public function on_token_issued(int $post_id, int $user_id): void
    {
        $this->write('issued', $post_id, $user_id);
    }

    public function on_token_used(int $post_id, int $user_id): void
    {
        $this->write('used', $post_id, $user_id);
    }

    public function on_invalid_token(string $ip): void
    {
        $this->write_security('invalid_token', "ip={$ip}");
    }

    public function on_rate_limit_exceeded(string $ip, string $endpoint): void
    {
        $this->write_security('rate_limit_exceeded', "ip={$ip} endpoint={$endpoint}");
    }

    public function on_capability_denied(int $user_id, int $post_id): void
    {
        $this->write_security('capability_denied', "user_id={$user_id} post_id={$post_id} ip={$this->get_ip()}");
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function write(string $event, int $post_id, int $user_id): void
    {
        $message = sprintf(
            Constants::LOG_PREFIX . ' event=%s post_id=%d user_id=%d ip=%s',
            $event,
            $post_id,
            $user_id,
            $this->get_ip()
        );
        $this->output($message);
    }

    private function write_security(string $event, string $context): void
    {
        $this->output(sprintf(Constants::LOG_PREFIX . ' event=%s %s', $event, $context));
    }

    private function output(string $message): void
    {
        $log_file = defined(Constants::DEFINE_LOG_FILE) ? constant(Constants::DEFINE_LOG_FILE) : null;
        if (is_string($log_file) && $log_file !== '') {
            $line = sprintf("[%s] %s\n", gmdate('Y-m-d\TH:i:s\Z'), $message);
            $this->write_to_file($log_file, $line);
        } else {
            error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional audit logger
        }
    }

    private function write_to_file(string $path, string $line): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        @error_log($line, 3, $path); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional audit logger
    }

    private function get_ip(): string
    {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
