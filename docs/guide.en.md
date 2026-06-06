# WP Preview Token

## Overview

A WordPress plugin that issues short-lived preview tokens for headless frontends.

When an editor clicks **Preview** in the WordPress admin, the plugin generates a temporary token and opens the frontend in a new tab with the token in the URL. The frontend forwards that token to the plugin's REST endpoint, which validates it and returns the draft post data.

**What it solves:**

- Frontend applications access draft content without storing WordPress credentials
- Tokens expire in 5 minutes — leaked URLs have limited exposure
- Scope is preview-only; no broader WordPress permissions are granted
- The frontend handles only a URL parameter — no secrets in frontend code or config

---

## How It Works

### Flow

```
Editor clicks Preview in WP Admin
  └─ plugin issues token → stored as Transient (TTL 300s)
  └─ new tab opens: https://front.example.com/preview?token=<token>

Frontend receives request
  └─ GET /wp-json/wp-preview-token/v1/preview?token=<token>

WordPress
  └─ validates token via Transient lookup
  └─ returns post data (WP REST API format)

Frontend renders preview
```

### Token

| Property   | Value                                     |
|------------|-------------------------------------------|
| Generation | `bin2hex(random_bytes(32))` — 256-bit hex |
| Storage    | WordPress Transients                      |
| TTL        | 300 seconds                               |
| Reuse      | Allowed within TTL                        |
| On expiry  | `401 Unauthorized` (same as invalid)      |

Reuse within TTL is intentional — editors commonly reload the preview tab or check multiple viewports.

### REST Endpoint

```
GET /wp-json/wp-preview-token/v1/preview?token=<token>
```

| Status | Condition                |
|--------|--------------------------|
| `200`  | Valid token, post found  |
| `401`  | Invalid or expired token |
| `404`  | Post not found           |

The response body matches the standard WordPress REST API post format (`/wp/v2/posts/{id}`). The following fields are removed before the response is returned:

| Removed field | Reason                  |
|---------------|-------------------------|
| `password`    | Sensitive               |
| `guid`        | Internal WP field       |
| `ping_status` | Not relevant to preview |
| `template`    | Not relevant to preview |

Additional fields can be removed by registering custom filter functions in the `ResponsePipeline`.

### CORS

If an allowed origin is configured, the plugin adds `Access-Control-Allow-Origin` only to responses from the preview endpoint. No other WordPress REST routes are affected.

---

## Get Started

### Requirements

- PHP 7.4+
- WordPress 5.6+
- Composer

### Installation

```bash
git clone https://github.com/uuki/wp-preview-token
cd wp-preview-token
composer install --no-dev
```

Copy the plugin directory to `wp-content/plugins/wp-preview-token` and activate it from **Plugins** in the WordPress admin.

### Configuration

Go to **Settings → Preview Token**.

| Setting               | Description                                                                  |
|-----------------------|------------------------------------------------------------------------------|
| External Preview URL  | URL of the external client or frontend for rendering preview content (headless, decoupled, etc.) (e.g. `https://front.example.com/preview`) |
| Allowed Origin (CORS) | Origin to allow for CORS requests. Leave empty to skip CORS headers          |
| Minimum Capability    | Minimum capability required to issue a token. Default: `edit_posts`          |

### Frontend Integration

Read `token` from the incoming URL and forward it to the REST endpoint. No credentials are needed on the frontend side.

```typescript
// Astro: src/pages/preview.astro
const token = Astro.url.searchParams.get('token');

if (!token) return Astro.redirect('/404');

const res = await fetch(
  `https://wp.example.com/wp-json/wp-preview-token/v1/preview?token=${token}`
);

if (!res.ok) return Astro.redirect('/404');

const post = await res.json();
```
