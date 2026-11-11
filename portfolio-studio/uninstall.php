<?php
/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the Plugins screen, not when it is deactivated.
 * It removes everything the plugin owns and nothing the user wrote.
 *
 * @package Portfolio_Studio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-portfolio-studio-content-types.php';
require_once __DIR__ . '/includes/class-portfolio-studio-meta.php';
require_once __DIR__ . '/includes/class-portfolio-studio-lifecycle.php';

// The plugin classes are not loaded during an uninstall (the plugin is gone), so the
// content type and the taxonomies are not registered in this request. The cleanup below
// therefore works on the stored data, not on the runtime objects.

/**
 * Remove what the plugin owns on the CURRENT site.
 *
 * @return void
 */
function portfolio_studio_uninstall_current_site() {
	// The state is read BEFORE it is deleted: it tells which taxonomy the default category
	// was created in, with another portfolio plugin installed, that taxonomy belongs to
	// that plugin.
	$portfolio_studio_state = get_option( Portfolio_Studio_Lifecycle::OPTION );

	$portfolio_studio_taxonomies = Portfolio_Studio_Lifecycle::default_term_taxonomies();
	if ( is_array( $portfolio_studio_state ) && ! empty( $portfolio_studio_state['category_taxonomy'] ) ) {
		array_unshift( $portfolio_studio_taxonomies, (string) $portfolio_studio_state['category_taxonomy'] );
		$portfolio_studio_taxonomies = array_values( array_unique( $portfolio_studio_taxonomies ) );
	}

	delete_option( Portfolio_Studio_Lifecycle::OPTION );

	// Remove the default category this plugin created, but only while it is still empty.
	// Only the terms that carry our own term meta are considered: a category that this
	// plugin did not create, the `uncategorized` category of WordPress, a category of
	// another portfolio plugin, a category an editor created by hand, is never deleted.
	foreach ( $portfolio_studio_taxonomies as $portfolio_studio_taxonomy ) {
		if ( ! Portfolio_Studio_Lifecycle::ensure_taxonomy_registered( $portfolio_studio_taxonomy ) ) {
			continue;
		}

		$portfolio_studio_term = Portfolio_Studio_Lifecycle::owned_default_term( $portfolio_studio_taxonomy );
		if ( null === $portfolio_studio_term ) {
			continue;
		}

		if ( (int) $portfolio_studio_term->count > 0 ) {
			continue;
		}

		wp_delete_term( (int) $portfolio_studio_term->term_id, $portfolio_studio_taxonomy );
	}

	// User written metadata is kept by default: deleting it would destroy the content of
	// the site. A site owner can opt in explicitly.
	if ( apply_filters( 'portfolio_studio_uninstall_remove_post_meta', false ) ) {
		// Only the keys this plugin declares are removed, and they are removed through the
		// WordPress API: no raw SQL is used here, and the metadata cache is invalidated.
		foreach ( Portfolio_Studio_Meta::keys() as $portfolio_studio_key ) {
			delete_post_meta_by_key( $portfolio_studio_key );
		}
	}
}

// The plugin can be activated for the whole network: the network activation writes its
// option and its default category in EVERY site of the network. The uninstall must
// therefore clean every site of the network, not only the one the administrator happens
// to be on, otherwise the sites of the network keep the settings of a plugin that has
// been deleted.
//
// The archived, the spam and the deleted sites are left out: they are not in use. They
// are the same three flags the network activation uses.
$portfolio_studio_site_ids = array();

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$portfolio_studio_site_ids = get_sites(
		array(
			'fields'   => 'ids',
			'number'   => 0,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		)
	);
}

if ( ! is_array( $portfolio_studio_site_ids ) ) {
	$portfolio_studio_site_ids = array();
}

// The current site is always cleaned, even when the list of the sites is empty (a single
// site, or a site that is not in the list of the network).
$portfolio_studio_current_site = get_current_blog_id();
$portfolio_studio_site_ids[]   = $portfolio_studio_current_site;
$portfolio_studio_site_ids     = array_values( array_unique( array_map( 'intval', $portfolio_studio_site_ids ) ) );

foreach ( $portfolio_studio_site_ids as $portfolio_studio_site_id ) {
	if ( $portfolio_studio_site_id !== $portfolio_studio_current_site && function_exists( 'switch_to_blog' ) ) {
		switch_to_blog( $portfolio_studio_site_id );
	}

	portfolio_studio_uninstall_current_site();

	if ( $portfolio_studio_site_id !== $portfolio_studio_current_site && function_exists( 'restore_current_blog' ) ) {
		restore_current_blog();
	}
}
