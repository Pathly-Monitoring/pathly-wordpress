#!/usr/bin/env bash
# Builds dist/pathly and dist/pathly.zip (direct download).
# With --wporg: same plugin without shipped translations, for the WordPress.org review.
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf dist
mkdir -p dist/pathly
cp -R pathly.php uninstall.php readme.txt LICENSE includes blocks assets languages dist/pathly/

ZIP=pathly.zip
if [ "${1:-}" = "--wporg" ]; then
	node bin/strip-textdomain.js dist/pathly
	ZIP=pathly-wporg.zip
fi

cd dist
if command -v zip >/dev/null 2>&1; then
	zip -qr "$ZIP" pathly
else
	python3 -m zipfile -c "$ZIP" pathly/ 2>/dev/null || python -m zipfile -c "$ZIP" pathly/
fi
echo "dist/$ZIP"
