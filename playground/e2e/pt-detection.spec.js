/**
 * E2E tests for pt parameter detection in preview_url.
 *
 * Verifies that the plugin resolves the correct pt value based on
 * WordPress Reading settings (Settings → Reading → Homepage displays):
 *   - pt=front_page  when the page is set as the static front page
 *   - pt=posts_page  when the page is set as the posts index page
 *   - pt=page        for any other regular draft page
 *
 * Setup: creates three draft pages, configures show_on_front=page with
 * two of them, then restores original settings and deletes pages in afterAll.
 */

import { test, expect } from '@playwright/test';

const WP     = 'http://127.0.0.1:9400';
const API    = `${WP}/wp-json/yui-preview-token/v1`;
const WP_API = `${WP}/wp-json/wp/v2`;

// ── Helpers ───────────────────────────────────────────────────────────────────

async function adminLogin(browser) {
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto(`${WP}/wp-login.php`);
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(`${WP}/wp-admin/**`);
    await page.goto(`${WP}/wp-admin/profile.php`);
    const nonce = await page.evaluate(() => window.wpApiSettings?.nonce ?? null);
    return { ctx, page, nonce };
}

async function createDraftPage(page, nonce, title) {
    const res = await page.request.post(`${WP_API}/pages`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { title, status: 'draft' },
    });
    expect(res.status()).toBe(201);
    return (await res.json()).id;
}

async function updateSiteSettings(page, nonce, settings) {
    const res = await page.request.post(`${WP_API}/settings`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: settings,
    });
    expect(res.status()).toBe(200);
}

async function deletePage(page, nonce, pageId) {
    await page.request.delete(`${WP_API}/pages/${pageId}?force=true`, {
        headers: { 'X-WP-Nonce': nonce },
    });
}

async function issueToken(page, nonce, postId) {
    return page.request.post(`${API}/token`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
            post_id:    postId,
            expires_at: Math.floor(Date.now() / 1000) + 3600,
        },
    });
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('pt parameter detection', () => {
    let adminCtx, adminPage, adminNonce;
    let homePageId, blogPageId, regularPageId;
    let originalSettings;

    test.beforeAll(async ({ browser }) => {
        ({ ctx: adminCtx, page: adminPage, nonce: adminNonce } = await adminLogin(browser));

        // Save original Reading settings so afterAll can restore them.
        const res = await adminPage.request.get(`${WP_API}/settings`, {
            headers: { 'X-WP-Nonce': adminNonce },
        });
        originalSettings = await res.json();

        // Create three draft pages: two for the Reading settings, one as a
        // control (regular page — must not be classified as front/posts page).
        homePageId    = await createDraftPage(adminPage, adminNonce, 'PT Test: Home');
        blogPageId    = await createDraftPage(adminPage, adminNonce, 'PT Test: Blog');
        regularPageId = await createDraftPage(adminPage, adminNonce, 'PT Test: About');

        // Configure WordPress Reading settings.
        await updateSiteSettings(adminPage, adminNonce, {
            show_on_front:  'page',
            page_on_front:  homePageId,
            page_for_posts: blogPageId,
        });
    });

    test.afterAll(async () => {
        // Restore Reading settings.
        await updateSiteSettings(adminPage, adminNonce, {
            show_on_front:  originalSettings.show_on_front  ?? 'posts',
            page_on_front:  originalSettings.page_on_front  ?? 0,
            page_for_posts: originalSettings.page_for_posts ?? 0,
        });

        // Remove created pages.
        for (const id of [homePageId, blogPageId, regularPageId]) {
            if (id) await deletePage(adminPage, adminNonce, id);
        }

        await adminCtx.close();
    });

    test('pt=front_page for the page configured as the static front page', async () => {
        const res  = await issueToken(adminPage, adminNonce, homePageId);
        expect(res.status()).toBeLessThan(300);
        const body = await res.json();
        expect(body.preview_url).toContain('pt=front_page');
        expect(body.preview_url).not.toContain('pt=page');
    });

    test('pt=posts_page for the page configured as the posts index', async () => {
        const res  = await issueToken(adminPage, adminNonce, blogPageId);
        expect(res.status()).toBeLessThan(300);
        const body = await res.json();
        expect(body.preview_url).toContain('pt=posts_page');
        expect(body.preview_url).not.toContain('pt=page');
    });

    test('pt=page for a regular draft page not assigned to any reading setting', async () => {
        const res  = await issueToken(adminPage, adminNonce, regularPageId);
        expect(res.status()).toBeLessThan(300);
        const body = await res.json();
        expect(body.preview_url).toContain('pt=page');
        expect(body.preview_url).not.toContain('pt=front_page');
        expect(body.preview_url).not.toContain('pt=posts_page');
    });
});
