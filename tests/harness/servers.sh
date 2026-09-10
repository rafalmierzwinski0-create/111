#!/usr/bin/env bash
#
# Starting the three test sites' web servers, and making sure they are up.
#
# Sourced by both tests/setup-env.sh and tests/run-all.sh. It lives in its own
# file because a long-running sandbox reaps idle background processes: the
# servers built by setup-env.sh are frequently gone by the time somebody runs
# the suite, and a whole run then fails on ERR_CONNECTION_REFUSED with nothing
# wrong in the code. A suite that checks its own servers first cannot make that
# mistake, and cannot report a dead server as a failing plugin.
#
# Expects $SCRATCH to be set.

lstab_start_server() {
	local dir="$1" port="$2"

	if curl -s -o /dev/null --noproxy '*' --max-time 3 "http://127.0.0.1:$port/"; then
		return 0
	fi

	setsid nohup php -S "127.0.0.1:$port" -t "$SCRATCH/$dir" "$SCRATCH/router-$dir.php" \
		>> "$SCRATCH/server-$dir.log" 2>&1 < /dev/null &

	local waited=0
	while [ "$waited" -lt 15 ]; do
		sleep 1
		waited=$(( waited + 1 ))
		if curl -s -o /dev/null --noproxy '*' --max-time 3 "http://127.0.0.1:$port/"; then
			echo "  started $dir on port $port"
			return 0
		fi
	done

	echo "  could not start $dir on port $port — see $SCRATCH/server-$dir.log" >&2
	return 1
}

# Every site the suites use. Silent when they are already answering.
lstab_start_servers() {
	lstab_start_server wp    8088
	lstab_start_server wp71  8089
	lstab_start_server wpzip 8090
}
