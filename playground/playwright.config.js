import { defineConfig, devices } from '@playwright/test';

// WP Playground sets site-url to 127.0.0.1 by default.
// Using localhost instead causes cookie domain mismatch and breaks WP login.
const WP_URL      = 'http://127.0.0.1:9400';
const FRONTEND_URL = 'http://localhost:5173';

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  retries: process.env.CI ? 1 : 0,

  use: {
    baseURL: FRONTEND_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: [
    {
      // WP Playground takes ~60s on first boot (WASM init + blueprint)
      command: 'pnpm run wp',
      url: WP_URL,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
    {
      command: 'pnpm run frontend',
      url: FRONTEND_URL,
      reuseExistingServer: !process.env.CI,
      timeout: 15_000,
    },
  ],
});
