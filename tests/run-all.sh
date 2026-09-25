#!/usr/bin/env bash
#
# Full verification run.
#
#   1. PHP end-to-end suite against WordPress 6.7 (the minimum the plugin claims)
#   2. PHP end-to-end suite against WordPress 7.1 (current)
#   3. Build the distributable zip
#   4. PHP end-to-end suite against a clean site installed *from that zip*
#   5. Exploratory suite: sheets and requests nobody would write on purpose
#   6. Browser suite on WordPress 7.1, which also captures the screenshots
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

# Sorting first, and on its own: it needs no WordPress and no browser profile,
# runs in a second, and a parser that reads a date wrongly makes every later
# assertion about ordering meaningless. The PHP run also writes the file the
# browser run compares itself against, so the order of these two matters.
echo
echo "=============================================="
echo " Sorting — dates, clocks and numbers"
echo "=============================================="
php "$REPO/tests/sort-test.php"
node "$REPO/tests/sort-browser.mjs"

echo
echo "=============================================="
echo " Column looks — bars, buttons and the shapes a rule wears"
echo "=============================================="
# Same shape as the sorting pair: the PHP run writes the cells it produced and
# the browser run draws exactly those, so what is checked is what the plugin
# emits rather than a hand-typed imitation of it.
php "$REPO/tests/column-looks-test.php"
node "$REPO/tests/column-looks-browser.mjs"

echo
echo "=============================================="
echo " Colour rules in the preview, before they are saved"
echo "=============================================="
php "$REPO/tests/rules-preview-test.php"
node "$REPO/tests/rules-preview-browser.mjs"

# A long-lived sandbox reaps idle background processes, so the servers built by
# setup-env.sh are often gone by the time anybody runs this. Three whole runs
# have failed that way, every one of them reported as a broken plugin. They are
# checked, and restarted if need be, before a single assertion is made.
# shellcheck source=harness/servers.sh
. "$REPO/tests/harness/servers.sh"
lstab_start_servers

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

# The free plugin's suite asserts the free tier, so Pro must be off before it —
# not merely off by the end of the previous run. A site left with Pro active by
# hand would otherwise fail ten free-tier assertions for no reason of the code's.
# Both sites the suite runs on, because a Pro zip tested by hand lands on the
# clean one and stays there.
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wpzip" 8090 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null 2>&1 || true

run_suite wp    "WordPress 6.7 (the minimum the plugin claims)"
run_suite wp71  "WordPress 7.1 (current)"

echo
echo "=============================================="
echo " Exploratory suite — adversarial sheets and requests"
echo "=============================================="
rm -f "$SCRATCH/wp71/wp-content/debug.log"
php "$REPO/tests/explore-test.php" "$SCRATCH/wp71"

if [ -f "$SCRATCH/wp71/wp-content/debug.log" ] && grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | grep -q .; then
	echo
	echo "  PHP notices raised while being provoked:"
	grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | sed 's/^/    /'
	exit 1
fi
echo "  PHP notices raised while being provoked: none"

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
# Again here: the deactivation above happened before the packaging step, and
# this is the last moment before the free tier is asserted.
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wpzip" 8090 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null 2>&1 || true
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

echo
echo "=============================================="
echo " Whole-table skins, and the dials on top of them"
echo "=============================================="
# Nine skins, each drawn three ways, on real pages of the real site: the PHP run
# publishes them and writes down what it published, the browser run opens those
# pages and measures what a visitor would see. The skins are premium, so this
# belongs here, while the add-on is still active.
php "$REPO/tests/skins-test.php" "$SCRATCH/wp71"
node "$REPO/tests/skins-browser.mjs"
# The pages have to go again: a page naming a source id is exactly what the
# end-to-end suite asks about when it checks that an unused table is unused.
php "$REPO/tests/harness/drop-skins.php" "$SCRATCH/wp71"

# The free plugin's own suite asserts the free tier, so Pro must be off for it.
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php

echo
echo "=============================================="
echo " Elementor widget — WordPress 7.1"
echo "=============================================="
if [ -f "$SCRATCH/wp71/wp-content/plugins/elementor/elementor.php" ]; then
	php "$REPO/tests/harness/activate.php" "$SCRATCH/wp71" 8089 elementor/elementor.php > /dev/null
	php "$REPO/tests/elementor-test.php" "$SCRATCH/wp71"
	# Elementor raises deprecations of its own on PHP 8, which would otherwise
	# be read as this plugin's, and it changes what the browser run sees.
	php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 elementor/elementor.php > /dev/null
	rm -f "$SCRATCH/wp71/wp-content/debug.log"
else
	echo "  Elementor is not installed in the scratch site — skipped."
	echo "  git clone --depth 1 https://github.com/elementor/elementor.git \\"
	echo "    \"$SCRATCH/elementor\" && ln -s \"$SCRATCH/elementor\" \\"
	echo "    \"$SCRATCH/wp71/wp-content/plugins/elementor\""
fi

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
# The suites call these through paths relative to their own file, so the copies
# in the scratch directory are the ones that run. Refreshed beside the suite for
# the same reason the suite itself is: an edited helper that never reaches the
# scratch reports a pass from its old self.
for helper in drop-source set-detail set-paging set-sync-log; do
	cp "$REPO/tests/harness/$helper.php" "$SCRATCH/$helper.php"
done

cd "$SCRATCH" && LSTAB_SCRATCH="$SCRATCH" LSTAB_SHOTS="$REPO/screenshots" node browser-test.mjs

echo
echo "=============================================="
echo " Pro picker in a browser — WordPress 7.1"
echo "=============================================="
# Clicking things is the whole point of the picker, and the only part of it PHP
# cannot check. The add-on has to be running for the screen to exist at all.
php "$REPO/tests/harness/activate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null
php "$SCRATCH/seed71.php" > /dev/null
cp "$REPO/tests/harness/picker-test.mjs" "$SCRATCH/picker-test.mjs"
cd "$SCRATCH" && LSTAB_SHOTS="$REPO/screenshots" node picker-test.mjs
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null

echo
echo "=============================================="
echo " Pro add-on suite — WordPress 6.7"
echo "=============================================="
# The oldest branch the plugin claims, exercised as thoroughly as the newest.
# Running the add-on here is what caught add_submenu_page() being absent from a
# command-line WordPress 6.7 — on 7.1 it happens to be loaded, so a suite that
# only ever ran on 7.1 could not have seen it.
php "$REPO/tests/harness/activate.php" "$SCRATCH/wp" 8088 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null
rm -f "$SCRATCH/wp/wp-content/debug.log"
php "$REPO/tests/pro-test.php" "$SCRATCH/wp"

if [ -f "$SCRATCH/wp/wp-content/debug.log" ] && grep -v "$NOISE" "$SCRATCH/wp/wp-content/debug.log" | grep -q .; then
	echo
	echo "  PHP notices raised by the Pro add-on on 6.7:"
	grep -v "$NOISE" "$SCRATCH/wp/wp-content/debug.log" | sed 's/^/    /'
	exit 1
fi
echo "  PHP notices raised by the Pro add-on on 6.7: none"

php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp" 8088 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null

echo
echo "=============================================="
echo " Browser suite — WordPress 6.7"
echo "=============================================="
# Same run, oldest supported WordPress. The screenshots stay the 7.1 ones: they
# are the plugin's shop window, not a record of this run.
php "$SCRATCH/seed67.php" > /dev/null
cd "$SCRATCH" && LSTAB_SCRATCH="$SCRATCH" LSTAB_BASE="http://127.0.0.1:8088" \
	LSTAB_SHOTS="$SCRATCH/shots67" node browser-test.mjs

echo
echo "All suites passed."
