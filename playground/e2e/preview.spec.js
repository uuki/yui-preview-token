import { test, expect } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';

// ── Shared helper ────────────────────────────────────────────────────────────

async function loginToAdmin(page) {
    await page.goto(`${WP}/wp-login.php`);
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(`${WP}/wp-admin/**`);
    // Navigate to wp-admin/ and wait for full load — ensures session is
    // fully established before subsequent navigations (mirrors url-debug.spec.js).
    await page.goto(`${WP}/wp-admin/`);
    await page.waitForLoadState('domcontentloaded');
}

// ── Preview page (Vite frontend) ─────────────────────────────────────────────

test.describe('Preview page', () => {
    test('shows error when no token in URL', async ({ page }) => {
        await page.goto('http://localhost:5173/preview');
        await expect(page.locator('#status.error')).toBeVisible({ timeout: 10_000 });
    });

    test('shows error for an invalid token', async ({ page }) => {
        await page.goto('http://localhost:5173/preview?token=invalid');
        await expect(page.locator('#status.error')).toBeVisible({ timeout: 10_000 });
    });
});

// ── REST API ──────────────────────────────────────────────────────────────────

test.describe('REST API', () => {
    test('returns 401 for an invalid token', async ({ request }) => {
        const res = await request.get(`${WP}/wp-json/yui-preview-token/v1/preview?token=invalid`);
        expect(res.status()).toBe(401);
    });

    test('returns 400 when token param is missing', async ({ request }) => {
        const res = await request.get(`${WP}/wp-json/yui-preview-token/v1/preview`);
        expect(res.status()).toBe(400);
    });
});

// ── Gutenberg sidebar ─────────────────────────────────────────────────────────

test.describe('Gutenberg sidebar preview', () => {
    test('generate token and open frontend preview from block editor', async ({
        page,
        context,
    }) => {
        test.setTimeout(90_000);

        // Login — inline to exactly mirror url-debug.spec.js which reliably
        // navigates to Gutenberg without WP Playground redirects.
        await page.goto(`${WP}/wp-login.php`);
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForURL(`${WP}/wp-admin/**`);
        await page.goto(`${WP}/wp-admin/`);
        await page.waitForLoadState('domcontentloaded');

        // Get edit href from post list
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post&author=1`);
        await page.waitForLoadState('domcontentloaded');
        const editHref = await page.locator('#the-list tr[id^="post-"]').first()
            .locator('a.row-title').getAttribute('href');

        // Navigate to Gutenberg editor
        await page.goto(editHref);
        await page.waitForLoadState('domcontentloaded');
        // Use only fixed waits — no Playwright polling that can close WP Playground context.

        // Wait for Gutenberg to initialize
        await page.waitForTimeout(3000);

        // Dismiss tutorial dialog if present (don't click all aria-expanded buttons
        // as that would accidentally open Gutenberg toolbar menus)
        await page.evaluate(() => {
            const dlgBtn = document.querySelector('[role="dialog"] button[aria-label]');
            if (dlgBtn) dlgBtn.click();
            // Only expand sidebar panel buttons (not toolbar buttons)
            const sidebar = document.querySelector('.editor-sidebar, .interface-complementary-area');
            if (sidebar) {
                sidebar.querySelectorAll('[aria-expanded="false"]').forEach(btn => {
                    if (btn instanceof HTMLElement) btn.click();
                });
            }
        }).catch(() => null);

        await page.waitForTimeout(1000);

        // Check if generate button exists and click it
        const hasGenerate = await page.evaluate(() =>
            !!document.querySelector('[data-yuipt-action="generate"]')
        ).catch(() => false);

        if (hasGenerate) {
            await page.evaluate(() => {
                const span = document.querySelector('[data-yuipt-action="generate"]');
                if (span) (span.querySelector('button') ?? span).click();
            }).catch(() => null);
            // Wait for token generation (fixed wait — no polling)
            await page.waitForTimeout(4000);
        }

        // Click preview button — Gutenberg calls window.open() internally after auto-save
        const popupPromise = context.waitForEvent('page');
        await page.evaluate(() => {
            const btn = document.querySelector('[data-yuipt-action="preview"] button');
            if (btn) btn.click();
        }).catch(() => null);
        const previewPage = await popupPromise;
        const previewUrl = previewPage.url();

        await previewPage.waitForURL(/[?&]token=[0-9a-f]{64}/, { timeout: 30_000 });
        expect(previewPage.url()).toMatch(/[?&]token=[0-9a-f]{64}/);
        await expect(previewPage.locator('#title')).toContainText('Draft: Preview Test');
        await expect(previewPage.locator('#content')).toContainText('draft');

        await context.close();
    });
});

// ── Quick Edit panel ─────────────────────────────────────────────────────────

test.describe('Quick Edit token panel', () => {
    test('renders token UI inside Quick Edit and generates a token', async ({ page }) => {
        test.setTimeout(60_000);

        await loginToAdmin(page);
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post`);

        const postId = await page.evaluate(() => {
            const row = document.querySelector('#the-list tr[id^="post-"]');
            return row ? parseInt(row.id.replace('post-', ''), 10) : 0;
        });
        expect(postId).toBeGreaterThan(0);

        await page.evaluate(() => {
            jQuery('#the-list .editinline').first().trigger('click');
        });

        // Wait for Quick Edit form to open
        await page.waitForFunction(() => {
            const rows = document.querySelectorAll('#the-list tr[id^="edit-"]');
            return rows.length > 0;
        }, { timeout: 15_000 });

        // Wait for PVT panel to finish loading
        await page.waitForFunction(() => {
            const panel = document.querySelector('.yuipt-quick-edit-root [data-yuipt-panel]');
            return panel && panel.getAttribute('data-yuipt-panel') !== 'loading';
        }, { timeout: 20_000 });

        await page.waitForFunction(() =>
            !!document.querySelector('.yuipt-quick-edit-root [data-yuipt-action="generate"], .yuipt-quick-edit-root [data-yuipt-action="preview"]'),
            { timeout: 5_000 }
        );

        const hasGenerate = await page.evaluate(() =>
            !!document.querySelector('.yuipt-quick-edit-root [data-yuipt-action="generate"]')
        );

        if (hasGenerate) {
            await page.evaluate(() => {
                const span = document.querySelector('.yuipt-quick-edit-root [data-yuipt-action="generate"]');
                (span?.querySelector('button') ?? span)?.click();
            });
            await page.waitForFunction(() =>
                !!document.querySelector('.yuipt-quick-edit-root [data-yuipt-action="preview"]'),
                { timeout: 10_000 }
            );
        }

        const href = await page.evaluate(() => {
            const span = document.querySelector('.yuipt-quick-edit-root [data-yuipt-action="preview"]');
            return (span?.querySelector('a') ?? span)?.href ?? '';
        });
        expect(href).toMatch(/[?&]token=[0-9a-f]{64}/);
    });
});

// ── Classic Editor token panel ───────────────────────────────────────────────

test.describe('Classic Editor token panel', () => {
    const FIXTURE_SLUG = 'classic-editor-test-fixture';

    test.beforeAll(async ({ browser }) => {
        const page = await browser.newPage();
        await loginToAdmin(page);
        await page.goto(`${WP}/wp-admin/plugins.php`);
        const activateLink = page.locator(`[data-slug="${FIXTURE_SLUG}"] .activate a`);
        if (await activateLink.count() > 0) {
            await activateLink.click();
            await page.waitForURL(`${WP}/wp-admin/plugins.php*`);
        }
        await page.close();
    });

    test.afterAll(async ({ browser }) => {
        const page = await browser.newPage();
        await loginToAdmin(page);
        await page.goto(`${WP}/wp-admin/plugins.php`);
        const deactivateLink = page.locator(`[data-slug="${FIXTURE_SLUG}"] .deactivate a`);
        if (await deactivateLink.count() > 0) {
            await deactivateLink.click();
            await page.waitForURL(`${WP}/wp-admin/plugins.php*`);
        }
        await page.close();
    });

    test('shows PVT panel in Classic Editor and generates a token', async ({ page }) => {
        test.setTimeout(60_000);

        await loginToAdmin(page);

        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post`);
        const editHref = await page.locator('#the-list tr').first().locator('a.row-title').getAttribute('href');
        await page.goto(editHref + '&classic-editor');
        await page.waitForURL(`${WP}/wp-admin/post.php?post=*&action=edit*`);

        // Wait for PVT meta box panel to finish loading
        await page.waitForFunction(() => {
            const panel = document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-panel]');
            return panel && panel.getAttribute('data-yuipt-panel') !== 'loading';
        }, { timeout: 20_000 });

        await page.waitForFunction(() =>
            !!document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-action="generate"], #yuipt-classic-meta-box-root [data-yuipt-action="preview"]'),
            { timeout: 5_000 }
        );

        const hasGenerate = await page.evaluate(() =>
            !!document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-action="generate"]')
        );

        if (hasGenerate) {
            await page.evaluate(() => {
                const span = document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-action="generate"]');
                (span?.querySelector('button') ?? span)?.click();
            });
            await page.waitForFunction(() =>
                !!document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-action="preview"]'),
                { timeout: 10_000 }
            );
        }

        const href = await page.evaluate(() => {
            const span = document.querySelector('#yuipt-classic-meta-box-root [data-yuipt-action="preview"]');
            return (span?.querySelector('a') ?? span)?.href ?? '';
        });
        expect(href).toMatch(/[?&]token=[0-9a-f]{64}/);
    });
});
