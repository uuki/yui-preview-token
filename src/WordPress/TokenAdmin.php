<?php

declare(strict_types=1);

namespace PVT\WordPress;

use PVT\Token\TokenIssuer;

/**
 * Renders the "Issued Tokens" admin tab and handles token deletion actions.
 * Registered via Plugin::init(); hooks into Settings::render_page() via
 * the pvt_settings_render_tokens_tab action.
 */
class TokenAdmin
{
    /** Threshold above which expires_at is treated as "no expiry" (50 years). */
    private const NO_EXPIRY_THRESHOLD = 50 * 365 * DAY_IN_SECONDS;

    private TokenIssuer $issuer;

    public function __construct(TokenIssuer $issuer)
    {
        $this->issuer = $issuer;
    }

    public function register(): void
    {
        add_action('pvt_settings_render_tokens_tab', [$this, 'render_token_table']);
        add_action('admin_post_pvt_delete_token',    [$this, 'handle_delete_token']);
        add_action('admin_post_pvt_delete_expired',  [$this, 'handle_delete_expired']);
    }

    // ── Action handlers ───────────────────────────────────────────────────────

    public function handle_delete_token(): void
    {
        $post_id = absint(wp_unslash($_GET['post_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified on next line
        check_admin_referer("pvt_delete_token_{$post_id}");

        if ($post_id && current_user_can('manage_options')) {
            $this->issuer->delete_by_post($post_id);
        }

        wp_safe_redirect($this->tab_url(['deleted' => '1']));
        exit;
    }

    public function handle_delete_expired(): void
    {
        check_admin_referer('pvt_delete_expired');

        if (current_user_can('manage_options')) {
            $this->issuer->cleanup_expired();
        }

        wp_safe_redirect($this->tab_url(['cleaned' => '1']));
        exit;
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function render_token_table(): void
    {
        $tokens  = $this->issuer->get_all_tokens();
        $now     = time();
        $tab_url = $this->tab_url();

        if (isset($_GET['deleted'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Token revoked.', 'preview-token')
                . '</p></div>';
        }
        if (isset($_GET['cleaned'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Expired tokens deleted.', 'preview-token')
                . '</p></div>';
        }
        ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <h2 style="margin:0"><?php esc_html_e('Issued Tokens', 'preview-token'); ?></h2>
            <?php if (!empty($tokens)): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pvt_delete_expired'); ?>
                <input type="hidden" name="action" value="pvt_delete_expired">
                <button type="submit" class="button button-small">
                    <?php esc_html_e('Delete Expired', 'preview-token'); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($tokens)): ?>
            <p><?php esc_html_e('No tokens have been issued yet.', 'preview-token'); ?></p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:0">
            <thead>
                <tr>
                    <th style="width:28%"><?php esc_html_e('Post', 'preview-token'); ?></th>
                    <th style="width:10%"><?php esc_html_e('Post Status', 'preview-token'); ?></th>
                    <th style="width:10%"><?php esc_html_e('Token Status', 'preview-token'); ?></th>
                    <th style="width:16%"><?php esc_html_e('Issued At', 'preview-token'); ?></th>
                    <th style="width:16%"><?php esc_html_e('Expires At', 'preview-token'); ?></th>
                    <th style="width:12%"><?php esc_html_e('Issued By', 'preview-token'); ?></th>
                    <th style="width:8%"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $token):
                $post       = get_post($token['post_id']);
                $user       = get_userdata($token['user_id']);
                $is_expired = ($token['expires_at'] > 0 && $token['expires_at'] <= $now);
                $no_expiry  = ($token['expires_at'] > $now + self::NO_EXPIRY_THRESHOLD);
                $delete_url = wp_nonce_url(
                    admin_url("admin-post.php?action=pvt_delete_token&post_id={$token['post_id']}"),
                    "pvt_delete_token_{$token['post_id']}"
                );
            ?>
                <tr>
                    <td>
                        <?php if ($post): ?>
                            <strong>
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID) ?? '#'); ?>">
                                    <?php echo esc_html($post->post_title ?: __('(no title)', 'preview-token')); ?>
                                </a>
                            </strong>
                        <?php else: ?>
                            <em style="color:#888"><?php echo esc_html__('Post deleted', 'preview-token'); ?> (#<?php echo esc_html($token['post_id']); ?>)</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($post): ?>
                            <span style="text-transform:capitalize"><?php echo esc_html($post->post_status); ?></span>
                        <?php else: ?>
                            <span style="color:#888">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($no_expiry): ?>
                            <span style="color:#2271b1;font-weight:600"><?php esc_html_e('No expiry', 'preview-token'); ?></span>
                        <?php elseif ($is_expired): ?>
                            <span style="color:#b32d2e;font-weight:600"><?php esc_html_e('Expired', 'preview-token'); ?></span>
                        <?php else: ?>
                            <span style="color:#1e7c34;font-weight:600"><?php esc_html_e('Active', 'preview-token'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $token['issued_at']
                            ? esc_html(wp_date('Y-m-d H:i', $token['issued_at']))
                            : '<span style="color:#888">—</span>'; ?>
                    </td>
                    <td>
                        <?php if ($no_expiry): ?>
                            <span style="color:#888">—</span>
                        <?php else: ?>
                            <?php echo esc_html(wp_date('Y-m-d H:i', $token['expires_at'])); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $user
                            ? esc_html($user->user_login)
                            : '<span style="color:#888">—</span>'; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($delete_url); ?>"
                           style="color:#b32d2e"
                           onclick="return confirm('<?php esc_attr_e('Revoke this token?', 'preview-token'); ?>')">
                            <?php esc_html_e('Revoke', 'preview-token'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="margin-top:8px">
            <?php
            printf(
                /* translators: %d: number of issued tokens */
                esc_html__('%d token(s) issued in total.', 'preview-token'),
                count($tokens)
            ); ?>
        </p>
        <p class="description" style="margin-top:4px">
            <?php esc_html_e('Expired tokens are rejected immediately upon use. However, the database records are removed once daily by WP Cron, so expired tokens may remain visible in this list until the next scheduled cleanup. To remove them right away, use the "Delete Expired" button above.', 'preview-token'); ?>
        </p>
        <?php endif;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tab_url(array $extra = []): string
    {
        return add_query_arg(
            array_merge(['page' => 'preview-token', 'tab' => 'tokens'], $extra),
            admin_url('options-general.php')
        );
    }
}
