<?php
/**
 * Exclusion of the archived, the spam and the deleted sites on a network activation.
 *
 * THE DEFECT (found by the mother on 10/11/2026, by re-reading the delivered ZIP):
 * `Portfolio_Studio_Lifecycle::network_site_ids()` announced that the archived, the
 * spam and the deleted sites were left out, but it called `get_sites()` WITHOUT the
 * three flags. `WP_Site_Query` adds the `archived = 0`, `spam = 0` and `deleted = 0`
 * clauses ONLY when the flag is given: the defaults are `null`, `null` is not numeric,
 * so the clause is not added at all and the query carries no filter
 * (core source: wp-includes/class-wp-site-query.php, `if ( is_numeric( $this->query_vars['archived'] ) )`).
 *
 * The consequence is real: on a network that holds a deleted, a spam or an archived
 * site, the network activation — and the network deactivation — switch to that site
 * and write the option `portfolio_studio_state`, create the category `uncategorized`
 * and flush the rewrite rules there. The deactivation deletes the option and the
 * term there too. The code promised the opposite: a false claim in delivered code.
 *
 * WHY A REAL MULTISITE IS NEEDED: the other suites stub `get_sites()` with the
 * `sites_pre_query` filter, which short-circuits the SQL query — and the SQL query
 * is exactly the part that carries the defect. This suite runs on a real network,
 * with real rows in `wp_blogs`, and measures what the plugin really writes.
 *
 * The suite prepares its own network (one live site, one archived, one spam, one
 * deleted), measures, and restores the network it found.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

// ---------------------------------------------------------------------------
// The suite needs a real network. On a single site it says so and stops.
// ---------------------------------------------------------------------------
if ( ! is_multisite() ) {
	printf( "not ok 1 - la suite exige un reseau (MULTISITE) : elle est lancee sur un mono-site\n" );
	printf( "1..1\n" );
	printf( "# passed 0\n" );
	printf( "# failed 1\n" );
	exit( 1 );
}

if ( ! class_exists( 'Portfolio_Studio_Lifecycle' ) ) {
	printf( "not ok 1 - le plugin portfolio-studio n est pas actif sur le reseau\n" );
	printf( "1..1\n" );
	printf( "# passed 0\n" );
	printf( "# failed 1\n" );
	exit( 1 );
}

/**
 * The state of the network, read from the `blogs` table.
 *
 * @return array{live:array<int,int>,archived:array<int,int>,spam:array<int,int>,deleted:array<int,int>}
 */
function ps_msx_network_state() {
	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT blog_id, archived, spam, deleted FROM {$wpdb->blogs} ORDER BY blog_id ASC",
		ARRAY_A
	);

	$state = array(
		'live'     => array(),
		'archived' => array(),
		'spam'     => array(),
		'deleted'  => array(),
	);

	foreach ( (array) $rows as $row ) {
		$blog_id = (int) $row['blog_id'];

		if ( 1 === (int) $row['archived'] ) {
			$state['archived'][] = $blog_id;
		} elseif ( 1 === (int) $row['spam'] ) {
			$state['spam'][] = $blog_id;
		} elseif ( 1 === (int) $row['deleted'] ) {
			$state['deleted'][] = $blog_id;
		} else {
			$state['live'][] = $blog_id;
		}
	}

	return $state;
}

/**
 * Set the three status flags of a site.
 *
 * @param int $blog_id Site id.
 * @param int $archived Archived flag.
 * @param int $spam     Spam flag.
 * @param int $deleted  Deleted flag.
 * @return void
 */
function ps_msx_set_flags( $blog_id, $archived, $spam, $deleted ) {
	global $wpdb;

	$wpdb->update(
		$wpdb->blogs,
		array(
			'archived' => (int) $archived,
			'spam'     => (int) $spam,
			'deleted'  => (int) $deleted,
		),
		array( 'blog_id' => (int) $blog_id )
	);

	// The list of the sites is cached by WordPress: a stale list would hide the defect.
	wp_cache_flush();
}

/**
 * Create a site of the network.
 *
 * @param string $slug  Slug of the site.
 * @param string $title Title of the site.
 * @return int The site id, or 0 when the site could not be created.
 */
function ps_msx_create_site( $slug, $title ) {
	$domain = defined( 'DOMAIN_CURRENT_SITE' ) ? DOMAIN_CURRENT_SITE : 'localhost';

	$blog_id = wpmu_create_blog( $domain, '/' . $slug . '/', $title, get_current_user_id() );

	if ( is_wp_error( $blog_id ) ) {
		return 0;
	}

	return (int) $blog_id;
}

/**
 * The taxonomy the plugin uses for the categories on the current site.
 *
 * @return string
 */
function ps_msx_category_taxonomy() {
	Portfolio_Studio_Content_Types::register();

	return Portfolio_Studio_Content_Types::taxonomy_category();
}

/**
 * The id of the term with the slug `uncategorized` on the current site.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return int The term id, or 0 when there is none.
 */
function ps_msx_uncategorized_term_id( $taxonomy ) {
	$term = get_term_by( 'slug', 'uncategorized', $taxonomy );

	if ( ! $term || is_wp_error( $term ) ) {
		return 0;
	}

	return (int) $term->term_id;
}

/**
 * Write the sentinel the plugin must not touch.
 *
 * The sentinel is the option `portfolio_studio_state` with a value the plugin never
 * writes. The default category of WordPress is removed, so that a visit of the
 * plugin WOULD create the category `uncategorized` — the category is then the
 * witness of the visit.
 *
 * @param int  $blog_id      Site id.
 * @param bool $with_owned   Also create a category `uncategorized` that carries the
 *                           plugin's own term meta (the marker that says "this term
 *                           belongs to the plugin" — the deactivation deletes exactly
 *                           those).
 * @return void
 */
function ps_msx_write_sentinel( $blog_id, $with_owned = false ) {
	switch_to_blog( $blog_id );

	$taxonomy = ps_msx_category_taxonomy();

	// The default category of WordPress is not the plugin's: the plugin must not
	// touch it. It is removed here so that a visit of the plugin WOULD create it.
	$default = ps_msx_uncategorized_term_id( $taxonomy );
	if ( $default ) {
		wp_delete_term( $default, $taxonomy );
	}

	if ( $with_owned ) {
		// A category the plugin owns, with the marker the deactivation looks for.
		$created = wp_insert_term(
			'Uncategorized',
			$taxonomy,
			array(
				'slug'        => 'uncategorized',
				'description' => 'sentinelle du test d exclusion',
			)
		);

		if ( ! is_wp_error( $created ) ) {
			update_term_meta( (int) $created['term_id'], Portfolio_Studio_Lifecycle::TERM_META, '1' );
		}
	}

	update_option(
		Portfolio_Studio_Lifecycle::OPTION,
		array( 'sentinel' => 'ne pas toucher' ),
		false
	);

	restore_current_blog();
}

/**
 * Read the sentinel of a site.
 *
 * @param int $blog_id Site id.
 * @return array{option:mixed,term:int}
 */
function ps_msx_read_sentinel( $blog_id ) {
	switch_to_blog( $blog_id );

	$taxonomy = ps_msx_category_taxonomy();

	$option = get_option( Portfolio_Studio_Lifecycle::OPTION );
	$term   = ps_msx_uncategorized_term_id( $taxonomy );

	restore_current_blog();

	return array(
		'option' => $option,
		'term'   => $term,
	);
}

/**
 * Remove the sentinel of a site.
 *
 * @param int $blog_id Site id.
 * @return void
 */
function ps_msx_remove_sentinel( $blog_id ) {
	switch_to_blog( $blog_id );

	$taxonomy = ps_msx_category_taxonomy();

	$term = ps_msx_uncategorized_term_id( $taxonomy );
	if ( $term ) {
		wp_delete_term( $term, $taxonomy );
	}

	delete_option( Portfolio_Studio_Lifecycle::OPTION );

	restore_current_blog();
}

// ---------------------------------------------------------------------------
// 1. The network the suite measures on.
// ---------------------------------------------------------------------------
$ps_msx_created = array();

$ps_msx_state = ps_msx_network_state();

foreach ( array( 'archived', 'spam', 'deleted' ) as $ps_msx_kind ) {
	if ( empty( $ps_msx_state[ $ps_msx_kind ] ) ) {
		$ps_msx_slug = 'msx-' . $ps_msx_kind;
		$ps_msx_id   = ps_msx_create_site( $ps_msx_slug, 'MSX ' . $ps_msx_kind );

		if ( $ps_msx_id ) {
			$ps_msx_created[] = $ps_msx_id;
		}
	}
}

// One extra live site, so that the live list is never the main site alone.
$ps_msx_state = ps_msx_network_state();
if ( count( $ps_msx_state['live'] ) < 2 ) {
	$ps_msx_id = ps_msx_create_site( 'msx-live', 'MSX live' );

	if ( $ps_msx_id ) {
		$ps_msx_created[] = $ps_msx_id;
	}
}

$ps_msx_state = ps_msx_network_state();

foreach ( array( 'archived' => array( 1, 0, 0 ), 'spam' => array( 0, 1, 0 ), 'deleted' => array( 0, 0, 1 ) ) as $ps_msx_kind => $ps_msx_flags ) {
	foreach ( $ps_msx_state[ $ps_msx_kind ] as $ps_msx_id ) {
		ps_msx_set_flags( $ps_msx_id, $ps_msx_flags[0], $ps_msx_flags[1], $ps_msx_flags[2] );
	}
}

$ps_msx_state = ps_msx_network_state();

PS_Test_Runner::ok(
	! empty( $ps_msx_state['live'] ),
	'le reseau de mesure compte au moins un site vivant',
	'etat : ' . wp_json_encode( $ps_msx_state )
);
PS_Test_Runner::ok(
	! empty( $ps_msx_state['archived'] ),
	'le reseau de mesure compte au moins un site archive'
);
PS_Test_Runner::ok(
	! empty( $ps_msx_state['spam'] ),
	'le reseau de mesure compte au moins un site poubelle'
);
PS_Test_Runner::ok(
	! empty( $ps_msx_state['deleted'] ),
	'le reseau de mesure compte au moins un site supprime'
);

$ps_msx_excluded = array_merge(
	$ps_msx_state['archived'],
	$ps_msx_state['spam'],
	$ps_msx_state['deleted']
);
$ps_msx_live = $ps_msx_state['live'];

// ---------------------------------------------------------------------------
// 2. The arguments really passed to `get_sites()`.
//
// The filter `sites_pre_query` returns `null`: the SQL query runs, and the query
// vars it receives are the arguments the plugin handed to `get_sites()`.
// ---------------------------------------------------------------------------
$ps_msx_query_vars = array();

$ps_msx_capture = function ( $site_data, $query ) use ( &$ps_msx_query_vars ) {
	if ( is_array( $query->query_vars ) ) {
		$ps_msx_query_vars[] = $query->query_vars;
	}

	return null;
};

add_filter( 'sites_pre_query', $ps_msx_capture, 10, 2 );

$ps_msx_method = new ReflectionMethod( 'Portfolio_Studio_Lifecycle', 'network_site_ids' );
$ps_msx_method->setAccessible( true );
$ps_msx_ids = $ps_msx_method->invoke( null );

remove_filter( 'sites_pre_query', $ps_msx_capture, 10 );

PS_Test_Runner::same(
	$ps_msx_live,
	$ps_msx_ids,
	'network_site_ids() ne renvoie que les sites vivants'
);

$ps_msx_flagged = null;
foreach ( $ps_msx_query_vars as $ps_msx_vars ) {
	if ( isset( $ps_msx_vars['archived'], $ps_msx_vars['spam'], $ps_msx_vars['deleted'] ) ) {
		$ps_msx_flagged = $ps_msx_vars;
		break;
	}
}

PS_Test_Runner::ok(
	null !== $ps_msx_flagged,
	'get_sites() recoit les trois drapeaux archived, spam et deleted',
	'vars recues : ' . wp_json_encode( $ps_msx_query_vars )
);

if ( null !== $ps_msx_flagged ) {
	PS_Test_Runner::same(
		0,
		(int) $ps_msx_flagged['archived'],
		'le drapeau archived vaut 0'
	);
	PS_Test_Runner::same(
		0,
		(int) $ps_msx_flagged['spam'],
		'le drapeau spam vaut 0'
	);
	PS_Test_Runner::same(
		0,
		(int) $ps_msx_flagged['deleted'],
		'le drapeau deleted vaut 0'
	);
}

// ---------------------------------------------------------------------------
// 3. The network activation: the excluded sites must not be visited, and
//    nothing must be written in them.
// ---------------------------------------------------------------------------
$ps_msx_switched = array();

$ps_msx_listener = function ( $new_blog_id, $prev_blog_id, $context ) use ( &$ps_msx_switched ) {
	if ( 'switch' === $context ) {
		$ps_msx_switched[] = (int) $new_blog_id;
	}
};

foreach ( $ps_msx_excluded as $ps_msx_id ) {
	ps_msx_write_sentinel( $ps_msx_id );
}

add_action( 'switch_blog', $ps_msx_listener, 10, 3 );

$ps_msx_switched = array();
Portfolio_Studio_Lifecycle::activate( true );

remove_action( 'switch_blog', $ps_msx_listener, 10 );

PS_Test_Runner::same(
	$ps_msx_live,
	$ps_msx_switched,
	' une activation reseau ne visite que les sites vivants'
);

foreach ( $ps_msx_excluded as $ps_msx_id ) {
	$ps_msx_read = ps_msx_read_sentinel( $ps_msx_id );

	PS_Test_Runner::same(
		array( 'sentinel' => 'ne pas toucher' ),
		$ps_msx_read['option'],
		' l activation reseau n ecrit pas l option dans le site exclu ' . $ps_msx_id
	);
	PS_Test_Runner::same(
		0,
		$ps_msx_read['term'],
		' l activation reseau ne cree pas la categorie dans le site exclu ' . $ps_msx_id
	);
}

// ---------------------------------------------------------------------------
// 3 bis. The deactivation must not clean the excluded sites either.
//
// A category `uncategorized` that carries the plugin's own term meta is put in the
// excluded sites: it is exactly what the deactivation deletes (the marker says
// "this term belongs to the plugin").
// ---------------------------------------------------------------------------
foreach ( $ps_msx_excluded as $ps_msx_id ) {
	ps_msx_write_sentinel( $ps_msx_id, true );
}

// ---------------------------------------------------------------------------
// 4. The network deactivation: the excluded sites must not be cleaned either.
// ---------------------------------------------------------------------------
$ps_msx_switched = array();

add_action( 'switch_blog', $ps_msx_listener, 10, 3 );

$ps_msx_switched = array();
Portfolio_Studio_Lifecycle::deactivate( true );

remove_action( 'switch_blog', $ps_msx_listener, 10 );

PS_Test_Runner::same(
	$ps_msx_live,
	$ps_msx_switched,
	' une desactivation reseau ne visite que les sites vivants'
);

foreach ( $ps_msx_excluded as $ps_msx_id ) {
	$ps_msx_read = ps_msx_read_sentinel( $ps_msx_id );

	PS_Test_Runner::same(
		array( 'sentinel' => 'ne pas toucher' ),
		$ps_msx_read['option'],
		' la desactivation reseau ne supprime pas l option du site exclu ' . $ps_msx_id
	);
	PS_Test_Runner::ok(
		0 !== $ps_msx_read['term'],
		' la desactivation reseau ne supprime pas la categorie du site exclu ' . $ps_msx_id
	);
}

// The live sites ARE cleaned: the test above is not vacuous.
foreach ( $ps_msx_live as $ps_msx_id ) {
	switch_to_blog( $ps_msx_id );
	$ps_msx_option = get_option( Portfolio_Studio_Lifecycle::OPTION );
	$ps_msx_term   = ps_msx_uncategorized_term_id( ps_msx_category_taxonomy() );
	restore_current_blog();

	PS_Test_Runner::same(
		false,
		$ps_msx_option,
		' la desactivation reseau supprime bien l option du site vivant ' . $ps_msx_id
	);
	PS_Test_Runner::same(
		0,
		$ps_msx_term,
		' la desactivation reseau supprime bien la categorie du site vivant ' . $ps_msx_id
	);
}

// ---------------------------------------------------------------------------
// 5. The network is restored.
// ---------------------------------------------------------------------------
foreach ( $ps_msx_excluded as $ps_msx_id ) {
	ps_msx_remove_sentinel( $ps_msx_id );
}

foreach ( $ps_msx_created as $ps_msx_id ) {
	switch_to_blog( $ps_msx_id );
	$ps_msx_term = ps_msx_uncategorized_term_id( ps_msx_category_taxonomy() );
	if ( $ps_msx_term ) {
		wp_delete_term( $ps_msx_term, ps_msx_category_taxonomy() );
	}
	delete_option( Portfolio_Studio_Lifecycle::OPTION );
	restore_current_blog();

	wp_delete_site( $ps_msx_id );
}

wp_cache_flush();

exit( PS_Test_Runner::summary() );
