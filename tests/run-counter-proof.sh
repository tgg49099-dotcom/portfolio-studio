#!/bin/bash
# =====================================================================
# Portfolio Studio — counter-proof of the input sanitisation.
#
#   bash tests/run-counter-proof.sh /path/to/wordpress
#
# The official WordPress.org validator (Plugin Check) raises four
# `InputNotSanitized` warnings on the four `$_POST` reads of the editor panel.
# This script does not argue that they are false positives: it injects a hostile
# string through the real save path and checks what comes out.
#
#   1. VERT   — the suite passes against the plugin as it is.
#   2. ROUGE  — the same suite fails against a variant whose sanitiser does not
#               sanitise (class-portfolio-studio-meta.php).
#   3. ROUGE 2 — the same suite fails against a variant whose editor panel lost
#               its first cleaning (class-portfolio-studio-fields.php).
#               A test that cannot fail proves nothing.
#
# Environment variables:
#   WP_DIR   path of the WordPress installation (default: the first argument)
#   WP_CLI   path of the WP-CLI binary (default: wp)
#
# No account, no network call, nothing published.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"

WP_DIR="${1:-${WP_DIR:-}}"
WP_CLI="${WP_CLI:-wp}"

if [ -z "$WP_DIR" ] || [ ! -d "$WP_DIR" ]; then
	echo "usage: bash tests/run-counter-proof.sh /path/to/wordpress"
	exit 2
fi

PLUGDIR="$WP_DIR/wp-content/plugins"
FIXTURES="${PS_FIXTURES:-$(mktemp -d)}"
mkdir -p "$FIXTURES"

FAILURES=0
say() { printf '%s\n' "$*"; }

wp_cmd() {
	"$WP_CLI" --allow-root --path="$WP_DIR" "$@"
}

say "=============================================================="
say "Counter-proof of the input sanitisation"
say "=============================================================="

rm -rf "$PLUGDIR/portfolio-studio"
cp -a "$REPO/portfolio-studio" "$PLUGDIR/portfolio-studio"

ACTIVE_BEFORE="$(wp_cmd plugin list --status=active --field=name 2>/dev/null | grep -v '^sqlite-database-integration$' | grep -v '^db.php$')"

# --- 1. VERT ------------------------------------------------------------------
say ""
say "--- VERT : le plugin tel qu'il est ---"
wp_cmd plugin activate portfolio-studio > /dev/null 2>&1
PS_PHASE=secu-hostile wp_cmd eval-file "$HERE/suite-secu.php" --user=1 > "$FIXTURES/secu-vert.log" 2>&1
VERT_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/secu-vert.log" | sed 's/^/  /'
if [ $VERT_RC -ne 0 ]; then
	say "  -> ECHEC : la suite doit passer sur le plugin reel"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite passe sur le plugin reel"
fi

# --- 2. ROUGE -----------------------------------------------------------------
say ""
say "--- ROUGE : la variante dont l'assainissement a ete retire ---"
python3 -I "$HERE/build-unsanitised-variant.py" \
	"$REPO/portfolio-studio/includes/class-portfolio-studio-meta.php" \
	"$PLUGDIR/portfolio-studio/includes/class-portfolio-studio-meta.php" > "$FIXTURES/build.log" 2>&1
BUILD_RC=$?
cat "$FIXTURES/build.log" | sed 's/^/  /'
if [ $BUILD_RC -ne 0 ]; then
	say "  -> ECHEC : la variante n'a pas pu etre construite"
	FAILURES=$(( FAILURES + 1 ))
else
	PS_PHASE=secu-hostile wp_cmd eval-file "$HERE/suite-secu.php" --user=1 > "$FIXTURES/secu-rouge.log" 2>&1
	ROUGE_RC=$?
	grep -c '^not ok ' "$FIXTURES/secu-rouge.log" | sed 's/^/  assertions rouges : /'
	if [ $ROUGE_RC -eq 0 ]; then
		say "  -> ECHEC : la suite aurait du ECHOUER sur la variante non assainie"
		FAILURES=$(( FAILURES + 1 ))
	else
		say "  -> OK : la suite ECHOUE bien sur la variante non assainie"
	fi
fi

# --- 2b. ROUGE 2 : la variante dont le PREMIER assainissement a ete retire -----
# The first cleaning of the write path (sanitize_text_field) is what the
# WordPress.org static analyser looks at. The suite asserts that the panel has
# it: this variant removes it and the suite MUST fail.
say ""
say "--- ROUGE 2 : la variante dont le premier assainissement du panneau a ete retire ---"
rm -rf "$PLUGDIR/portfolio-studio"
cp -a "$REPO/portfolio-studio" "$PLUGDIR/portfolio-studio"
python3 -I "$HERE/build-unsanitised-fields-variant.py" \
	"$REPO/portfolio-studio/includes/class-portfolio-studio-fields.php" \
	"$PLUGDIR/portfolio-studio/includes/class-portfolio-studio-fields.php" > "$FIXTURES/build-fields.log" 2>&1
BUILD2_RC=$?
cat "$FIXTURES/build-fields.log" | sed 's/^/  /'
if [ $BUILD2_RC -ne 0 ]; then
	say "  -> ECHEC : la variante affaiblie n'a pas pu etre construite"
	FAILURES=$(( FAILURES + 1 ))
else
	PS_PHASE=secu-hostile wp_cmd eval-file "$HERE/suite-secu.php" --user=1 > "$FIXTURES/secu-rouge2.log" 2>&1
	ROUGE2_RC=$?
	grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/secu-rouge2.log" | sed 's/^/  /'
	grep -c '^not ok ' "$FIXTURES/secu-rouge2.log" | sed 's/^/  assertions rouges : /'
	if [ $ROUGE2_RC -eq 0 ]; then
		say "  -> ECHEC : la suite aurait du ECHOUER sur la variante affaiblie"
		FAILURES=$(( FAILURES + 1 ))
	else
		say "  -> OK : la suite ECHOUE bien sur la variante affaiblie"
	fi
fi

# --- 3. restore ---------------------------------------------------------------
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
say "echecs : $FAILURES"
say "RESULTAT: $( [ $FAILURES -eq 0 ] && echo OK || echo ECHEC )"
say "=============================================================="

exit $FAILURES
