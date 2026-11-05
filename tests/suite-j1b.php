<?php
/**
 * J1b test suite for Portfolio Studio — the three defects found by the hostile review.
 *
 * Run with:
 *   PS_PHASE=<phase> /work/wp/wpenv/wp.sh eval-file /work/wp/j1/tests/suite-j1b.php --user=1
 *
 * Phases:
 *   j1b-defaut1-activation    — the plugin was activated with a pre-existing category.
 *   j1b-defaut1-deactivated   — the plugin was deactivated.
 *   j1b-defaut1-uninstalled   — the uninstall routine was run.
 *   j1b-defaut2               — the optional cleanup of the metadata was run.
 *   j1b-defaut3-base          — the pre-existing portfolio plugin (underscored taxonomies).
 *   j1b-defaut3-compat        — a third party plugin with hyphenated taxonomies.
 *   j1b-readme                — the readme must be exact.
 *
 * The suite only uses public WordPress APIs and the registration log of the test
 * mu-plugin, so that a defective version fails with readable assertions instead of
 * a fatal error.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$ps_phase = getenv( 'PS_PHASE' );
if ( ! $ps_phase ) {
	$ps_phase = 'j1b-readme';
}

$ps_fixtures = getenv( 'PS_FIXTURES' ) ? getenv( 'PS_FIXTURES' ) : '/work/wp/preuves/cycleJ1b';

printf( "# Portfolio Studio - J1b suite (phase: %s)\n", $ps_phase );
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
 * How many times a name was registered during the request.
 *
 * @param string $kind Either `post_type` or `taxonomy`.
 * @param string $name Registered name.
 * @return int
 */
function ps_registration_count( $kind, $name ) {
	$ps_log = ps_registration_log();

	if ( ! isset( $ps_log[ $kind ][ $name ] ) || ! is_array( $ps_log[ $kind ][ $name ] ) ) {
		return 0;
	}

	return count( $ps_log[ $kind ][ $name ] );
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
 * Read a JSON fixture.
 *
 * @param string $path File path.
 * @return array
 */
function ps_read_json_fixture( $path ) {
	$ps_decoded = json_decode( ps_read_fixture( $path ), true );

	return is_array( $ps_decoded ) ? $ps_decoded : array();
}

/**
 * The category-like taxonomies attached to the content type.
 *
 * @return array<int,string>
 */
function ps_attached_category_taxonomies() {
	$ps_attached = get_object_taxonomies( 'portfolio', 'names' );

	if ( ! is_array( $ps_attached ) ) {
		return array();
	}

	return array_values(
		array_intersect(
			$ps_attached,
			array( 'portfolio-category', 'portfolio_category' )
		)
	);
}

/**
 * Make sure a taxonomy is readable in the current request.
 *
 * When the plugin is deactivated, the taxonomy it had registered is gone: WordPress
 * then refuses to read a term of that taxonomy. The test registers it again, with
 * neutral arguments, only to be able to look at the data that is in the database.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return void
 */
function ps_ensure_taxonomy( $taxonomy ) {
	if ( '' === $taxonomy || taxonomy_exists( $taxonomy ) ) {
		return;
	}

	register_taxonomy(
		$taxonomy,
		'portfolio',
		array(
			'public'       => true,
			'hierarchical' => true,
			'show_ui'      => true,
		)
	);
}

/* -------------------------------------------------------------------------
 * DEFECT 1 — the plugin must never delete a category it did not create.
 * ---------------------------------------------------------------------- */
if ( 'j1b-defaut1-activation' === $ps_phase ) {

	$ps_fixture = ps_read_json_fixture( $ps_fixtures . '/defaut1-term.json' );
	$ps_term_id = isset( $ps_fixture['term_id'] ) ? (int) $ps_fixture['term_id'] : 0;
	$ps_tax     = isset( $ps_fixture['taxonomy'] ) ? (string) $ps_fixture['taxonomy'] : '';

	PS_Test_Runner::ok( $ps_term_id > 0 && '' !== $ps_tax, 'the pre-existing category fixture is readable' );

	ps_ensure_taxonomy( $ps_tax );

	$ps_term = $ps_term_id ? get_term( $ps_term_id, $ps_tax ) : null;

	PS_Test_Runner::ok(
		$ps_term instanceof WP_Term,
		'the category that existed before the activation still exists',
		'the term is not readable: ' . ( is_wp_error( $ps_term ) ? $ps_term->get_error_message() : gettype( $ps_term ) )
	);

	PS_Test_Runner::same(
		'',
		$ps_term_id ? (string) get_term_meta( $ps_term_id, 'portfolio_studio_default_term', true ) : '',
		'Portfolio Studio did not claim the category it did not create'
	);

	PS_Test_Runner::ok(
		false !== get_option( 'portfolio_studio_state' ),
		'the plugin state option was written by the activation'
	);
}

if ( 'j1b-defaut1-deactivated' === $ps_phase ) {

	$ps_fixture = ps_read_json_fixture( $ps_fixtures . '/defaut1-term.json' );
	$ps_term_id = isset( $ps_fixture['term_id'] ) ? (int) $ps_fixture['term_id'] : 0;
	$ps_tax     = isset( $ps_fixture['taxonomy'] ) ? (string) $ps_fixture['taxonomy'] : '';

	// `get_term()` returns a WP_Error when the taxonomy is not registered any more
	// (the plugin is deactivated): a WP_Error is truthy, so it must not be used as
	// the condition of the test.
	ps_ensure_taxonomy( $ps_tax );

	$ps_term = $ps_term_id ? get_term( $ps_term_id, $ps_tax ) : null;
	PS_Test_Runner::ok(
		$ps_term instanceof WP_Term,
		'the category that existed before the activation survived the deactivation',
		'the term is not readable: ' . ( is_wp_error( $ps_term ) ? $ps_term->get_error_message() : gettype( $ps_term ) )
	);

	PS_Test_Runner::same(
		false,
		get_option( 'portfolio_studio_state' ),
		'the plugin state option was removed by the deactivation'
	);

	PS_Test_Runner::ok(
		! is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'the plugin is not active any more'
	);
}

if ( 'j1b-defaut1-uninstalled' === $ps_phase ) {

	$ps_fixture = ps_read_json_fixture( $ps_fixtures . '/defaut1-term.json' );
	$ps_term_id = isset( $ps_fixture['term_id'] ) ? (int) $ps_fixture['term_id'] : 0;
	$ps_tax     = isset( $ps_fixture['taxonomy'] ) ? (string) $ps_fixture['taxonomy'] : '';

	ps_ensure_taxonomy( $ps_tax );

	$ps_term = $ps_term_id ? get_term( $ps_term_id, $ps_tax ) : null;
	PS_Test_Runner::ok(
		$ps_term instanceof WP_Term,
		'the category that existed before the activation survived the uninstall',
		'the term is not readable: ' . ( is_wp_error( $ps_term ) ? $ps_term->get_error_message() : gettype( $ps_term ) )
	);

	PS_Test_Runner::same(
		false,
		get_option( 'portfolio_studio_state' ),
		'the plugin state option was removed by the uninstall'
	);
}

/* -------------------------------------------------------------------------
 * DEFECT 2 — the optional cleanup of the metadata must really clean it.
 * ---------------------------------------------------------------------- */
if ( 'j1b-defaut2' === $ps_phase ) {

	$ps_fixture = ps_read_json_fixture( $ps_fixtures . '/defaut2-item.json' );
	$ps_item_id = isset( $ps_fixture['id'] ) ? (int) $ps_fixture['id'] : 0;

	PS_Test_Runner::ok( $ps_item_id > 0, 'the item of the metadata cleanup is readable' );

	// `get_post_meta()` returns the DEFAULT of the registered metadata when the row is
	// gone (0 for the year, an empty array for the technologies): the existence of the
	// row is what has to be checked.
	foreach ( array( 'client', 'year', 'role', 'technologies' ) as $ps_key ) {
		PS_Test_Runner::same(
			false,
			$ps_item_id ? metadata_exists( 'post', $ps_item_id, 'portfolio_studio_' . $ps_key ) : true,
			'the optional cleanup removed the ' . $ps_key . ' of the plugin'
		);
	}

	PS_Test_Runner::same(
		true,
		$ps_item_id ? metadata_exists( 'post', $ps_item_id, 'other_meta' ) : false,
		'the optional cleanup left the metadata of the site alone'
	);

	PS_Test_Runner::same(
		'kept',
		$ps_item_id ? (string) get_post_meta( $ps_item_id, 'other_meta', true ) : '',
		'the metadata of the site still holds its value'
	);

	if ( $ps_item_id ) {
		wp_delete_post( $ps_item_id, true );
	}
}

/* -------------------------------------------------------------------------
 * DEFECT 3 — the taxonomies that already exist are reused, never duplicated.
 * ---------------------------------------------------------------------- */
if ( 'j1b-defaut3-base' === $ps_phase ) {

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'Portfolio Studio is active'
	);

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-post-type/portfolio-post-type.php' ),
		'the pre-existing portfolio plugin is active at the same time'
	);

	PS_Test_Runner::same(
		1,
		ps_registration_count( 'post_type', 'portfolio' ),
		'the portfolio content type was registered exactly once'
	);

	PS_Test_Runner::same(
		1,
		ps_registration_count( 'taxonomy', 'portfolio_category' ),
		'the portfolio_category taxonomy was registered exactly once'
	);

	PS_Test_Runner::same(
		1,
		ps_registration_count( 'taxonomy', 'portfolio_tag' ),
		'the portfolio_tag taxonomy was registered exactly once'
	);

	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio' ),
		'Portfolio Studio did not register the content type'
	);

	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio_category' ),
		'Portfolio Studio did not register the portfolio_category taxonomy'
	);

	PS_Test_Runner::ok(
		! Portfolio_Studio_Content_Types::owns( 'portfolio_tag' ),
		'Portfolio Studio did not register the portfolio_tag taxonomy'
	);

	PS_Test_Runner::same(
		1,
		count( ps_attached_category_taxonomies() ),
		'the editor shows a single list of portfolio categories'
	);

	PS_Test_Runner::ok(
		in_array( 'portfolio_category', get_object_taxonomies( 'portfolio', 'names' ), true ),
		'the category taxonomy of the pre-existing plugin is attached to the content type'
	);
}

if ( 'j1b-defaut3-compat' === $ps_phase ) {

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-studio/portfolio-studio.php' ),
		'Portfolio Studio is active'
	);

	PS_Test_Runner::ok(
		is_plugin_active( 'portfolio-third-party-taxonomies/portfolio-third-party-taxonomies.php' ),
		'the third party plugin with hyphenated taxonomies is active'
	);

	PS_Test_Runner::same(
		0,
		ps_registration_count( 'taxonomy', 'portfolio_category' ),
		'Portfolio Studio did not register an underscored category taxonomy'
	);

	PS_Test_Runner::same(
		0,
		ps_registration_count( 'taxonomy', 'portfolio_tag' ),
		'Portfolio Studio did not register an underscored tag taxonomy'
	);

	PS_Test_Runner::ok(
		taxonomy_exists( 'portfolio-category' ),
		'the hyphenated category taxonomy of the third party plugin is still there'
	);

	PS_Test_Runner::ok(
		in_array( 'portfolio-category', get_object_taxonomies( 'portfolio', 'names' ), true ),
		'the hyphenated category taxonomy was attached to the content type'
	);

	PS_Test_Runner::same(
		1,
		count( ps_attached_category_taxonomies() ),
		'the editor shows a single list of portfolio categories'
	);

	PS_Test_Runner::ok(
		in_array( 'portfolio-tag', get_object_taxonomies( 'portfolio', 'names' ), true ),
		'the hyphenated tag taxonomy was attached to the content type'
	);
}

/* -------------------------------------------------------------------------
 * The readme must be exact: every sentence must be true of the code.
 * ---------------------------------------------------------------------- */
if ( 'j1b-readme' === $ps_phase ) {

	$ps_readme_path = WP_PLUGIN_DIR . '/portfolio-studio/readme.txt';
	$ps_readme      = file_exists( $ps_readme_path ) ? (string) file_get_contents( $ps_readme_path ) : '';

	PS_Test_Runner::ok( '' !== $ps_readme, 'the readme is present in the plugin' );

	PS_Test_Runner::ok(
		false !== strpos( $ps_readme, 'no duplicate categories' ),
		'the readme promises no duplicate categories'
	);

	foreach ( array( 'client', 'year', 'role', 'technologies' ) as $ps_detail ) {
		PS_Test_Runner::ok(
			false !== stripos( $ps_readme, $ps_detail ),
			'the readme names the ' . $ps_detail . ' detail'
		);
	}

	PS_Test_Runner::ok(
		false !== stripos( $ps_readme, 'Contributors:' ),
		'the readme has a Contributors field'
	);

	// The readme is written in English: a French word would mean it was not reviewed.
	$ps_french = array( 'catégorie', 'détail', 'élément', 'technologie', 'clientèle', 'à la' );
	$ps_hits   = array();

	foreach ( $ps_french as $ps_word ) {
		// Whole words only: "technologies" is English, "technologie" is French.
		if ( preg_match( '/\b' . preg_quote( $ps_word, '/' ) . '\b/ui', $ps_readme ) ) {
			$ps_hits[] = $ps_word;
		}
	}

	PS_Test_Runner::same(
		array(),
		$ps_hits,
		'the readme is written in English'
	);
}

exit( PS_Test_Runner::summary() );
