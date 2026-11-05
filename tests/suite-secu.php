<?php
/**
 * J1c suite — the counter-proof of the `InputNotSanitized` warnings.
 *
 * Run with:
 *   PS_PHASE=secu-hostile /work/wp/wpenv/wp.sh eval-file /work/wp/j1/tests/suite-secu.php --user=1
 *
 * What this suite proves
 * ----------------------
 * Plugin Check (the official WordPress.org validator) raises 4 warnings
 * `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` on the four
 * `$_POST[...]` reads of `includes/class-portfolio-studio-fields.php`.
 *
 * This suite does not assert that the warning is a false positive: it asserts that the
 * value that comes out of the plugin is clean, whatever the input was. The chain is
 * exercised end to end, through the real save path of the real editor panel:
 *
 *   $_POST[ KEY ]                                   fields.php  (the read)
 *     -> wp_unslash()                              fields.php  (slashes removed)
 *     -> Portfolio_Studio_Meta::sanitize_*()       meta.php    (the sanitiser)
 *          -> sanitize_text_field()                meta.php    (the WordPress sanitiser)
 *     -> update_post_meta()                        fields.php  (the write)
 *     -> sanitize_meta() -> the registered sanitize_callback (meta.php) -> same sanitiser
 *
 * The same hostile string is injected three ways:
 *   1. directly into the sanitiser,
 *   2. through the editor panel (`Portfolio_Studio_Fields::save()`),
 *   3. through the metadata API used by the REST API (`update_post_meta()`).
 *
 * For each of them the suite reads the value back FROM THE DATABASE (not from the
 * accessor) and renders the editor panel, and asserts that no markup survived.
 *
 * The red proof: the same suite, run against the version of the plugin whose
 * sanitiser does not sanitise (`/work/wp/j1/versions/v2-unsanitized/`), MUST fail.
 * A test that cannot fail proves nothing.
 *
 * @package Portfolio_Studio_J1
 */

require_once __DIR__ . '/framework.php';

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$ps_phase = getenv( 'PS_PHASE' );
if ( ! $ps_phase ) {
	$ps_phase = 'secu-hostile';
}

printf( "# Portfolio Studio - J1c suite (phase: %s)\n", $ps_phase );
printf( "# WordPress %s / PHP %s\n", get_bloginfo( 'version' ), PHP_VERSION );

/**
 * Find the number of the first line of a file that contains a needle.
 *
 * @param string $file   Absolute path of the file.
 * @param string $needle Text to look for.
 * @return int The line number, or 0 when the needle is nowhere in the file.
 */
function ps_find_line( $file, $needle ) {
	if ( ! file_exists( $file ) ) {
		return 0;
	}

	$ps_lines = file( $file );
	if ( ! is_array( $ps_lines ) ) {
		return 0;
	}

	foreach ( $ps_lines as $ps_index => $ps_text ) {
		if ( false !== strpos( $ps_text, $needle ) ) {
			return $ps_index + 1;
		}
	}

	return 0;
}

/**
 * Print one line of a source file, with its number.
 *
 * This is the evidence that the chain described above is really in the code, and not
 * only in the documentation. The line is found by its content, not by a hard coded
 * number, so the proof stays true when the file moves.
 *
 * @param string $file   Absolute path of the file.
 * @param string $needle Text the line must contain.
 * @param string $label  What the line proves.
 * @return void
 */
function ps_show_line( $file, $needle, $label ) {
	$ps_lines = file_exists( $file ) ? file( $file ) : array();
	$ps_line  = ps_find_line( $file, $needle );
	$ps_text  = $ps_line > 0 ? rtrim( $ps_lines[ $ps_line - 1 ] ) : '(ligne introuvable)';

	printf( "# %s\n", $label );
	printf( "#   %s:%d: %s\n", basename( $file ), $ps_line, trim( $ps_text ) );
}

/**
 * The hostile strings used by the suite.
 *
 * @return array<string,string>
 */
function ps_hostile_strings() {
	return array(
		'script'  => '<script>alert("ps")</script>',
		'img'     => '"><img src=x onerror=alert(1)>',
		'quote'   => '\'"><svg/onload=alert(1)>',
		'entity'  => '&lt;script&gt;alert(1)&lt;/script&gt;',
	);
}

/**
 * Tell whether a value carries markup that must never reach the database or the page.
 *
 * @param string $value Value to inspect.
 * @return array<int,string> The markers found (empty when the value is clean).
 */
function ps_markup_markers( $value ) {
	$ps_markers = array(
		'<script',
		'</script',
		'<img',
		'<svg',
		'onerror=',
		'onload=',
		'javascript:',
	);

	$ps_found = array();
	$ps_lower = strtolower( (string) $value );

	foreach ( $ps_markers as $ps_marker ) {
		if ( false !== strpos( $ps_lower, $ps_marker ) ) {
			$ps_found[] = $ps_marker;
		}
	}

	return $ps_found;
}

if ( 'secu-hostile' === $ps_phase ) {

	$ps_plugin_dir = WP_PLUGIN_DIR . '/portfolio-studio';
	$ps_fields     = $ps_plugin_dir . '/includes/class-portfolio-studio-fields.php';
	$ps_meta       = $ps_plugin_dir . '/includes/class-portfolio-studio-meta.php';

	// ---------------------------------------------------------------------
	// 0. The chain, read in the code itself
	// ---------------------------------------------------------------------
	PS_Test_Runner::ok( file_exists( $ps_fields ), 'the editor panel of the plugin is installed' );
	PS_Test_Runner::ok( file_exists( $ps_meta ), 'the metadata class of the plugin is installed' );

	// The write path: the four reads of $_POST and the four sanitiser calls.
	$ps_fields_source = file_exists( $ps_fields ) ? (string) file_get_contents( $ps_fields ) : '';
	$ps_meta_source   = file_exists( $ps_meta ) ? (string) file_get_contents( $ps_meta ) : '';

	foreach ( array( 'client', 'year', 'role', 'technologies' ) as $ps_detail ) {
		PS_Test_Runner::ok(
			(bool) preg_match( '/\$_POST\[\s*Portfolio_Studio_Meta::KEY_' . strtoupper( $ps_detail ) . '\s*\]/', $ps_fields_source ),
			'the editor panel reads the ' . $ps_detail . ' from the request'
		);

		PS_Test_Runner::ok(
			(bool) preg_match( '/Portfolio_Studio_Meta::sanitize_' . $ps_detail . '\(\s*wp_unslash\(/', $ps_fields_source ),
			'the editor panel passes the ' . $ps_detail . ' through its sanitiser'
		);
	}

	foreach ( array( 'sanitize_client', 'sanitize_role', 'sanitize_technologies' ) as $ps_sanitiser ) {
		PS_Test_Runner::ok(
			(bool) preg_match( '/function\s+' . $ps_sanitiser . '\b.*?sanitize_text_field\(/s', $ps_meta_source ),
			$ps_sanitiser . '() calls sanitize_text_field()'
		);
	}

	PS_Test_Runner::ok(
		(bool) preg_match( "/'sanitize_callback'\s*=>\s*array\(\s*__CLASS__,\s*'sanitize_client'\s*\)/", $ps_meta_source ),
		'the client key registers sanitize_client() as its sanitisation callback'
	);

	// The proof is printed in the log: the reader sees the chain, not a claim.
	ps_show_line( $ps_fields, '$client = isset( $_POST[', 'the write path reads the request' );
	ps_show_line( $ps_fields, 'Portfolio_Studio_Meta::sanitize_client( wp_unslash(', 'the write path calls the sanitiser' );
	ps_show_line( $ps_meta, 'return sanitize_text_field( (string) $value );', 'the sanitiser calls the WordPress sanitiser' );

	// ---------------------------------------------------------------------
	// 1. The sanitiser, called directly
	// ---------------------------------------------------------------------
	foreach ( ps_hostile_strings() as $ps_label => $ps_hostile ) {
		$ps_clean = Portfolio_Studio_Meta::sanitize_client( $ps_hostile );

		printf( "# hostile string (%s)\n", $ps_label );
		printf( "#   in  : %s\n", $ps_hostile );
		printf( "#   out : %s\n", $ps_clean );

		PS_Test_Runner::same(
			array(),
			ps_markup_markers( $ps_clean ),
			'the sanitiser removes the markup of the hostile string (' . $ps_label . ')'
		);

		// A `<` would be the start of a tag: after the sanitisation there is none.
		// A lonely `>` is inert text and is kept: it cannot open anything.
		PS_Test_Runner::ok(
			false === strpos( $ps_clean, '<' ),
			'the sanitised value holds no opening angle bracket (' . $ps_label . ')'
		);
	}

	// ---------------------------------------------------------------------
	// 2. The editor panel: the real save path, with the real nonce
	// ---------------------------------------------------------------------
	$ps_item = wp_insert_post(
		array(
			'post_type'   => 'portfolio',
			'post_title'  => 'J1c hostile input fixture',
			'post_status' => 'publish',
		)
	);

	PS_Test_Runner::ok( $ps_item > 0, 'a portfolio item can be created' );

	if ( $ps_item > 0 ) {
		$ps_post = get_post( $ps_item );

		$_POST = array(
			Portfolio_Studio_Fields::NONCE_NAME => wp_create_nonce( Portfolio_Studio_Fields::NONCE_ACTION ),
			Portfolio_Studio_Meta::KEY_CLIENT   => '<script>alert("ps")</script>',
			Portfolio_Studio_Meta::KEY_YEAR     => '2024',
			Portfolio_Studio_Meta::KEY_ROLE     => '"><img src=x onerror=alert(1)>',
			Portfolio_Studio_Meta::KEY_TECHNOLOGIES => '<svg/onload=alert(1)>, PHP',
		);

		Portfolio_Studio_Fields::save( $ps_item, $ps_post );

		// Read the database, not the accessor: what is stored is what matters.
		global $wpdb;

		/**
		 * Read one raw metadata row from the database.
		 *
		 * @param int    $post_id Post ID.
		 * @param string $key     Meta key.
		 * @return string The raw value, or an empty string when there is no row.
		 */
		$ps_read_raw = function ( $post_id, $key ) use ( $wpdb ) {
			$ps_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$post_id,
					$key
				)
			);

			return ( null === $ps_value ) ? '' : (string) $ps_value;
		};

		// The client: the whole script block is removed, the value becomes empty,
		// so the panel removes the key instead of storing an empty row.
		$ps_client = $ps_read_raw( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT );

		printf( "# stored client   : %s\n", $ps_client );
		printf( "# stored role     : %s\n", $ps_read_raw( $ps_item, Portfolio_Studio_Meta::KEY_ROLE ) );
		printf( "# stored technol. : %s\n", $ps_read_raw( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES ) );

		PS_Test_Runner::same(
			array(),
			ps_markup_markers( $ps_client ),
			'no markup reached the database for the client'
		);

		PS_Test_Runner::same(
			'',
			$ps_client,
			'the empty client was removed instead of being stored'
		);

		// The role: the tag is removed, the inert leftovers are kept.
		$ps_role = $ps_read_raw( $ps_item, Portfolio_Studio_Meta::KEY_ROLE );

		PS_Test_Runner::same(
			array(),
			ps_markup_markers( $ps_role ),
			'no markup reached the database for the role'
		);

		PS_Test_Runner::ok(
			false === strpos( $ps_role, '<' ),
			'the stored role holds no opening angle bracket'
		);

		PS_Test_Runner::ok(
			'' !== $ps_role,
			'the role was written to the database'
		);

		// The technologies: the hostile label is dropped, the harmless one is kept.
		$ps_stored_technologies = Portfolio_Studio_Meta::get( $ps_item, Portfolio_Studio_Meta::KEY_TECHNOLOGIES );
		PS_Test_Runner::ok( is_array( $ps_stored_technologies ), 'the technologies are read back as an array' );

		foreach ( (array) $ps_stored_technologies as $ps_technology ) {
			PS_Test_Runner::same(
				array(),
				ps_markup_markers( $ps_technology ),
				'no markup reached the database for a technology'
			);
		}

		PS_Test_Runner::same(
			array( 'PHP' ),
			array_values( (array) $ps_stored_technologies ),
			'the hostile technology was dropped and the harmless one was kept'
		);

		// -----------------------------------------------------------------
		// 3. The output: the panel is rendered, and what it prints is checked
		// -----------------------------------------------------------------
		ob_start();
		Portfolio_Studio_Fields::render( $ps_post );
		$ps_html = (string) ob_get_clean();

		PS_Test_Runner::ok( '' !== $ps_html, 'the editor panel renders something' );

		PS_Test_Runner::same(
			array(),
			ps_markup_markers( $ps_html ),
			'no markup is printed by the editor panel'
		);

		PS_Test_Runner::ok(
			false === strpos( $ps_html, 'onerror' ) && false === strpos( $ps_html, '<img' ),
			'the printed panel carries no injected tag'
		);

		// -----------------------------------------------------------------
		// 4. The metadata API (the path used by the REST API)
		// -----------------------------------------------------------------
		update_post_meta( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT, '<script>alert("rest")</script>' );

		$ps_rest_stored = $ps_read_raw( $ps_item, Portfolio_Studio_Meta::KEY_CLIENT );

		PS_Test_Runner::same(
			array(),
			ps_markup_markers( $ps_rest_stored ),
			'no markup reached the database through the metadata API'
		);

		PS_Test_Runner::ok(
			false === strpos( $ps_rest_stored, '<' ),
			'the value written through the metadata API holds no opening angle bracket'
		);

		$_POST = array();

		wp_delete_post( $ps_item, true );
	}
}

exit( PS_Test_Runner::summary() );
