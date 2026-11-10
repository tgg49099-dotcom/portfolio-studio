#!/bin/bash
# =====================================================================
# Portfolio Studio — contre-epreuve de l'exclusion des sites archive,
# poubelle et supprime lors d'une activation reseau.
#
#   bash tests/run-exclusion-proof.sh [dossier-wordpress-multisite]
#
#   1. VERT  — le plugin tel qu'il est : les sites archive, poubelle et
#              supprime ne sont ni visites, ni ecrits, ni nettoyes.
#   2. ROUGE — le plugin tel qu'il etait AVANT la correction
#              (tests/variants/exclusion-avant/) : la suite ECHOUE.
#   3. VERT  — la suite repasse quand le fichier corrige est restitue.
#
# Un test qui ne peut pas echouer ne prouve rien : l'etat rouge est obligatoire.
#
# Variables d'environnement :
#   WP_DIR   installation WordPress MULTISITE (defaut : /work/wp/ms/wordpress)
#   PS_PHP   binaire PHP (defaut : /work/tools/php/x-8.5.8/php)
#   PS_WP_CLI binaire WP-CLI (defaut : /work/wp/wpenv/wp-cli.phar)
#
# Cout : 0 $. Aucun compte, aucun envoi, aucune publication.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"

WP_DIR="${1:-${WP_DIR:-/work/wp/ms/wordpress}}"
PS_PHP="${PS_PHP:-/work/tools/php/x-8.5.8/php}"
PS_WP_CLI="${PS_WP_CLI:-/work/wp/wpenv/wp-cli.phar}"

SRC="$REPO/portfolio-studio"
AVANT="$HERE/variants/exclusion-avant"
TARGET="$WP_DIR/wp-content/plugins/portfolio-studio"
FIXTURES="${PS_FIXTURES:-$(mktemp -d)}"
mkdir -p "$FIXTURES"

FAILURES=0
say() { printf '%s\n' "$*"; }

if [ ! -d "$WP_DIR" ]; then
	say "ECHEC: le bac a sable multisite est introuvable : $WP_DIR"
	say "  le construire d'abord : bash tests/setup-multisite-sandbox.sh"
	exit 2
fi
if [ ! -d "$AVANT" ]; then
	say "ECHEC: la variante avant correction est introuvable : $AVANT"
	exit 2
fi
if [ ! -d "$SRC" ]; then
	say "ECHEC: le dossier du plugin est introuvable : $SRC"
	exit 2
fi

wp_cmd() {
	"$PS_PHP" -d phar.require_hash=0 "$PS_WP_CLI" --allow-root --path="$WP_DIR" "$@" 2>&1 \
		| grep -v 'phar:///work/wp/wpenv/wp-cli.phar'
	return "${PIPESTATUS[0]}"
}

say "=============================================================="
say "Contre-epreuve de l'exclusion des sites archive/poubelle/supprime"
say "=============================================================="
say "WordPress  : $WP_DIR"
say "variante   : $AVANT"
say "plugin     : $SRC"
say "fixtures   : $FIXTURES"

if ! wp_cmd eval 'echo is_multisite() ? "MULTISITE" : "MONO-SITE";' | grep -q MULTISITE; then
	say "ECHEC: le bac a sable n'est pas un reseau (MULTISITE absent)"
	exit 2
fi

# --- installation du plugin --------------------------------------------------
rm -rf "$TARGET"
cp -a "$SRC" "$TARGET"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio --network > "$FIXTURES/activate.log" 2>&1
RC_ACT=$?
if [ $RC_ACT -ne 0 ]; then
	say "ECHEC: l'activation reseau du plugin a echoue (rc=$RC_ACT)"
	cat "$FIXTURES/activate.log"
	exit 1
fi

# --- 1. VERT ----------------------------------------------------------------
say ""
say "--- 1. VERT : le plugin tel qu'il est ---"
wp_cmd eval-file "$HERE/suite-multisite-exclusion.php" --user=1 > "$FIXTURES/exclusion-vert.log" 2>&1
VERT_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/exclusion-vert.log" | sed 's/^/  /'
if [ $VERT_RC -ne 0 ]; then
	say "  -> ECHEC : la suite doit passer sur le plugin corrige"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite passe sur le plugin corrige"
fi

# --- 2. ROUGE ---------------------------------------------------------------
say ""
say "--- 2. ROUGE : le plugin d avant la correction ---"
cp -f "$AVANT/class-portfolio-studio-lifecycle.php" "$TARGET/includes/class-portfolio-studio-lifecycle.php"
wp_cmd eval-file "$HERE/suite-multisite-exclusion.php" --user=1 > "$FIXTURES/exclusion-rouge.log" 2>&1
ROUGE_RC=$?
say "  assertions rouges : $(grep -c '^not ok ' "$FIXTURES/exclusion-rouge.log")"
grep '^not ok ' "$FIXTURES/exclusion-rouge.log" | sed 's/^/  /'
if [ $ROUGE_RC -eq 0 ]; then
	say "  -> ECHEC : la suite aurait du ECHOUER sur la variante d avant la correction"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite ECHOUE bien sur la variante d avant la correction"
fi

# --- 3. VERT (restitution) --------------------------------------------------
say ""
say "--- 3. VERT : le fichier corrige est restitue ---"
cp -f "$SRC/includes/class-portfolio-studio-lifecycle.php" "$TARGET/includes/class-portfolio-studio-lifecycle.php"
wp_cmd eval-file "$HERE/suite-multisite-exclusion.php" --user=1 > "$FIXTURES/exclusion-restitution.log" 2>&1
REST_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/exclusion-restitution.log" | sed 's/^/  /'
if [ $REST_RC -ne 0 ]; then
	say "  -> ECHEC : la suite doit repasser apres restitution"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite repasse apres restitution"
fi

# --- resume -----------------------------------------------------------------
say ""
say "=============================================================="
say "phases en echec : $FAILURES"
say "RESULTAT: $( [ $FAILURES -eq 0 ] && echo OK || echo ECHEC )"
say "=============================================================="

exit $FAILURES
