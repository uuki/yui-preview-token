#!/usr/bin/env bash
# Usage: bin/bump-version.sh <new-version>
# Called by semantic-release @semantic-release/exec prepareCmd.
# Updates the version string in:
#   - yui-preview-token.php  (plugin header: " * Version:     X.Y.Z")
#   - docs/readme.txt    ("Stable tag: X.Y.Z")
#   - docs/readme-ja.txt ("Stable tag: X.Y.Z")
set -euo pipefail

VERSION="$1"

# macOS sed requires an extension argument for -i; use empty string for in-place.
sed -i.bak "s/^\( \* Version:     \).*/\1${VERSION}/" plugin/yui-preview-token.php
rm -f plugin/yui-preview-token.php.bak

sed -i.bak "s/^Stable tag:.*/Stable tag: ${VERSION}/" plugin/readme.txt
rm -f plugin/readme.txt.bak

sed -i.bak "s/^Stable tag:.*/Stable tag: ${VERSION}/" docs/readme-ja.txt
rm -f docs/readme-ja.txt.bak

echo "Bumped version to ${VERSION}"
