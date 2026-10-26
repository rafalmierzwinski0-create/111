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

# The seed scripts are made from a template; made again here for the same reason
# the browser suite is copied below, so an edited seed never runs its old self.
sed -e "s#__SITE__#$SCRATCH/wp71#" -e "s#__PORT__#8089#" \
	"$REPO/tests/harness/seed.template.php" > "$SCRATCH/seed71.php"
sed -e "s#__SITE__#$SCRATCH/wp#" -e "s#__PORT__#8088#" \
	"$REPO/tests/harness/seed.template.php" > "$SCRATCH/seed67.php"

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
echo " The wording — notes for translators, one name per thing, readable sentences"
echo "=============================================="
node "$REPO/tests/texts-audit.mjs"

echo
echo "=============================================="
echo " Striped rows — every other row a reader can see"
echo "=============================================="
node "$REPO/tests/stripes-browser.mjs"

echo
echo "=============================================="
echo " Colours picked by hand, and rows a rule paints"
echo "=============================================="
node "$REPO/tests/colours-browser.mjs"

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
echo " A page from a page cache keeps its tables current"
echo "=============================================="
node "$REPO/tests/keep-current-browser.mjs"

echo
echo "=============================================="
echo " Tables checked while somebody looks — free every 30 minutes, Pro every 5"
echo "=============================================="
node "$REPO/tests/refresh-browser.mjs"

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
echo " Deleting the plugin the way WordPress does"
echo "=============================================="
# Switched off first, then its uninstall routine run with none of it loaded.
# On the clean site, which is rebuilt from the zip on every run anyway.
php "$REPO/tests/uninstall-test.php" "$SCRATCH/wpzip" 8090

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
echo " Awkward characters through every feature — WordPress 7.1"
echo "=============================================="
# Headings and values with line breaks, odd spaces, quotes, "<", "&", brackets,
# emoji and things shaped like formulas, renamed, hidden, coloured, filtered,
# searched, sorted and downloaded. The add-on has to be active for most of it.
rm -f "$SCRATCH/wp71/wp-content/debug.log"
php "$REPO/tests/characters-test.php" "$SCRATCH/wp71"

if [ -f "$SCRATCH/wp71/wp-content/debug.log" ] && grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | grep -q .; then
	echo
	echo "  PHP notices raised by awkward characters:"
	grep -v "$NOISE" "$SCRATCH/wp71/wp-content/debug.log" | sed 's/^/    /'
	exit 1
fi
echo "  PHP notices raised by awkward characters: none"

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

echo
echo "=============================================="
echo " Nothing spills — every style, layout and shape of sheet"
echo "=============================================="
# Six shapes of sheet in all ten styles and three layouts, on the default theme
# at a phone and a desk width, under the table and button rules aggressive
# themes ship, and right to left. A pinned first column that slid off its edge
# on a hostile theme is what this was written after. It builds its own pages
# and takes them away again, for the same reason the skins pages go above.
node "$REPO/tests/layout-sweep.mjs"

echo
echo "=============================================="
echo " What a person notices in the first minute"
echo "=============================================="
# Every switch on the edit screen changes the preview, lists fold back, menus
# close, sort arrows can be seen, a glow stays off the buttons, every column
# has its lines, the heading row sticks cleanly, the table has an edge, and
# help text points at screens and buttons that exist. Measured in pixels.
node "$REPO/tests/ux-audit.mjs"

echo
echo "=============================================="
echo " A table's own CSS — every shape of rule, wherever the table is shown"
echo "=============================================="
# Seventy-odd rule shapes on a page, the same again in the live preview, then
# pages turned, a newer copy swapped in, the block editor, and a Pro colour rule
# over an own background. Here, while the add-on is still active, for the rule.
node "$REPO/tests/custom-css-browser.mjs" "$SCRATCH/wp71" 8089

echo
echo "=============================================="
echo " A heading row that sticks — nothing shows through it, in any style"
echo "=============================================="
node "$REPO/tests/sticky-head-browser.mjs"

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
echo " Hiding columns and rows in a browser — free plugin alone, WordPress 7.1"
echo "=============================================="
# Clicking things is the whole point of the picker, and the only part of it PHP
# cannot check. It comes with the free plugin, so it is driven without the
# add-on first, then again with it for the add-on's own cards.
php "$REPO/tests/harness/deactivate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null
php "$SCRATCH/seed71.php" > /dev/null
cp "$REPO/tests/harness/picker-test.mjs" "$SCRATCH/picker-test.mjs"
cd "$SCRATCH" && LSTAB_SHOTS="$REPO/screenshots" node picker-test.mjs

echo
echo "=============================================="
echo " The add-on's cards in a browser — WordPress 7.1"
echo "=============================================="
php "$REPO/tests/harness/activate.php" "$SCRATCH/wp71" 8089 live-sheets-table-pro/live-sheets-table-pro.php > /dev/null
php "$SCRATCH/seed71.php" > /dev/null
cd "$SCRATCH" && LSTAB_WITH_PRO=1 LSTAB_SHOTS="$REPO/screenshots" node picker-test.mjs

echo
echo "=============================================="
echo " The dashboard on a phone, a tablet and a desk"
echo "=============================================="
# Every screen and every pane, with the add-on's cards on them, at five widths.
# The columns card once pushed a phone's screen sideways and stood the sheet's
# headings one letter to a line; WordPress sizes its fields for a laptop.
php "$SCRATCH/seed71.php" > /dev/null
node "$REPO/tests/admin-sweep.mjs"
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

php "$REPO/tests/characters-test.php" "$SCRATCH/wp"

if [ -f "$SCRATCH/wp/wp-content/debug.log" ] && grep -v "$NOISE" "$SCRATCH/wp/wp-content/debug.log" | grep -q .; then
	echo
	echo "  PHP notices raised by awkward characters on 6.7:"
	grep -v "$NOISE" "$SCRATCH/wp/wp-content/debug.log" | sed 's/^/    /'
	exit 1
fi

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
