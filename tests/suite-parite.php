<?php
/**
 * Parity suite: the five parity defects measured against the original plugin.
 *
 * The original is `portfolio-post-type` 1.0.1 — the stable tag that the 40 000
 * installations actually run — downloaded from `downloads.wordpress.org`. Every
 * expected value below is the value the ORIGINAL produces, read from its own
 * source:
 *
 *   - `includes/class-portfolio-post-type-post-type.php` (labels, rewrite, menu)
 *   - `includes/class-portfolio-post-type-taxonomy-category.php`
 *   - `includes/class-portfolio-post-type-taxonomy-tag.php`
 *   - `includes/class-portfolio-post-type.php` (network activation, new sites)
 *
 * One test per defect:
 *
 *   BUG 1  the URL slugs of the taxonomies;
 *   BUG 2  `rewrite.with_front`;
 *   BUG 3  the position of the admin menu;
 *   BUG 4  the admin labels;
 *   BUG 5  the multisite activation.
 *
 * The suite runs inside a REAL WordPress through `wp eval-file`: it reads the
 * objects WordPress actually registered, not the source of the plugin.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

/**
 * The labels the original declares for the `portfolio` content type.
 *
 * @return array<string,string>
 */
function ps_parite_original_post_type_labels() {
	return array(
		'name'                  => 'Portfolio',
		'singular_name'         => 'Portfolio Item',
		'menu_name'             => 'Portfolio',
		'name_admin_bar'        => 'Portfolio Item',
		'add_new'               => 'Add New Item',
		'add_new_item'          => 'Add New Portfolio Item',
		'new_item'              => 'Add New Portfolio Item',
		'edit_item'             => 'Edit Portfolio Item',
		'view_item'             => 'View Item',
		'all_items'             => 'All Portfolio Items',
		'search_items'          => 'Search Portfolio',
		'parent_item_colon'     => 'Parent Portfolio Item:',
		'not_found'             => 'No portfolio items found',
		'not_found_in_trash'    => 'No portfolio items found in trash',
		'filter_items_list'     => 'Filter portfolio items list',
		'items_list'            => 'Portfolio items list',
		'items_list_navigation' => 'Portfolio items list navigation',
	);
}

/**
 * The labels the original declares for the `portfolio_category` taxonomy.
 *
 * @return array<string,string>
 */
function ps_parite_original_category_labels() {
	return array(
		'name'                       => 'Portfolio Categories',
		'singular_name'              => 'Portfolio Category',
		'menu_name'                  => 'Portfolio Categories',
		'edit_item'                  => 'Edit Portfolio Category',
		'update_item'                => 'Update Portfolio Category',
		'add_new_item'               => 'Add New Portfolio Category',
		'new_item_name'              => 'New Portfolio Category Name',
		'parent_item'                => 'Parent Portfolio Category',
		'parent_item_colon'          => 'Parent Portfolio Category:',
		'all_items'                  => 'All Portfolio Categories',
		'search_items'               => 'Search Portfolio Categories',
		'popular_items'              => 'Popular Portfolio Categories',
		'separate_items_with_commas' => 'Separate portfolio categories with commas',
		'add_or_remove_items'        => 'Add or remove portfolio categories',
		'choose_from_most_used'      => 'Choose from the most used portfolio categories',
		'not_found'                  => 'No portfolio categories found.',
		'items_list_navigation'      => 'Portfolio categories list navigation',
		'items_list'                 => 'Portfolio categories list',
		// `view_item` is not declared by the original: WordPress computes it from
		// the default labels of a hierarchical taxonomy — "View Category".
		'view_item'                  => 'View Category',
	);
}

/**
 * The labels the original declares for the `portfolio_tag` taxonomy.
 *
 * @return array<string,string>
 */
function ps_parite_original_tag_labels() {
	return array(
		'name'                       => 'Portfolio Tags',
		'singular_name'              => 'Portfolio Tag',
		'menu_name'                  => 'Portfolio Tags',
		'edit_item'                  => 'Edit Portfolio Tag',
		'update_item'                => 'Update Portfolio Tag',
		'add_new_item'               => 'Add New Portfolio Tag',
		'new_item_name'              => 'New Portfolio Tag Name',
		'parent_item'                => 'Parent Portfolio Tag',
		'parent_item_colon'          => 'Parent Portfolio Tag:',
		'all_items'                  => 'All Portfolio Tags',
		'search_items'               => 'Search Portfolio Tags',
		'popular_items'              => 'Popular Portfolio Tags',
		'separate_items_with_commas' => 'Separate portfolio tags with commas',
		'add_or_remove_items'        => 'Add or remove portfolio tags',
		'choose_from_most_used'      => 'Choose from the most used portfolio tags',
		'not_found'                  => 'No portfolio tags found.',
		'items_list_navigation'      => 'Portfolio tags list navigation',
		'items_list'                 => 'Portfolio tags list',
		// `view_item` is not declared by the original: WordPress computes it from
		// the default labels of a flat taxonomy — "View Tag".
		'view_item'                  => 'View Tag',
	);
}

/**
 * The value of a label of a registered object, or null when it is not set.
 *
 * @param object $labels The `labels` object of a post type or of a taxonomy.
 * @param string $key    The label name.
 * @return string|null
 */
function ps_parite_label( $labels, $key ) {
	if ( ! is_object( $labels ) || ! isset( $labels->{$key} ) ) {
		return null;
	}

	return (string) $labels->{$key};
}

/**
 * Compare a set of labels with the labels of the original.
 *
 * @param object                $labels   The `labels` object of a registered object.
 * @param array<string,string>  $expected The labels of the original.
 * @param string                $what     The name of the object, for the messages.
 * @return void
 */
function ps_parite_check_labels( $labels, array $expected, $what ) {
	foreach ( $expected as $key => $value ) {
		PS_Test_Runner::same(
			$value,
			ps_parite_label( $labels, $key ),
			sprintf( 'BUG 4 : label %s of %s', $key, $what )
		);
	}
}

$runner = 'Portfolio Studio — suite de parité (5 BUG)';
printf( "# %s\n", $runner );

// ---------------------------------------------------------------------------
// The plugin must own the content type and its taxonomies, otherwise the
// objects read below would be the ones of another plugin.
// ---------------------------------------------------------------------------
PS_Test_Runner::ok(
	post_type_exists( 'portfolio' ),
	'le type de contenu portfolio est enregistre'
);

$post_type = get_post_type_object( 'portfolio' );
$category  = get_taxonomy( 'portfolio_category' );
$tag       = get_taxonomy( 'portfolio_tag' );

PS_Test_Runner::ok(
	null !== $post_type,
	'l objet du type de contenu portfolio est lisible'
);
PS_Test_Runner::ok(
	null !== $category,
	'l objet de la taxonomie portfolio_category est lisible'
);
PS_Test_Runner::ok(
	null !== $tag,
	'l objet de la taxonomie portfolio_tag est lisible'
);

// The arguments the plugin passes to WordPress are read as well: they are what
// the registration is built from, and they do not depend on what another plugin
// has already registered.
$post_type_args = Portfolio_Studio_Content_Types::post_type_args();
$category_args  = Portfolio_Studio_Content_Types::taxonomy_args( 'portfolio_category', 'category' );
$tag_args       = Portfolio_Studio_Content_Types::taxonomy_args( 'portfolio_tag', 'tag' );

// ---------------------------------------------------------------------------
// BUG 1 — the URL slugs of the taxonomies.
//
// The original publishes `portfolio_category` and `portfolio_tag`; we published
// `portfolio-category` and `portfolio-tag`. The URL of the categories and of the
// tags changed: the links of the sites that migrated are broken.
// ---------------------------------------------------------------------------
PS_Test_Runner::same(
	'portfolio_category',
	isset( $category->rewrite['slug'] ) ? $category->rewrite['slug'] : null,
	'BUG 1 : le slug d URL de la taxonomie portfolio_category est portfolio_category'
);
PS_Test_Runner::same(
	'portfolio_tag',
	isset( $tag->rewrite['slug'] ) ? $tag->rewrite['slug'] : null,
	'BUG 1 : le slug d URL de la taxonomie portfolio_tag est portfolio_tag'
);
PS_Test_Runner::same(
	'portfolio_category',
	isset( $category_args['rewrite']['slug'] ) ? $category_args['rewrite']['slug'] : null,
	'BUG 1 : les arguments de la taxonomie portfolio_category annonce portfolio_category'
);
PS_Test_Runner::same(
	'portfolio_tag',
	isset( $tag_args['rewrite']['slug'] ) ? $tag_args['rewrite']['slug'] : null,
	'BUG 1 : les arguments de la taxonomie portfolio_tag annonce portfolio_tag'
);

// ---------------------------------------------------------------------------
// BUG 2 — `rewrite.with_front`.
//
// The original does not set it, so it is `true`: the URL keeps the prefix of
// the permalink of the site. We set it to `false`: the prefix was lost.
// ---------------------------------------------------------------------------
PS_Test_Runner::same(
	true,
	isset( $post_type->rewrite['with_front'] ) ? $post_type->rewrite['with_front'] : null,
	'BUG 2 : with_front du type de contenu est true'
);
PS_Test_Runner::same(
	true,
	isset( $category->rewrite['with_front'] ) ? $category->rewrite['with_front'] : null,
	'BUG 2 : with_front de la taxonomie portfolio_category est true'
);
PS_Test_Runner::same(
	true,
	isset( $tag->rewrite['with_front'] ) ? $tag->rewrite['with_front'] : null,
	'BUG 2 : with_front de la taxonomie portfolio_tag est true'
);
PS_Test_Runner::same(
	true,
	isset( $post_type_args['rewrite']['with_front'] ) ? $post_type_args['rewrite']['with_front'] : null,
	'BUG 2 : les arguments du type de contenu annoncent with_front true'
);

// ---------------------------------------------------------------------------
// BUG 3 — the position of the admin menu.
//
// The original puts the "Portfolio" menu at the position 5; we put it at 26.
// ---------------------------------------------------------------------------
PS_Test_Runner::same(
	5,
	isset( $post_type->menu_position ) ? (int) $post_type->menu_position : null,
	'BUG 3 : la position du menu Portfolio est 5'
);
PS_Test_Runner::same(
	5,
	isset( $post_type_args['menu_position'] ) ? (int) $post_type_args['menu_position'] : null,
	'BUG 3 : les arguments du type de contenu annoncent menu_position 5'
);

// ---------------------------------------------------------------------------
// BUG 4 — the admin labels.
//
// 6 labels of the content type, 7 of the category, 4 of the tag.
// ---------------------------------------------------------------------------
ps_parite_check_labels(
	isset( $post_type->labels ) ? $post_type->labels : null,
	ps_parite_original_post_type_labels(),
	'le type de contenu portfolio'
);
ps_parite_check_labels(
	isset( $category->labels ) ? $category->labels : null,
	ps_parite_original_category_labels(),
	'la taxonomie portfolio_category'
);
ps_parite_check_labels(
	isset( $tag->labels ) ? $tag->labels : null,
	ps_parite_original_tag_labels(),
	'la taxonomie portfolio_tag'
);

// The labels are declared, not only computed: the arguments must carry them too.
$ps_parite_declared = array(
	'portfolio_category' => array( $category_args, ps_parite_original_category_labels() ),
	'portfolio_tag'      => array( $tag_args, ps_parite_original_tag_labels() ),
);

foreach ( $ps_parite_declared as $name => $pair ) {
	list( $ps_parite_args, $ps_parite_expected ) = $pair;

	foreach ( $ps_parite_expected as $key => $value ) {
		PS_Test_Runner::same(
			$value,
			isset( $ps_parite_args['labels'][ $key ] ) ? $ps_parite_args['labels'][ $key ] : null,
			sprintf( 'BUG 4 : l argument labels[%s] de %s vaut la valeur de l original', $key, $name )
		);
	}
}

// ---------------------------------------------------------------------------
// BUG 5 — the multisite activation.
//
// The original configures every site of the network when the plugin is network
// activated, and every site created afterwards. We were discarding the
// `$network_wide` flag and we had no hook for the new sites.
//
// The multisite API is not loaded on a single site since WordPress 6.4, so the
// suite loads it here: the list of the sites is injected with the
// `sites_pre_query` filter, and nothing is written to the network tables.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'get_sites' ) ) {
	require_once ABSPATH . WPINC . '/class-wp-site-query.php';
	require_once ABSPATH . WPINC . '/ms-blogs.php';
}

PS_Test_Runner::ok(
	false !== has_action( 'wp_initialize_site' ),
	'BUG 5 : le plugin ecoute wp_initialize_site'
);
PS_Test_Runner::ok(
	false === has_action( 'wpmu_new_blog' ),
	'BUG 5 : le plugin n ecoute plus le crochet deprecie wpmu_new_blog'
);

// The sites visited by the plugin are counted with the `switch_blog` action.
$ps_parite_switched = array();
$ps_parite_listener  = function ( $new_blog_id, $prev_blog_id, $context ) use ( &$ps_parite_switched ) {
	if ( 'switch' === $context ) {
		$ps_parite_switched[] = (int) $new_blog_id;
	}
};
add_action( 'switch_blog', $ps_parite_listener, 10, 3 );

// --- a new site is configured as soon as it is created ----------------------
// The site is configured ONLY when the plugin is activated for the whole network:
// on a network where the plugin is activated on a single site only, the sites that
// are created later must be left alone — the plugin has no business writing there.
$ps_parite_switched = array();
do_action( 'wp_initialize_site', (object) array( 'blog_id' => 1 ) );
PS_Test_Runner::same(
	array(),
	$ps_parite_switched,
	'BUG 5 : un site nouveau n est PAS configure quand le plugin n est pas active pour le reseau'
);

// The same site IS configured when the plugin is activated for the whole network.
$ps_parite_network_before = get_site_option( 'active_sitewide_plugins', null );
update_site_option(
	'active_sitewide_plugins',
	array( 'portfolio-studio/portfolio-studio.php' => time() )
);

$ps_parite_switched = array();
do_action( 'wp_initialize_site', (object) array( 'blog_id' => 1 ) );
PS_Test_Runner::same(
	array( 1 ),
	$ps_parite_switched,
	'BUG 5 : wp_initialize_site configure le site cree quand le plugin est active pour le reseau'
);

if ( null === $ps_parite_network_before ) {
	delete_site_option( 'active_sitewide_plugins' );
} else {
	update_site_option( 'active_sitewide_plugins', $ps_parite_network_before );
}

// --- a network activation configures every site of the network -------------
$ps_parite_sites = function () {
	return array( 1, 2, 3 );
};
add_filter( 'sites_pre_query', $ps_parite_sites );

$ps_parite_switched = array();
Portfolio_Studio_Lifecycle::activate( true );
PS_Test_Runner::same(
	array( 1, 2, 3 ),
	$ps_parite_switched,
	'BUG 5 : une activation reseau configure tous les sites du reseau'
);

remove_filter( 'sites_pre_query', $ps_parite_sites );
remove_action( 'switch_blog', $ps_parite_listener, 10 );

exit( PS_Test_Runner::summary() );
