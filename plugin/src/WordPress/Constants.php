<?php

declare(strict_types=1);

namespace PVT\WordPress;

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

    public const REST_NAMESPACE = 'preview-token/v1';

    public const ROUTE_TOKEN   = '/token';
    public const ROUTE_PREVIEW = '/preview';

    /** Post statuses that may be previewed via a token. */
    public const PREVIEWABLE_STATUSES = ['draft', 'pending', 'future'];

    // ── wp_options keys ──────────────────────────────────────────────────────

    public const OPTION_FRONTEND_URL        = 'pvt_frontend_url';
    public const OPTION_ALLOWED_ORIGINS     = 'pvt_allowed_origins';
    public const OPTION_MIN_CAPABILITY      = 'pvt_min_capability';
    public const OPTION_RATE_LIMIT_REQUESTS = 'pvt_rate_limit_requests';
    public const OPTION_RATE_LIMIT_WINDOW   = 'pvt_rate_limit_window';
    public const OPTION_ALLOW_NO_EXPIRY         = 'pvt_allow_no_expiry';
    public const OPTION_SKIP_HTTPS_CHECK        = 'pvt_skip_https_check';
    public const OPTION_ALLOW_EXTERNAL_ISSUANCE = 'pvt_allow_external_issuance';

    // ── Action / filter hooks ─────────────────────────────────────────────────

    // HOOK_TOKEN_ISSUED is defined on TokenIssuer::HOOK_TOKEN_ISSUED (Token layer owns it)
    public const HOOK_TOKEN_USED                = 'pvt_token_used';
    public const HOOK_INVALID_TOKEN             = 'pvt_invalid_token';
    public const HOOK_RATE_LIMIT_EXCEEDED       = 'pvt_rate_limit_exceeded';
    public const HOOK_CAPABILITY_DENIED         = 'pvt_capability_denied';
    public const HOOK_CLEANUP_TOKENS            = 'pvt_cleanup_tokens';
    public const HOOK_SETTINGS_RENDER_TOKENS_TAB = 'pvt_settings_render_tokens_tab';
    public const FILTER_PREVIEW_RESPONSE_DATA   = 'pvt_preview_response_data';

    // ── admin-post actions & nonce bases ─────────────────────────────────────

    public const ADMIN_ACTION_DELETE_TOKEN   = 'pvt_delete_token';
    public const ADMIN_ACTION_DELETE_EXPIRED = 'pvt_delete_expired';
    // Nonce: NONCE_DELETE_TOKEN . "_{$post_id}"
    public const NONCE_DELETE_TOKEN   = 'pvt_delete_token';
    public const NONCE_DELETE_EXPIRED = 'pvt_delete_expired';

    // ── Settings page ─────────────────────────────────────────────────────────

    public const SETTINGS_PAGE_SLUG = 'preview-token';
    public const SETTINGS_GROUP     = 'pvt_settings';
    public const SETTINGS_SECTION   = 'pvt_main';

    // ── Script handles ────────────────────────────────────────────────────────

    public const SCRIPT_SIDEBAR        = 'pvt-sidebar';
    public const SCRIPT_QUICK_EDIT     = 'pvt-quick-edit';
    public const SCRIPT_CLASSIC_EDITOR = 'pvt-classic-editor';
    public const SCRIPT_SETTINGS       = 'pvt-settings';

    // ── JS global variable names (wp_localize_script) ─────────────────────────

    public const JS_PREVIEW_DATA  = 'pvtPreviewData';
    public const JS_SETTINGS_DATA = 'pvtSettingsData';

    // ── HTML element IDs ─── kept in sync with constants.ts ELEMENT_* ─────────

    public const ELEMENT_CLASSIC_ROOT     = 'pvt-classic-meta-box-root';
    public const ELEMENT_ORIGINS_LIST     = 'pvt-origins-list';
    public const ELEMENT_ADD_ORIGIN       = 'pvt-add-origin';
    public const ELEMENT_WILDCARD_WARNING = 'pvt-wildcard-warning';

    // ── CSS classes ─── kept in sync with constants.ts CLASS_* ───────────────

    public const CLASS_ORIGIN_ROW    = 'pvt-origin-row';
    public const CLASS_REMOVE_ORIGIN = 'pvt-remove-origin';

    // ── data attributes ─── kept in sync with constants.ts ATTR_* ────────────

    public const ATTR_PANEL  = 'data-pvt-panel';
    public const ATTR_ACTION = 'data-pvt-action';

    // ── Meta box ID ───────────────────────────────────────────────────────────

    public const META_BOX_ID = 'pvt-preview';

    // ── Audit log ─────────────────────────────────────────────────────────────

    public const LOG_PREFIX        = '[pvt]';
    /** Name of the PHP define() constant users set in wp-config.php. */
    public const DEFINE_LOG_FILE   = 'PVT_LOG_FILE';

    private function __construct() {}
}
