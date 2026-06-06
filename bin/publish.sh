#!/usr/bin/env bash
# bin/publish.sh — Build a production-ready zip for WordPress.org submission.
#
# Output: dist/wp-preview-token.zip
#
# Checklist (WordPress.org "production-ready" requirement):
#   ✓ TypeScript compiled to assets/js/*.iife.js
#   ✓ composer --no-dev (devDependencies excluded)
#   ✓ No test files, dev tools, playground files, or source maps
#   ✓ No node_modules, .git, .claude
#   ✓ PO/MO translation files included

set -euo pipefail

PLUGIN_SLUG="wp-preview-token"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"
DIST_DIR="${ROOT_DIR}/dist"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"
STAGE="${BUILD_DIR}/${PLUGIN_SLUG}"

cleanup() { rm -rf "${BUILD_DIR}"; }
trap cleanup EXIT

cd "${ROOT_DIR}"

echo "▸ Building TypeScript..."
pnpm run build

echo "▸ Installing production PHP dependencies..."
composer install --no-dev --optimize-autoloader --quiet

echo "▸ Copying production files..."
# Use rsync to skip the large dirs that are never needed
rsync -a \
    --exclude='node_modules/' \
    --exclude='.git/' \
    --exclude='dist/' \
    . "${STAGE}/"

echo "▸ Removing non-production files..."
# Directories
for d in .github .claude playground tests src/js docs bin; do
    rm -rf "${STAGE:?}/${d}"
done
# Files
for f in .gitignore .gitattributes .distignore .phpunit.result.cache \
          CLAUDE.md LICENSE package.json pnpm-lock.yaml \
          tsconfig.json tsdown.config.ts composer.json composer.lock \
          phpunit.xml patchwork.json; do
    rm -f "${STAGE:?}/${f}"
done
# POT templates and macOS metadata
find "${STAGE}/languages" -name "*.pot" -delete 2>/dev/null || true
find "${STAGE}" -name ".DS_Store" -delete 2>/dev/null || true

echo "▸ Creating zip..."
mkdir -p "${DIST_DIR}"
rm -f "${ZIP_FILE}"
(cd "${BUILD_DIR}" && zip -r "${ZIP_FILE}" "${PLUGIN_SLUG}" -q)

ZIP_SIZE=$(du -sh "${ZIP_FILE}" | cut -f1)
echo "✓ ${ZIP_FILE} (${ZIP_SIZE})"

echo "▸ Restoring dev PHP dependencies..."
composer install --quiet

echo "✓ Done."
