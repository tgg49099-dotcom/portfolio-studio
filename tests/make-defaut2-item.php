<?php
/**
 * Creates the item used by the test of the optional cleanup of the metadata.
 *
 * The item carries metadata of the plugin AND metadata of the site: the cleanup must
 * remove the first and leave the second.
 *
 * @package Portfolio_Studio_J1
 */

$ps_fixture = rtrim( getenv( 'PS_FIXTURES' ) ? getenv( 'PS_FIXTURES' ) : '/work/wp/preuves/cycleJ1b', '/' ) . '/defaut2-item.json';

$ps_item = wp_insert_post(
	array(
		'post_type'   => 'portfolio',
		'post_title'  => 'J1b metadata cleanup fixture',
		'post_status' => 'publish',
	)
);

if ( is_wp_error( $ps_item ) || ! $ps_item ) {
	echo "make-defaut2-item: the item could not be created\n";
	exit( 1 );
}

update_post_meta( $ps_item, 'portfolio_studio_client', 'Cleanup Client' );
update_post_meta( $ps_item, 'portfolio_studio_year', 2021 );
update_post_meta( $ps_item, 'portfolio_studio_role', 'Editor' );
update_post_meta( $ps_item, 'portfolio_studio_technologies', array( 'PHP', 'WordPress' ) );
update_post_meta( $ps_item, 'other_meta', 'kept' );

file_put_contents( $ps_fixture, wp_json_encode( array( 'id' => (int) $ps_item ) ) );

printf( "make-defaut2-item: item %d created\n", (int) $ps_item );
