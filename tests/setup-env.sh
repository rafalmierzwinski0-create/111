#!/usr/bin/env bash
#
# Builds the local WordPress test environment from scratch:
#
#   $LSTAB_SCRATCH/wp     — WordPress 6.8, the oldest branch the plugin claims
#   $LSTAB_SCRATCH/wp71   — WordPress 7.1, the current release
#   $LSTAB_SCRATCH/wpzip  — empty site used to install the built zip into
#
# All three run on SQLite (via the official sqlite-database-integration
# drop-in) and are served by PHP's built-in web server, so nothing beyond PHP
# and Node is required.
#
# Google is never contacted: tests/mock-google-mu-plugin.php is installed as an
# mu-plugin and answers docs.google.com requests from tests/fixtures/ through
# WordPress's own pre_http_request filter.
#
# Usage: tests/setup-env.sh
#
set -euo pipefail

SCRATCH="${LSTAB_SCRATCH:-/tmp/lstab-env}"
REPO="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

WP_OLD_TAG="${LSTAB_WP_OLD:-6.8}"
WP_NEW_TAG="${LSTAB_WP_NEW:-7.1}"

mkdir -p "$SCRATCH"
cd "$SCRATCH"

# ---------------------------------------------------------------- sources

if [ ! -d "$SCRATCH/wp-core-old" ]; then
	echo "Fetching WordPress $WP_OLD_TAG…"
	git clone --depth 1 --branch "$WP_OLD_TAG" https://github.com/WordPress/WordPress.git wp-core-old
fi

if [ ! -d "$SCRATCH/wp-core-new" ]; then
	echo "Fetching WordPress $WP_NEW_TAG…"
	git clone --depth 1 --branch "$WP_NEW_TAG" https://github.com/WordPress/WordPress.git wp-core-new
fi

if [ ! -d "$SCRATCH/sqlite-di" ]; then
	echo "Fetching the SQLite database drop-in…"
	git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git sqlite-di
fi

if [ ! -d "$SCRATCH/sqlite-di/build/plugin-sqlite-database-integration" ]; then
	( cd "$SCRATCH/sqlite-di" && bash bin/build-sqlite-plugin-zip.sh > /dev/null )
fi
SQLITE_BUILT="$SCRATCH/sqlite-di/build/plugin-sqlite-database-integration"

# ------------------------------------------------------------------ sites

make_site() {
	local dir="$1" core="$2" port="$3" link_plugin="$4"

	rm -rf "$SCRATCH/$dir"
	cp -R "$SCRATCH/$core" "$SCRATCH/$dir"
	rm -rf "$SCRATCH/$dir/.git"

	mkdir -p "$SCRATCH/$dir/wp-content/plugins" \
	         "$SCRATCH/$dir/wp-content/mu-plugins" \
	         "$SCRATCH/$dir/wp-content/database"

	cp -R "$SQLITE_BUILT" "$SCRATCH/$dir/wp-content/plugins/sqlite-database-integration"
	cp "$SCRATCH/$dir/wp-content/plugins/sqlite-database-integration/db.copy" \
	   "$SCRATCH/$dir/wp-content/db.php"
	sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SCRATCH/$dir/wp-content/plugins/sqlite-database-integration#" \
	   "$SCRATCH/$dir/wp-content/db.php"

	# The Google mock, so no test ever leaves the machine.
	ln -sfn "$REPO/tests/mock-google-mu-plugin.php" "$SCRATCH/$dir/wp-content/mu-plugins/lstab-mock.php"

	# The Pro add-on is linked in but left inactive: the free plugin's suite
	# asserts the free tier's limits, so it must not run with those lifted.
	# tests/run-all.sh activates it for the Pro suite and deactivates it after.
	ln -sfn "$REPO/live-sheets-table-pro" "$SCRATCH/$dir/wp-content/plugins/live-sheets-table-pro"

	# wp and wp71 run the plugin straight from the working tree; wpzip gets the
	# built archive installed into it instead.
	if [ "$link_plugin" = "yes" ]; then
		ln -sfn "$REPO/live-sheets-table" "$SCRATCH/$dir/wp-content/plugins/live-sheets-table"
	fi

	sed -e "s#__PORT__#$port#g" "$REPO/tests/harness/wp-config.template.php" \
		> "$SCRATCH/$dir/wp-config.php"

	php "$REPO/tests/harness/install.php" "$SCRATCH/$dir" "$port"

	if [ "$link_plugin" = "yes" ]; then
		php "$REPO/tests/harness/activate.php" "$SCRATCH/$dir" "$port"
	fi

	sed -e "s#__ROOT__#$SCRATCH/$dir#" "$REPO/tests/harness/router.template.php" \
		> "$SCRATCH/router-$dir.php"
}

echo
echo "Building the WordPress $WP_OLD_TAG site on port 8088…"
make_site wp    wp-core-old 8088 yes

echo "Building the WordPress $WP_NEW_TAG site on port 8089…"
make_site wp71  wp-core-new 8089 yes

echo "Building the clean site for zip installs on port 8090…"
make_site wpzip wp-core-new 8090 no

# The seed script needs to know where the 7.1 site lives.
sed -e "s#__SITE__#$SCRATCH/wp71#" -e "s#__PORT__#8089#" \
	"$REPO/tests/harness/seed.template.php" > "$SCRATCH/seed71.php"

# ---------------------------------------------------------------- servers

start_server() {
	local dir="$1" port="$2"
	if curl -s -o /dev/null --noproxy '*' --max-time 3 "http://127.0.0.1:$port/"; then
		echo "  port $port already serving"
		return
	fi
	setsid nohup php -S "127.0.0.1:$port" -t "$SCRATCH/$dir" "$SCRATCH/router-$dir.php" \
		> "$SCRATCH/server-$dir.log" 2>&1 < /dev/null &
	sleep 1
	echo "  started $dir on port $port"
}

echo
echo "Starting web servers…"
start_server wp    8088
start_server wp71  8089
start_server wpzip 8090

# ------------------------------------------------------------- node deps

if [ ! -d "$SCRATCH/node_modules/playwright" ]; then
	echo
	echo "Installing Playwright…"
	( cd "$SCRATCH" && npm init -y > /dev/null 2>&1 || true; cd "$SCRATCH" && npm install --no-audit --no-fund playwright > /dev/null )
fi
cp "$REPO/tests/harness/browser-test.mjs" "$SCRATCH/browser-test.mjs"

echo
echo "Environment ready under $SCRATCH"
echo "  WordPress $WP_OLD_TAG  http://127.0.0.1:8088/wp-admin/  (admin / admin123)"
echo "  WordPress $WP_NEW_TAG  http://127.0.0.1:8089/wp-admin/  (admin / admin123)"
echo "  clean site   http://127.0.0.1:8090/wp-admin/  (admin / admin123)"
echo
echo "Now run: tests/run-all.sh"
