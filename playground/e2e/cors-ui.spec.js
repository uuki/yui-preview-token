import { test, expect } from '@playwright/test';
const WP = 'http://127.0.0.1:9400';

test.describe('CORS origins UI', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${WP}/wp-login.php`);
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForURL(`${WP}/wp-admin/**`);
        await page.goto(`${WP}/wp-admin/options-general.php?page=preview-token`);
    });

    test('shows at least one origin input row', async ({ page }) => {
        const rows = page.locator('.pvt-origin-row');
        await expect(rows.first()).toBeVisible();
    });

    test('Add origin button appends a new input row', async ({ page }) => {
        const before = await page.locator('.pvt-origin-row').count();
        await page.getByRole('button', { name: /add origin/i }).click();
        const after = await page.locator('.pvt-origin-row').count();
        expect(after).toBe(before + 1);
    });

    test('remove button clears last row instead of deleting it', async ({ page }) => {
        // Ensure only one row exists by removing extras first
        while (await page.locator('.pvt-origin-row').count() > 1) {
            await page.locator('.pvt-remove-origin').last().click();
        }
        const input = page.locator('.pvt-origin-row input').first();
        await input.fill('https://example.com');
        await page.locator('.pvt-remove-origin').first().click();
        await expect(input).toHaveValue('');
        await expect(page.locator('.pvt-origin-row')).toHaveCount(1);
    });

    test('saves multiple origins and reloads them as separate inputs', async ({ page }) => {
        // Remove all but one row
        while (await page.locator('.pvt-origin-row').count() > 1) {
            await page.locator('.pvt-remove-origin').last().click();
        }

        // Fill first row
        await page.locator('.pvt-origin-row input').first().fill('http://localhost:4321');

        // Add and fill second row
        await page.getByRole('button', { name: /add origin/i }).click();
        await page.locator('.pvt-origin-row input').nth(1).fill('https://*.amplifyapp.com');

        await page.getByRole('button', { name: /save changes/i }).click();
        await page.waitForURL(`${WP}/wp-admin/options-general.php*`);

        const inputs = page.locator('.pvt-origin-row input');
        await expect(inputs).toHaveCount(2);
        await expect(inputs.nth(0)).toHaveValue('http://localhost:4321');
        await expect(inputs.nth(1)).toHaveValue('https://*.amplifyapp.com');
    });
});
