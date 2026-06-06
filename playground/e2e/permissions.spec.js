/**
 * Token creation permission tests by WordPress role.
 *
 * Uses cookie-based authentication + X-WP-Nonce obtained from the wp-admin
 * dashboard. All roles (including subscriber) can access /wp-admin/ and
 * receive wpApiSettings.nonce on that page.
 *
 * Capability table (WordPress core):
 *   administrator : edit_posts ✓  edit_others_posts ✓  manage_options ✓
 *   editor        : edit_posts ✓  edit_others_posts ✓  manage_options –
 *   author        : edit_posts ✓  edit_others_posts –  manage_options –
 *   contributor   : edit_posts ✓  edit_others_posts –  manage_options –
 *   subscriber    : edit_posts –  edit_others_posts –  manage_options –
 *
 * Note: `user_can($user, 'edit_posts', $post_id)` checks the PRIMITIVE
 * capability — post ownership is NOT considered for plural caps. So
 * author/contributor both have 'edit_posts', making them eligible.
 */

import { test, expect } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';

const ALL_USERS = [
    'admin',
    'test_editor',
    'test_author',
    'test_contributor',
    'test_subscriber',
];

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Login as a user and return { page, nonce, ctx }.
 * All WP roles can access /wp-admin/ and receive wpApiSettings.nonce there.
 */
async function loginAndGetNonce(browser, username) {
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();

    await page.goto(`${WP}/wp-login.php`);
    await page.fill('#user_login', username);
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');

    // WordPress redirects subscribers to the home page (no edit_posts capability),
    // while other roles go to /wp-admin/. Wait for any redirect away from login page.
    await page.waitForURL(url => !url.href.includes('wp-login.php'), { timeout: 20_000 });

    // Navigate to profile.php — accessible to ALL roles (including subscriber),
    // and always loads wp-api-request with wpApiSettings.nonce.
    await page.goto(`${WP}/wp-admin/profile.php`);

    const nonce = await page.evaluate(() => window.wpApiSettings?.nonce ?? null);
    if (!nonce) {
        // Fallback: try to get nonce via user meta page (sometimes more reliable)
        await page.goto(`${WP}/wp-admin/`);
        const fallbackNonce = await page.evaluate(() => window.wpApiSettings?.nonce ?? null);
        console.log(`[${username}] profile nonce null, dashboard nonce: ${fallbackNonce ? 'ok' : 'null'} url=${page.url()}`);
        return { page, nonce: fallbackNonce, ctx };
    }
    return { page, nonce, ctx };
}

/**
 * POST to the token endpoint from within the browser context (shares cookies).
 */
async function tryCreateToken(page, nonce, postId) {
    return page.evaluate(async ({ wptUrl, n, pid, exp }) => {
        const r = await fetch(wptUrl, {
            method: 'POST',
            headers: { 'X-WP-Nonce': n, 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: pid, expires_at: exp }),
        });
        const body = await r.json().catch(() => ({}));
        return { status: r.status, code: body.code ?? null, message: body.message ?? null };
    }, {
        wptUrl: `${WP}/wp-json/wp-preview-token/v1/token`,
        n: nonce,
        pid: postId,
        exp: Math.floor(Date.now() / 1000) + 3600,
    });
}

// ── Suite ─────────────────────────────────────────────────────────────────────

test.describe('Token creation permissions by WordPress role', () => {
    let testPostId;

    /** Get the admin's draft post ID (created by blueprint). */
    test.beforeAll(async ({ browser }) => {
        test.setTimeout(60_000);
        const { page, nonce, ctx } = await loginAndGetNonce(browser, 'admin');

        // Ensure test users exist (idempotent: 409 = already exists → ok).
        // Created here rather than in blueprint to avoid wp-cli failure on re-runs.
        const testUsers = [
            { username: 'test_editor',      email: 'editor@test.local',      password: 'password', roles: ['editor'] },
            { username: 'test_author',       email: 'author@test.local',      password: 'password', roles: ['author'] },
            { username: 'test_contributor',  email: 'contributor@test.local', password: 'password', roles: ['contributor'] },
            { username: 'test_subscriber',   email: 'subscriber@test.local',  password: 'password', roles: ['subscriber'] },
        ];
        for (const userData of testUsers) {
            await page.request.post(`${WP}/wp-json/wp/v2/users`, {
                headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                data: userData,
            });
            // Intentionally ignore response: 201 = created, 409 = already exists
        }

        const postsRes = await page.request.get(
            `${WP}/wp-json/wp/v2/posts?status=draft&per_page=1&orderby=date&order=desc`,
            { headers: { 'X-WP-Nonce': nonce } }
        );
        const posts = await postsRes.json();
        testPostId = posts[0]?.id;
        expect(testPostId, 'Draft post must exist').toBeGreaterThan(0);

        await ctx.close();
    });

    // ── Scenario 1: edit_posts minimum (default) ──────────────────────────────

    test.describe('minimum role: contributor (default)', () => {
        /**
         * contributor → mapped to edit_posts (primitive capability).
         * All roles that have edit_posts (contributor and above) → 201; subscriber → 403.
         */
        const EXPECTED = {
            admin:            201,
            test_editor:      201,
            test_author:      201,
            test_contributor: 201,
            test_subscriber:  403,
        };

        for (const username of ALL_USERS) {
            const role = username.replace('test_', '');
            test(`${role} → HTTP ${EXPECTED[username]}`, async ({ browser }) => {
                test.setTimeout(45_000);
                const { page, nonce, ctx } = await loginAndGetNonce(browser, username);
                try {
                    const result = await tryCreateToken(page, nonce, testPostId);
                    if (result.status !== EXPECTED[username]) {
                        console.log(`[${username}] status=${result.status} code=${result.code} msg=${result.message}`);
                    }
                    expect(result.status).toBe(EXPECTED[username]);
                } finally {
                    await ctx.close();
                }
            });
        }
    });

    // ── Scenario 2: manage_options minimum ────────────────────────────────────

    test.describe('minimum role: administrator', () => {
        /**
         * administrator → mapped to manage_options (administrator-only capability).
         * Expected: admin → 201; all other roles → 403
         */
        const EXPECTED = {
            admin:            201,
            test_editor:      403,
            test_author:      403,
            test_contributor: 403,
            test_subscriber:  403,
        };

        test.beforeAll(async ({ browser }) => {
            test.setTimeout(45_000);
            const { page, ctx } = await loginAndGetNonce(browser, 'admin');
            await page.goto(`${WP}/wp-admin/options-general.php?page=wp-preview-token`);
            await page.selectOption('select[name="wpt_min_capability"]', 'administrator');
            await page.getByRole('button', { name: /save changes/i }).click();
            await page.waitForURL(`${WP}/wp-admin/options-general.php*`);
            await ctx.close();
        });

        test.afterAll(async ({ browser }) => {
            test.setTimeout(45_000);
            const { page, ctx } = await loginAndGetNonce(browser, 'admin');
            await page.goto(`${WP}/wp-admin/options-general.php?page=wp-preview-token`);
            await page.selectOption('select[name="wpt_min_capability"]', 'contributor');
            await page.getByRole('button', { name: /save changes/i }).click();
            await page.waitForURL(`${WP}/wp-admin/options-general.php*`);
            await ctx.close();
        });

        for (const username of ALL_USERS) {
            const role = username.replace('test_', '');
            test(`${role} → HTTP ${EXPECTED[username]}`, async ({ browser }) => {
                test.setTimeout(45_000);
                const { page, nonce, ctx } = await loginAndGetNonce(browser, username);
                try {
                    const result = await tryCreateToken(page, nonce, testPostId);
                    if (result.status !== EXPECTED[username]) {
                        console.log(`[${username}] status=${result.status} code=${result.code} msg=${result.message}`);
                    }
                    expect(result.status).toBe(EXPECTED[username]);
                } finally {
                    await ctx.close();
                }
            });
        }
    });
});
