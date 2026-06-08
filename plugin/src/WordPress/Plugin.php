<?php

declare(strict_types=1);

namespace PVT\WordPress;

use PVT\Support\ResponseFilters;
use PVT\Support\ResponsePipeline;
use PVT\Token\TokenIssuer;
use PVT\Token\TokenValidator;

class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    private function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public static function get_instance(): self
    {
        return self::$instance ??= new self(new Settings());
    }

    public function init(): void
    {
        $pipeline = new ResponsePipeline([
            [ResponseFilters::class, 'strip_password'],
            [ResponseFilters::class, 'strip_internal_fields'],
        ]);

        $rate_limiter = new RateLimiter(
            $this->settings->get_rate_limit_requests(),
            $this->settings->get_rate_limit_window()
        );

        $issuer   = new TokenIssuer();
        $endpoint = new RestEndpoint(new TokenValidator(), $pipeline, $this->settings, $rate_limiter);
        $issue    = new IssueEndpoint($issuer, $this->settings, $rate_limiter);

        $this->settings->register();
        (new AdminScripts($this->settings, plugin_dir_url(PVT_PLUGIN_FILE)))->register();
        (new AuditLogger())->register();
        (new TokenAdmin($issuer))->register();

        add_action('rest_api_init', [$endpoint, 'register']);
        add_action('rest_api_init', [$issue,    'register']);

        // Daily cleanup of expired token options
        add_action(Constants::HOOK_CLEANUP_TOKENS, static function() use ($issuer): void {
            $issuer->cleanup_expired();
        });
        add_action('init', static function(): void {
            if (!wp_next_scheduled(Constants::HOOK_CLEANUP_TOKENS)) {
                wp_schedule_event(time(), 'daily', Constants::HOOK_CLEANUP_TOKENS);
            }
        });
    }
}
