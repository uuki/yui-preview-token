import { test, expect } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';

// ── Shared helper ────────────────────────────────────────────────────────────

async function loginToAdmin(page) {
    await page.goto(`${WP}/wp-login.php`);
    await page.getByLabel(/username or email/i).fill('admin');
    await page.getByLabel(/^password$/i).fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL(`${WP}/wp-admin/**`);
}

// ── Preview page (Vite frontend) ─────────────────────────────────────────────

test.describe('Preview page', () => {
    test('shows error when no token in URL', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByText('No preview token in URL.')).toBeVisible();
    });

    test('shows error for an invalid token', async ({ page }) => {
        await page.goto('/?token=invalidtoken');
        await expect(page.getByText(/invalid or expired/i)).toBeVisible();
    });
});

// ── REST API ─────────────────────────────────────────────────────────────────

test.describe('REST API', () => {
    test('returns 401 for an invalid token', async ({ request }) => {
        const res = await request.get(
            `${WP}/wp-json/wp-preview-token/v1/preview?token=invalid`
        );
        expect(res.status()).toBe(401);
        const body = await res.json();
        expect(body.code).toBe('invalid_token');
    });

    test('returns 400 when token param is missing', async ({ request }) => {
        const res = await request.get(`${WP}/wp-json/wp-preview-token/v1/preview`);
        expect(res.status()).toBe(400);
    });
});

// ── Gutenberg sidebar ────────────────────────────────────────────────────────
// New flow: user generates a token in the sidebar first,
// then clicks the "Open external preview" link (rendered as <a>, not <button>).

test.describe('Gutenberg sidebar preview', () => {
    test('generate token and open frontend preview from block editor', async ({
        page,
        context,
    }) => {
        test.setTimeout(60_000);

        await loginToAdmin(page);

        // Open the draft post
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post`);
        await page.getByRole('link', { name: 'Draft: Preview Test' }).first().click();
        await page.waitForURL(`${WP}/wp-admin/post.php?post=*`);

        // Dismiss the "Welcome to the editor" tutorial dialog if it appears
        // (shown on first open of a fresh WP installation).
        const welcomeDialog = page.getByRole('dialog', { name: /welcome to the editor/i });
        if (await welcomeDialog.isVisible({ timeout: 3_000 }).catch(() => false)) {
            await welcomeDialog.getByRole('button', { name: /close/i })
                .or(welcomeDialog.getByLabel(/close/i))
                .first()
                .click();
        }

        // Ensure the "External Preview" panel is expanded.
        // On a fresh WP installation, the panel may render collapsed despite initialOpen:true.
        const panelHeader = page.getByRole('button', { name: /external preview/i });
        await expect(panelHeader).toBeVisible({ timeout: 10_000 });
        await panelHeader.scrollIntoViewIfNeeded();
        const isExpanded = await panelHeader.evaluate(
            (el) => el.getAttribute('aria-expanded') === 'true'
        );
        if (!isExpanded) {
            await panelHeader.click();
        }

        // Wait for the WPT sidebar panel to load
        const generateBtn = page.getByRole('button', { name: /generate token/i });
        const previewLink = page.getByRole('link', { name: /open external preview/i });

        // Token may exist from a previous run — handle both states
        await expect(generateBtn.or(previewLink)).toBeVisible({ timeout: 30_000 });

        if (await generateBtn.isVisible()) {
            await generateBtn.click();
            await expect(previewLink).toBeVisible({ timeout: 10_000 });
        }

        // Open preview in a new tab
        const popupPromise = context.waitForEvent('page');
        await previewLink.click();
        const previewPage = await popupPromise;

        // Wait for navigation to token URL then verify content
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

        // Navigate to draft list
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post`);

        // Clicking .editinline relies on jQuery event delegation which doesn't fire
        // reliably from Playwright's pointer events when the element is CSS-hidden.
        // Instead, call inlineEditPost.open() directly with the post ID — this is
        // exactly what the click handler does internally.
        const postId = await page.evaluate(() => {
            const row = document.querySelector('#the-list tr[id^="post-"]');
            return row ? parseInt(row.id.replace('post-', ''), 10) : 0;
        });
        expect(postId).toBeGreaterThan(0);

        // Trigger Quick Edit the same way WordPress does: via jQuery's click handler
        // attached to #the-list with event delegation on .editinline.
        // jQuery.trigger('click') fires through jQuery's event system, ensuring
        // WordPress's handler runs and shows the #inline-edit form.
        await page.evaluate(() => {
            jQuery('#the-list .editinline').first().trigger('click');
        });

        // #inline-edit is a <tr> element — Playwright's toBeVisible can mis-report it.
        // Check the "Update" button instead, which is reliably visible when Quick Edit is open.
        await expect(page.getByRole('button', { name: 'Update' })).toBeVisible({ timeout: 15_000 });

        // Wait for the WPT panel to mount and render.
        // Scope to .wpt-quick-edit-root directly (Playwright considers the #inline-edit <tr>
        // as CSS-hidden even when visible, which would make child locators fail).
        const wptPanel    = page.locator('.wpt-quick-edit-root');
        const generateBtn = wptPanel.getByRole('button', { name: /generate token/i });
        const previewLink = wptPanel.getByRole('link', { name: /open external preview/i });

        // Wait for the WPT panel to render past the "Loading…" state.
        // Use waitForFunction to check the DOM directly, avoiding Playwright's
        // visibility limitations with elements inside CSS-hidden <tr> containers.
        await page.waitForFunction(() => {
            const roots = document.querySelectorAll('.wpt-quick-edit-root');
            return Array.from(roots).some(r =>
                r.textContent.includes('Open external preview') ||
                r.textContent.includes('Generate token')
            );
        }, { timeout: 20_000 });

        const panelResult = await page.evaluate(() => {
            // If no token exists yet, generate one via the API
            const generateBtnEl = Array.from(document.querySelectorAll('.wpt-quick-edit-root button'))
                .find(b => /generate token/i.test(b.textContent));
            if (generateBtnEl) generateBtnEl.click();

            // Return any existing preview link href
            const linkEl = Array.from(document.querySelectorAll('.wpt-quick-edit-root a'))
                .find(a => /open external preview/i.test(a.textContent));
            return { hasGenerateBtn: !!generateBtnEl, previewHref: linkEl?.href ?? null };
        });

        if (panelResult.hasGenerateBtn) {
            // Wait for "Open external preview" link to appear after token generation
            await page.waitForFunction(() =>
                Array.from(document.querySelectorAll('.wpt-quick-edit-root a'))
                    .some(a => /open external preview/i.test(a.textContent)),
                { timeout: 10_000 }
            );
        }

        // Verify the preview URL contains a valid token
        const href = await page.evaluate(() =>
            Array.from(document.querySelectorAll('.wpt-quick-edit-root a'))
                .find(a => /open external preview/i.test(a.textContent))?.href ?? ''
        );
        expect(href).toMatch(/[?&]token=[0-9a-f]{64}/);
    });
});

// ── Classic Editor token panel ───────────────────────────────────────────────
// Classic Editor is installed by the blueprint but NOT activated by default.
// These tests activate it in beforeAll and deactivate in afterAll so other
// tests (Gutenberg) are unaffected.

test.describe('Classic Editor token panel', () => {
    // Uses a local fixture plugin (writeFile in blueprint) instead of installing from
    // wordpress.org, which requires network access and may be unavailable in test envs.
    // Slug is derived from the Plugin Name ("Classic Editor (Test Fixture)")
    // by WordPress: lowercased, spaces/brackets → hyphens.
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

    test('shows WPT panel in Classic Editor and generates a token', async ({ page }) => {
        test.setTimeout(60_000);

        await loginToAdmin(page);

        // Open the draft post in Classic Editor
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post`);
        const postRow = page.locator('#the-list tr').filter({ hasText: 'Draft: Preview Test' }).first();
        // Get the edit URL and append &classic-editor to force Classic Editor regardless
        // of user preferences (Classic Editor plugin respects this parameter).
        const editHref = await postRow.locator('a.row-title').getAttribute('href');
        await page.goto(editHref + '&classic-editor');
        await page.waitForURL(`${WP}/wp-admin/post.php?post=*&action=edit*`);

        // Wait for the WPT meta box to mount and render
        await page.waitForFunction(() => {
            const root = document.getElementById('wpt-classic-meta-box-root');
            return root && (
                root.textContent.includes('Open external preview') ||
                root.textContent.includes('Generate token')
            );
        }, { timeout: 20_000 });

        // Generate token if none exists
        const generated = await page.evaluate(() => {
            const btn = Array.from(document.querySelectorAll('#wpt-classic-meta-box-root button'))
                .find(b => /generate token/i.test(b.textContent));
            if (btn) btn.click();
            return !!btn;
        });

        if (generated) {
            await page.waitForFunction(() =>
                Array.from(document.querySelectorAll('#wpt-classic-meta-box-root a'))
                    .some(a => /open external preview/i.test(a.textContent)),
                { timeout: 10_000 }
            );
        }

        // Verify the preview URL contains a valid token
        const href = await page.evaluate(() =>
            Array.from(document.querySelectorAll('#wpt-classic-meta-box-root a'))
                .find(a => /open external preview/i.test(a.textContent))?.href ?? ''
        );
        expect(href).toMatch(/[?&]token=[0-9a-f]{64}/);
    });
});
