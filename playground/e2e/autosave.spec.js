/**
 * Auto-save before external preview — Gutenberg
 *
 * Verifies that clicking "Open external preview" in Gutenberg automatically
 * saves unsaved changes so the external frontend receives the latest content.
 *
 * Flow:
 *   1. Open a draft in Gutenberg
 *   2. Generate a token (so the preview button is visible)
 *   3. Edit a block's content via wp.data (creates unsaved/dirty state)
 *   4. Confirm isEditedPostDirty() === true
 *   5. Click "Open external preview" → onBeforeOpenPreview saves the post
 *   6. Confirm isEditedPostDirty() === false (save completed)
 *   7. Fetch /preview?token=… and confirm the edited text is in the response
 */

import { test, expect } from '@playwright/test';

const WP = 'http://127.0.0.1:9400';

test.describe('Gutenberg auto-save before external preview', () => {

    test('saves unsaved block changes before opening the preview URL', async ({ page, context }) => {
        test.setTimeout(120_000);

        // ── Login ─────────────────────────────────────────────────────────────
        await page.goto(`${WP}/wp-login.php`);
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForURL(`${WP}/wp-admin/**`);
        await page.goto(`${WP}/wp-admin/`);
        await page.waitForLoadState('domcontentloaded');

        // ── Open draft in Gutenberg ───────────────────────────────────────────
        await page.goto(`${WP}/wp-admin/edit.php?post_status=draft&post_type=post&author=1`);
        await page.waitForLoadState('domcontentloaded');
        const editHref = await page.locator('#the-list tr[id^="post-"]').first()
            .locator('a.row-title').getAttribute('href');
        await page.goto(editHref);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(3000); // Gutenberg init (WP Playground constraint)

        // Dismiss welcome dialog; expand sidebar panels
        await page.evaluate(() => {
            const dlg = document.querySelector('[role="dialog"] button[aria-label]');
            if (dlg) dlg.click();
            const sidebar = document.querySelector('.editor-sidebar, .interface-complementary-area');
            if (sidebar) sidebar.querySelectorAll('[aria-expanded="false"]').forEach(b => b.click());
        }).catch(() => null);

        await page.waitForTimeout(1000);

        // ── Ensure there is an active token ──────────────────────────────────
        // Generate if needed so the "Open external preview" button is shown.
        const panelState = await page.evaluate(() =>
            document.querySelector('[data-yuipt-panel]')?.getAttribute('data-yuipt-panel') ?? 'none'
        ).catch(() => 'none');

        if (panelState !== 'active') {
            await page.evaluate(() => {
                const btn = document.querySelector('[data-yuipt-action="generate"] button');
                if (btn) btn.click();
            });
            // Wait for token generation (network round-trip)
            await page.waitForTimeout(5000);
        }

        // ── Edit a block via wp.data (unsaved change) ─────────────────────────
        const uniqueText = 'PVT_AUTOSAVE_' + Date.now();
        const editMade = await page.evaluate((text) => {
            try {
                const blocks = wp.data.select('core/block-editor').getBlocks();
                if (!blocks.length) return false;
                wp.data.dispatch('core/block-editor').updateBlockAttributes(
                    blocks[0].clientId,
                    { content: (blocks[0].attributes.content ?? '') + ' ' + text }
                );
                return true;
            } catch { return false; }
        }, uniqueText);
        expect(editMade, 'Block edit via wp.data must succeed').toBe(true);

        await page.waitForTimeout(500);

        // ── Confirm post is dirty before clicking preview ─────────────────────
        const dirtyBefore = await page.evaluate(() => {
            try { return wp.data.select('core/editor').isEditedPostDirty(); }
            catch { return false; }
        });
        expect(dirtyBefore, 'Post must be dirty after block edit').toBe(true);

        // ── Click "Open external preview" (triggers savePost + window.open) ──
        const popupPromise = context.waitForEvent('page', { timeout: 30_000 });
        await page.evaluate(() => {
            const btn = document.querySelector('[data-yuipt-action="preview"] button');
            if (btn) btn.click();
        });

        // ── Capture popup URL and close it ────────────────────────────────────
        const previewPage = await popupPromise;
        const previewUrl  = previewPage.url();
        await previewPage.close();

        const token = previewUrl.match(/[?&]token=([0-9a-f]{64})/)?.[1] ?? null;
        expect(token, 'Preview URL must contain a valid token').toBeTruthy();

        // Brief wait for save to be fully committed to the database
        await page.waitForTimeout(1000);

        // ── Confirm post is no longer dirty (savePost completed) ─────────────
        const dirtyAfter = await page.evaluate(() => {
            try { return wp.data.select('core/editor').isEditedPostDirty(); }
            catch { return false; }
        }).catch(() => true);
        expect(dirtyAfter, 'Post must not be dirty after auto-save').toBe(false);

        // ── Fetch preview endpoint and verify edited content is present ────────
        const res  = await page.request.get(`${WP}/wp-json/yui-preview-token/v1/preview?token=${token}`);
        expect(res.status()).toBe(200);
        const body = await res.json();
        expect(
            body.content?.rendered ?? '',
            'Preview response must include the unsaved edit'
        ).toContain(uniqueText);
    });

});
