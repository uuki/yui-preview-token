<?php

declare(strict_types=1);

namespace WPT\WordPress;

class Settings
{

    /**
     * WordPress role slugs displayed in the settings UI.
     *
     * @var array<string, string>  role slug => display label
     */
    /** Role slugs → translation function keys (translated at render time, not as constants). */
    private const ROLE_SLUGS = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];

    /** @return array<string, string>  role slug => translated label */
    private static function role_options(): array
    {
        return [
            'subscriber'    => __('Subscriber',    'wp-preview-token'),
            'contributor'   => __('Contributor',   'wp-preview-token'),
            'author'        => __('Author',        'wp-preview-token'),
            'editor'        => __('Editor',        'wp-preview-token'),
            'administrator' => __('Administrator', 'wp-preview-token'),
        ];
    }

    /**
     * Maps each role slug to the primitive capability that distinguishes it from
     * lower roles. Used in user_can() to achieve "at least this role" semantics.
     *
     * subscriber    → read              (all logged-in users)
     * contributor   → edit_posts        (can draft posts)
     * author        → publish_posts     (can publish own posts)
     * editor        → edit_others_posts (can edit any post)
     * administrator → manage_options    (full admin)
     *
     * @var array<string, string>  role slug => primitive capability
     */
    private const ROLE_TO_CAPABILITY = [
        'subscriber'    => 'read',
        'contributor'   => 'edit_posts',
        'author'        => 'publish_posts',
        'editor'        => 'edit_others_posts',
        'administrator' => 'manage_options',
    ];

    public function register(): void
    {
        add_action('admin_menu',            [$this, 'add_settings_page']);
        add_action('admin_init',            [$this, 'register_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_scripts']);
    }

    public function enqueue_settings_scripts(string $hook): void
    {
        if ($hook !== 'settings_page_wp-preview-token') {
            return;
        }

        $plugin_url  = rtrim(plugin_dir_url(WPT_PLUGIN_FILE), '/');
        $asset_path  = dirname(dirname(__DIR__)) . '/assets/js/settings.iife.js';
        $version     = file_exists($asset_path) ? (string) filemtime($asset_path) : '0';

        wp_enqueue_script(
            'wpt-settings',
            "{$plugin_url}/assets/js/settings.iife.js",
            [],
            $version,
            true
        );

        wp_localize_script('wpt-settings', 'wptSettingsData', [
            'field'        => Constants::OPTION_ALLOWED_ORIGINS . '[]',
            'removeLabel'  => __('Remove this origin', 'wp-preview-token'),
            'warningTitle' => __('Security Warning', 'wp-preview-token'),
            'warningText'  => __('The bare wildcard (*) allows any origin to access draft content via a valid token. Use specific origin patterns whenever possible.', 'wp-preview-token'),
        ]);
    }

    public function add_settings_page(): void
    {
        add_options_page(
            __('WP Preview Token', 'wp-preview-token'),
            __('Preview Token',    'wp-preview-token'),
            'manage_options',
            'wp-preview-token',
            [$this, 'render_page']
        );
    }

    public function register_fields(): void
    {
        register_setting('wpt_settings', Constants::OPTION_FRONTEND_URL, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        register_setting('wpt_settings', Constants::OPTION_ALLOWED_ORIGINS, [
            'type'              => 'string',
            // Accepts either a newline-separated string (legacy textarea) or
            // an array submitted by the dynamic input list (name="wpt_allowed_origins[]").
            'sanitize_callback' => static function ($value): string {
                $lines = is_array($value)
                    ? $value
                    : explode("\n", (string) $value);
                $lines = array_filter(
                    array_map('trim', $lines),
                    static fn(string $l): bool => $l !== '' && strncmp($l, '#', 1) !== 0
                );
                return implode("\n", $lines);
            },
        ]);

        $allowed_roles = self::ROLE_SLUGS;
        register_setting('wpt_settings', Constants::OPTION_MIN_CAPABILITY, [
            'type'              => 'string',
            'sanitize_callback' => static function (string $value) use ($allowed_roles): string {
                return in_array($value, $allowed_roles, true) ? $value : 'contributor';
            },
            'default'           => 'contributor',
        ]);

        register_setting('wpt_settings', Constants::OPTION_RATE_LIMIT_REQUESTS, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);

        register_setting('wpt_settings', Constants::OPTION_RATE_LIMIT_WINDOW, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ]);

        register_setting('wpt_settings', Constants::OPTION_ALLOW_NO_EXPIRY, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($v): bool => (bool) $v,
            'default'           => false,
        ]);

        register_setting('wpt_settings', Constants::OPTION_SKIP_HTTPS_CHECK, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($v): bool => (bool) $v,
            'default'           => false,
        ]);

        add_settings_section('wpt_main', '', '__return_null', 'wp-preview-token');

        add_settings_field('wpt_frontend_url',        __('External Preview URL',    'wp-preview-token'), [$this, 'render_frontend_url'],        'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_allowed_origins',     __('Allowed Origins (CORS)',  'wp-preview-token'), [$this, 'render_allowed_origins'],     'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_min_capability',      __('Minimum Capability',      'wp-preview-token'), [$this, 'render_min_capability'],      'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_rate_limit_requests', __('Rate Limit',              'wp-preview-token'), [$this, 'render_rate_limit_requests'], 'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_rate_limit_window',   __('Rate Limit Window',       'wp-preview-token'), [$this, 'render_rate_limit_window'],   'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_allow_no_expiry',     __('Allow No-Expiry Tokens',  'wp-preview-token'), [$this, 'render_allow_no_expiry'],     'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_skip_https_check',    __('Skip HTTPS Check',        'wp-preview-token'), [$this, 'render_skip_https_check'],    'wp-preview-token', 'wpt_main');
    }

    public function render_page(): void
    {
        // Defense-in-depth: verify capability explicitly even though
        // add_options_page already restricts access to manage_options.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-preview-token'));
        }

        $current_tab = sanitize_key($_GET['tab'] ?? 'settings');
        $base_url    = admin_url('options-general.php?page=wp-preview-token');
        $tabs        = [
            'settings' => __('Settings',      'wp-preview-token'),
            'tokens'   => __('Issued Tokens', 'wp-preview-token'),
        ];
        ?>
        <div class="wrap">
            <h1>WP Preview Token</h1>
            <nav class="nav-tab-wrapper" style="margin-bottom:20px">
                <?php foreach ($tabs as $slug => $label): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $slug, $base_url)); ?>"
                   class="nav-tab<?php echo $current_tab === $slug ? ' nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($current_tab === 'tokens'): ?>
                <?php do_action('wpt_settings_render_tokens_tab'); ?>
            <?php else: ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpt_settings');
                do_settings_sections('wp-preview-token');
                submit_button();
                ?>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_frontend_url(): void
    {
        printf(
            '<input type="url" name="%s" value="%s" class="regular-text" />'
            . '<p class="description">%s</p>',
            esc_attr(Constants::OPTION_FRONTEND_URL),
            esc_attr($this->get_frontend_url()),
            esc_html__('URL of the external client or frontend used to render preview content. Intended for headless setups (e.g. Astro, Next.js, Nuxt) and decoupled architectures where draft content is rendered outside of WordPress.', 'wp-preview-token')
        );
    }

    public function render_allowed_origins(): void
    {
        $origins = $this->get_allowed_origins();
        if ($origins === []) {
            $origins = [''];
        }
        $field = esc_attr(Constants::OPTION_ALLOWED_ORIGINS . '[]');
        ?>
        <div id="wpt-origins-list" style="display:flex;flex-direction:column;gap:6px;max-width:500px">
            <?php foreach ($origins as $origin): ?>
            <div class="wpt-origin-row" style="display:flex;gap:6px;align-items:center">
                <input type="text"
                       name="<?php echo $field; ?>"
                       value="<?php echo esc_attr($origin); ?>"
                       class="regular-text code"
                       placeholder="https://example.com  or  https://*.example.com"
                       style="flex:1;font-family:monospace" />
                <button type="button"
                        class="button wpt-remove-origin"
                        aria-label="<?php esc_attr_e('Remove this origin', 'wp-preview-token'); ?>">&#x2715;</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="wpt-add-origin" class="button" style="margin-top:6px">
            <?php esc_html_e('+ Add origin', 'wp-preview-token'); ?>
        </button>
        <?php
        // Server-side warning when * is already saved (shown before JS loads).
        if (in_array('*', $this->get_allowed_origins(), true)):
        ?>
        <div id="wpt-wildcard-warning" class="notice notice-warning inline" style="margin-top:6px;padding:8px 12px">
            <strong><?php esc_html_e('Security Warning', 'wp-preview-token'); ?>:</strong>
            <?php esc_html_e('The bare wildcard (*) allows any origin to access draft content via a valid token. Use specific origin patterns whenever possible.', 'wp-preview-token'); ?>
        </div>
        <?php endif; ?>
        <p class="description" style="margin-top:6px">
            <?php esc_html_e('Wildcards are supported (e.g. https://*.example.com, *). Leave all fields empty to disable CORS headers.', 'wp-preview-token'); ?>
        </p>
        <?php
        // JS (settings.iife.js) is enqueued via enqueue_settings_scripts() and
        // receives wptSettingsData via wp_localize_script — no inline script needed.
    }

    public function render_min_capability(): void
    {
        $current = $this->get_min_role();

        printf('<select name="%s">', esc_attr(Constants::OPTION_MIN_CAPABILITY));

        foreach (self::role_options() as $role => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($role),
                selected($current, $role, false),
                esc_html($label)
            );
        }

        echo '</select>';
    }

    public function render_rate_limit_requests(): void
    {
        printf(
            '<input type="number" name="%s" value="%d" min="1" max="1000" class="small-text" /> ' . esc_html__('requests per window', 'wp-preview-token'),
            esc_attr(Constants::OPTION_RATE_LIMIT_REQUESTS),
            $this->get_rate_limit_requests()
        );
    }

    public function render_rate_limit_window(): void
    {
        printf(
            '<input type="number" name="%s" value="%d" min="1" max="3600" class="small-text" /> ' . esc_html__('seconds', 'wp-preview-token'),
            esc_attr(Constants::OPTION_RATE_LIMIT_WINDOW),
            $this->get_rate_limit_window()
        );
    }

    public function get_frontend_url(): string
    {
        return (string) get_option(Constants::OPTION_FRONTEND_URL, '');
    }

    /** Raw newline-separated string stored in the database. */
    public function get_allowed_origins_raw(): string
    {
        return (string) get_option(Constants::OPTION_ALLOWED_ORIGINS, '');
    }

    /**
     * Returns parsed, non-empty origin patterns as an array.
     *
     * @return string[]
     */
    public function get_allowed_origins(): array
    {
        $raw = $this->get_allowed_origins_raw();
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            static fn(string $l): bool => $l !== '' && strncmp($l, '#', 1) !== 0
        ));
    }

    /**
     * Returns the stored role slug (e.g. 'contributor').
     * Use this for display and comparison against role names.
     */
    public function get_min_role(): string
    {
        return (string) get_option(Constants::OPTION_MIN_CAPABILITY, 'contributor');
    }

    /**
     * Returns the primitive capability corresponding to the minimum role.
     * This is the value passed to user_can() — callers need not know about roles.
     */
    public function get_min_capability(): string
    {
        return self::ROLE_TO_CAPABILITY[$this->get_min_role()] ?? 'edit_posts';
    }

    public function get_rate_limit_requests(): int
    {
        return max(1, (int) get_option(Constants::OPTION_RATE_LIMIT_REQUESTS, 30));
    }

    public function get_rate_limit_window(): int
    {
        return max(1, (int) get_option(Constants::OPTION_RATE_LIMIT_WINDOW, 60));
    }

    public function render_allow_no_expiry(): void
    {
        printf(
            '<label><input type="checkbox" name="%s" value="1"%s /> %s</label>'
            . '<p class="description">%s</p>',
            esc_attr(Constants::OPTION_ALLOW_NO_EXPIRY),
            checked($this->get_allow_no_expiry(), true, false),
            esc_html__('Enable', 'wp-preview-token'),
            esc_html__('Allow preview tokens with no expiry. Use with caution — these tokens persist until manually deleted.', 'wp-preview-token')
        );
    }

    public function get_allow_no_expiry(): bool
    {
        return (bool) get_option(Constants::OPTION_ALLOW_NO_EXPIRY, false);
    }

    public function render_skip_https_check(): void
    {
        $enabled = $this->get_skip_https_check();
        ?>
        <fieldset>
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr(Constants::OPTION_SKIP_HTTPS_CHECK); ?>"
                       value="1"
                       <?php checked($enabled); ?> />
                <?php esc_html_e('Enable (development only)', 'wp-preview-token'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('By default the preview endpoint requires HTTPS so that tokens cannot be intercepted in transit. Enable this option only when running WordPress on a local development environment where HTTPS is unavailable.', 'wp-preview-token'); ?>
            </p>
            <?php if ($enabled): ?>
            <div class="notice notice-warning inline" style="margin-top:6px;padding:8px 12px">
                <strong><?php esc_html_e('Security Warning', 'wp-preview-token'); ?>:</strong>
                <?php esc_html_e('The HTTPS check is currently disabled. Preview tokens can be sent over unencrypted HTTP. Do not use this setting in a production environment.', 'wp-preview-token'); ?>
            </div>
            <?php endif; ?>
            <p class="description" style="margin-top:6px;font-style:italic">
                <?php esc_html_e('If the WPT_SKIP_HTTPS_CHECK constant is defined in wp-config.php, it takes precedence over this setting.', 'wp-preview-token'); ?>
            </p>
        </fieldset>
        <?php
    }

    public function get_skip_https_check(): bool
    {
        return (bool) get_option(Constants::OPTION_SKIP_HTTPS_CHECK, false);
    }
}
