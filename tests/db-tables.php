<?php
/**
 * Print the list of tables of the current database, one per line.
 *
 * Used by the runner to prove that the plugin creates no table of its own and leaves
 * no orphan table behind.
 *
 * @package Portfolio_Studio_J1
 */

global $wpdb;

$tables = array();

$show = $wpdb->get_col( 'SHOW TABLES' );
if ( ! empty( $show ) ) {
	$tables = $show;
} else {
	// SQLite drop-in fallback.
	$show = $wpdb->get_col( "SELECT name FROM sqlite_master WHERE type='table'" );
	if ( ! empty( $show ) ) {
		$tables = $show;
	}
}

sort( $tables );

foreach ( $tables as $table ) {
	echo $table, "\n";
}
