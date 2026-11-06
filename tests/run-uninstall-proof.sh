#!/bin/bash
# =====================================================================
# Portfolio Studio — proof of the uninstall routine.
#
#   bash tests/run-uninstall-proof.sh /path/to/wordpress
#
# Environment variables:
#   WP_DIR   path of the WordPress installation (default: the first argument)
#   WP_CLI   path of the WP-CLI binary (default: wp)
#   PS_FIXTURES  where the logs are written (default: a temporary folder)
#
# What it proves:
#   GREEN  `uninstall-keep-proof.php` against the real `uninstall.php` — every
#          assertion must pass:
#            - the user's `portfolio` item still exists after the uninstall,
#              not trashed, title and content intact;
#            - the four metadata keys of the plugin are kept when the cleanup
#              is NOT requested;
#            - the four metadata keys are removed when the cleanup IS requested
#              (the explicit `portfolio_studio_uninstall_remove_post_meta`
#              filter);
#            - the metadata of the site is never touched;
#            - the uninstall routine really ran (its own option is gone).
#   RED    the same test against a witness that deletes the `portfolio` items —
#          the test MUST fail. A test that can fail proves; an assertion does not.
#
# The witness is written to a temporary folder at every run: a script that
# deletes the items of a site has no business in a public repository.
#
# Exit code 0 when the green proof passes AND the red proof fails as expected.
# Cost: 0 $. No account, no network call, nothing published.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"

WP_DIR="${1:-${WP_DIR:-}}"
WP_CLI="${WP_CLI:-wp}"

if [ -z "$WP_DIR" ]; then
	echo "usage: bash tests/run-uninstall-proof.sh /path/to/wordpress"
	echo "       WP_DIR=... WP_CLI=... bash tests/run-uninstall-proof.sh"
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

SUITE="$HERE/uninstall-keep-proof.php"
if [ ! -f "$SUITE" ]; then
	SUITE="$HERE/../tests/uninstall-keep-proof.php"
fi

FAILURES=0

say() { printf '%s\n' "$*"; }

wp_cmd() {
	"$WP_CLI" --allow-root --path="$WP_DIR" "$@"
}

# --- the witness, written on the fly ------------------------------------------
TEMOIN="$FIXTURES/witness-deletes-the-items.php"
cat > "$TEMOIN" <<'PHP'
<?php
/**
 * WITNESS — an uninstall that deletes the `portfolio` items.
 *
 * Written by `run-uninstall-proof.sh` at every run. It is not in the repository
 * and it is not in any archive: it exists only to prove that
 * `uninstall-keep-proof.php` is able to detect the deletion of the items.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$ps_ids = get_posts(
	array(
		'post_type'   => 'portfolio',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( (array) $ps_ids as $ps_id ) {
	wp_delete_post( (int) $ps_id, true );
}
printf( "witness: %d portfolio item(s) deleted\n", count( (array) $ps_ids ) );
PHP

say "=============================================================="
say "Portfolio Studio — uninstall proof"
say "=============================================================="
say "WordPress : $WP_DIR"
say "fixtures  : $FIXTURES"

# --- installation --------------------------------------------------------------
rm -rf "$PLUGDIR/portfolio-studio"
cp -a "$REPO/portfolio-studio" "$PLUGDIR/portfolio-studio"

ACTIVE_BEFORE="$(wp_cmd plugin list --status=active --field=name 2>/dev/null | grep -v '^sqlite-database-integration$' | grep -v '^db.php$')"

wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate.log" 2>&1
ACT_RC=$?
if [ $ACT_RC -ne 0 ]; then
	say "ECHEC: l'activation a echoue (rc=$ACT_RC)"
	cat "$FIXTURES/activate.log"
	FAILURES=$(( FAILURES + 1 ))
fi

# --- 1. GREEN proof ------------------------------------------------------------
say ""
say "--- GREEN: the real uninstall.php ---"
wp_cmd eval-file "$SUITE" "$PLUGDIR/portfolio-studio/uninstall.php" \
	> "$FIXTURES/proof-green.log" 2>&1
GREEN_RC=$?
grep -E '^(ok|not ok|# passed|# failed|1\.\.)' "$FIXTURES/proof-green.log" | sed 's/^/  /'
say "  -> rc (attendu: 0) : $GREEN_RC"
if [ $GREEN_RC -ne 0 ]; then
	FAILURES=$(( FAILURES + 1 ))
	say "  -> ECHEC: la preuve verte a echoue"
fi

# --- 2. RED proof --------------------------------------------------------------
say ""
say "--- RED: the same test against a witness that deletes the items ---"
wp_cmd eval-file "$SUITE" "$TEMOIN" > "$FIXTURES/proof-red.log" 2>&1
RED_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/proof-red.log" | sed 's/^/  /'
say "  -> rc (attendu: NON 0) : $RED_RC"
if [ $RED_RC -eq 0 ]; then
	FAILURES=$(( FAILURES + 1 ))
	say "  -> ECHEC: la preuve rouge a passe — le test ne detecte pas la suppression"
else
	say "  -> OK: le test detecte la suppression (le test peut echouer)"
fi

# --- 3. restore ----------------------------------------------------------------
say ""
say "--- restoring the test site ---"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
rm -rf "$PLUGDIR/portfolio-studio"
echo "$ACTIVE_BEFORE" | while read -r p; do
	[ -z "$p" ] && continue
	wp_cmd plugin activate "$p" > /dev/null 2>&1
done

say ""
say "=============================================================="
say "phases en echec : $FAILURES"
say "RESULTAT: $( [ $FAILURES -eq 0 ] && echo OK || echo ECHEC )"
say "=============================================================="

exit $FAILURES
