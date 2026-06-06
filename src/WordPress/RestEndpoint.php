<?php

declare(strict_types=1);

namespace WPT\WordPress;

use WPT\Support\ResponsePipeline;
use WPT\Token\TokenValidator;
use WP_Error;
use WP_HTTP_Response;
use WP_Post;
use WP_REST_Posts_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestEndpoint
{
    public const NAMESPACE = 'wp-preview-token/v1';
    public const ROUTE     = '/preview';

    private const PREVIEWABLE_STATUSES = ['draft', 'pending', 'future'];

    private TokenValidator $validator;
    private ResponsePipeline $pipeline;
    private Settings $settings;
    private RateLimiter $rate_limiter;

    public function __construct(
        TokenValidator $validator,
        ResponsePipeline $pipeline,
        Settings $settings,
        RateLimiter $rate_limiter
    ) {
        $this->validator    = $validator;
        $this->pipeline     = $pipeline;
        $this->settings     = $settings;
        $this->rate_limiter = $rate_limiter;
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        $this->register_response_headers();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle(WP_REST_Request $request)
    {
        // M-3: HTTPS required. Override with define('WPT_SKIP_HTTPS_CHECK', true) in wp-config.php.
        if (!is_ssl() && !(defined('WPT_SKIP_HTTPS_CHECK') && WPT_SKIP_HTTPS_CHECK)) {
            return new WP_Error(
                'https_required',
                'HTTPS is required.',
                ['status' => 403]
            );
        }

        // M-2: Rate limiting per client IP
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!$this->rate_limiter->is_allowed($ip)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests.',
                ['status' => 429]
            );
        }

        $token = (string) $request->get_param('token');
        $data  = $this->validator->validate($token);

        if ($data === false) {
            return new WP_Error(
                'invalid_token',
                'Invalid or expired preview token.',
                ['status' => 401]
            );
        }

        $post = get_post($data['post_id']);

        if (!($post instanceof WP_Post)) {
            return new WP_Error(
                'post_not_found',
                'Post not found.',
                ['status' => 404]
            );
        }

        // L-3: Restrict to previewable statuses only
        if (!in_array($post->post_status, self::PREVIEWABLE_STATUSES, true)) {
            return new WP_Error(
                'invalid_post_status',
                'Preview is only available for unpublished posts.',
                ['status' => 403]
            );
        }

        // L-5: Audit log
        do_action('wpt_token_used', $post->ID, $data['user_id']);

        $controller = new WP_REST_Posts_Controller($post->post_type);
        $prepared   = $controller->prepare_item_for_response($post, $request);
        $filtered   = $this->pipeline->process($prepared->get_data());

        // I-2: Allow application-layer response shaping
        // add_filter('wpt_preview_response_data', function(array $data, WP_Post $post, WP_REST_Request $req): array { ... }, 10, 3);
        $filtered = apply_filters('wpt_preview_response_data', $filtered, $post, $request);

        return new WP_REST_Response($filtered, 200);
    }

    private function register_response_headers(): void
    {
        $origin = $this->settings->get_allowed_origin();

        add_filter(
            'rest_pre_serve_request',
            function (bool $served, WP_HTTP_Response $result, WP_REST_Request $request) use ($origin): bool {
                if (!str_starts_with($request->get_route(), '/' . self::NAMESPACE)) {
                    return $served;
                }

                // M-3: Prevent token leakage via Referer header on external resource loads
                header('Referrer-Policy: no-referrer');

                if ($origin !== '') {
                    // L-1: Strip newlines before header interpolation (belt-and-suspenders over esc_url_raw)
                    $safe_origin = str_replace(["\r", "\n"], '', $origin);
                    header("Access-Control-Allow-Origin: {$safe_origin}");
                    header('Access-Control-Allow-Methods: GET, OPTIONS');
                    header('Access-Control-Allow-Credentials: false');
                    // L-1: Required to prevent CDN/proxy caching a single-origin response for all origins
                    header('Vary: Origin');
                }

                return $served;
            },
            10,
            3
        );
    }
}
