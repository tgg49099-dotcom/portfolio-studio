<?php
/**
 * PREUVE — uninstall.php ne supprime JAMAIS les articles `portfolio` de l'utilisateur,
 * et ne supprime les 4 cles de meta du plugin que sur opt-in explicite.
 *
 *   wp eval-file /work/wp/j1/tests/uninstall-keep-proof.php -- <chemin de uninstall.php>
 *
 * Le script fait les DEUX passages dans le meme run :
 *   A. desinstallation SANS opt-in (le defaut) -> les 4 cles de meta restent,
 *      l'article de l'utilisateur reste ;
 *   B. desinstallation AVEC opt-in (filtre `portfolio_studio_uninstall_remove_post_meta`)
 *      -> les 4 cles de meta partent, l'article de l'utilisateur reste quand meme.
 *
 * Il cree lui-meme un article `portfolio` avec les 4 cles de meta du plugin et une
 * cle etrangere, puis nettoie ce qu'il a cree.
 *
 * Ce fichier est un TEST : il ne fait pas partie du plugin et n'entre pas dans l'archive
 * livree a WordPress.org.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'portfolio-studio/portfolio-studio.php' );
}

$ps_args   = isset( $args ) && is_array( $args ) ? $args : array();
$ps_path   = isset( $ps_args[0] ) ? (string) $ps_args[0] : '';
$ps_opt_in = isset( $ps_args[1] ) ? (string) $ps_args[1] : '';

if ( '' === $ps_path || ! file_exists( $ps_path ) ) {
	echo "uninstall-keep-proof: file not found: " . $ps_path . "\n";
	exit( 1 );
}

$ps_keys = array(
	'portfolio_studio_client',
	'portfolio_studio_year',
	'portfolio_studio_role',
	'portfolio_studio_technologies',
);

// --- 1. L'article de l'utilisateur ------------------------------------------
$ps_item = wp_insert_post(
	array(
		'post_type'    => 'portfolio',
		'post_title'   => 'J1b uninstall proof — item of the user',
		'post_content' => 'The content the user wrote.',
		'post_status'  => 'publish',
	)
);

if ( is_wp_error( $ps_item ) || ! $ps_item ) {
	echo "uninstall-keep-proof: the item could not be created\n";
	exit( 1 );
}

$ps_item = (int) $ps_item;
printf( "uninstall-keep-proof: article de l'utilisateur cree, id=%d\n", $ps_item );

foreach ( $ps_keys as $ps_key ) {
	update_post_meta( $ps_item, $ps_key, 'proof-' . $ps_key );
}
update_post_meta( $ps_item, 'other_meta', 'kept' );

// --- 2. Passage A : desinstallation SANS opt-in ------------------------------
echo "\n# passage A — desinstallation SANS opt-in (comportement par defaut)\n";

require $ps_path;

$ps_post = get_post( $ps_item );
PS_Test_Runner::ok(
	$ps_post instanceof WP_Post,
	"A1 l'article portfolio de l'utilisateur existe toujours apres la desinstallation",
	'get_post() a rendu ' . ( is_null( $ps_post ) ? 'null' : gettype( $ps_post ) )
);
PS_Test_Runner::ok(
	$ps_post instanceof WP_Post && 'trash' !== $ps_post->post_status,
	'A2 l\'article n\'a pas ete mis a la corbeille'
);
PS_Test_Runner::same(
	'J1b uninstall proof — item of the user',
	$ps_post instanceof WP_Post ? $ps_post->post_title : '',
	'A3 le titre de l\'article est intact'
);
PS_Test_Runner::same(
	'The content the user wrote.',
	$ps_post instanceof WP_Post ? $ps_post->post_content : '',
	'A4 le contenu de l\'article est intact'
);

$ps_meta_left = 0;
foreach ( $ps_keys as $ps_key ) {
	if ( ! metadata_exists( 'post', $ps_item, $ps_key ) ) {
		$ps_meta_left++;
	}
}
PS_Test_Runner::same(
	0,
	$ps_meta_left,
	'A5 sans opt-in, les 4 cles de meta du plugin sont conservees'
);
PS_Test_Runner::same(
	'kept',
	(string) get_post_meta( $ps_item, 'other_meta', true ),
	'A6 sans opt-in, la meta du site est intacte'
);
PS_Test_Runner::same(
	false,
	get_option( 'portfolio_studio_state' ),
	'A7 la routine a bien ete executee (option d\'etat supprimee)'
);

// --- 3. Passage B : desinstallation AVEC opt-in ------------------------------
echo "\n# passage B — desinstallation AVEC opt-in (nettoyage des metadonnees)\n";

add_filter( 'portfolio_studio_uninstall_remove_post_meta', '__return_true' );

require $ps_path;

$ps_post = get_post( $ps_item );
PS_Test_Runner::ok(
	$ps_post instanceof WP_Post,
	"B1 l'article portfolio de l'utilisateur existe TOUJOURS apres le nettoyage des metadonnees",
	'get_post() a rendu ' . ( is_null( $ps_post ) ? 'null' : gettype( $ps_post ) )
);
PS_Test_Runner::ok(
	$ps_post instanceof WP_Post && 'trash' !== $ps_post->post_status,
	'B2 l\'article n\'a pas ete mis a la corbeille'
);
PS_Test_Runner::same(
	'The content the user wrote.',
	$ps_post instanceof WP_Post ? $ps_post->post_content : '',
	'B3 le contenu de l\'article est intact'
);

$ps_meta_gone = 0;
foreach ( $ps_keys as $ps_key ) {
	if ( ! metadata_exists( 'post', $ps_item, $ps_key ) ) {
		$ps_meta_gone++;
	}
}
PS_Test_Runner::same(
	4,
	$ps_meta_gone,
	'B4 avec opt-in, les 4 cles de meta du plugin sont supprimees'
);
PS_Test_Runner::same(
	'kept',
	(string) get_post_meta( $ps_item, 'other_meta', true ),
	'B5 avec opt-in, la meta du site est intacte'
);

// --- 4. Nettoyage du temoin -------------------------------------------------
if ( $ps_post instanceof WP_Post ) {
	wp_delete_post( $ps_item, true );
}

exit( PS_Test_Runner::summary() );
