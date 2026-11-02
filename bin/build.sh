#!/usr/bin/env bash
# Builds dist/pathly (the plugin as published on WordPress.org) and dist/pathly.zip.
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf dist
mkdir -p dist/pathly
cp -R pathly.php uninstall.php readme.txt LICENSE includes blocks assets languages dist/pathly/

cd dist
if command -v zip >/dev/null 2>&1; then
	zip -qr pathly.zip pathly
else
	python3 -m zipfile -c pathly.zip pathly/ 2>/dev/null || python -m zipfile -c pathly.zip pathly/
fi
echo "dist/pathly.zip"
