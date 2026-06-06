# Preview Token

## Overview

A WordPress plugin that issues time-limited preview tokens for headless frontends.

An authorized WordPress user generates a token from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box. The plugin embeds the token in a preview URL pointing to the configured external frontend. The frontend calls the plugin's REST endpoint with that token and receives the draft post data.

**What it solves:**

- Frontend applications access draft content without storing WordPress credentials
- Tokens have a configurable expiry (1 h / 24 h / 30 d / custom / no-expiry) — leaked URLs have limited exposure
- Scope is preview-only; no broader WordPress permissions are granted
- The frontend handles only a URL parameter — no secrets in frontend code or config

---

## How It Works

### Flow

```
Editor generates token in Gutenberg sidebar / Quick Edit / Classic Editor
  └─ plugin issues token → SHA-256 hash stored in wp_options (pvt_tk_{hash})
  └─ preview URL built: https://front.example.com/preview?token=<64-char-hex>

User opens the preview URL in browser

Frontend
  └─ GET /wp-json/preview-token/v1/preview?token=<token>

WordPress
  └─ hashes the token, looks up wp_options key
  └─ validates expiry
  └─ returns post data (WP REST API format)

Frontend renders preview
```

### Token

| Property   | Value                                                              |
|------------|--------------------------------------------------------------------|
| Generation | `bin2hex(random_bytes(32))` — 256-bit CSPRNG, 64-char hex          |
| Storage    | `wp_options` key = `pvt_tk_` + `sha256(token)` (O(1) lookup)      |
| Expiry     | Configurable: 1 h / 24 h / 30 d / custom datetime / no-expiry     |
| Reuse      | Allowed within validity window                                     |
| On expiry  | `401 Unauthorized` (same as invalid)                               |

The lookup key stored in `wp_options` is the SHA-256 hash of the raw token, not the token itself — a database leak does not expose usable token strings directly.

Reuse within the validity window is intentional — editors commonly reload the preview tab or check multiple viewports.

### REST Endpoints

**Preview (public)**
```
GET /wp-json/preview-token/v1/preview?token=<token>
```

| Status | Condition                |
|--------|--------------------------|
| `200`  | Valid token, post found  |
| `401`  | Invalid or expired token |
| `400`  | Missing token parameter  |
| `403`  | HTTPS required           |
| `404`  | Post not found           |
| `429`  | Rate limit exceeded      |

**Token management (authenticated)**
```
POST   /wp-json/preview-token/v1/token   # issue
GET    /wp-json/preview-token/v1/token   # get current token for a post
PATCH  /wp-json/preview-token/v1/token   # update expiry only
DELETE /wp-json/preview-token/v1/token   # revoke
```

The preview response body matches the standard WordPress REST API post format (`/wp/v2/posts/{id}`). The following fields are removed before the response is returned:

| Removed field | Reason                  |
|---------------|-------------------------|
| `password`    | Sensitive               |
| `guid`        | Internal WP field       |
| `ping_status` | Not relevant to preview |
| `template`    | Not relevant to preview |

Additional fields can be removed via the `pvt_preview_response_data` filter.

### CORS

Allowed origins are configured in **Settings → Preview Token → Allowed Origins (CORS)**. Multiple origins and wildcard patterns (`https://*.example.com`) are supported. The plugin runs at `rest_pre_serve_request` priority 11, overriding WordPress core's unconditional echo-back for non-allowed origins.

---

## Get Started

### Requirements

- PHP 7.4+
- WordPress 5.9+
- Composer

### Installation

```bash
git clone https://github.com/uuki/preview-token
cd preview-token
composer install --no-dev
```

Copy (or symlink) the plugin directory to `wp-content/plugins/preview-token` and activate it from **Plugins** in the WordPress admin.

### Configuration

Go to **Settings → Preview Token**.

| Setting                | Description                                                                           |
|------------------------|---------------------------------------------------------------------------------------|
| External Preview URL   | Base URL of the frontend. Clicking "Open external preview" navigates directly here.  |
| Allowed Origins (CORS) | One origin per line. Wildcards supported (`https://*.example.com`). Leave empty to disable CORS headers. |
| Minimum Capability     | Minimum WordPress role required to issue tokens. Default: `contributor`.              |
| Rate Limit             | Maximum requests per IP per window. Default: 30 req / 60 s.                          |
| Allow No-Expiry Tokens | Permit tokens with no expiry date. Disabled by default.                               |
| Skip HTTPS Check       | Bypass HTTPS enforcement. Development environments only.                              |

### Frontend Integration

Read `token` from the incoming URL and forward it to the REST endpoint. No credentials are needed on the frontend side.

```typescript
// Astro: src/pages/preview.astro
const token = Astro.url.searchParams.get('token');

if (!token) return Astro.redirect('/404');

const res = await fetch(
  `https://wp.example.com/wp-json/preview-token/v1/preview?token=${token}`
);

if (!res.ok) return Astro.redirect('/404');

const post = await res.json();
```
