<?php

declare(strict_types=1);

namespace PVT\Token;

class TokenIssuer
{
    public const OPTIONS_PREFIX  = 'pvt_tk_';
    public const META_HASH       = '_pvt_token_hash';
    public const META_RAW        = '_pvt_token_raw';
    public const META_EXPIRES_AT = '_pvt_expires_at';
    public const META_ISSUED_BY  = '_pvt_issued_by';
    public const META_ISSUED_AT  = '_pvt_issued_at';
    public const HOOK_TOKEN_ISSUED = 'pvt_token_issued';

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

        do_action(self::HOOK_TOKEN_ISSUED, $post_id, $user_id);

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
     * Return all issued tokens, enriched with post meta, sorted newest-first.
     *
     * @return array<int, array{hash:string,post_id:int,user_id:int,expires_at:int,issued_at:int}>
     */
    public function get_all_tokens(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no WP API for prefix-based option scan
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like(self::OPTIONS_PREFIX) . '%'
            )
        );

        $tokens = [];
        foreach ($rows as $row) {
            $data = maybe_unserialize($row->option_value);
            if (!is_array($data)) {
                continue;
            }
            $post_id = (int) ($data['post_id'] ?? 0);
            if (!$post_id) {
                continue;
            }
            $tokens[] = [
                'hash'       => substr((string) $row->option_name, strlen(self::OPTIONS_PREFIX)),
                'post_id'    => $post_id,
                'user_id'    => (int) ($data['user_id'] ?? 0),
                'expires_at' => (int) ($data['expires_at'] ?? 0),
                'issued_at'  => (int) get_post_meta($post_id, self::META_ISSUED_AT, true),
            ];
        }

        usort($tokens, static fn(array $a, array $b): int => $b['issued_at'] - $a['issued_at']);

        return $tokens;
    }

    /**
     * Remove expired pvt_tk_* options. Called by WP Cron daily.
     */
    public function cleanup_expired(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no WP API for prefix-based option scan
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
