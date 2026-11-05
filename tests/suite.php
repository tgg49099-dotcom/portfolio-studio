<?php
/**
 * J1 test suite for the Portfolio Studio skeleton.
 *
 * Run with:
 *   PS_PHASE=<phase> /work/wp/wpenv/wp.sh eval-file /work/wp/j1/tests/suite.php --user=1
 *
 * Phases:
 *   solo         — the plugin is active and alone: registration + metadata.
 *   activated    — the plugin has just been activated: lifecycle artefacts.
 *   deactivated  — the plugin has just been deactivated: nothing of ours is left.
 *   coexistence  — the plugin and the pre-existing portfolio plugin are both active.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$ps_phase = getenv( 'PS_PHASE' );
if ( ! $ps_phase ) {
	$ps_phase = 'solo';
}

$ps_fixtures = getenv( 'PS_FIXTURES' ) ? getenv( 'PS_FIXTURES' ) : '/work/wp/preuves/cycleJ1';

printf( "# Portfolio Studio - J1 suite (phase: %s)\n", $ps_phase );
printf( "# WordPress %s / PHP %s\n", get_bloginfo( 'version' ), PHP_VERSION );

/**
 * Read the registration log collected by the test mu-plugin.
 *
 * @return array
 */
function ps_registration_log() {
	if ( isset( $GLOBALS['ps_reg_log'] ) && is_array( $GLOBALS['ps_reg_log'] ) ) {
		return $GLOBALS['ps_reg_log'];
	}

	return array();
}

/**
 * Read a fixture file.
 *
 * @param string $path File path.
 * @return string
 */
function ps_read_fixture( $path ) {
	if ( ! file_exists( $path ) ) {
		return '';
	}

	return (string) file_get_contents( $path );
}

/**
 * Write a fixture file.
 *
 * @param string $path     File path.
 * @param string $contents Contents.
 * @return void
 */
function ps_write_fixture( $path, $contents ) {
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0777, true );
	}
	file_put_contents( $path, $contents );
}

/* -------------------------------------------------------------------------
 * Phase: solo — registration and metadata.
 * ---------------------------------------------------------------------- */
if ( 'solo' === $ps_phase ) {

	$ps_post_type = get_post_type_object( 'portfolio' );

	PS_Test_Runner::ok( (bool) $ps_post_type, 'the portfolio content type is registered' );

	if ( $ps_post_type ) {
		PS_Test_Runner::ok( (bool) $ps_post_type->public, 'the content type is public' );
		PS_Test_Runner::ok( (bool) $ps_post_type->show_ui, 'the content type has an admin screen' );
		PS_Test_Runner::ok( (bool) $ps_post_type->publicly_queryable, 'the content type is publicly queryable' );
		PS_Test_Runner::ok( (bool) $ps_post_type->has_archive, 'the content type has an archive' );
		PS_Test_Runner::ok( (bool) $ps_post_type->show_in_rest, 'the content type is exposed in the REST API' );
		PS_Test_Runner::same( 'portfolio', $ps_post_type->rewrite['slug'], 'the content type rewrite slug is portfolio' );
		// The test compares the KEYS of the supported features, not their values:
		// `'supports' => array( 'title', 'editor', ... )` is the standard form of
		// WordPress, and the object stores `array( 'title' => true, ... )`.
		// WordPress core 7.1 unsets `WP_Post_Type::$supports` once it has applied it
		// (see `WP_Post_Type::add_supports()`), so the map of the features is read
		// with the public API `post_type_supports()`.
		$ps_supports = array();
		foreach ( array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'author', 'revisions' ) as $ps_feature ) {
			if ( post_type_supports( 'portfolio', $ps_feature ) ) {
				$ps_supports[ $ps_feature ] = true;
			}
		}

		$ps_missing_supports = array();
		foreach ( array( 'title', 'editor', 'excerpt', 'thumbnail' ) as $ps_feature ) {
			if ( ! array_key_exists( $ps_feature, $ps_supports ) ) {
				$ps_missing_supports[] = $ps_feature;
			}
		}
		PS_Test_Runner::ok(
			array() === $ps_missing_supports,
			'the content type supports title, editor, excerpt and thumbnail',
			'missing: ' . implode( ',', $ps_missing_supports )
		);
		PS_Test_Runner::ok(
			0 === strpos( $ps_post_type->menu_icon, 'dashicons-' ),
			'the menu icon is a core dashicon',
			'icon: ' . $ps_post_type->menu_icon
		);
	}

	// --- taxonomies ------------------------------------------------------
	$ps_category = get_taxonomy( 'portfolio_category' );
	$ps_tag      = get_taxonomy( 'portfolio_tag' );

	PS_Test_Runner::ok( (bool) $ps_category, 'the portfolio_category taxonomy is registered' );
	PS_Test_Runner::ok( (bool) $ps_tag, 'the portfolio_tag taxonomy is registered' );

	if ( $ps_category ) {
		PS_Test_Runner::ok( (bool) $ps_category->hierarchical, 'portfolio_category is hierarchical' );
		PS_Test_Runner::ok( (bool) $ps_category->public, 'portfolio_category is public' );
		PS_Test_Runner::ok( (bool) $ps_category->show_in_rest, 'portfolio_category is exposed in the REST API' );
	}

	if ( $ps_tag ) {
		PS_Test_Runner::ok( ! $ps_tag->hierarchical, 'portfolio_tag is flat' );
		PS_Test_Runner::ok( (bool) $ps_tag->show_in_rest, 'portfolio_tag is exposed in the REST API' );
	}

	$ps_attached = get_object_taxonomies( 'portfolio' );

	if ( $ps_post_type ) {
		PS_Test_Runner::ok(
			in_array( 'portfolio_category', $ps_attached, true ),
			'portfolio_category is attached to the portfolio content type'
		);
		PS_Test_Runner::ok(
			in_array( 'portfolio_tag', $ps_attached, true ),
			'portfolio_tag is attached to the portfolio content type'
		);
	}

	// The plugin must own what it registers, and nothing else.
	PS_Test_Runner::ok(
		Portfolio_Studio_Content_Types::owns( 'portfolio' ),
		'this plugin registered the portfolio content type itself (no other plugin was active)'
	);
	PS_Test_Runner::ok(
		Portfolio_Studio_Content_Types::owns( 'portfolio_category' ),
		'this plugin registered the portfolio_category taxonomy itself'
	);
	PS_Test_Runner::ok(
		Portfolio_Studio_Content_Types::owns( 'portfolio_tag' ),
		'this plugin registered the portfolio_tag taxonomy itself'
	);

	// --- metadata --------------------------------------------------------
	$ps_definitions = Portfolio_Studio_Meta::definitions();

	PS_Test_Runner::same( 4, count( $ps_definitions ), 'four metadata keys are declared' );
	PS_Test_Runner::same(
		'string',
		$ps_definitions[ Portfolio_Studio_Meta::KEY_CLIENT ]['type'],
		'the client key is typed string'
	);
	PS_Test_Runner::same(
		'integer',
		$ps_definitions[ Portfolio_Studio_Meta::KEY_YEAR ]['type'],
		'the year key is typed integer'
	);
	PS_Test_Runner::same(
		'string',
		$ps_definitions[ Portfolio_Studio_Meta::KEY_ROLE ]['type'],
		'the role key is typed string'
	);
	PS_Test_Runner::same(
		'array',
		$ps_definitions[ Portfolio_Studio_Meta::KEY_TECHNOLOGIES ]['type'],
		'the technologies key is typed array'
	);

	PS_Test_Runner::ok(
		(bool) $ps_definitions[ Portfolio_Studio_Meta::KEY_TECHNOLOGIES ]['show_in_rest']['schema']['items'],
		'the technologies key declares the schema of its items (required by WordPress for array meta)'
	);

	$ps_prefixed = true;
	foreach ( array_keys( $ps_definitions ) as $ps_key ) {
		if ( 0 !== strpos( $ps_key, Portfolio_Studio_Meta::PREFIX ) ) {
			$ps_prefixed = false;
		}
	}
	PS_Test_Runner::ok( $ps_prefixed, 'every metadata key is prefixed with ' . Portfolio_Studio_Meta::PREFIX );

	$ps_registered = get_registered_meta_keys( 'post', 'portfolio' );
	$ps_all_present = true;
	foreach ( array_keys( $ps_definitions ) as $ps_key ) {
		if ( ! isset( $ps_registered[ $ps_key ] ) ) {
			$ps_all_present = false;
		}
	}
	PS_Test_Runner::ok( $ps_all_present, 'every metadata key is registered on the portfolio content type' );

	// --- round trip ------------------------------------------------------
	$ps_item = wp_insert_post(
		array(
			'post_type'   => 'portfolio',
			'post_title'  => 'J1 fixture item',
			'post_status' => 'publish',
		)
	);
	PS_Test_Runner::ok( $ps_item > 0, 'a portfolio item can be created' );

	if ( $ps_item > 0 ) {
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT, '  Acme & Co  ' );
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_YEAR, '2024' );
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_ROLE, '<b>Lead developer</b>' );
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES, 'PHP, WordPress , PHP' );

		PS_Test_Runner::same(
			'Acme & Co',
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT ),
			'the client is trimmed and stripped on save'
		);
		PS_Test_Runner::same(
			2024,
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_YEAR ),
			'the year is stored as an integer'
		);
		PS_Test_Runner::same(
			'Lead developer',
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_ROLE ),
			'the role is stripped of markup on save'
		);
		PS_Test_Runner::same(
			array( 'PHP', 'WordPress' ),
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES ),
			'the technologies are stored as a clean, unique list'
		);

		// Sanitisers, called directly.
		PS_Test_Runner::same( 0, Portfolio_Studio_Meta::sanitize_year( 'not a year' ), 'a non numeric year becomes 0' );
		PS_Test_Runner::same( 0, Portfolio_Studio_Meta::sanitize_year( '1969' ), 'a year before 1970 becomes 0' );
		PS_Test_Runner::same( 0, Portfolio_Studio_Meta::sanitize_year( '2101' ), 'a year after 2100 becomes 0' );
		PS_Test_Runner::same( 2024, Portfolio_Studio_Meta::sanitize_year( 2024 ), 'a valid year is kept' );
		PS_Test_Runner::same( array(), Portfolio_Studio_Meta::sanitize_technologies( array( array( 'x' ) ) ), 'nested arrays are dropped from the technologies' );
		PS_Test_Runner::same( '', Portfolio_Studio_Meta::sanitize_client( array( 'x' ) ), 'a non scalar client becomes an empty string' );

		// The REST API must see the same typed values.
		$ps_rest = get_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES, true );
		PS_Test_Runner::ok( is_array( $ps_rest ), 'the technologies are read back as an array' );

		wp_delete_post( $ps_item, true );
	}
}

/* -------------------------------------------------------------------------
 * Phase: activated — the plugin has just been activated.
 * ---------------------------------------------------------------------- */
if ( 'activated' === $ps_phase ) {

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'the plugin is active'
	);

	$ps_option = get_option( 'portfolio_studio_state' );
	PS_Test_Runner::ok( is_array( $ps_option ), 'the plugin state option exists' );
	PS_Test_Runner::same(
		'0.1.0',
		is_array( $ps_option ) ? $ps_option['version'] : '',
		'the state option records the plugin version'
	);

	$ps_default = get_term_by( 'slug', 'uncategorized', 'portfolio_category' );
	PS_Test_Runner::ok( (bool) $ps_default, 'the default portfolio category exists after activation' );

	if ( $ps_default ) {
		PS_Test_Runner::same(
			'1',
			get_term_meta( $ps_default->term_id, 'portfolio_studio_default_term', true ),
			'the default category is marked as owned by this plugin'
		);
	}

	// No table of our own.
	$ps_before = ps_read_fixture( $ps_fixtures . '/tables-before-activation.txt' );
	$ps_after  = ps_read_fixture( $ps_fixtures . '/tables-after-activation.txt' );
	PS_Test_Runner::same(
		trim( $ps_before ),
		trim( $ps_after ),
		'activation created no database table'
	);

	// A fixture item with metadata, kept for the deactivation phase.
	$ps_item = wp_insert_post(
		array(
			'post_type'   => 'portfolio',
			'post_title'  => 'J1 lifecycle fixture',
			'post_status' => 'publish',
		)
	);
	update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT, 'Fixture Client' );
	update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_YEAR, 2020 );
	update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_ROLE, 'Tester' );
	update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES, array( 'PHP' ) );

	ps_write_fixture(
		$ps_fixtures . '/fixture-item.json',
		wp_json_encode(
			array(
				'id'     => (int) $ps_item,
				'title'  => 'J1 lifecycle fixture',
				'client' => 'Fixture Client',
				'year'   => 2020,
				'role'   => 'Tester',
				'tech'   => array( 'PHP' ),
			)
		)
	);

	PS_Test_Runner::ok( $ps_item > 0, 'the lifecycle fixture item was created' );
	PS_Test_Runner::same(
		'Fixture Client',
		get_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT, true ),
		'the fixture item carries its metadata'
	);
}

/* -------------------------------------------------------------------------
 * Phase: deactivated — the plugin has just been deactivated.
 * ---------------------------------------------------------------------- */
if ( 'deactivated' === $ps_phase ) {

	PS_Test_Runner::ok(
		! is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'the plugin is not active any more'
	);

	PS_Test_Runner::ok(
		false === get_option( 'portfolio_studio_state' ),
		'the plugin state option was removed'
	);

	PS_Test_Runner::ok(
		false === get_term_by( 'slug', 'uncategorized', 'portfolio_category' ),
		'the default portfolio category created by the plugin was removed'
	);

	$ps_before = trim( ps_read_fixture( $ps_fixtures . '/tables-before-activation.txt' ) );
	$ps_after  = trim( ps_read_fixture( $ps_fixtures . '/tables-after-deactivation.txt' ) );
	PS_Test_Runner::same( $ps_before, $ps_after, 'deactivation left no orphan table' );

	// No leftover option of ours anywhere.
	global $wpdb;
	$ps_leftovers = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'portfolio_studio_' ) . '%'
		)
	);
	PS_Test_Runner::same( array(), $ps_leftovers, 'no option of the plugin is left in the database' );

	// User content survives.
	$ps_fixture = json_decode( ps_read_fixture( $ps_fixtures . '/fixture-item.json' ), true );
	if ( is_array( $ps_fixture ) && ! empty( $ps_fixture['id'] ) ) {
		$ps_post = get_post( (int) $ps_fixture['id'] );
		PS_Test_Runner::ok( (bool) $ps_post, 'the item created while the plugin was active still exists' );

		PS_Test_Runner::same(
			'Fixture Client',
			get_post_meta( (int) $ps_fixture['id'], 'portfolio_studio_client', true ),
			'deactivation does not delete the metadata written by the user'
		);
		// A SQL database always gives back a string: the typed value is obtained with
		// the accessor of the plugin, which returns an integer.
		if ( ! class_exists( 'Portfolio_Studio_Meta' ) ) {
			require_once WP_PLUGIN_DIR . '/portfolio-studio/includes/class-portfolio-studio-meta.php';
		}
		PS_Test_Runner::same(
			2020,
			Portfolio_Studio_Meta::get( (int) $ps_fixture['id'], Portfolio_Studio_Meta::KEY_YEAR ),
			'deactivation does not delete the year written by the user'
		);
		PS_Test_Runner::same(
			array( 'PHP' ),
			get_post_meta( (int) $ps_fixture['id'], 'portfolio_studio_technologies', true ),
			'deactivation does not delete the technologies written by the user'
		);

		wp_delete_post( (int) $ps_fixture['id'], true );
	} else {
		PS_Test_Runner::ok( false, 'the fixture file of the lifecycle phase was readable' );
	}
}

/* -------------------------------------------------------------------------
 * Phase: coexistence — both plugins are active.
 * ---------------------------------------------------------------------- */
if ( 'coexistence' === $ps_phase ) {

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'Portfolio Studio is active'
	);
	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-post-type/portfolio-post-type.php' ),
		'the pre-existing portfolio plugin is active at the same time'
	);

	$ps_log = ps_registration_log();

	$ps_post_type_registrations = isset( $ps_log['post_type']['portfolio'] ) ? count( $ps_log['post_type']['portfolio'] ) : 0;
	PS_Test_Runner::same(
		1,
		$ps_post_type_registrations,
		'the portfolio content type was registered exactly once (Portfolio Studio did not overwrite it)'
	);

	$ps_category_registrations = isset( $ps_log['taxonomy']['portfolio_category'] ) ? count( $ps_log['taxonomy']['portfolio_category'] ) : 0;
	PS_Test_Runner::same(
		1,
		$ps_category_registrations,
		'the portfolio_category taxonomy was registered exactly once'
	);

	$ps_tag_registrations = isset( $ps_log['taxonomy']['portfolio_tag'] ) ? count( $ps_log['taxonomy']['portfolio_tag'] ) : 0;
	PS_Test_Runner::same(
		1,
		$ps_tag_registrations,
		'the portfolio_tag taxonomy was registered exactly once'
	);

	// The surviving registration must be the one of the pre-existing plugin.
	$ps_post_type = get_post_type_object( 'portfolio' );
	PS_Test_Runner::ok( (bool) $ps_post_type, 'the portfolio content type is registered' );

	if ( $ps_post_type && $ps_post_type_registrations > 0 ) {
		PS_Test_Runner::ok(
			$ps_post_type === $ps_log['post_type']['portfolio'][0],
			'the surviving content type registration is the one of the pre-existing plugin, untouched'
		);
		PS_Test_Runner::same(
			5,
			(int) $ps_post_type->menu_position,
			'the pre-existing menu position of the content type was preserved'
		);
		// `show_in_graphql` and `graphql_single_name` are not core properties: the
		// pre-existing plugin passes them as extra args of register_post_type(), and
		// WordPress stores every unknown arg as a dynamic property of the object.
		// The test reads them defensively so that it never raises a PHP Warning of
		// its own when the registration has been overwritten.
		PS_Test_Runner::ok(
			property_exists( $ps_post_type, 'show_in_graphql' ) && (bool) $ps_post_type->show_in_graphql,
			'the pre-existing GraphQL flag of the content type was preserved',
			'property exists: ' . ( property_exists( $ps_post_type, 'show_in_graphql' ) ? 'yes' : 'no' )
		);
		PS_Test_Runner::same(
			'portfolio_item',
			property_exists( $ps_post_type, 'graphql_single_name' ) ? (string) $ps_post_type->graphql_single_name : '',
			'the pre-existing GraphQL name of the content type was preserved'
		);
	}

	$ps_category = get_taxonomy( 'portfolio_category' );
	if ( $ps_category && $ps_category_registrations > 0 ) {
		PS_Test_Runner::ok(
			$ps_category === $ps_log['taxonomy']['portfolio_category'][0],
			'the surviving portfolio_category registration is the one of the pre-existing plugin, untouched'
		);
	}

	// Our plugin must have detected that it was not the owner.
	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio' ),
		'Portfolio Studio did not register the content type again'
	);
	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio_category' ),
		'Portfolio Studio did not register the portfolio_category taxonomy again'
	);
	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio_tag' ),
		'Portfolio Studio did not register the portfolio_tag taxonomy again'
	);
	PS_Test_Runner::ok(
		Portfolio_Studio_Content_Types::foreign( 'portfolio' ) && Portfolio_Studio_Content_Types::foreign( 'portfolio_category' ),
		'Portfolio Studio detected the registrations of the other plugin'
	);

	// Both taxonomies stay attached to the content type.
	$ps_attached = get_object_taxonomies( 'portfolio' );
	if ( $ps_post_type ) {
		PS_Test_Runner::ok(
			in_array( 'portfolio_category', $ps_attached, true ),
			'portfolio_category is still attached to the portfolio content type'
		);
		PS_Test_Runner::ok(
			in_array( 'portfolio_tag', $ps_attached, true ),
			'portfolio_tag is still attached to the portfolio content type'
		);
	}

	// Our metadata still works on the items of the other plugin.
	$ps_registered = get_registered_meta_keys( 'post', 'portfolio' );
	PS_Test_Runner::ok(
		isset( $ps_registered[ Portfolio_Studio_Meta::KEY_CLIENT ] ),
		'the client metadata is registered even when the other plugin owns the content type'
	);
	PS_Test_Runner::ok(
		isset( $ps_registered[ Portfolio_Studio_Meta::KEY_TECHNOLOGIES ] ),
		'the technologies metadata is registered even when the other plugin owns the content type'
	);

	// An item published by the other plugin must remain readable.
	$ps_item = wp_insert_post(
		array(
			'post_type'   => 'portfolio',
			'post_title'  => 'J1 coexistence item',
			'post_status' => 'publish',
		)
	);
	PS_Test_Runner::ok( $ps_item > 0, 'a portfolio item can still be created with both plugins active' );

	if ( $ps_item > 0 ) {
		$ps_term = wp_insert_term( 'J1 coexistence category', 'portfolio_category' );
		$ps_term_id = is_wp_error( $ps_term ) ? 0 : (int) $ps_term['term_id'];
		if ( $ps_term_id ) {
			wp_set_object_terms( $ps_item, array( $ps_term_id ), 'portfolio_category' );
		}

		$ps_read = get_post( $ps_item );
		PS_Test_Runner::ok( (bool) $ps_read, 'the item is readable' );
		PS_Test_Runner::same( 'J1 coexistence item', $ps_read ? $ps_read->post_title : '', 'the item keeps its title' );
		PS_Test_Runner::ok( '' !== get_permalink( $ps_item ), 'the item has a permalink' );

		$ps_terms = wp_get_object_terms( $ps_item, 'portfolio_category' );
		PS_Test_Runner::ok(
			! is_wp_error( $ps_terms ) && 1 === count( $ps_terms ),
			'the taxonomy terms of the item are intact'
		);

		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT, 'Coexistence Client' );
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES, array( 'PHP', 'JS' ) );
		PS_Test_Runner::same(
			'Coexistence Client',
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT ),
			'the editorial metadata can be written with both plugins active'
		);
		PS_Test_Runner::same(
			array( 'PHP', 'JS' ),
			Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES ),
			'the technologies can be written with both plugins active'
		);

		if ( $ps_term_id ) {
			wp_delete_term( $ps_term_id, 'portfolio_category' );
		}
		wp_delete_post( $ps_item, true );
	}

	// The default category of Portfolio Studio is present and marked.
	$ps_default = get_term_by( 'slug', 'uncategorized', 'portfolio_category' );
	PS_Test_Runner::ok( (bool) $ps_default, 'the default category of Portfolio Studio exists with both plugins active' );
}

exit( PS_Test_Runner::summary() );
