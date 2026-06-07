# Preview Token

A WordPress plugin that issues time-limited preview tokens for headless setups. External frontends (Astro, Next.js, Nuxt, SvelteKit, etc.) can fetch draft content via the REST API without storing long-lived credentials.

## How it works

1. An editor generates a token from the Gutenberg sidebar, Quick Edit panel, or Classic Editor meta box.
2. The plugin embeds the token in a preview URL pointing to the configured frontend.
3. The frontend calls `/wp-json/preview-token/v1/preview?token=…` to fetch the draft post.
4. The token expires automatically.

Tokens are generated with `bin2hex(random_bytes(32))` (256-bit CSPRNG). The `wp_options` lookup key is the SHA-256 hash of the raw token — a database leak does not expose usable tokens.

## Requirements

- PHP 7.4+
- WordPress 5.9+

## Installation

Download the latest zip from [Releases](https://github.com/uuki/preview-token/releases) and install via **Plugins → Add New → Upload Plugin**, or extract to `wp-content/plugins/preview-token/`.

## Repository structure

```
.
├── .github/                    # CI/CD — GitHub Actions workflows
├── .husky/                     # Git hooks (commitlint on commit-msg, PHPUnit on pre-commit)
├── assets/                     # WP.org listing assets → synced to SVN assets/ (not installed)
│   ├── banner-*.png
│   ├── icon-*.png
│   └── screenshot-*.png
├── bin/
│   ├── bump-version.sh         # Version string updater called by semantic-release
│   └── publish.sh              # Builds and packages the distribution zip
├── docs/
│   ├── readme-ja.txt           # Japanese readme (non-standard, not required by WP.org)
│   └── guide.*.md              # Developer guides (en/ja)
├── playground/                 # Local dev environment — WP Playground + Playwright E2E
│   ├── blueprint.json          # WP Playground setup (plugin activation, option fixtures)
│   ├── e2e/                    # Playwright test specs
│   └── index.html              # Vite preview frontend for manual token testing
├── plugin/                     # WordPress plugin source → synced to SVN trunk/ on release
│   ├── assets/js/              # Compiled IIFE bundles (gitignored — build artifact)
│   ├── languages/              # i18n .po / .mo / .pot (ja, zh_CN)
│   ├── src/
│   │   ├── assets/js/          # TypeScript source files
│   │   ├── WordPress/          # Core plugin PHP classes
│   │   ├── Token/              # Token issuance and validation
│   │   └── Support/            # Response pipeline and filters
│   ├── tests/                  # PHPUnit unit tests
│   ├── vendor/                 # Composer dependencies (gitignored)
│   ├── preview-token.php       # Plugin entry point and header
│   ├── readme.txt              # WP.org plugin page (en) — part of trunk
│   ├── composer.json
│   ├── package.json            # JS build deps: tsdown, @wordpress/*
│   └── tsdown.config.ts        # TypeScript → IIFE bundle config
├── .releaserc.json             # semantic-release config (analyzes commits, bumps version)
├── commitlint.config.js        # Conventional Commits rule set
└── package.json                # Root tooling only: husky, commitlint, semantic-release
```

`assets/` (root) maps to SVN `assets/` — WP.org marketplace display only, never installed.
`plugin/` maps to SVN `trunk/` — the actual plugin files delivered to users.

## Development

### Prerequisites

- Node.js v20+, pnpm
- PHP 7.4+, Composer

### Setup

```bash
git clone https://github.com/uuki/preview-token.git
cd preview-token
composer install
pnpm install
```

### Build JavaScript bundles

```bash
pnpm run build
```

TypeScript sources are in `src/js/`. Compiled bundles go to `assets/js/`.

### Local development environment

```bash
pnpm run dev
```

Starts WP Playground (`http://127.0.0.1:9400`) and a Vite preview frontend (`http://localhost:5173`) concurrently. See [docs/playground.en.md](docs/playground.en.md) for details.

### Tests

```bash
# PHP unit tests
composer test

# End-to-end tests (requires dev servers running)
pnpm run test
```

### Production zip

```bash
pnpm run release   # → dist/preview-token.zip
```

## Documentation

- [Developer guide](docs/guide.en.md)
- [Playground setup](docs/playground.en.md)
- [開発者ガイド（日本語）](docs/guide.ja.md)
- [Playground（日本語）](docs/playground.ja.md)

## License

GPL-2.0-or-later — see the [WordPress.org plugin page](https://wordpress.org/plugins/preview-token/) for details.
