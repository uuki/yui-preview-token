<?php

declare(strict_types=1);

namespace PVT\WordPress;

use PVT\Token\TokenIssuer;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class IssueEndpoint
{
    private const NO_EXPIRY_SECONDS = 3_153_600_000; // 100 × YEAR_IN_SECONDS (no-overflow on 64-bit PHP)

    private TokenIssuer $issuer;
    private Settings    $settings;
    private RateLimiter $rate_limiter;

    public function __construct(TokenIssuer $issuer, Settings $settings, RateLimiter $rate_limiter)
    {
        $this->issuer       = $issuer;
        $this->settings     = $settings;
        $this->rate_limiter = $rate_limiter;
    }

    public function register(): void
    {
        $post_id_arg = ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'];
        $expiry_arg  = ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'];

        register_rest_route(Constants::REST_NAMESPACE, Constants::ROUTE_TOKEN, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_get'],
                'permission_callback' => 'is_user_logged_in',
                'args'                => ['post_id' => $post_id_arg],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_post'],
                'permission_callback' => 'is_user_logged_in',
                'args'                => ['post_id' => $post_id_arg, 'expires_at' => $expiry_arg],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'handle_patch'],
                'permission_callback' => 'is_user_logged_in',
                'args'                => ['post_id' => $post_id_arg, 'expires_at' => $expiry_arg],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handle_delete'],
                'permission_callback' => 'is_user_logged_in',
                'args'                => ['post_id' => $post_id_arg],
            ],
        ]);
    }

    /** @return WP_REST_Response|WP_Error */
    public function handle_get(WP_REST_Request $request)
    {
        $post_id = (int) $request->get_param('post_id');

        $err = $this->check_capability(get_current_user_id(), $post_id);
        if ($err) return $err;

        $data = $this->issuer->get_by_post($post_id);
        if (!$data) return new WP_REST_Response(null, 404);

        return new WP_REST_Response($this->format($data, $post_id), 200);
    }

    /** @return WP_REST_Response|WP_Error */
    public function handle_post(WP_REST_Request $request)
    {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!$this->rate_limiter->is_allowed($ip)) {
            do_action('pvt_rate_limit_exceeded', $ip, 'token');
            return new WP_Error('rate_limit_exceeded', 'Too many requests.', ['status' => 429]);
        }

        if (!$this->settings->get_allow_external_issuance() && !$this->is_admin_ui_request($request)) {
            return new WP_Error(
                'external_issuance_disabled',
                'Token issuance from external clients is not enabled. Enable "Allow External Token Issuance" in Settings → Preview Token.',
                ['status' => 403]
            );
        }

        $post_id    = (int) $request->get_param('post_id');
        $expires_at = (int) $request->get_param('expires_at');
        $user_id    = get_current_user_id();

        $err = $this->check_capability($user_id, $post_id);
        if ($err) return $err;

        $err = $this->check_post_status($post_id);
        if ($err) return $err;

        $resolved = $this->resolve_expires_at($expires_at);
        if ($resolved instanceof WP_Error) return $resolved;

        $this->issuer->issue($post_id, $user_id, $resolved);

        return new WP_REST_Response($this->format($this->issuer->get_by_post($post_id), $post_id), 201);
    }

    /** @return WP_REST_Response|WP_Error */
    public function handle_patch(WP_REST_Request $request)
    {
        if (!$this->settings->get_allow_external_issuance() && !$this->is_admin_ui_request($request)) {
            return new WP_Error('external_issuance_disabled', 'Token issuance from external clients is not enabled.', ['status' => 403]);
        }

        $post_id    = (int) $request->get_param('post_id');
        $expires_at = (int) $request->get_param('expires_at');
        $user_id    = get_current_user_id();

        $err = $this->check_capability($user_id, $post_id);
        if ($err) return $err;

        $resolved = $this->resolve_expires_at($expires_at);
        if ($resolved instanceof WP_Error) return $resolved;

        if (!$this->issuer->update_expiry($post_id, $resolved)) {
            return new WP_Error('no_token', 'No active token for this post.', ['status' => 404]);
        }

        return new WP_REST_Response($this->format($this->issuer->get_by_post($post_id), $post_id), 200);
    }

    /** @return WP_REST_Response|WP_Error */
    public function handle_delete(WP_REST_Request $request)
    {
        if (!$this->settings->get_allow_external_issuance() && !$this->is_admin_ui_request($request)) {
            return new WP_Error('external_issuance_disabled', 'Token issuance from external clients is not enabled.', ['status' => 403]);
        }

        $post_id = (int) $request->get_param('post_id');

        $err = $this->check_capability(get_current_user_id(), $post_id);
        if ($err) return $err;

        $this->issuer->delete_by_post($post_id);

        return new WP_REST_Response(null, 204);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return WP_Error|null */
    /**
     * Returns true when the request originates from the WordPress admin UI.
     *
     * The admin JS always sends X-WP-Nonce (generated by wp_create_nonce('wp_rest')).
     * Programmatic clients using Application Passwords or other REST auth typically
     * do not send this header, so its presence — combined with nonce verification —
     * is a reliable signal that the request came from a logged-in admin session.
     */
    private function is_admin_ui_request(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            return false;
        }
        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    private function check_capability(int $user_id, int $post_id)
    {
        if (!user_can($user_id, $this->settings->get_min_capability(), $post_id)) {
            do_action('pvt_capability_denied', $user_id, $post_id);
            return new WP_Error('forbidden', 'Insufficient permissions.', ['status' => 403]);
        }
        return null;
    }

    /** @return WP_Error|null */
    private function check_post_status(int $post_id)
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
        }
        if (!in_array($post->post_status, Constants::PREVIEWABLE_STATUSES, true)) {
            return new WP_Error('invalid_post_status', 'Preview is only available for unpublished posts.', ['status' => 403]);
        }
        return null;
    }

    /**
     * expires_at = 0 means "no expiry" → stored as time() + 100 years.
     *
     * @return int|WP_Error
     */
    private function resolve_expires_at(int $expires_at)
    {
        if ($expires_at !== 0) return $expires_at;

        if (!$this->settings->get_allow_no_expiry()) {
            return new WP_Error('no_expiry_disabled', 'No-expiry tokens are not enabled on this site.', ['status' => 403]);
        }

        return time() + self::NO_EXPIRY_SECONDS;
    }

    private function format(array $data, int $post_id): array
    {
        $post_type = (string) (get_post_type($post_id) ?: 'post');

        return [
            'preview_url' => add_query_arg(
                [
                    'p'       => $post_id,
                    'pt'      => $post_type,
                    'preview' => 'true',
                    'token'   => $data['raw'],
                ],
                $this->settings->get_frontend_url() ?: home_url('/')
            ),
            'expires_at'  => $data['expires_at'],
            'issued_at'   => $data['issued_at'],
            'issued_by'   => $data['issued_by'],
        ];
    }
}
