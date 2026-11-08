#!/bin/bash
# =====================================================================
# Portfolio Studio — contre-epreuve des cinq correctifs de parite.
#
#   bash tests/run-parite-proof.sh /path/to/wordpress [avant-dir] [plugin-src]
#
#   1. VERT  — la suite de parite passe contre le plugin tel qu'il est.
#   2. ROUGE — la MEME suite echoue contre le plugin tel qu'il etait AVANT
#              les cinq correctifs (les fichiers de `tests/variants/parite-avant/`).
#   3. VERT  — la suite repasse quand les fichiers corriges sont restitues.
#
# Un test qui ne peut pas echouer ne prouve rien : l'etat rouge est obligatoire.
#
# Variables d'environnement :
#   WP_DIR   chemin de l'installation WordPress (par defaut : le 1er argument)
#   WP_CLI   chemin du binaire WP-CLI (par defaut : wp)
#
# Cout : 0 $. Aucun compte, aucun envoi, aucune publication.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"

WP_DIR="${1:-${WP_DIR:-}}"
WP_CLI="${WP_CLI:-wp}"
AVANT="${2:-$HERE/variants/parite-avant}"
SRC="${3:-$REPO/portfolio-studio}"
AVANT_MULTI="${4:-$HERE/variants/multisite-avant}"

if [ -z "$WP_DIR" ] || [ ! -d "$WP_DIR" ]; then
	echo "usage: bash tests/run-parite-proof.sh /path/to/wordpress [avant-dir]"
	exit 2
fi
if [ ! -d "$AVANT" ]; then
	echo "ECHEC: le dossier de la variante avant correction est introuvable : $AVANT"
	exit 2
fi
if [ ! -d "$AVANT_MULTI" ]; then
	echo "ECHEC: le dossier de la variante avant la correction multisite est introuvable : $AVANT_MULTI"
	exit 2
fi
if [ ! -d "$SRC" ]; then
	echo "ECHEC: le dossier du plugin est introuvable : $SRC"
	exit 2
fi

PLUGDIR="$WP_DIR/wp-content/plugins"
TARGET="$PLUGDIR/portfolio-studio"
FIXTURES="${PS_FIXTURES:-$(mktemp -d)}"
mkdir -p "$FIXTURES"

FAILURES=0
say() { printf '%s\n' "$*"; }

wp_cmd() {
	"$WP_CLI" --allow-root --path="$WP_DIR" "$@"
}

# The three files that carry the five fixes.
FILES="portfolio-studio.php includes/class-portfolio-studio-content-types.php includes/class-portfolio-studio-lifecycle.php"

say "=============================================================="
say "Contre-epreuve des cinq correctifs de parite"
say "=============================================================="
say "WordPress  : $WP_DIR"
say "variante   : $AVANT"
say "plugin     : $SRC"
say "fixtures   : $FIXTURES"

# The suite must be run against a plugin that owns the content type: another
# portfolio plugin installed on the site would register it first.
if [ -d "$PLUGDIR/portfolio-post-type" ]; then
	say "portfolio-post-type est installe : il est retire pour la mesure"
	wp_cmd plugin deactivate portfolio-post-type > /dev/null 2>&1
	rm -rf "$PLUGDIR/portfolio-post-type"
fi

ACTIVE_BEFORE="$(wp_cmd plugin list --status=active --field=name 2>/dev/null | grep -v '^sqlite-database-integration$' | grep -v '^db.php$')"

# --- 1. VERT ---------------------------------------------------------------
say ""
say "--- 1. VERT : le plugin tel qu'il est ---"
rm -rf "$TARGET"
cp -a "$SRC" "$TARGET"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate-vert.log" 2>&1
PS_PHASE=parite wp_cmd eval-file "$HERE/suite-parite.php" --user=1 > "$FIXTURES/parite-vert.log" 2>&1
VERT_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/parite-vert.log" | sed 's/^/  /'
if [ $VERT_RC -ne 0 ]; then
	say "  -> ECHEC : la suite de parite doit passer sur le plugin corrige"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite de parite passe sur le plugin corrige"
fi

# --- 2. ROUGE --------------------------------------------------------------
say ""
say "--- 2. ROUGE : le plugin d avant les correctifs de parite ---"
for f in $FILES; do
	cp -f "$AVANT/$(basename "$f")" "$TARGET/$f"
done
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate-rouge.log" 2>&1
PS_PHASE=parite wp_cmd eval-file "$HERE/suite-parite.php" --user=1 > "$FIXTURES/parite-rouge.log" 2>&1
ROUGE_RC=$?
say "  assertions rouges : $(grep -c '^not ok ' "$FIXTURES/parite-rouge.log")"
grep '^not ok ' "$FIXTURES/parite-rouge.log" | sed 's/^/  /'
if [ $ROUGE_RC -eq 0 ]; then
	say "  -> ECHEC : la suite aurait du ECHOUER sur la variante d avant les correctifs"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite ECHOUE bien sur la variante d avant les correctifs"
fi

# Each of the five defects must be caught by at least one assertion.
for bug in "BUG 1" "BUG 2" "BUG 3" "BUG 4" "BUG 5"; do
	COUNT=$(grep "^not ok .*${bug} " "$FIXTURES/parite-rouge.log" | grep -c . || true)
	if [ "$COUNT" -eq 0 ]; then
		say "  -> ECHEC : aucune assertion rouge pour ${bug}"
		FAILURES=$(( FAILURES + 1 ))
	else
		say "  -> OK : ${bug} : ${COUNT} assertion(s) rouge(s)"
	fi
done

# --- 3. VERT again ---------------------------------------------------------
say ""
say "--- 3. VERT : les fichiers corriges sont restitues ---"
rm -rf "$TARGET"
cp -a "$SRC" "$TARGET"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate-vert2.log" 2>&1
PS_PHASE=parite wp_cmd eval-file "$HERE/suite-parite.php" --user=1 > "$FIXTURES/parite-vert2.log" 2>&1
VERT2_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/parite-vert2.log" | sed 's/^/  /'
if [ $VERT2_RC -ne 0 ]; then
	say "  -> ECHEC : la suite doit repasser apres restitution"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite repasse apres restitution"
fi

# --- 4. ROUGE : the plugin of BEFORE the multisite correction ---------------
# The variant is the 0.1.1 code: it listens to the deprecated hook and it
# configures every new site of the network, even the ones where the plugin is
# not activated. The same suite must catch both.
say ""
say "--- 4. ROUGE : le plugin d avant la correction multisite (variante multisite-avant) ---"
for f in portfolio-studio.php includes/class-portfolio-studio-lifecycle.php; do
	cp -f "$AVANT_MULTI/$(basename "$f")" "$TARGET/$f"
done
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate-multi-rouge.log" 2>&1
PS_PHASE=parite wp_cmd eval-file "$HERE/suite-parite.php" --user=1 > "$FIXTURES/parite-multi-rouge.log" 2>&1
MULTI_ROUGE_RC=$?
say "  assertions rouges : $(grep -c '^not ok ' "$FIXTURES/parite-multi-rouge.log")"
grep '^not ok ' "$FIXTURES/parite-multi-rouge.log" | sed 's/^/  /'
if [ $MULTI_ROUGE_RC -eq 0 ]; then
	say "  -> ECHEC : la suite aurait du ECHOUER sur la variante d avant la correction multisite"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite ECHOUE bien sur la variante d avant la correction multisite"
fi

# Each new assertion must be one of the red ones.
for marker in \
	"n ecoute plus le crochet deprecie" \
	"n est PAS configure quand le plugin n est pas active pour le reseau"
do
	COUNT=$(grep "^not ok .*${marker}" "$FIXTURES/parite-multi-rouge.log" | grep -c . || true)
	if [ "$COUNT" -eq 0 ]; then
		say "  -> ECHEC : aucune assertion rouge pour « ${marker} »"
		FAILURES=$(( FAILURES + 1 ))
	else
		say "  -> OK : « ${marker} » : ${COUNT} assertion(s) rouge(s)"
	fi
done

# --- 5. VERT again ---------------------------------------------------------
say ""
say "--- 5. VERT : les fichiers corriges sont restitues ---"
rm -rf "$TARGET"
cp -a "$SRC" "$TARGET"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
wp_cmd plugin activate portfolio-studio > "$FIXTURES/activate-vert3.log" 2>&1
PS_PHASE=parite wp_cmd eval-file "$HERE/suite-parite.php" --user=1 > "$FIXTURES/parite-vert3.log" 2>&1
VERT3_RC=$?
grep -E '^(not ok|# passed|# failed|1\.\.)' "$FIXTURES/parite-vert3.log" | sed 's/^/  /'
if [ $VERT3_RC -ne 0 ]; then
	say "  -> ECHEC : la suite doit repasser apres restitution"
	FAILURES=$(( FAILURES + 1 ))
else
	say "  -> OK : la suite repasse apres restitution"
fi

# --- 6. restore the test site ---------------------------------------------
say ""
say "--- restitution du site de test ---"
wp_cmd plugin deactivate portfolio-studio > /dev/null 2>&1
rm -rf "$TARGET"
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
