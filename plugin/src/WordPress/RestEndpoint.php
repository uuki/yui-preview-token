<?php

declare(strict_types=1);

namespace PVT\WordPress;

use PVT\Support\ResponsePipeline;
use PVT\Token\TokenValidator;
use WP_Error;
use WP_HTTP_Response;
use WP_Post;
use WP_REST_Posts_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestEndpoint
{

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
        register_rest_route(Constants::REST_NAMESPACE, Constants::ROUTE_PREVIEW, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle'],
            // Intentionally public: authorization is handled by token validation in the callback.
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
        // M-3: HTTPS required.
        // Override options (precedence: wp-config constant > DB option):
        //   define('PVT_SKIP_HTTPS_CHECK', true)         — server-level override
        //   Settings > Skip HTTPS Check (checkbox)       — admin UI toggle
        $skip_https = (defined('PVT_SKIP_HTTPS_CHECK') && PVT_SKIP_HTTPS_CHECK)
                   || $this->settings->get_skip_https_check();
        if (!is_ssl() && !$skip_https) {
            return new WP_Error(
                'https_required',
                __('HTTPS is required.', 'preview-token'),
                ['status' => 403]
            );
        }

        // M-2: Rate limiting per client IP
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!$this->rate_limiter->is_allowed($ip)) {
            do_action(Constants::HOOK_RATE_LIMIT_EXCEEDED, $ip, 'preview');
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests.', 'preview-token'),
                ['status' => 429]
            );
        }

        $token = (string) $request->get_param('token');
        $data  = $this->validator->validate($token);

        if ($data === false) {
            do_action(Constants::HOOK_INVALID_TOKEN, $ip);
            return new WP_Error(
                'invalid_token',
                __('Invalid or expired preview token.', 'preview-token'),
                ['status' => 401]
            );
        }

        $post = get_post($data['post_id']);

        if (!($post instanceof WP_Post)) {
            return new WP_Error(
                'post_not_found',
                __('Post not found.', 'preview-token'),
                ['status' => 404]
            );
        }

        // L-3: Restrict to previewable statuses only
        if (!in_array($post->post_status, Constants::PREVIEWABLE_STATUSES, true)) {
            return new WP_Error(
                'invalid_post_status',
                __('Preview is only available for unpublished posts.', 'preview-token'),
                ['status' => 403]
            );
        }

        // L-5: Audit log
        do_action(Constants::HOOK_TOKEN_USED, $post->ID, $data['user_id']);

        $controller = new WP_REST_Posts_Controller($post->post_type);
        $prepared   = $controller->prepare_item_for_response($post, $request);
        $filtered   = $this->pipeline->process($prepared->get_data());

        // I-2: Allow application-layer response shaping
        // add_filter('pvt_preview_response_data', function(array $data, WP_Post $post, WP_REST_Request $req): array { ... }, 10, 3);
        $filtered = apply_filters(Constants::FILTER_PREVIEW_RESPONSE_DATA, $filtered, $post, $request);

        return new WP_REST_Response($filtered, 200);
    }

    private function register_response_headers(): void
    {
        add_filter(
            'rest_pre_serve_request',
            function (bool $served, WP_HTTP_Response $result, WP_REST_Request $request): bool {
                if (!str_starts_with($request->get_route(), '/' . Constants::REST_NAMESPACE)) {
                    return $served;
                }

                // M-3: Prevent token leakage via Referer header on external resource loads
                header('Referrer-Policy: no-referrer');

                $patterns = $this->settings->get_allowed_origins();
                if ($patterns === []) {
                    return $served;
                }

                $request_origin = isset($_SERVER['HTTP_ORIGIN'])
                    ? trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])))
                    : '';

                if ($request_origin === '' || !$this->is_origin_allowed($request_origin, $patterns)) {
                    // WP core's rest_send_cors_headers (priority 10) unconditionally echoes
                    // back the request origin.  Only remove that specific echo-back; leave
                    // any Access-Control-Allow-Origin set to a *different* value intact so
                    // that other plugins' deliberate CORS policies are not overridden.
                    $current = $this->current_acao_header();
                    if ($current !== null && $current === $request_origin) {
                        header_remove('Access-Control-Allow-Origin');
                        header_remove('Access-Control-Allow-Methods');
                        header_remove('Access-Control-Allow-Credentials');
                    }
                    return $served;
                }

                // L-1: Always echo back the *actual* request origin, never the pattern.
                //      Browsers reject wildcard pattern strings as ACAO values.
                $safe = str_replace(["\r", "\n"], '', $request_origin);
                header("Access-Control-Allow-Origin: {$safe}");
                header('Access-Control-Allow-Methods: GET, OPTIONS');
                header('Access-Control-Allow-Credentials: false');
                // L-1: Required to prevent CDN/proxy caching a single-origin response for all origins
                header('Vary: Origin');

                return $served;
            },
            // Priority 11: run after WP core's rest_send_cors_headers (priority 10)
            // so we can override or remove the headers it sets for our endpoints.
            11,
            3
        );
    }

    /**
     * Returns true when $origin matches at least one pattern in $patterns.
     *
     * Pattern syntax:
     *   - Exact match:   https://example.com
     *   - Host wildcard: https://*.example.com  (matches one subdomain level)
     *   - Full wildcard: *  (matches any origin — use with caution)
     *
     * @param string   $origin   The actual HTTP_ORIGIN value from the request.
     * @param string[] $patterns Origin patterns from Settings::get_allowed_origins().
     */
    public function is_origin_allowed(string $origin, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if ($pattern === $origin) {
                return true;
            }

            if (strpos($pattern, '*') === false) {
                continue;
            }

            // Convert the wildcard pattern to a regex.
            // Use '#' as delimiter so literal '/' in URLs need not be escaped.
            // Each literal segment is quoted; '*' becomes [^:/]+ so wildcards
            // cannot cross scheme/host/port boundaries.
            $segments = explode('*', $pattern);
            // [^.:/]+ — wildcard matches one host component only (no dots, no port)
            $regex    = implode('[^.:/]+', array_map(static fn($s) => preg_quote($s, '#'), $segments));

            if (preg_match('#^' . $regex . '$#', $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the current Access-Control-Allow-Origin value from already-sent
     * PHP response headers, or null if the header has not been set.
     */
    private function current_acao_header(): ?string
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Access-Control-Allow-Origin:') === 0) {
                return trim(substr($header, strlen('Access-Control-Allow-Origin:')));
            }
        }
        return null;
    }
}
