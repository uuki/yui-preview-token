import { test, expect } from '@playwright/test';
const WP = 'http://127.0.0.1:9400';

test.describe('CORS origins UI', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${WP}/wp-login.php`);
        await page.waitForLoadState('domcontentloaded');
        // Use pressSequentially to prevent browser autofill from overwriting values
        await page.locator('#user_login').pressSequentially('admin');
        await page.locator('#user_pass').pressSequentially('password');
        await page.click('#wp-submit');
        await page.waitForURL(`${WP}/wp-admin/**`);
        await page.waitForLoadState('domcontentloaded');
        // Navigate via dashboard first to ensure WP admin is fully initialized
        await page.goto(`${WP}/wp-admin/`);
        await page.waitForLoadState('domcontentloaded');
        await page.goto(`${WP}/wp-admin/admin.php?page=yui-preview-token`);
        await page.waitForLoadState('domcontentloaded');
    });

    test('shows at least one origin input row', async ({ page }) => {
        const rows = page.locator('.yuipt-origin-row');
        await expect(rows.first()).toBeVisible();
    });

    test('Add origin button appends a new input row', async ({ page }) => {
        const before = await page.locator('.yuipt-origin-row').count();
        await page.getByRole('button', { name: /add origin/i }).click();
        const after = await page.locator('.yuipt-origin-row').count();
        expect(after).toBe(before + 1);
    });

    test('remove button clears last row instead of deleting it', async ({ page }) => {
        // Ensure only one row exists by removing extras first
        while (await page.locator('.yuipt-origin-row').count() > 1) {
            await page.locator('.yuipt-remove-origin').last().click();
        }
        const input = page.locator('.yuipt-origin-row input').first();
        await input.fill('https://example.com');
        await page.locator('.yuipt-remove-origin').first().click();
        await expect(input).toHaveValue('');
        await expect(page.locator('.yuipt-origin-row')).toHaveCount(1);
    });

    test('saves multiple origins and reloads them as separate inputs', async ({ page }) => {
        // Remove all but one row
        while (await page.locator('.yuipt-origin-row').count() > 1) {
            await page.locator('.yuipt-remove-origin').last().click();
        }

        // Fill first row
        await page.locator('.yuipt-origin-row input').first().fill('http://localhost:4321');

        // Add and fill second row
        await page.getByRole('button', { name: /add origin/i }).click();
        await page.locator('.yuipt-origin-row input').nth(1).fill('https://*.amplifyapp.com');

        await page.getByRole('button', { name: /save changes/i }).click();
        await page.waitForURL(`${WP}/wp-admin/options-general.php*`);

        const inputs = page.locator('.yuipt-origin-row input');
        await expect(inputs).toHaveCount(2);
        await expect(inputs.nth(0)).toHaveValue('http://localhost:4321');
        await expect(inputs.nth(1)).toHaveValue('https://*.amplifyapp.com');
    });
});
