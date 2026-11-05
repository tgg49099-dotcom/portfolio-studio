<?php
/**
 * Runs the uninstall routine of Portfolio Studio in a controlled way.
 *
 *   wp eval-file /work/wp/j1/tests/uninstall-runner.php -- <path of uninstall.php> [remove-meta]
 *
 * WordPress runs `uninstall.php` when a plugin is deleted from the Plugins screen:
 * it defines WP_UNINSTALL_PLUGIN, then includes the file. This runner reproduces that
 * sequence so that the uninstall can be tested — including the optional cleanup of the
 * metadata, which is opt-in through the `portfolio_studio_uninstall_remove_post_meta`
 * filter.
 *
 * @package Portfolio_Studio_J1
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'portfolio-studio/portfolio-studio.php' );
}

$ps_args = isset( $args ) && is_array( $args ) ? $args : array();
$ps_path = isset( $ps_args[0] ) ? (string) $ps_args[0] : '';
$ps_meta = isset( $ps_args[1] ) ? (string) $ps_args[1] : '';

if ( '' === $ps_path || ! file_exists( $ps_path ) ) {
	echo "uninstall-runner: file not found: " . $ps_path . "\n";
	exit( 1 );
}

if ( 'remove-meta' === $ps_meta ) {
	add_filter( 'portfolio_studio_uninstall_remove_post_meta', '__return_true' );
}

require $ps_path;

echo "uninstall-runner: uninstall.php executed\n";
