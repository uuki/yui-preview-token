#!/usr/bin/env bash
# bin/validate-svn-structure.sh — Validate that readme.txt, the plugin version
# header, and assets/ conform to the WordPress.org SVN repository structure.
#
# Usage: bin/validate-svn-structure.sh <version>
#
# Checks:
#   - plugin/readme.txt starts with "=== Plugin Name ==="
#   - plugin/readme.txt "Stable tag" matches <version>
#   - plugin/yui-preview-token.php "Version" header matches <version>
#   - == Screenshots == numbering in readme.txt matches assets/screenshot-N.*
#     files 1:1 and forms a contiguous sequence starting at 1
#   - required assets/ files (icons, banners) are present
#   - no .DS_Store files are tracked in git

set -euo pipefail

VERSION="${1:?Usage: $0 <version>}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
README="${ROOT_DIR}/plugin/readme.txt"
PLUGIN_FILE="${ROOT_DIR}/plugin/yui-preview-token.php"
ASSETS_DIR="${ROOT_DIR}/assets"

ERRORS=0

ok() { echo "✓ $1"; }
fail() {
    echo "✗ $1"
    ERRORS=$((ERRORS + 1))
}

# 1. readme.txt header format
if head -1 "$README" | grep -qE '^=== .+ ===$'; then
    ok "readme.txt has a valid '=== Plugin Name ===' header"
else
    fail "readme.txt is missing the '=== Plugin Name ===' header"
fi

# 2. Stable tag matches deploy version
STABLE_TAG=$(grep -E '^Stable tag:' "$README" | sed -E 's/^Stable tag:[[:space:]]*//')
if [[ "$STABLE_TAG" == "$VERSION" ]]; then
    ok "readme.txt Stable tag matches version ($VERSION)"
else
    fail "readme.txt Stable tag ($STABLE_TAG) does not match version ($VERSION)"
fi

# 3. Plugin file Version header matches deploy version
PLUGIN_VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//')
if [[ "$PLUGIN_VERSION" == "$VERSION" ]]; then
    ok "plugin file Version header matches version ($VERSION)"
else
    fail "plugin file Version header ($PLUGIN_VERSION) does not match version ($VERSION)"
fi

# 4. Screenshots numbering: readme.txt <-> assets/screenshot-N.*
README_NUMS=$(awk '/^== Screenshots ==/{flag=1; next} /^== /{flag=0} flag' "$README" \
    | grep -oE '^[0-9]+\.' | tr -d '.' | sort -n)
ASSET_NUMS=$(ls "$ASSETS_DIR" | grep -oE '^screenshot-[0-9]+' | grep -oE '[0-9]+' | sort -n)

if [[ "$README_NUMS" == "$ASSET_NUMS" ]]; then
    ok "readme.txt Screenshots section matches assets/screenshot-*.* files"
else
    fail "readme.txt Screenshots numbers ($(echo "$README_NUMS" | tr '\n' ',')) do not match assets/screenshot-*.* files ($(echo "$ASSET_NUMS" | tr '\n' ','))"
fi

EXPECTED_SEQ=$(seq 1 "$(echo "$README_NUMS" | wc -l | tr -d ' ')")
if [[ "$README_NUMS" == "$EXPECTED_SEQ" ]]; then
    ok "Screenshots numbering is contiguous starting at 1"
else
    fail "Screenshots numbering has gaps or does not start at 1: $(echo "$README_NUMS" | tr '\n' ',')"
fi

# 5. Required assets/ files
for f in icon-128x128.png icon-256x256.png banner-772x250.png banner-1544x500.png; do
    if [[ -f "${ASSETS_DIR}/${f}" ]]; then
        ok "assets/${f} present"
    else
        fail "assets/${f} is missing"
    fi
done

# 6. No .DS_Store tracked in git
if git -C "$ROOT_DIR" ls-files | grep -q '\.DS_Store$'; then
    fail "tracked .DS_Store file(s) found"
else
    ok "no tracked .DS_Store files"
fi

echo
if [[ "$ERRORS" -gt 0 ]]; then
    echo "FAILED: ${ERRORS} check(s) failed."
    exit 1
fi
echo "All checks passed."
