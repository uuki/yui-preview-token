<?php

declare(strict_types=1);

namespace YUIPT\WordPress;

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

    public const REST_NAMESPACE = 'yui-preview-token/v1';

    public const ROUTE_TOKEN   = '/token';
    public const ROUTE_PREVIEW = '/preview';

    /** Post statuses that may be previewed via a token. */
    public const PREVIEWABLE_STATUSES = ['draft', 'pending', 'future'];

    // ── wp_options keys ──────────────────────────────────────────────────────

    public const OPTION_FRONTEND_URL        = 'yuipt_frontend_url';
    public const OPTION_ALLOWED_ORIGINS     = 'yuipt_allowed_origins';
    public const OPTION_MIN_CAPABILITY      = 'yuipt_min_capability';
    public const OPTION_RATE_LIMIT_REQUESTS = 'yuipt_rate_limit_requests';
    public const OPTION_RATE_LIMIT_WINDOW   = 'yuipt_rate_limit_window';
    public const OPTION_ALLOW_NO_EXPIRY         = 'yuipt_allow_no_expiry';
    public const OPTION_SKIP_HTTPS_CHECK        = 'yuipt_skip_https_check';
    public const OPTION_ALLOW_EXTERNAL_ISSUANCE = 'yuipt_allow_external_issuance';

    // ── Action / filter hooks ─────────────────────────────────────────────────

    // HOOK_TOKEN_ISSUED is defined on TokenIssuer::HOOK_TOKEN_ISSUED (Token layer owns it)
    public const HOOK_TOKEN_USED                = 'yuipt_token_used';
    public const HOOK_INVALID_TOKEN             = 'yuipt_invalid_token';
    public const HOOK_RATE_LIMIT_EXCEEDED       = 'yuipt_rate_limit_exceeded';
    public const HOOK_CAPABILITY_DENIED         = 'yuipt_capability_denied';
    public const HOOK_CLEANUP_TOKENS            = 'yuipt_cleanup_tokens';
    public const HOOK_SETTINGS_RENDER_TOKENS_TAB = 'yuipt_settings_render_tokens_tab';
    public const FILTER_PREVIEW_RESPONSE_DATA   = 'yuipt_preview_response_data';

    // ── admin-post actions & nonce bases ─────────────────────────────────────

    public const ADMIN_ACTION_DELETE_TOKEN   = 'yuipt_delete_token';
    public const ADMIN_ACTION_DELETE_EXPIRED = 'yuipt_delete_expired';
    // Nonce: NONCE_DELETE_TOKEN . "_{$post_id}"
    public const NONCE_DELETE_TOKEN   = 'yuipt_delete_token';
    public const NONCE_DELETE_EXPIRED = 'yuipt_delete_expired';

    // ── Settings page ─────────────────────────────────────────────────────────

    public const SETTINGS_PAGE_SLUG = 'yui-preview-token';
    public const SETTINGS_GROUP     = 'yuipt_settings';
    public const SETTINGS_SECTION   = 'yuipt_main';

    // ── Script handles ────────────────────────────────────────────────────────

    public const SCRIPT_SIDEBAR        = 'yuipt-sidebar';
    public const SCRIPT_QUICK_EDIT     = 'yuipt-quick-edit';
    public const SCRIPT_CLASSIC_EDITOR = 'yuipt-classic-editor';
    public const SCRIPT_SETTINGS       = 'yuipt-settings';

    // ── JS global variable names (wp_localize_script) ─────────────────────────

    public const JS_PREVIEW_DATA  = 'yuiptPreviewData';
    public const JS_SETTINGS_DATA = 'yuiptSettingsData';

    // ── HTML element IDs ─── kept in sync with constants.ts ELEMENT_* ─────────

    public const ELEMENT_CLASSIC_ROOT     = 'yuipt-classic-meta-box-root';
    public const ELEMENT_ORIGINS_LIST     = 'yuipt-origins-list';
    public const ELEMENT_ADD_ORIGIN       = 'yuipt-add-origin';
    public const ELEMENT_WILDCARD_WARNING = 'yuipt-wildcard-warning';

    // ── CSS classes ─── kept in sync with constants.ts CLASS_* ───────────────

    public const CLASS_ORIGIN_ROW    = 'yuipt-origin-row';
    public const CLASS_REMOVE_ORIGIN = 'yuipt-remove-origin';

    // ── data attributes ─── kept in sync with constants.ts ATTR_* ────────────

    public const ATTR_PANEL  = 'data-yuipt-panel';
    public const ATTR_ACTION = 'data-yuipt-action';

    // ── Meta box ID ───────────────────────────────────────────────────────────

    public const META_BOX_ID = 'yuipt-preview';

    // ── Transient key prefixes ────────────────────────────────────────────────

    public const TRANSIENT_PREFIX_RATE_LIMITER = 'yuipt_rl_';

    // ── Audit log ─────────────────────────────────────────────────────────────

    public const LOG_PREFIX        = '[yuipt]';
    /** Name of the PHP define() constant set in the plugin bootstrap file. */
    public const DEFINE_PLUGIN_FILE = 'YUIPT_PLUGIN_FILE';
    /** Name of the PHP define() constant users set in wp-config.php. */
    public const DEFINE_LOG_FILE        = 'YUIPT_LOG_FILE';
    public const DEFINE_SKIP_HTTPS_CHECK = 'YUIPT_SKIP_HTTPS_CHECK';

    private function __construct() {}
}
