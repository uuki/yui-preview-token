=== Preview Token ===
Contributors: uuki
Tags: preview, headless, rest-api, token, draft
Requires at least: 5.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Issue short-lived preview tokens for headless WordPress setups. Let external frontends render draft content without long-lived credentials.

== Description ==

**Preview Token** solves the authentication problem in decoupled (headless) WordPress architectures. Instead of sharing Application Passwords or other long-lived secrets with your frontend, the plugin issues short-lived tokens that grant read access to a single draft post for a limited time.

The frontend (Astro, Next.js, Nuxt, etc.) receives a preview URL containing the token and can fetch the draft content via the REST API — no secrets stored, no persistent credentials required.

= How it works =

1. An authorized WordPress user generates a token from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box.
2. The token is embedded in a preview URL pointing to your external frontend.
3. The frontend calls `/wp-json/preview-token/v1/preview?token=…` to retrieve the draft content.
4. The token expires automatically; no manual cleanup needed.

= Key Features =

**Token Management**

* Generate tokens with expiry presets: 1 hour, 24 hours, 30 days, custom date/time, or no expiry.
* One token per post — issuing a new token invalidates the previous one.
* Tokens are stored as SHA-256 hashes; the raw value is never stored in the lookup table.
* Automatic cleanup of expired tokens via WP Cron (daily).

**Editor Integration**

* **Gutenberg**: dedicated panel in the Document Settings sidebar.
* **Quick Edit**: token controls directly in the post list.
* **Classic Editor**: meta box in the editor sidebar.
* Copy preview URL to clipboard with one click.
* Update expiry without invalidating the current token.

**Admin Settings**

* Set the external frontend URL.
* Configure allowed CORS origins (multiple, with wildcard support — `https://*.example.com`).
* Choose the minimum WordPress role required to issue tokens (Subscriber → Administrator).
* Tune rate limiting (requests per time window).
* Optionally permit no-expiry tokens.

**Issued Tokens List**

* View all active and expired tokens with post title, status, expiry, and issuer.
* Revoke individual tokens or bulk-delete expired ones — all from the Settings screen.

**Security**

* HTTPS required for the preview endpoint (overridable for local development).
* Per-IP rate limiting with configurable thresholds (default: 30 req / 60 s).
* Role-based access control for token issuance.
* CORS headers only sent for explicitly configured origins; WP core's permissive echo-back is suppressed.
* `Referrer-Policy: no-referrer` prevents token leakage via referer headers.
* Tokens are only valid for `draft`, `pending`, and `future` post statuses.
* Admin-only settings page with defence-in-depth capability checks.
* CSRF protection on all admin actions (nonce verification).

**Audit Logging**

* Logs token issuance and usage events (post ID, user ID, client IP).
* Logs security events: invalid token attempts, rate-limit violations, capability denials.
* Output goes to `WP_DEBUG_LOG` by default; point to a dedicated file with `WPT_LOG_FILE`.

**Internationalisation**

* Ships with Japanese (ja) and Simplified Chinese (zh_CN) translations.
* All admin UI strings are translation-ready.

= Developer Hooks =

**Filter**

* `wpt_preview_response_data` — Modify the REST API response data before it is sent.

**Actions**

* `wpt_token_issued( int $post_id, int $user_id )` — Fires after a token is issued.
* `wpt_token_used( int $post_id, int $user_id )` — Fires when a token is used successfully.
* `wpt_invalid_token( string $ip )` — Fires on an invalid/expired token attempt.
* `wpt_rate_limit_exceeded( string $ip, string $endpoint )` — Fires when rate limit is hit.
* `wpt_capability_denied( int $user_id, int $post_id )` — Fires on a capability denial.

**Constants (wp-config.php)**

* `WPT_SKIP_HTTPS_CHECK` — Set to `true` to disable the HTTPS requirement (development only).
* `WPT_LOG_FILE` — Absolute path to a dedicated audit log file.

= Use Case =

This plugin is designed for **headless WordPress** setups where a decoupled frontend (e.g. Astro, Next.js, Nuxt, SvelteKit) renders content from the WordPress REST API. It gives content editors a simple, secure way to share draft previews with stakeholders without granting them WordPress accounts or exposing long-lived API credentials.

== Installation ==

1. Upload the `preview-token` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Preview Token** and set your **External Preview URL** (the base URL of your frontend).
4. Add the allowed CORS origin(s) for your frontend domain.
5. Generate tokens from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box.

= Build from Source =

The JavaScript bundles in `assets/js/` are compiled from TypeScript sources located in `src/js/`. To rebuild them:

1. Install Node.js dependencies: `pnpm install`
2. Compile: `pnpm run build`

Full source code, including TypeScript sources and build configuration, is available at: https://github.com/uuki/preview-token

= Minimum Requirements =

* WordPress 5.9 or later
* PHP 7.4 or later
* A decoupled frontend that can consume the WordPress REST API

== Frequently Asked Questions ==

= Does the frontend need to be a specific framework? =

No. Any HTTP client that can call the WordPress REST API works. The plugin returns standard WordPress REST API post objects.

= What post statuses can be previewed? =

Only `draft`, `pending`, and `future` posts. Published posts are intentionally excluded — they are already publicly accessible.

= Can multiple people use the same preview URL? =

Yes. Tokens are designed for repeated use within their validity window (e.g. reloading the preview, checking on different screen sizes). Issuing a new token invalidates the previous URL.

= What happens when a token expires? =

The token is rejected with a 401 response. The expired token is automatically deleted by the daily WP Cron job. In the editor UI, the panel shows a fresh "Generate token" view without exposing the expiry state.

= Is HTTPS required? =

By default, yes. The preview endpoint returns a 403 for HTTP requests to protect the token from being intercepted in transit. For local development, add `define('WPT_SKIP_HTTPS_CHECK', true);` to `wp-config.php`.

= How do I restrict who can generate tokens? =

In **Settings → Preview Token → Minimum Capability**, choose the minimum WordPress role (Subscriber, Contributor, Author, Editor, or Administrator). Users below that role will receive a 403 when attempting to generate tokens.

= Can I use wildcard origins in CORS settings? =

Yes. You can enter patterns like `https://*.example.com` to allow all subdomains of a domain. The bare wildcard `*` is supported but triggers a security warning — prefer specific patterns when possible.

= Where are audit logs stored? =

By default, log entries are written via PHP's `error_log()`, which follows the `WP_DEBUG_LOG` setting. To write to a dedicated file, add `define('WPT_LOG_FILE', '/absolute/path/to/wpt.log');` to `wp-config.php`.

= Does this work with the Classic Editor plugin? =

Yes. When the Classic Editor plugin is active, the token panel appears as a meta box in the post editor sidebar.

== Screenshots ==

1. **Gutenberg sidebar** — Generate and manage preview tokens directly from the block editor.
2. **Quick Edit panel** — Issue tokens without leaving the post list screen.
3. **Settings page** — Configure the frontend URL, CORS origins, role requirements, and rate limits.
4. **Issued Tokens tab** — Review all active tokens, see who issued them, and revoke them individually or in bulk.

== Changelog ==

= 1.0.0 =
* Initial release.
* Token issuance, validation, and revocation via REST API.
* Gutenberg sidebar, Quick Edit, and Classic Editor integrations.
* Settings page: frontend URL, CORS origins, minimum capability, rate limiting, no-expiry option.
* Issued Tokens admin tab with individual and bulk revocation.
* Audit logging for lifecycle and security events.
* Japanese and Simplified Chinese translations.
* CORS multi-origin with wildcard support; WP core echo-back override at priority 11.
* Rate limiting per IP via WP transients.
* TypeScript source compiled to IIFE bundles via tsdown.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
