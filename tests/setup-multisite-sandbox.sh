#!/bin/bash
# =====================================================================
# Portfolio Studio — montage du bac a sable MULTISITE du test d exclusion.
#
#   bash tests/setup-multisite-sandbox.sh [dossier-cible]
#
# Le test d exclusion (tests/suite-multisite-exclusion.php) a besoin d'un
# VRAI reseau : les autres suites.stubbent `get_sites()` avec le filtre
# `sites_pre_query`, qui court-circuite la requete SQL — et c'est exactement
# la requete SQL qui porte le defaut.
#
# Ce script :
#   1. copie une installation WordPress (mono-site) dans un dossier a part ;
#   2. lui donne sa propre base SQLite ;
#   3. l'installe, puis la convertit en reseau (subdirectory) ;
#   4. cree quatre sites : un vivant, un archive, un poubelle, un supprime.
#
# Variables d'environnement :
#   PS_WP_SOURCE  installation WordPress de depart (defaut :
#                  /work/wp/wpenv/wordpress)
#   PS_MS_DIR     dossier cible du bac a sable (defaut : /work/wp/ms/wordpress)
#   PS_PHP        binaire PHP (defaut : /work/tools/php/x-8.5.8/php)
#   PS_WP_CLI     binaire WP-CLI (defaut : /work/wp/wpenv/wp-cli.phar)
#
# Cout : 0 $. Aucun compte, aucun envoi, aucune publication.
# =====================================================================

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

PS_WP_SOURCE="${PS_WP_SOURCE:-/work/wp/wpenv/wordpress}"
PS_MS_DIR="${PS_MS_DIR:-/work/wp/ms/wordpress}"
PS_PHP="${PS_PHP:-/work/tools/php/x-8.5.8/php}"
PS_WP_CLI="${PS_WP_CLI:-/work/wp/wpenv/wp-cli.phar}"

say() { printf '%s\n' "$*"; }

if [ ! -d "$PS_WP_SOURCE" ]; then
	say "ECHEC: installation WordPress de depart introuvable : $PS_WP_SOURCE"
	exit 2
fi

# --- 0. le bac a sable existe deja ? ---------------------------------------
if [ -f "$PS_MS_DIR/wp-config.php" ] && grep -q "MULTISITE" "$PS_MS_DIR/wp-config.php"; then
	say "Le bac a sable multisite existe deja : $PS_MS_DIR"
else
	# --- 1. copie ------------------------------------------------------------
	say "copie de $PS_WP_SOURCE vers $PS_MS_DIR ..."
	mkdir -p "$(dirname "$PS_MS_DIR")"
	rm -rf "$PS_MS_DIR"
	cp -a "$PS_WP_SOURCE" "$PS_MS_DIR"
	rm -f "$PS_MS_DIR/wp-content/database/wpenv.sqlite"

	# --- 2. base propre ------------------------------------------------------
	python3 -I - "$PS_MS_DIR/wp-config.php" <<'PY'
import sys
path = sys.argv[1]
s = open(path, encoding='utf-8').read()
s = s.replace("define( 'DB_FILE', 'wpenv.sqlite' );", "define( 'DB_FILE', 'wpenv-ms.sqlite' );")
open(path, 'w', encoding='utf-8').write(s)
print('base SQLite du bac a sable : wpenv-ms.sqlite')
PY

	# --- 3. installation + conversion en reseau ------------------------------
	MSPHP="$PS_PHP"
	MSCLI="$PS_WP_CLI"

	"$MSPHP" -d phar.require_hash=0 "$MSCLI" --allow-root --path="$PS_MS_DIR" core install \
		--url=wp.test --title="PS Multisite" --admin_user=admin --admin_password=admin123 \
		--admin_email=admin@example.com --skip-email > /dev/null 2>&1
	RC_INSTALL=$?
	if [ $RC_INSTALL -ne 0 ]; then
		say "ECHEC: installation du bac a sable impossible (rc=$RC_INSTALL)"
		exit 1
	fi
	say "installation faite"

	"$MSPHP" -d phar.require_hash=0 "$MSCLI" --allow-root --path="$PS_MS_DIR" core multisite-convert \
		--title="PS Multisite" --subdomains=false > /dev/null 2>&1
	RC_CONVERT=$?
	if [ $RC_CONVERT -ne 0 ]; then
		say "ECHEC: conversion en reseau impossible (rc=$RC_CONVERT)"
		exit 1
	fi

	# Les constantes du reseau ne sont pas toujours ecrites dans wp-config.php.
	python3 -I - "$PS_MS_DIR/wp-config.php" <<'PY'
import sys
path = sys.argv[1]
s = open(path, encoding='utf-8').read()
if 'MULTISITE' in s:
    print('constantes deja presentes')
    sys.exit(0)
block = """
/* Multisite (bac a sable de test) */
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
define( 'DOMAIN_CURRENT_SITE', 'wp.test:8080' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

if ( ! defined( 'ABSPATH' ) ) {"""
s = s.replace("\nif ( ! defined( 'ABSPATH' ) ) {", block, 1)
open(path, 'w', encoding='utf-8').write(s)
print('constantes du reseon ecrites')
PY
	say "reseau installe"
fi

# --- 4. les quatre sites du test -------------------------------------------
MSPHP="$PS_PHP"
MSCLI="$PS_WP_CLI"

ms_wp() {
	"$MSPHP" -d phar.require_hash=0 "$MSCLI" --allow-root --path="$PS_MS_DIR" "$@" 2>&1 \
		| grep -v 'phar:///work/wp/wpenv/wp-cli.phar'
	return "${PIPESTATUS[0]}"
}

for slug in msx-live msx-archived msx-spam msx-deleted; do
	if ! ms_wp site list --field=url 2>/dev/null | grep -q "/$slug/"; then
		ms_wp site create --slug="$slug" --quiet > /dev/null 2>&1
	fi
done

ms_wp eval '
global $wpdb;
$wpdb->update( $wpdb->blogs, array( "archived" => 1 ), array( "path" => "/msx-archived/" ) );
$wpdb->update( $wpdb->blogs, array( "spam"     => 1 ), array( "path" => "/msx-spam/" ) );
$wpdb->update( $wpdb->blogs, array( "deleted"  => 1 ), array( "path" => "/msx-deleted/" ) );
$rows = $wpdb->get_results( "SELECT blog_id, path, archived, spam, deleted FROM {$wpdb->blogs} ORDER BY blog_id ASC", ARRAY_A );
foreach ( $rows as $r ) { echo implode( " | ", $r ), "\n"; }
' > /dev/null 2>&1

say ""
say "etat du reseau de test :"
ms_wp eval '
global $wpdb;
$rows = $wpdb->get_results( "SELECT blog_id, path, archived, spam, deleted FROM {$wpdb->blogs} ORDER BY blog_id ASC", ARRAY_A );
foreach ( $rows as $r ) { echo "  ", implode( " | ", $r ), "\n"; }
'

say ""
say "RESULTAT: OK"
exit 0
