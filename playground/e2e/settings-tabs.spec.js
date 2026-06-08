/**
 * Plugin settings page tab access tests.
 *
 * Verifies that both tabs of the plugin settings page are accessible
 * and render their expected content — not the WordPress permission-denied
 * error "Sorry, you are not allowed to access this page."
 *
 * Regression guard for the issue where options-general.php?page=yui-preview-token
 * returned a permission error in certain environments.
 */
import { test, expect } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';

async function loginAndGoTo(page, path) {
    await page.goto(`${WP}/wp-login.php`);
    await page.waitForLoadState('domcontentloaded');
    await page.locator('#user_login').pressSequentially('admin');
    await page.locator('#user_pass').pressSequentially('password');
    await page.click('#wp-submit');
    await page.waitForURL(`${WP}/wp-admin/**`);
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${WP}/wp-admin/`);
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${WP}${path}`);
    await page.waitForLoadState('domcontentloaded');
}

test.describe('Plugin settings page tabs', () => {
    test('Settings tab is accessible and not permission-denied', async ({ page }) => {
        await loginAndGoTo(page, '/wp-admin/options-general.php?page=yui-preview-token');

        // Must NOT show the WordPress permission error
        await expect(page.locator('body')).not.toContainText('Sorry, you are not allowed to access this page.');
        await expect(page.locator('body')).not.toContainText('権限がありません');

        // Must show the plugin settings heading
        await expect(page.locator('h1')).toContainText('YUI Preview Token');
    });

    test('Issued Tokens tab is accessible and not permission-denied', async ({ page }) => {
        await loginAndGoTo(page, '/wp-admin/options-general.php?page=yui-preview-token&tab=tokens');

        // Must NOT show the WordPress permission error
        await expect(page.locator('body')).not.toContainText('Sorry, you are not allowed to access this page.');
        await expect(page.locator('body')).not.toContainText('権限がありません');

        // Must show either the tokens table heading or the empty-state message
        const hasHeading  = await page.locator('h2').filter({ hasText: 'Issued Tokens' }).count();
        const hasEmptyMsg = await page.locator('p').filter({ hasText: 'No tokens have been issued yet.' }).count();
        expect(hasHeading + hasEmptyMsg).toBeGreaterThan(0);
    });
});
