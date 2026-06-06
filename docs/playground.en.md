# Playground

A local development environment for verifying the full preview flow in a browser, using WASM-based WordPress via `@wp-playground/cli` and a minimal Vite frontend.

---

## What It Does

- Boots a WordPress instance locally (no Docker, no PHP install required)
- Mounts the plugin from the local filesystem and activates it automatically
- Creates a draft post for testing on every startup
- Serves a minimal preview page via Vite that reads `?token=` and renders the draft post
- Routes `/wp-json` from Vite to WordPress Playground, so the browser makes no cross-origin requests

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

```bash
pnpm run dev
```

This runs two processes concurrently:

| Process | URL | Role |
|---------|-----|------|
| WP Playground | `http://localhost:9400` | WordPress (admin + REST API) |
| Vite | `http://localhost:5173` | Preview frontend |

On first run, `predev` executes `composer install --no-dev` in the project root to ensure `vendor/` exists before WordPress boots.

WP Playground takes ~60 seconds on first boot (WASM initialization + blueprint execution). Subsequent starts are faster.

### What the blueprint sets up

Each time WP Playground starts, the blueprint automatically:

1. Logs in as `admin` / `password`
2. Mounts the plugin from `../` into `wp-content/plugins/wp-preview-token`
3. Activates the plugin
4. Configures plugin settings:
   - **External Preview URL**: `http://localhost:5173`
   - **Allowed Origin**: `http://localhost:5173`
   - **Minimum Capability**: `edit_posts`
5. Creates a draft post titled `Draft: Preview Test`

State resets on every restart — the blueprint re-runs from scratch.

---

## Full Preview Flow

1. Open `http://localhost:9400/wp-admin` (admin / password)
2. Go to **Posts → All Posts**, open `Draft: Preview Test`
3. Click **Preview → Preview in new tab**
4. A new tab opens: `http://localhost:5173?token=<64-char-hex>`
5. The preview page fetches the draft content via the REST API and renders it

---

## E2E Tests

Tests are written with Playwright and cover three areas:

| Suite | What it tests |
|-------|---------------|
| `Preview page` | Error states when token is absent or invalid |
| `REST API` | `401` and `400` responses from the endpoint directly |
| `Full preview flow` | Complete browser flow from WP admin to rendered frontend |

### Run tests

Both servers must be reachable before running tests. If `pnpm run dev` is already running in another terminal, tests reuse those servers (`reuseExistingServer: true`). Otherwise, Playwright starts them automatically.

```bash
# Run all tests (headless)
pnpm run test

# Run with Playwright UI (interactive, recommended for debugging)
pnpm run test:ui

# Run headed (visible browser)
pnpm run test:headed
```

### Notes

- The full flow test navigates through the Gutenberg editor. Selectors use ARIA roles to minimize fragility, but may need updating on major WordPress releases.
- WP Playground resets state on each restart; `test-results/` and `playwright-report/` are gitignored.
