/**
 * Creates playground/plugin-staging/ — a clean, symlink-based plugin directory
 * that mirrors only production files.
 *
 * - Live files (src/, assets/js/, languages/, vendor/, *.php, readme.txt)
 *   are symlinked so edits are reflected immediately in WP Playground without
 *   a resync step.
 * - Static image assets (banners, icons) are copied once.
 *
 * WP Playground is started with:
 *   --auto-mount=./plugin-staging --follow-symlinks
 * so it mounts plugin-staging/ as the preview-token plugin.
 */

import fs   from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT  = path.resolve(__dirname, '../..');       // project root
const STAGE = path.resolve(__dirname, '../plugin-staging');

// ── Helpers ──────────────────────────────────────────────────────────────────

function symlink(target, linkPath) {
    const rel = path.relative(path.dirname(linkPath), target);
    fs.symlinkSync(rel, linkPath);
}

function ensureDir(dir) {
    fs.mkdirSync(dir, { recursive: true });
}

// ── Rebuild staging directory ─────────────────────────────────────────────────

fs.rmSync(STAGE, { recursive: true, force: true });
ensureDir(path.join(STAGE, 'assets'));

// Symlinked directories — changes are immediately visible in Playground
for (const dir of ['src', 'languages', 'vendor']) {
    symlink(path.join(ROOT, dir), path.join(STAGE, dir));
}

// assets/js/ — TS build output, symlinked for immediate hot reload
symlink(path.join(ROOT, 'assets', 'js'), path.join(STAGE, 'assets', 'js'));

// Symlinked root files
for (const file of ['preview-token.php', 'readme.txt']) {
    symlink(path.join(ROOT, file), path.join(STAGE, file));
}

// Static image assets — copied once (no live-update needed)
const assetsDir = path.join(ROOT, 'assets');
for (const file of fs.readdirSync(assetsDir)) {
    if (/\.(png|jpg|jpeg|gif|svg)$/.test(file)) {
        fs.copyFileSync(
            path.join(assetsDir, file),
            path.join(STAGE, 'assets', file)
        );
    }
}

console.log('✓ plugin-staging/ ready');
