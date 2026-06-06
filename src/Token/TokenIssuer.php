<?php

declare(strict_types=1);

namespace WPT\Token;

class TokenIssuer
{
    public const OPTIONS_PREFIX  = 'wpt_tk_';
    public const META_HASH       = '_wpt_token_hash';
    public const META_RAW        = '_wpt_token_raw';
    public const META_EXPIRES_AT = '_wpt_expires_at';
    public const META_ISSUED_BY  = '_wpt_issued_by';
    public const META_ISSUED_AT  = '_wpt_issued_at';

    /**
     * Issue a new token, overwriting any existing one for the post.
     * expires_at must be a Unix timestamp (use time() + 100*YEAR_IN_SECONDS for "no expiry").
     */
    public function issue(int $post_id, int $user_id, int $expires_at): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        $this->delete_by_post($post_id);

        // Store for O(1) validation lookup
        update_option(self::OPTIONS_PREFIX . $hash, [
            'post_id'    => $post_id,
            'user_id'    => $user_id,
            'expires_at' => $expires_at,
        ], false);

        // Store per-post data for sidebar display
        update_post_meta($post_id, self::META_HASH,       $hash);
        update_post_meta($post_id, self::META_RAW,        $token);
        update_post_meta($post_id, self::META_EXPIRES_AT, $expires_at);
        update_post_meta($post_id, self::META_ISSUED_BY,  $user_id);
        update_post_meta($post_id, self::META_ISSUED_AT,  time());

        do_action('wpt_token_issued', $post_id, $user_id);

        return $token;
    }

    /**
     * Update the expiry of the current token without regenerating it.
     */
    public function update_expiry(int $post_id, int $new_expires_at): bool
    {
        $hash = get_post_meta($post_id, self::META_HASH, true);
        if (!$hash) return false;

        $data = get_option(self::OPTIONS_PREFIX . $hash);
        if (!is_array($data)) return false;

        $data['expires_at'] = $new_expires_at;
        update_option(self::OPTIONS_PREFIX . $hash, $data, false);
        update_post_meta($post_id, self::META_EXPIRES_AT, $new_expires_at);

        return true;
    }

    public function delete_by_post(int $post_id): void
    {
        $hash = get_post_meta($post_id, self::META_HASH, true);
        if ($hash) {
            delete_option(self::OPTIONS_PREFIX . $hash);
        }
        delete_post_meta($post_id, self::META_HASH);
        delete_post_meta($post_id, self::META_RAW);
        delete_post_meta($post_id, self::META_EXPIRES_AT);
        delete_post_meta($post_id, self::META_ISSUED_BY);
        delete_post_meta($post_id, self::META_ISSUED_AT);
    }

    /**
     * @return array{raw: string, hash: string, expires_at: int, issued_by: int, issued_at: int}|null
     */
    public function get_by_post(int $post_id): ?array
    {
        $hash = get_post_meta($post_id, self::META_HASH, true);
        if (!$hash) return null;

        return [
            'raw'        => (string) get_post_meta($post_id, self::META_RAW,        true),
            'hash'       => $hash,
            'expires_at' => (int)    get_post_meta($post_id, self::META_EXPIRES_AT, true),
            'issued_by'  => (int)    get_post_meta($post_id, self::META_ISSUED_BY,  true),
            'issued_at'  => (int)    get_post_meta($post_id, self::META_ISSUED_AT,  true),
        ];
    }

    /**
     * Remove expired wpt_tk_* options. Called by WP Cron daily.
     */
    public function cleanup_expired(): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::OPTIONS_PREFIX) . '%'
            )
        );

        $now = time();
        foreach ($rows as $row) {
            $data = maybe_unserialize($row->option_value);
            if (is_array($data) && isset($data['expires_at']) && $now > $data['expires_at']) {
                delete_option($row->option_name);
            }
        }
    }
}
