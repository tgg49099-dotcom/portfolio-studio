<?php
/**
 * Removes the default category of the portfolio taxonomies.
 *
 * The J1 suite tests the activation on a site that has no portfolio category yet.
 * The J1b suite, on the other hand, deliberately leaves a category that existed before
 * the activation behind — that category must survive. This script restores the
 * precondition of the J1 scenario.
 *
 * The `category` taxonomy of WordPress is never touched: only the portfolio taxonomies
 * are looked at.
 *
 * @package Portfolio_Studio_J1
 */

foreach ( array( 'portfolio_category', 'portfolio-category' ) as $ps_taxonomy ) {
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

	$ps_term = get_term_by( 'slug', 'uncategorized', $ps_taxonomy );

	if ( $ps_term && ! is_wp_error( $ps_term ) ) {
		wp_delete_term( (int) $ps_term->term_id, $ps_taxonomy );
		delete_term_meta( (int) $ps_term->term_id, 'portfolio_studio_default_term' );
	}
}

echo "clean-default-term: done\n";
