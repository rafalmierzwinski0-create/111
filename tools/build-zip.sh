#!/usr/bin/env bash
#
# Packages the plugin into build/tabliko.zip, laid out the way
# WordPress expects: a single tabliko/ directory at the root.
#
set -euo pipefail

REPO="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
SLUG="${1:-tabliko}"
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

if [ "$SLUG" = "tabliko" ] && [ ! -f "$STAGE/readme.txt" ]; then
	echo "ERROR: readme.txt missing"; fail=1
fi
# Both plugins are English only: no language setting and no bundled
# translations. A catalogue left in the archive would quietly put a site set
# to that language back into it.
if [ -d "$STAGE/languages" ] || [ -n "$( find "$STAGE" -name '*.mo' -o -name '*.po' -o -name 'readme-*.txt' )" ]; then
	echo "ERROR: a translation is in the archive; the plugin ships in English only"; fail=1
fi

if [ "$SLUG" = "tabliko" ]; then

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
