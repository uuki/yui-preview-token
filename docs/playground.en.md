# Playground

A local development environment for verifying the full preview flow in a browser, using WASM-based WordPress via `@wp-playground/cli` and a minimal Vite frontend.

---

## What It Does

- Boots a WordPress instance locally (no Docker, no PHP install required)
- Mounts the plugin from the local filesystem and activates it automatically
- Installs Plugin Check for running compliance checks in the admin UI
- Creates a draft post for testing on every startup
- Serves a minimal preview page via Vite that reads `?token=` and renders the draft post
- Routes `/wp-json` from Vite to WordPress Playground via proxy, so the browser makes no cross-origin requests

---

## Requirements

- Node.js v24+
- pnpm
- Composer (to generate `vendor/` before first boot)

---

## Setup

```bash
cd playground
pnpm install
pnpm exec playwright install chromium   # for e2e tests only
```

---

## Usage

### Start dev servers

Run from the **project root**:

```bash
pnpm run dev
```

This runs two processes concurrently:

| Process | URL | Role |
|---------|-----|------|
| WP Playground | `http://127.0.0.1:9400` | WordPress (admin + REST API) |
| Vite | `http://localhost:5173` | Preview frontend |

On first run, `predev` executes `composer install --no-dev` in the project root to ensure `vendor/` exists before WordPress boots.

WP Playground takes ~60 seconds on first boot (WASM initialization + blueprint execution). Subsequent starts are faster.

> **Note:** Login page labels vary by WP locale. Always use the stable element IDs `#user_login` and `#user_pass` in tests, not text-based selectors.

### What the blueprint sets up

Each time WP Playground starts, the blueprint automatically:

1. Activates the `preview-token` plugin (mounted from `../` as `preview-token/`)
2. Installs Plugin Check from WordPress.org
3. Creates the Classic Editor test fixture plugin (used by E2E tests)
4. Configures plugin settings:
   - **External Preview URL**: `http://localhost:5173`
   - **Allowed Origins (CORS)**: `http://localhost:4321`
   - **Minimum Capability**: `contributor`
   - **Rate Limit**: 60 req / 60 s
5. Creates a draft post titled `Draft: Preview Test`

State resets on every restart — the blueprint re-runs from scratch.

---

## Full Preview Flow

1. Open `http://127.0.0.1:9400/wp-admin` (admin / password)
2. Go to **Posts → All Posts**, open `Draft: Preview Test` in Gutenberg
3. In the **External Preview** sidebar panel, select an expiry and click **Generate token**
4. Click **Open external preview**
5. A new tab opens: `http://localhost:5173/preview?token=<64-char-hex>`
6. The preview page fetches the draft content via the REST API and renders it

Token management is also available from the **Quick Edit** panel in the post list and from the **Classic Editor** meta box when the Classic Editor plugin is active.

---

## E2E Tests

Tests are written with Playwright. Run from the project root:

```bash
pnpm run test          # headless
pnpm run test:ui       # Playwright UI (recommended for debugging)
pnpm run test:headed   # visible browser
```

Both servers must be reachable before running tests. If `pnpm run dev` is already running in another terminal, tests reuse those servers.

### Test suites

| File | What it tests |
|------|---------------|
| `preview.spec.js` | Preview page error states; Gutenberg / Quick Edit / Classic Editor full flows |
| `permissions.spec.js` | Role-based token issuance (subscriber → administrator) |
| `cors-ui.spec.js` | CORS origins dynamic input list in settings |
| `security.spec.js` | OWASP Top 10 black-box attack vectors (33 tests) |

### Known WP Playground constraints

| Issue | Cause | Workaround |
|-------|-------|------------|
| Gutenberg redirects to profile.php mid-load | Toolbar menu opens when expanding all `[aria-expanded="false"]` buttons | Expand only buttons inside `.editor-sidebar` |
| `waitForFunction` closes page context | WP Playground WASM destroys context during long-running JS polls | Use fixed `waitForTimeout` instead of polling in Gutenberg tests |
| Login labels vary by WP locale | WP translates form labels | Always use `#user_login` / `#user_pass` / `#wp-submit` |
| Plugin Check detects dev dotfiles | `plugin_basename()` calls `realpath()`, resolving symlinks to the project root | Run Plugin Check against `dist/preview-token.zip` only |
