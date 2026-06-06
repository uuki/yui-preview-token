<?php

declare(strict_types=1);

namespace WPT\WordPress;

class AdminScripts
{
    private Settings $settings;
    private string   $plugin_url;

    public function __construct(Settings $settings, string $plugin_url)
    {
        $this->settings   = $settings;
        $this->plugin_url = rtrim($plugin_url, '/');
    }

    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor']);
        add_action('admin_enqueue_scripts',       [$this, 'enqueue_admin']);
        add_action('add_meta_boxes',              [$this, 'register_classic_meta_box']);
    }

    // ── Block editor (Gutenberg) ─────────────────────────────────────────

    public function enqueue_block_editor(): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        wp_enqueue_script(
            'wpt-sidebar',
            $this->asset_url('sidebar'),
            ['wp-edit-post', 'wp-element', 'wp-components', 'wp-plugins', 'wp-data', 'wp-editor'],
            $this->asset_version('sidebar'),
            true
        );
        wp_localize_script('wpt-sidebar', 'wptPreviewData', $this->preview_data());
    }

    // ── Post list (Quick Edit) ───────────────────────────────────────────

    public function enqueue_admin(string $hook): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        if ($hook === 'edit.php') {
            wp_enqueue_script(
                'wpt-quick-edit',
                $this->asset_url('quick-edit'),
                ['wp-element', 'inline-edit-post'],
                $this->asset_version('quick-edit'),
                true
            );
            wp_localize_script('wpt-quick-edit', 'wptPreviewData', $this->preview_data());

        } elseif (in_array($hook, ['post.php', 'post-new.php'], true)) {
            $screen = get_current_screen();
            if (!$screen || $screen->is_block_editor()) {
                return;
            }

            wp_enqueue_script(
                'wpt-classic-editor',
                $this->asset_url('classic-editor'),
                ['wp-element'],
                $this->asset_version('classic-editor'),
                true
            );
            wp_localize_script('wpt-classic-editor', 'wptPreviewData', $this->preview_data());
        }
    }

    // ── Classic Editor meta box ──────────────────────────────────────────

    public function register_classic_meta_box(): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->is_block_editor()) {
            return;
        }

        add_meta_box(
            'wpt-preview',
            __('External Preview', 'preview-token'),
            [$this, 'render_classic_meta_box'],
            null,
            'side',
            'high'
        );
    }

    public function render_classic_meta_box(\WP_Post $post): void
    {
        printf(
            '<div id="wpt-classic-meta-box-root" data-post-id="%d"></div>',
            esc_attr($post->ID)
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function preview_data(): array
    {
        return [
            'tokenBase'     => rest_url(Constants::REST_NAMESPACE . Constants::ROUTE_TOKEN),
            'nonce'         => wp_create_nonce('wp_rest'),
            'allowNoExpiry' => $this->settings->get_allow_no_expiry(),
            'i18n'          => [
                // Preset labels
                'preset1h'        => __('1 hour',    'preview-token'),
                'preset24h'       => __('24 hours',  'preview-token'),
                'preset30d'       => __('30 days',   'preview-token'),
                'presetCustom'    => __('Custom',    'preview-token'),
                'presetNoExpiry'  => __('No expiry', 'preview-token'),
                // Panel UI
                'loading'         => __('Loading…',                  'preview-token'),
                'expiry'          => __('Expiry',                    'preview-token'),
                'update'          => __('Update',                    'preview-token'),
                'cancel'          => __('Cancel',                    'preview-token'),
                'openPreview'     => __('Open external preview',     'preview-token'),
                'copyPreviewUrl'  => __('Copy external preview URL', 'preview-token'),
                'changeExpiry'    => __('Change expiry',             'preview-token'),
                'deleteToken'     => __('Delete',                    'preview-token'),
                'deleteConfirm'   => __('Delete this token?',        'preview-token'),
                'yes'             => __('Yes',                       'preview-token'),
                'generateToken'   => __('Generate token',            'preview-token'),
                'regenerateToken' => __('Regenerate token',          'preview-token'),
                // Status — %s is a placeholder substituted in JS
                /* translators: %s: token expiry date */
                'tokenExpired'    => __('Token expired: %s',          'preview-token'),
                /* translators: 1: expiry date, 2: relative time remaining */
                'expiresRelative' => __('Expires: %1$s (%2$s remaining)', 'preview-token'),
                'lessThan1min'    => __('< 1 min',                    'preview-token'),
                'errorOccurred'   => __('An error occurred',          'preview-token'),
            ],
        ];
    }

    private function asset_url(string $name): string
    {
        return "{$this->plugin_url}/assets/js/{$name}.iife.js";
    }

    private function asset_version(string $name): string
    {
        $path = dirname(dirname(__DIR__)) . "/assets/js/{$name}.iife.js";
        return file_exists($path) ? (string) filemtime($path) : '0';
    }
}
