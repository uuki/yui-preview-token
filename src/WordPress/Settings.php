<?php

declare(strict_types=1);

namespace WPT\WordPress;

class Settings
{
    private const OPTION_FRONTEND_URL        = 'wpt_frontend_url';
    private const OPTION_ALLOWED_ORIGIN      = 'wpt_allowed_origin';
    private const OPTION_MIN_CAPABILITY      = 'wpt_min_capability';
    private const OPTION_RATE_LIMIT_REQUESTS = 'wpt_rate_limit_requests';
    private const OPTION_RATE_LIMIT_WINDOW   = 'wpt_rate_limit_window';
    private const OPTION_ALLOW_NO_EXPIRY     = 'wpt_allow_no_expiry';

    /** @var array<string, string> */
    private const CAPABILITY_OPTIONS = [
        'edit_posts'        => 'Author (edit_posts)',
        'publish_posts'     => 'Author+ (publish_posts)',
        'edit_others_posts' => 'Editor (edit_others_posts)',
        'manage_options'    => 'Administrator (manage_options)',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_fields']);
    }

    public function add_settings_page(): void
    {
        add_options_page(
            'WP Preview Token',
            'Preview Token',
            'manage_options',
            'wp-preview-token',
            [$this, 'render_page']
        );
    }

    public function register_fields(): void
    {
        register_setting('wpt_settings', self::OPTION_FRONTEND_URL, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        register_setting('wpt_settings', self::OPTION_ALLOWED_ORIGIN, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);

        $allowed_caps = array_keys(self::CAPABILITY_OPTIONS);
        register_setting('wpt_settings', self::OPTION_MIN_CAPABILITY, [
            'type'              => 'string',
            'sanitize_callback' => static function (string $value) use ($allowed_caps): string {
                return in_array($value, $allowed_caps, true) ? $value : 'edit_posts';
            },
            'default'           => 'edit_posts',
        ]);

        register_setting('wpt_settings', self::OPTION_RATE_LIMIT_REQUESTS, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);

        register_setting('wpt_settings', self::OPTION_RATE_LIMIT_WINDOW, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 60,
        ]);

        register_setting('wpt_settings', self::OPTION_ALLOW_NO_EXPIRY, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($v): bool => (bool) $v,
            'default'           => false,
        ]);

        add_settings_section('wpt_main', '', '__return_null', 'wp-preview-token');

        add_settings_field('wpt_frontend_url',        'External Preview URL',  [$this, 'render_frontend_url'],        'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_allowed_origin',      'Allowed Origin (CORS)', [$this, 'render_allowed_origin'],      'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_min_capability',      'Minimum Capability',    [$this, 'render_min_capability'],      'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_rate_limit_requests', 'Rate Limit',            [$this, 'render_rate_limit_requests'], 'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_rate_limit_window',   'Rate Limit Window',     [$this, 'render_rate_limit_window'],   'wp-preview-token', 'wpt_main');
        add_settings_field('wpt_allow_no_expiry',     'Allow No-Expiry Tokens', [$this, 'render_allow_no_expiry'],   'wp-preview-token', 'wpt_main');
    }

    public function render_page(): void
    {
        ?>
        <div class="wrap">
            <h1>WP Preview Token</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpt_settings');
                do_settings_sections('wp-preview-token');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_frontend_url(): void
    {
        printf(
            '<input type="url" name="%s" value="%s" class="regular-text" />'
            . '<p class="description">%s</p>',
            esc_attr(self::OPTION_FRONTEND_URL),
            esc_attr($this->get_frontend_url()),
            esc_html__(
                'URL of the external client or frontend used to render preview content. '
                . 'Intended for headless setups (e.g. Astro, Next.js, Nuxt) and decoupled architectures '
                . 'where draft content is rendered outside of WordPress.'
            )
        );
    }

    public function render_allowed_origin(): void
    {
        printf(
            '<input type="url" name="%s" value="%s" class="regular-text" />',
            esc_attr(self::OPTION_ALLOWED_ORIGIN),
            esc_attr($this->get_allowed_origin())
        );
    }

    public function render_min_capability(): void
    {
        $current = $this->get_min_capability();

        printf('<select name="%s">', esc_attr(self::OPTION_MIN_CAPABILITY));

        foreach (self::CAPABILITY_OPTIONS as $cap => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($cap),
                selected($current, $cap, false),
                esc_html($label)
            );
        }

        echo '</select>';
    }

    public function render_rate_limit_requests(): void
    {
        printf(
            '<input type="number" name="%s" value="%d" min="1" max="1000" class="small-text" /> requests per window',
            esc_attr(self::OPTION_RATE_LIMIT_REQUESTS),
            $this->get_rate_limit_requests()
        );
    }

    public function render_rate_limit_window(): void
    {
        printf(
            '<input type="number" name="%s" value="%d" min="1" max="3600" class="small-text" /> seconds',
            esc_attr(self::OPTION_RATE_LIMIT_WINDOW),
            $this->get_rate_limit_window()
        );
    }

    public function get_frontend_url(): string
    {
        return (string) get_option(self::OPTION_FRONTEND_URL, '');
    }

    public function get_allowed_origin(): string
    {
        return (string) get_option(self::OPTION_ALLOWED_ORIGIN, '');
    }

    public function get_min_capability(): string
    {
        return (string) get_option(self::OPTION_MIN_CAPABILITY, 'edit_posts');
    }

    public function get_rate_limit_requests(): int
    {
        return max(1, (int) get_option(self::OPTION_RATE_LIMIT_REQUESTS, 30));
    }

    public function get_rate_limit_window(): int
    {
        return max(1, (int) get_option(self::OPTION_RATE_LIMIT_WINDOW, 60));
    }

    public function render_allow_no_expiry(): void
    {
        printf(
            '<label><input type="checkbox" name="%s" value="1"%s /> %s</label>'
            . '<p class="description">%s</p>',
            esc_attr(self::OPTION_ALLOW_NO_EXPIRY),
            checked($this->get_allow_no_expiry(), true, false),
            esc_html__('Enable'),
            esc_html__('Allow preview tokens with no expiry. Use with caution — these tokens persist until manually deleted.')
        );
    }

    public function get_allow_no_expiry(): bool
    {
        return (bool) get_option(self::OPTION_ALLOW_NO_EXPIRY, false);
    }
}
