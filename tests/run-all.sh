#!/usr/bin/env bash
#
# Full verification run.
#
#   1. PHP end-to-end suite against WordPress 6.8 (minimum supported branch)
#   2. PHP end-to-end suite against WordPress 7.1 (current)
#   3. Build the distributable zip
#   4. PHP end-to-end suite against a clean site installed *from that zip*
#   5. Browser suite on WordPress 7.1, which also captures the screenshots
#
# The sites live outside the repo; see README.md for how they are built.
#
# Usage: tests/run-all.sh
#
set -euo pipefail

SCRATCH="${LSTAB_SCRATCH:-/tmp/lstab-env}"
REPO="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

# Warnings WordPress itself raises because this sandbox cannot reach wordpress.org.
NOISE='wordpress.org\|wp_version_check\|wp_update_plugins\|wp_update_themes'

run_suite() {
	local site="$1" label="$2"
	echo
	echo "=============================================="
	echo " PHP end-to-end suite — $label"
	echo "=============================================="
	rm -f "$SCRATCH/$site/wp-content/debug.log"
	php "$REPO/tests/e2e-test.php" "$SCRATCH/$site"

	local log="$SCRATCH/$site/wp-content/debug.log"
	if [ -f "$log" ] && grep -v "$NOISE" "$log" | grep -q .; then
		echo
		echo "  PHP notices raised by the plugin:"
		grep -v "$NOISE" "$log" | sed 's/^/    /'
		return 1
	fi
	echo "  PHP notices raised by the plugin: none"
}

run_suite wp    "WordPress 6.8 (minimum supported)"
run_suite wp71  "WordPress 7.1 (current)"

echo
echo "=============================================="
echo " Packaging"
echo "=============================================="
bash "$REPO/tools/build-zip.sh"
bash "$REPO/tools/build-zip.sh" live-sheets-table-pro

echo
echo "=============================================="
echo " Reinstalling the clean site from the zip"
echo "=============================================="
rm -rf "$SCRATCH/wpzip/wp-content/plugins/live-sheets-table"
unzip -q "$REPO/build/live-sheets-table.zip" -d "$SCRATCH/wpzip/wp-content/plugins/"
echo "  Installed $( find "$SCRATCH/wpzip/wp-content/plugins/live-sheets-table" -type f | wc -l ) files from the archive"
php "$REPO/tests/harness/activate.php" "$SCRATCH/wpzip" 8090

run_suite wpzip "clean install from the built zip"

echo
echo "=============================================="
echo " Pro add-on suite — WordPress 7.1"
echo "=============================================="
php "$REPO/tests/harness/activate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php
rm -f "$SCRATCH/wp71/wp-content/debug.log"
php "$REPO/tests/pro-test.php" "$SCRATCH/wp71"

if [ -f "$SCRATCH/wp71/wp-content/debug.log" ] && grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | grep -q .; then
	echo
	echo "  PHP notices raised by the Pro add-on:"
	grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | sed 's/^/    /'
	exit 1
fi
echo "  PHP notices raised by the Pro add-on: none"

# The free plugin's own suite asserts the free tier, so Pro must be off for it.
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php

echo
echo "=============================================="
echo " Re-seeding the demo page for the browser run"
echo "=============================================="
php "$SCRATCH/seed71.php"

echo
echo "=============================================="
echo " Browser suite + screenshots — WordPress 7.1"
echo "=============================================="
# The suite runs from the scratch directory, where node_modules lives, so the
# copy there has to be refreshed or an edited test silently runs its old self —
# which is worse than no test, because it reports a pass.
cp "$REPO/tests/harness/browser-test.mjs" "$SCRATCH/browser-test.mjs"

cd "$SCRATCH" && LSTAB_SCRATCH="$SCRATCH" LSTAB_SHOTS="$REPO/screenshots" node browser-test.mjs

echo
echo "All suites passed."
