/**
 * Playwright global setup — waits until the plugin settings page is
 * accessible before running any tests. This guards against a race where
 * the WP Playground HTTP server responds (satisfying webServer.url) before
 * the blueprint has finished activating the plugin.
 */
import { chromium } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';
const MAX_ATTEMPTS = 20;
const RETRY_MS     = 3_000;

export default async function globalSetup() {
    const browser = await chromium.launch();
    const page    = await browser.newPage();

    // Login
    await page.goto(`${WP}/wp-login.php`, { timeout: 30_000 });
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(`${WP}/wp-admin/**`, { timeout: 30_000 });
    await page.waitForLoadState('domcontentloaded');

    // Poll until plugin settings page responds without a permission error
    for (let i = 0; i < MAX_ATTEMPTS; i++) {
        await page.goto(`${WP}/wp-admin/options-general.php?page=yui-preview-token`);
        await page.waitForLoadState('domcontentloaded');
        const content = await page.content();
        if (!content.includes('not allowed to access') && !content.includes('権限がありません')) {
            console.log(`[globalSetup] plugin settings page ready after ${i + 1} attempt(s)`);
            break;
        }
        if (i === MAX_ATTEMPTS - 1) {
            console.warn('[globalSetup] plugin settings page never became accessible');
        }
        await page.waitForTimeout(RETRY_MS);
    }

    await browser.close();
}
