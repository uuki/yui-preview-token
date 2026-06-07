=== Preview Token ===
Contributors: uuki
Tags: preview, headless, rest-api, token, draft
Requires at least: 5.9
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Issue time-limited preview tokens for headless WordPress setups. Open draft content directly in your designated frontend URL without long-lived credentials.

== Description ==

**Preview Token** solves the authentication problem in decoupled (headless) WordPress architectures. Application Passwords are a great built-in WordPress feature for this purpose, but they require managing long-lived secrets on the frontend side. This plugin instead issues per-post tokens that grant read access for a configurable period — no persistent secrets required.

The frontend (Astro, Next.js, Nuxt, etc.) receives a preview URL and can fetch the draft content directly via the REST API.

= How it works =

1. An authorized WordPress user generates a token from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box.
2. The token is embedded in a preview URL pointing to your external frontend.
3. The frontend calls `/wp-json/preview-token/v1/preview?token=…` to retrieve the draft content.
4. The token expires automatically; no manual cleanup needed.

= Key Features =

**Token Management**

* Generate tokens with expiry presets: 1 hour, 24 hours, 30 days, custom date/time, or no expiry.
* One token per post — issuing a new token invalidates the previous one.
* Tokens are generated with `bin2hex(random_bytes(32))` (256-bit CSPRNG). The lookup key stored in `wp_options` is the SHA-256 hash of the raw token, not the token itself — so a database leak does not expose usable tokens directly.
* Automatic cleanup of expired tokens via WP Cron (daily).

**Editor Integration**

* **Gutenberg**: dedicated panel in the Document Settings sidebar.
* **Quick Edit**: token controls directly in the post list.
* **Classic Editor**: meta box in the editor sidebar.
* Copy preview URL to clipboard with one click.
* Update expiry without invalidating the current token.

**Admin Settings**

* Set the external frontend URL (clicking "Open external preview" navigates directly to this URL).
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
* Output goes to `WP_DEBUG_LOG` by default; point to a dedicated file with `PVT_LOG_FILE`.

**Internationalisation**

* Ships with Japanese (ja) and Simplified Chinese (zh_CN) translations.
* All admin UI strings are translation-ready.

= Developer Hooks =

**Filter**

* `pvt_preview_response_data` — Modify the REST API response data before it is sent.

**Actions**

* `pvt_token_issued( int $post_id, int $user_id )` — Fires after a token is issued.
* `pvt_token_used( int $post_id, int $user_id )` — Fires when a token is used successfully.
* `pvt_invalid_token( string $ip )` — Fires on an invalid/expired token attempt.
* `pvt_rate_limit_exceeded( string $ip, string $endpoint )` — Fires when rate limit is hit.
* `pvt_capability_denied( int $user_id, int $post_id )` — Fires on a capability denial.

**Constants (wp-config.php)**

* `PVT_SKIP_HTTPS_CHECK` — Set to `true` to disable the HTTPS requirement (development only).
* `PVT_LOG_FILE` — Absolute path to a dedicated audit log file.

= Use Case =

This plugin is designed for **headless WordPress** setups where a decoupled frontend (e.g. Astro, Next.js, Nuxt, SvelteKit) renders content from the WordPress REST API. It gives content editors a simple, secure way to share draft previews with stakeholders without granting them WordPress accounts or exposing long-lived API credentials.

== Installation ==

1. Upload the `preview-token` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Preview Token** and set your **External Preview URL** (the base URL of your frontend).
4. Add the allowed CORS origin(s) for your frontend domain.
5. Generate tokens from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box.

= JavaScript Source Code =

The files in `assets/js/` are compiled and minified bundles. Per WordPress.org guidelines, the human-readable TypeScript source files are available at:

https://github.com/uuki/preview-token/tree/main/src/js

= Build from Source =

To rebuild the JavaScript bundles from the TypeScript sources:

1. Clone the repository: `git clone https://github.com/uuki/preview-token.git`
2. Install Node.js dependencies: `pnpm install`
3. Compile: `pnpm run build`

The build configuration is defined in `tsdown.config.ts`. Each bundle in `assets/js/` corresponds to one entry point in `src/js/`.

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

By default, yes. The preview endpoint returns a 403 for HTTP requests to protect the token from being intercepted in transit. For local development, add `define('PVT_SKIP_HTTPS_CHECK', true);` to `wp-config.php`.

= How do I restrict who can generate tokens? =

In **Settings → Preview Token → Minimum Capability**, choose the minimum WordPress role (Subscriber, Contributor, Author, Editor, or Administrator). Users below that role will receive a 403 when attempting to generate tokens.

= Can I use wildcard origins in CORS settings? =

Yes. You can enter patterns like `https://*.example.com` to allow all subdomains of a domain. The bare wildcard `*` is supported but triggers a security warning — prefer specific patterns when possible.

= Where are audit logs stored? =

By default, log entries are written via PHP's `error_log()`, which follows the `WP_DEBUG_LOG` setting. To write to a dedicated file, add `define('PVT_LOG_FILE', '/absolute/path/to/pvt.log');` to `wp-config.php`.

= Does this work with the Classic Editor plugin? =

Yes. When the Classic Editor plugin is active, the token panel appears as a meta box in the post editor sidebar.

= How does the frontend fetch the post data? =

Pass the `token` query parameter directly to the preview endpoint — no authentication headers required:

```
GET /wp-json/preview-token/v1/preview?token=<token>
```

The token is bound to a specific post at issuance time (stored as a SHA-256 hash in `wp_options`). The server resolves which post to return from the token alone; the client cannot redirect it to a different post.

The response follows the standard WordPress REST API post format (`/wp/v2/posts/{id}`). The preview URL also includes `p=<post_id>`, `pt=<post_type>`, and `preview=true` parameters so the frontend can determine routing and template selection before making the API call.

```javascript
const params  = new URLSearchParams(location.search)
const token   = params.get('token')
const postType = params.get('pt')   // 'post', 'page', or a custom post type slug

const res  = await fetch(`https://wp.example.com/wp-json/preview-token/v1/preview?token=${token}`)
const post = await res.json()
```

== Screenshots ==

1. **Gutenberg sidebar** — Token generation panel before a token is issued. Select an expiry and click "Generate token".
2. **Gutenberg sidebar** — Active token: expiry info, open preview in the designated frontend URL, and copy-to-clipboard.
3. **Classic Editor** — Token panel in the meta box sidebar, before a token is issued.
4. **Classic Editor** — Active token with "Open external preview" button and change-expiry / delete actions.
6. **Settings page** — Configure the frontend URL, CORS origins, minimum role, and rate limits.
7. **Issued Tokens tab** — Review all issued tokens with post, status, expiry, and issuer. Revoke or bulk-delete expired ones.
8. **Quick Edit panel** — Token management directly from the post list screen, without opening the editor.

== Changelog ==

= 1.0.4 =
* Added "Allow External Token Issuance" setting (disabled by default). When enabled, authenticated users with the required role can issue tokens via the REST API from outside the WordPress admin — designed for CI/CD pipelines and automated workflows.

= 1.0.3 =
* Gutenberg: automatically saves the draft before opening the external preview so the frontend always receives the latest content. Classic Editor: added a note prompting users to save before previewing, since unsaved changes are not reflected in the external frontend.

= 1.0.1 =
* Preview URL now includes `p=<post_id>&pt=<post_type>&preview=true` alongside `token=` so the frontend can identify the target post and content type directly from the URL.

= 1.0.0 =
* Initial release.
