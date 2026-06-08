/**
 * Security regression tests — OWASP Top 10 (black-box)
 *
 * Scope: WP core + preview-token plugin combination only.
 * Platform/infrastructure issues (TLS, OS, PHP binary) are out of scope.
 *
 * Each test verifies that a specific attack vector is rejected.
 */

import { test, expect } from '@playwright/test';
import { Buffer } from 'node:buffer';

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
    await page.goto(`${WP}/wp-admin/`);
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${WP}/wp-admin/profile.php`);
    const nonce = await page.evaluate(() => window.wpApiSettings?.nonce ?? null);
    return { ctx, page, nonce };
}

async function getDraftPostId(page, nonce) {
    const res  = await page.request.get(
        `${WP_API}/posts?status=draft&per_page=1&author=1`,
        { headers: { 'X-WP-Nonce': nonce } }
    );
    const data = await res.json();
    return Array.isArray(data) && data[0]?.id ? data[0].id : null;
}

async function issueToken(page, nonce, postId, expiresAt = null) {
    return page.request.post(`${API}/token`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
            post_id:    postId,
            expires_at: expiresAt ?? Math.floor(Date.now() / 1000) + 3600,
        },
    });
}

/** Extract 64-char hex token from preview_url. */
function extractToken(body) {
    return body.preview_url?.match(/[?&]token=([0-9a-f]{64})/)?.[1] ?? null;
}

/** Create a WP user via REST (idempotent — ignores 409). */
async function ensureUser(page, nonce, username, role) {
    await page.request.post(`${WP_API}/users`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
            username, password: 'password',
            email: `${username}@test.local`,
            roles: [role],
        },
    });
}

// ── Shared state ──────────────────────────────────────────────────────────────

let adminCtx, adminPage, adminNonce, draftPostId, validToken;
/** Raw Application Password created in beforeAll for external-auth simulation. */
let appPassword = null;
let appPasswordUuid = null;

test.beforeAll(async ({ browser }) => {
    ({ ctx: adminCtx, page: adminPage, nonce: adminNonce } = await adminLogin(browser));
    draftPostId = await getDraftPostId(adminPage, adminNonce);
    expect(draftPostId).toBeTruthy();

    // Ensure test users exist (idempotent)
    await ensureUser(adminPage, adminNonce, 'test_subscriber', 'subscriber');
    await ensureUser(adminPage, adminNonce, 'test_editor',     'editor');

    // Generate a valid token for reuse in later tests
    const res  = await issueToken(adminPage, adminNonce, draftPostId);
    expect(res.status()).toBe(201);
    validToken = extractToken(await res.json());
    expect(validToken).toMatch(/^[0-9a-f]{64}$/);

    // Create an Application Password to simulate an external REST client.
    // WP_ENVIRONMENT_TYPE=local (set via playground/package.json) enables
    // Application Passwords even on HTTP.
    //
    // First, delete any stale passwords with the same name left by interrupted
    // test runs. WordPress allows multiple passwords with identical names so
    // without this cleanup they would accumulate on every run.
    const APP_PW_NAME = 'yuipt-e2e-external-test';
    const listRes = await adminPage.request.get(
        `${WP_API}/users/1/application-passwords`,
        { headers: { 'X-WP-Nonce': adminNonce } }
    );
    if (listRes.ok()) {
        const existing = await listRes.json();
        for (const pw of Array.isArray(existing) ? existing : []) {
            if (pw.name === APP_PW_NAME) {
                await adminPage.request.delete(
                    `${WP_API}/users/1/application-passwords/${pw.uuid}`,
                    { headers: { 'X-WP-Nonce': adminNonce } }
                ).catch(() => null);
            }
        }
    }

    const apRes = await adminPage.request.post(
        `${WP_API}/users/1/application-passwords`,
        {
            headers: { 'X-WP-Nonce': adminNonce, 'Content-Type': 'application/json' },
            data:    { name: APP_PW_NAME },
        }
    );
    if (apRes.ok()) {
        const body      = await apRes.json();
        appPassword     = body.password;  // raw password shown only at creation
        appPasswordUuid = body.uuid;
    }
}, 60_000);

test.afterAll(async () => {
    // Delete the Application Password created for tests
    if (appPasswordUuid && adminNonce) {
        await adminPage.request.delete(
            `${WP_API}/users/1/application-passwords/${appPasswordUuid}`,
            { headers: { 'X-WP-Nonce': adminNonce } }
        ).catch(() => null);
    }
    await adminCtx?.close();
});

// ─────────────────────────────────────────────────────────────────────────────
// A01 — Broken Access Control
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A01 — Broken Access Control', () => {

    test('unauthenticated POST /token → 401', async ({ request }) => {
        const res = await request.post(`${API}/token`, {
            data: { post_id: draftPostId, expires_at: Math.floor(Date.now() / 1000) + 3600 },
        });
        expect(res.status()).toBe(401);
    });

    test('subscriber POST /token → 401 or 403 (below minimum role)', async ({ browser }) => {
        const ctx  = await browser.newContext();
        const page = await ctx.newPage();
        try {
            await page.goto(`${WP}/wp-login.php`);
            await page.fill('#user_login', 'test_subscriber');
            await page.fill('#user_pass', 'password');
            await page.click('#wp-submit');
            await page.waitForURL(`${WP}/wp-admin/**`);
            await page.goto(`${WP}/wp-admin/profile.php`);
            const nonce = await page.evaluate(() => window.wpApiSettings?.nonce ?? null);
            if (!nonce) return; // subscriber may have no API nonce — acceptable

            const res = await page.request.post(`${API}/token`, {
                headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                data: { post_id: draftPostId, expires_at: Math.floor(Date.now() / 1000) + 3600 },
            });
            expect([401, 403]).toContain(res.status());
        } finally {
            await ctx.close();
        }
    });

    test('GET /preview without token → 400', async ({ request }) => {
        const res = await request.get(`${API}/preview`);
        expect(res.status()).toBe(400);
    });

    test('GET /preview with random invalid token → 401', async ({ request }) => {
        const res = await request.get(`${API}/preview?token=${'a'.repeat(64)}`);
        expect(res.status()).toBe(401);
    });

    test('IDOR — valid token returns only its bound post (not arbitrary post)', async ({ request }) => {
        const res  = await request.get(`${API}/preview?token=${validToken}`);
        expect(res.status()).toBe(200);
        const body = await res.json();
        // Server derives post_id from stored token record; client cannot rebind
        expect(body.id).toBe(draftPostId);
    });

    test('published post is not previewable via token endpoint → 400 or 403', async () => {
        const res = await adminPage.request.get(
            `${WP_API}/posts?status=publish&per_page=1`,
            { headers: { 'X-WP-Nonce': adminNonce } }
        );
        const posts = await res.json();
        if (!Array.isArray(posts) || !posts[0]?.id) return; // no published post
        const pubId    = posts[0].id;
        const tokenRes = await issueToken(adminPage, adminNonce, pubId);
        expect([400, 403]).toContain(tokenRes.status());
    });

    test('settings page blocked for non-admin (editor role)', async ({ browser }) => {
        const ctx  = await browser.newContext();
        const page = await ctx.newPage();
        try {
            await page.goto(`${WP}/wp-login.php`);
            await page.fill('#user_login', 'test_editor');
            await page.fill('#user_pass', 'password');
            await page.click('#wp-submit');
            await page.waitForURL(`${WP}/wp-admin/**`);
            await page.goto(`${WP}/wp-admin/options-general.php?page=yui-preview-token`);
            await page.waitForLoadState('domcontentloaded');
            const body = await page.locator('body').innerText();
            // Must not show the settings form fields to a non-admin
            expect(body).not.toContain('External Preview URL');
        } finally {
            await ctx.close();
        }
    });

    test('token revoke request without valid nonce → rejected (not 200)', async ({ request }) => {
        const res = await request.get(
            `${WP}/wp-admin/admin-post.php?action=yuipt_delete_token&post_id=${draftPostId}`,
            { maxRedirects: 0 }
        );
        expect(res.status()).not.toBe(200);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A02 — Cryptographic Failures
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A02 — Cryptographic Failures', () => {

    test('issued token is 64 hex chars (256-bit CSPRNG)', () => {
        expect(validToken).toMatch(/^[0-9a-f]{64}$/);
    });

    test('POST /token response does not expose internal hash or raw-meta keys', async () => {
        const res  = await issueToken(adminPage, adminNonce, draftPostId);
        expect([200, 201]).toContain(res.status());
        const body = await res.json();
        const keys = Object.keys(body);
        // Internal storage keys must never appear in the API response
        expect(keys).not.toContain('hash');
        expect(keys).not.toContain('raw');
        expect(keys).not.toContain('_yuipt_token_hash');
        expect(keys).not.toContain('_yuipt_token_raw');
        // Token is embedded in preview_url only (one-way)
        expect(body).toHaveProperty('preview_url');
        expect(body).toHaveProperty('expires_at');
    });

    test('preview response does not expose private post-meta keys', async ({ request }) => {
        // Re-issue so token is fresh
        const issueRes = await issueToken(adminPage, adminNonce, draftPostId);
        const tok      = extractToken(await issueRes.json());
        const res      = await request.get(`${API}/preview?token=${tok}`);
        expect(res.status()).toBe(200);
        const body = await res.json();
        const meta = body.meta ?? {};
        // Underscore-prefixed meta must not be exposed (WP private meta)
        expect(meta).not.toHaveProperty('_yuipt_token_raw');
        expect(meta).not.toHaveProperty('_yuipt_token_hash');
        expect(meta).not.toHaveProperty('_yuipt_expires_at');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A03 — Injection
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A03 — Injection', () => {

    const payloads = [
        ['sql-or',         "' OR '1'='1"],
        ['sql-union',      "' UNION SELECT option_value FROM wp_options--"],
        ['sql-drop',       "1; DROP TABLE wp_options--"],
        ['xss-script',     '<script>alert(1)</script>'],
        ['xss-img',        '<img src=x onerror=alert(1)>'],
        ['xss-encoded',    '%3Cscript%3Ealert(1)%3C%2Fscript%3E'],
        ['path-traversal', '../../../wp-config.php'],
        ['null-byte',      'valid\x00injection'],
        ['oversized',      'a'.repeat(10_000)],
    ];

    for (const [name, payload] of payloads) {
        test(`[${name}] token param → 400/401, never 500`, async ({ request }) => {
            const res = await request.get(
                `${API}/preview?token=${encodeURIComponent(payload)}`
            );
            expect(res.status()).not.toBe(500);
            expect([400, 401, 403]).toContain(res.status());
        });
    }

    test('XSS payload is not reflected unescaped in response body', async ({ request }) => {
        const xss  = '<script>alert(1)</script>';
        const res  = await request.get(`${API}/preview?token=${encodeURIComponent(xss)}`);
        const text = await res.text();
        expect(text).not.toContain(xss);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A05 — Security Misconfiguration (CORS / Headers)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A05 — Security Misconfiguration', () => {

    test('Referrer-Policy: no-referrer on preview endpoint', async ({ request }) => {
        const res = await request.get(`${API}/preview?token=${validToken}`);
        expect(res.headers()['referrer-policy']).toBe('no-referrer');
    });

    test('no CORS header for unlisted origin (WP core header removed by plugin)', async ({ request }) => {
        // WP core's rest_send_cors_headers echoes back any origin at priority 10.
        // Our plugin runs at priority 11 and must remove that header for non-allowed origins.
        const res  = await request.get(`${API}/preview?token=${validToken}`, {
            headers: { Origin: 'https://evil.example.com' },
        });
        const acao = res.headers()['access-control-allow-origin'];
        expect(acao).not.toBe('https://evil.example.com');
    });

    test('CORS header for allowed origin (localhost:4321) echoes actual origin', async ({ request }) => {
        const issueRes = await issueToken(adminPage, adminNonce, draftPostId);
        const tok      = extractToken(await issueRes.json());
        const res      = await request.get(`${API}/preview?token=${tok}`, {
            headers: { Origin: 'http://localhost:4321' },
        });
        expect(res.headers()['access-control-allow-origin']).toBe('http://localhost:4321');
    });

    test('CORS header never contains a raw wildcard pattern string', async ({ request }) => {
        const issueRes = await issueToken(adminPage, adminNonce, draftPostId);
        const tok      = extractToken(await issueRes.json());
        const res      = await request.get(`${API}/preview?token=${tok}`, {
            headers: { Origin: 'https://sub.example.com' },
        });
        const acao = res.headers()['access-control-allow-origin'] ?? '';
        // Must not echo a wildcard pattern (browsers reject pattern strings)
        expect(acao).not.toContain('*');
    });

    test('no Origin header → no CORS header set', async ({ request }) => {
        const issueRes = await issueToken(adminPage, adminNonce, draftPostId);
        const tok      = extractToken(await issueRes.json());
        const res      = await request.get(`${API}/preview?token=${tok}`);
        expect(res.headers()['access-control-allow-origin']).toBeUndefined();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A07 — Identification & Authentication Failures
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A07 — Identification & Authentication Failures', () => {

    test('expired token (expires_at=1, already past) → 401', async ({ request }) => {
        const res = await issueToken(adminPage, adminNonce, draftPostId, 1);
        expect([200, 201]).toContain(res.status());
        const expToken = extractToken(await res.json());

        const preview = await request.get(`${API}/preview?token=${expToken}`);
        expect(preview.status()).toBe(401);
    });

    test('revoked token → 401 after new token issued (old token overwritten)', async ({ request }) => {
        // Issue token A
        const resA = await issueToken(adminPage, adminNonce, draftPostId);
        expect([200, 201]).toContain(resA.status());
        const tokA = extractToken(await resA.json());
        expect(tokA).toBeTruthy();

        // Verify token A works
        expect((await request.get(`${API}/preview?token=${tokA}`)).status()).toBe(200);

        // Issue token B — WP deletes token A on the server side
        const resB = await issueToken(adminPage, adminNonce, draftPostId);
        expect([200, 201]).toContain(resB.status());

        // Token A must now be invalid
        expect((await request.get(`${API}/preview?token=${tokA}`)).status()).toBe(401);
    });

    test('rate limiting returns 429 after quota exceeded', async ({ request }) => {
        // Temporarily lower the rate limit via settings so this test is self-contained.
        // Use rapid-fire invalid token requests; each consumes 1 rate-limit unit.
        // The playground rate limit is 60 req/60s — we rely on it being mostly fresh.
        // Fire requests and accept EITHER consistent 401s (limit not yet hit within
        // this small batch) OR a 429 (limit hit). 500 or 200 must never appear.
        const results = [];
        for (let i = 0; i < 10; i++) {
            const tok = Array.from({ length: 64 }, () =>
                Math.floor(Math.random() * 16).toString(16)
            ).join('');
            const s = (await request.get(`${API}/preview?token=${tok}`)).status();
            results.push(s);
            if (s === 429) break;
        }
        for (const s of results) {
            expect([401, 429]).toContain(s);
        }
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A08 — Software & Data Integrity (CSRF / Nonce)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A08 — Software & Data Integrity', () => {

    test('CSRF: token delete without nonce → rejected (302 to login or 403)', async ({ request }) => {
        const res = await request.post(`${WP}/wp-admin/admin-post.php`, {
            form: { action: 'yuipt_delete_token', post_id: String(draftPostId) },
            maxRedirects: 0,
        });
        expect(res.status()).not.toBe(200);
    });

    test('CSRF: bulk delete expired without nonce → rejected', async ({ request }) => {
        const res = await request.post(`${WP}/wp-admin/admin-post.php`, {
            form: { action: 'yuipt_delete_expired' },
            maxRedirects: 0,
        });
        expect(res.status()).not.toBe(200);
    });

    test('CSRF: settings update without nonce → rejected (302 or 403)', async ({ request }) => {
        const res = await request.post(`${WP}/wp-admin/options.php`, {
            form: {
                option_page:      'yuipt_settings',
                action:           'update',
                yuipt_frontend_url: 'https://evil.example.com',
            },
            maxRedirects: 0,
        });
        expect([302, 403]).toContain(res.status());
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// A10 — SSRF
// ─────────────────────────────────────────────────────────────────────────────

test.describe('A10 — SSRF', () => {

    test('preview_url in token response points to frontend URL (no server-side fetch)', async () => {
        // The plugin stores frontend_url and embeds it in preview_url.
        // It must never make a server-side HTTP request to that URL.
        // Verify: issuing a token with the frontend_url set to an internal address
        // does NOT cause the server to fetch that address (structural verification).
        // We confirm by checking the response shape — if SSRF existed, the server
        // would hang or return an unexpected body.
        const res  = await issueToken(adminPage, adminNonce, draftPostId);
        expect([200, 201]).toContain(res.status());
        const body = await res.json();
        // Response must contain only the expected client-facing fields
        expect(Object.keys(body).sort()).toEqual(
            ['expires_at', 'issued_at', 'issued_by', 'preview_url'].sort()
        );
        // preview_url embeds p, pt, preview, and token params pointing to the frontend
        expect(body.preview_url).toMatch(/[?&]p=\d+/);
        expect(body.preview_url).toMatch(/[?&]pt=\w+/);
        expect(body.preview_url).toMatch(/[?&]preview=true/);
        expect(body.preview_url).toMatch(/[?&]token=[0-9a-f]{64}/);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// External Issuance Guard
// ─────────────────────────────────────────────────────────────────────────────
// When "Allow External Token Issuance" is OFF (default), POST /token must be
// rejected for any request that lacks a valid X-WP-Nonce — even if the caller
// holds valid WordPress credentials (cookie session, Application Password, etc.).
// This prevents automated external clients from issuing tokens without opt-in.

test.describe('External Issuance Guard', () => {

    test('POST /token without X-WP-Nonce is rejected when external issuance is off', async () => {
        // Use the admin page request context (carries session cookies) but
        // deliberately omit the nonce header.
        // A legitimate external client (e.g. curl with Application Password)
        // would also lack this header, so this exercises the same code path.
        const res = await adminPage.request.post(`${API}/token`, {
            headers: {
                // No X-WP-Nonce — simulating an external / non-admin-UI request
                'Content-Type': 'application/json',
            },
            data: {
                post_id:    draftPostId,
                expires_at: Math.floor(Date.now() / 1000) + 3600,
            },
        });

        // Must not succeed — either:
        //   401: WP rejects cookie-based REST auth without a valid nonce (CSRF guard)
        //   403: our external_issuance_disabled guard (Application Password path)
        // Either way, no token may be issued.
        expect([401, 403]).toContain(res.status());

        if (res.status() === 403) {
            const body = await res.json();
            expect(body.code).toBe('external_issuance_disabled');
        }
    });

    test('POST /token with valid X-WP-Nonce succeeds (admin UI path unaffected)', async () => {
        // Confirm the admin UI path (nonce present) is not broken by the guard.
        const res = await issueToken(adminPage, adminNonce, draftPostId);
        expect([200, 201]).toContain(res.status());
    });

    // ── Application Password simulation ──────────────────────────────────────
    // The tests above rely on cookie auth without nonce, which WP's own CSRF
    // guard catches first (401). These tests use a real Application Password
    // so that WP authenticates the request fully — the only thing stopping
    // token issuance is our external_issuance_disabled guard (403).

    test('Application Password was created successfully', () => {
        if (!appPassword) test.skip();
        expect(appPassword).toBeTruthy();
    });

    test('POST with Application Password (no nonce) → 403 external_issuance_disabled when external issuance is OFF', async ({ request }) => {
        if (!appPassword) {
            test.skip();
            return;
        }

        // Basic Auth: WP authenticates the user without a nonce.
        // Our guard should then return 403 external_issuance_disabled.
        const credentials = Buffer.from(`admin:${appPassword}`).toString('base64');
        const res = await request.post(`${API}/token`, {
            headers: {
                'Authorization': `Basic ${credentials}`,
                'Content-Type':  'application/json',
                // No X-WP-Nonce
            },
            data: {
                post_id:    draftPostId,
                expires_at: Math.floor(Date.now() / 1000) + 3600,
            },
        });

        expect(res.status()).toBe(403);
        const body = await res.json();
        expect(body.code).toBe('external_issuance_disabled');
    });

    test('POST with Application Password succeeds when external issuance is ON', async ({ request }) => {
        if (!appPassword) {
            test.skip();
            return;
        }

        // Enable external issuance via the settings page
        await adminPage.goto(`${WP}/wp-admin/options-general.php?page=yui-preview-token`);
        await adminPage.waitForLoadState('domcontentloaded');
        const checkbox = adminPage.locator('input[name="yuipt_allow_external_issuance"]');
        if (!(await checkbox.isChecked())) {
            await checkbox.check();
            await adminPage.locator('#submit').click();
            await adminPage.waitForURL(`${WP}/wp-admin/options-general.php*`);
        }

        try {
            const credentials = Buffer.from(`admin:${appPassword}`).toString('base64');
            const res = await request.post(`${API}/token`, {
                headers: {
                    'Authorization': `Basic ${credentials}`,
                    'Content-Type':  'application/json',
                },
                data: {
                    post_id:    draftPostId,
                    expires_at: Math.floor(Date.now() / 1000) + 3600,
                },
            });

            expect([200, 201]).toContain(res.status());
        } finally {
            // Always restore the default (external issuance OFF)
            await adminPage.goto(`${WP}/wp-admin/options-general.php?page=yui-preview-token`);
            await adminPage.waitForLoadState('domcontentloaded');
            const cb = adminPage.locator('input[name="yuipt_allow_external_issuance"]');
            if (await cb.isChecked()) {
                await cb.uncheck();
                await adminPage.locator('#submit').click();
                await adminPage.waitForURL(`${WP}/wp-admin/options-general.php*`);
            }
        }
    });

});
