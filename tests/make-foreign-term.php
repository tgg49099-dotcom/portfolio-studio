<?php
/**
 * Creates the category that existed BEFORE Portfolio Studio was activated.
 *
 * The point of the test is a category that the plugin did not create: it must survive
 * the deactivation and the uninstall of the plugin.
 *
 * @package Portfolio_Studio_J1
 */

$ps_taxonomy = 'portfolio_category';
$ps_slug     = 'uncategorized';
$ps_fixture  = rtrim( getenv( 'PS_FIXTURES' ) ? getenv( 'PS_FIXTURES' ) : '/work/wp/preuves/cycleJ1b', '/' ) . '/defaut1-term.json';

if ( ! taxonomy_exists( $ps_taxonomy ) ) {
	register_taxonomy(
		$ps_taxonomy,
		'portfolio',
		array(
			'public'       => true,
			'hierarchical' => true,
			'show_ui'      => true,
		)
	);
}

// Remove any leftover of a previous run, marker included.
$ps_existing = get_term_by( 'slug', $ps_slug, $ps_taxonomy );
if ( $ps_existing && ! is_wp_error( $ps_existing ) ) {
	wp_delete_term( (int) $ps_existing->term_id, $ps_taxonomy );
	delete_term_meta( (int) $ps_existing->term_id, 'portfolio_studio_default_term' );
}

$ps_created = wp_insert_term(
	'Uncategorized',
	$ps_taxonomy,
	array(
		'slug'        => $ps_slug,
		'description' => 'Category that existed before Portfolio Studio was activated.',
	)
);

if ( is_wp_error( $ps_created ) ) {
	echo 'make-foreign-term: ' . $ps_created->get_error_message() . "\n";
	exit( 1 );
}

file_put_contents(
	$ps_fixture,
	wp_json_encode(
		array(
			'term_id'  => (int) $ps_created['term_id'],
			'taxonomy' => $ps_taxonomy,
			'slug'     => $ps_slug,
		)
	)
);

printf( "make-foreign-term: term %d created in %s\n", (int) $ps_created['term_id'], $ps_taxonomy );
