<?php

declare(strict_types=1);

namespace WPT\WordPress;

/**
 * Plugin-wide constants.
 *
 * Centralises values that are referenced across multiple classes or that
 * benefit from a single source of truth (e.g. option key names, REST namespace).
 *
 * Values tightly coupled to a single class (e.g. TokenIssuer meta keys,
 * IssueEndpoint::NO_EXPIRY_SECONDS) remain in their respective classes.
 */
final class Constants
{
    // ── REST API ─────────────────────────────────────────────────────────────

    public const REST_NAMESPACE = 'wp-preview-token/v1';

    public const ROUTE_TOKEN   = '/token';
    public const ROUTE_PREVIEW = '/preview';

    /** Post statuses that may be previewed via a token. */
    public const PREVIEWABLE_STATUSES = ['draft', 'pending', 'future'];

    // ── wp_options keys ──────────────────────────────────────────────────────

    public const OPTION_FRONTEND_URL        = 'wpt_frontend_url';
    public const OPTION_ALLOWED_ORIGINS     = 'wpt_allowed_origins';
    public const OPTION_MIN_CAPABILITY      = 'wpt_min_capability';
    public const OPTION_RATE_LIMIT_REQUESTS = 'wpt_rate_limit_requests';
    public const OPTION_RATE_LIMIT_WINDOW   = 'wpt_rate_limit_window';
    public const OPTION_ALLOW_NO_EXPIRY     = 'wpt_allow_no_expiry';
    public const OPTION_SKIP_HTTPS_CHECK    = 'wpt_skip_https_check';

    private function __construct() {}
}
