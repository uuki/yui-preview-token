#!/usr/bin/env bash
# bin/publish.sh — Build a production-ready zip for WordPress.org submission.
#
# Output: dist/yui-preview-token.zip
#
# Checklist (WordPress.org "production-ready" requirement):
#   ✓ TypeScript compiled to plugin/assets/js/*.iife.js
#   ✓ composer --no-dev (devDependencies excluded)
#   ✓ No test files, dev tools, playground files, or source maps
#   ✓ No node_modules, .git, .claude
#   ✓ PO/MO translation files included

set -euo pipefail

PLUGIN_SLUG="yui-preview-token"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"
DIST_DIR="${ROOT_DIR}/dist"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"
STAGE="${BUILD_DIR}/${PLUGIN_SLUG}"

cleanup() { rm -rf "${BUILD_DIR}"; }
trap cleanup EXIT

cd "${ROOT_DIR}"

echo "▸ Building TypeScript..."
(cd plugin && pnpm run build)

echo "▸ Installing production PHP dependencies..."
composer install --no-dev --optimize-autoloader --quiet --working-dir=plugin

echo "▸ Copying production files..."
rsync -a \
    --exclude='node_modules/' \
    --exclude='src/assets/' \
    --exclude='.phpunit.result.cache' \
    --exclude='composer.lock' \
    plugin/ "${STAGE}/"

echo "▸ Removing non-production files..."
# Remove dev-only items from stage
for d in tests; do
    rm -rf "${STAGE:?}/${d}"
done
for f in package.json pnpm-lock.yaml tsconfig.json tsdown.config.ts \
          phpunit.xml patchwork.json; do
    rm -f "${STAGE:?}/${f}"
done
find "${STAGE}/languages" -name "*.pot" -delete 2>/dev/null || true
find "${STAGE}" -name ".DS_Store" -delete 2>/dev/null || true

echo "▸ Creating zip..."
mkdir -p "${DIST_DIR}"
rm -f "${ZIP_FILE}"
(cd "${BUILD_DIR}" && zip -r "${ZIP_FILE}" "${PLUGIN_SLUG}" -q)

ZIP_SIZE=$(du -sh "${ZIP_FILE}" | cut -f1)
echo "✓ ${ZIP_FILE} (${ZIP_SIZE})"

echo "▸ Restoring dev PHP dependencies..."
composer install --quiet --working-dir=plugin

echo "✓ Done."
