#!/usr/bin/env bash
#
# Packages the plugin into build/live-sheets-table.zip, laid out the way
# WordPress expects: a single live-sheets-table/ directory at the root.
#
set -euo pipefail

REPO="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
SLUG="${1:-live-sheets-table}"
BUILD="$REPO/build"
STAGE="$BUILD/$SLUG"

VERSION="$( grep -m1 "^ \* Version:" "$REPO/$SLUG/$SLUG.php" | sed 's/.*Version:[[:space:]]*//' )"

if [ ! -d "$REPO/$SLUG" ]; then
	echo "ERROR: no such plugin directory: $SLUG"
	exit 1
fi

rm -rf "$STAGE" "$BUILD/$SLUG.zip"
mkdir -p "$STAGE"

# Copy the plugin, then drop development leftovers.
cp -R "$REPO/$SLUG/." "$STAGE/"
find "$STAGE" \( -name '.git*' -o -name 'node_modules' -o -name '*.map' -o -name '.DS_Store' \) -prune -exec rm -rf {} +

# Sanity checks before we seal the archive.
fail=0

if [ "$SLUG" = "live-sheets-table" ] && [ ! -f "$STAGE/readme.txt" ]; then
	echo "ERROR: readme.txt missing"; fail=1
fi
# Both plugins ship a catalogue: without one in the archive the strings cannot
# be translated at all, however carefully they were wrapped in the source.
if [ ! -f "$STAGE/languages/$SLUG.pot" ]; then
	echo "ERROR: POT catalogue missing"; fail=1
fi

if [ "$SLUG" = "live-sheets-table" ]; then
	if [ ! -f "$STAGE/languages/$SLUG-pl_PL.mo" ]; then
		echo "ERROR: compiled Polish catalogue missing"; fail=1
	fi

	readme_version="$( grep -m1 '^Stable tag:' "$STAGE/readme.txt" | sed 's/.*Stable tag:[[:space:]]*//' )"
	if [ "$readme_version" != "$VERSION" ]; then
		echo "ERROR: readme.txt stable tag ($readme_version) does not match plugin version ($VERSION)"; fail=1
	fi
fi

while IFS= read -r php_file; do
	if ! php -l "$php_file" > /dev/null 2>&1; then
		echo "ERROR: PHP syntax error in $php_file"; fail=1
	fi
done < <( find "$STAGE" -name '*.php' )

if [ "$fail" -ne 0 ]; then
	echo "Build aborted."
	exit 1
fi

cd "$BUILD"
zip -qr "$SLUG.zip" "$SLUG"
rm -rf "$STAGE"

echo "Built build/$SLUG.zip (version $VERSION)"
unzip -l "$SLUG.zip" | tail -3
