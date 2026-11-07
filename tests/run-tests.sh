#!/bin/bash
# =====================================================================
# Portfolio Studio — portable test runner.
#
#   bash tests/run-tests.sh /path/to/wordpress
#
# Environment variables:
#   WP_DIR   path of the WordPress installation (default: the first argument)
#   WP_CLI   path of the WP-CLI binary (default: wp)
#   PS_FIXTURES  where the fixtures are written (default: a temporary folder)
#
# The tests run against a REAL WordPress, through WP-CLI: no mock, no network
# call, no account, nothing published.
#
# Phases:
#   suite.php       registration, metadata round trip, lifecycle, coexistence
#   suite-parite.php  the five parity fixes, measured against the original plugin
#   suite-secu.php  counter-proof of the input sanitisation
#
# Exit code 0 when every assertion passes.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"

WP_DIR="${1:-${WP_DIR:-}}"
WP_CLI="${WP_CLI:-wp}"

if [ -z "$WP_DIR" ]; then
	echo "usage: bash tests/run-tests.sh /path/to/wordpress"
	echo "       WP_DIR=... WP_CLI=... bash tests/run-tests.sh"
	exit 2
fi

if [ ! -d "$WP_DIR" ]; then
	echo "ECHEC: le dossier WordPress n'existe pas : $WP_DIR"
	exit 2
fi

PLUGDIR="$WP_DIR/wp-content/plugins"
FIXTURES="${PS_FIXTURES:-$(mktemp -d)}"
mkdir -p "$FIXTURES"
export PS_FIXTURES="$FIXTURES"

FAILURES=0

say() { printf '%s\n' "$*"; }

wp_cmd() {
	"$WP_CLI" --allow-root --path="$WP_DIR" "$@"
}

say "=============================================================="
say "Portfolio Studio — test harness"
say "=============================================================="
say "WordPress : $WP_DIR"
say "fixtures  : $FIXTURES"

# --- installation -------------------------------------------------------------
rm -rf "$PLUGDIR/portfolio-studio"
cp -a "$REPO/portfolio-studio" "$PLUGDIR/portfolio-studio"

ACTIVE_BEFORE="$(wp_cmd plugin list --status=active --field=name 2>/dev/null | grep -v '^sqlite-database-integration$' | grep -v '^db.php$')"

# --- 1. the J1 suite ----------------------------------------------------------
run_phase() {
	local phase="$1"
	local suite="$2"
	local out="$FIXTURES/tests-$phase.log"
	local rc

	PS_PHASE="$phase" wp_cmd eval-file "$HERE/$suite" --user=1 > "$out" 2>&1
	rc=$?
	grep -E '^(not ok|# passed|# failed|1\.\.)' "$out" | sed 's/^/  /'
	if [ $rc -ne 0 ]; then
		FAILURES=$(( FAILURES + 1 ))
		say "  -> phase $phase : ECHEC (rc=$rc)"
	else
		say "  -> phase $phase : OK"
	fi
	return 0
}

say ""
say "--- suite.php : registration, metadata, lifecycle ---"
# Precondition of the `activated` phase: the site has no portfolio category
# yet, so the plugin is the one that creates it — and owns it. A category that
# already existed belongs to somebody else and the plugin leaves it alone
# (that is exactly what the J1b suite tests). Only the portfolio taxonomies
# are touched, never the `category` taxonomy of the site.
wp_cmd eval-file "$HERE/clean-default-term.php" > "$FIXTURES/clean-default-term.log" 2>&1
# The plugin is deactivated first: `wp plugin activate` on a plugin that is
# already active does NOT run the activation hook again, and the `activated`
# phase asserts what that hook creates (the state option, the default
# category). Deactivating first makes the harness independent of the state the
# site was left in.
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate.log" 2>&1
ACT_RC=$?
if [ $ACT_RC -ne 0 ]; then
	say "ECHEC: l'activation a echoue (rc=$ACT_RC)"
	cat "$FIXTURES/activate.log"
	FAILURES=$(( FAILURES + 1 ))
fi

run_phase solo suite.php
run_phase activated suite.php

wp_cmd plugin deactivate portfolio-studio > "$FIXTURES/deactivate.log" 2>&1
run_phase deactivated suite.php

# The coexistence phase needs another portfolio plugin on the site. When one is
# installed, it is used; otherwise the phase is skipped and says so.
if [ -d "$PLUGDIR/portfolio-post-type" ]; then
	say ""
	say "--- suite.php : coexistence with an existing portfolio plugin ---"
	wp_cmd plugin activate portfolio-post-type > /dev/null 2>&1
	wp_cmd plugin activate portfolio-studio > /dev/null 2>&1
	run_phase coexistence suite.php
	wp_cmd plugin deactivate portfolio-studio portfolio-post-type > /dev/null 2>&1
else
	say ""
	say "--- coexistence : SKIPPED (no portfolio-post-type plugin installed) ---"
fi

# --- 2. the parity suite ------------------------------------------------------
# The parity suite reads the objects WordPress actually registered, so the
# plugin must be the only one registering the `portfolio` content type.
say ""
say "--- suite-parite.php : the five parity fixes ---"
wp_cmd plugin deactivate portfolio-post-type > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > /dev/null 2>&1
run_phase parite suite-parite.php

# --- 3. the counter-proof -----------------------------------------------------
say ""
say "--- suite-secu.php : hostile input, from the request to the page ---"
wp_cmd plugin activate portfolio-studio > /dev/null 2>&1
run_phase secu-hostile suite-secu.php

# --- 3. restore ---------------------------------------------------------------
say ""
say "--- restoring the test site ---"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
rm -rf "$PLUGDIR/portfolio-studio"
echo "$ACTIVE_BEFORE" | while read -r p; do
	[ -z "$p" ] && continue
	wp_cmd plugin activate "$p" > /dev/null 2>&1
done

# --- 4. summary ---------------------------------------------------------------
say ""
say "=============================================================="
say "phases en echec : $FAILURES"
say "RESULTAT: $( [ $FAILURES -eq 0 ] && echo OK || echo ECHEC )"
say "=============================================================="

exit $FAILURES
