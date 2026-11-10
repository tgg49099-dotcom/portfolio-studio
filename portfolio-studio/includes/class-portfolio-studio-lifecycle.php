<?php
/**
 * Activation and deactivation.
 *
 * @package Portfolio_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin lifecycle: what is written to the database, and what is removed from it.
 *
 * DATABASE CONTRACT
 * -----------------
 * This plugin creates **no custom table**. Everything it stores lives in the standard
 * WordPress tables, and everything it owns is removed when it is deactivated:
 *
 *  - one option, `portfolio_studio_state` (version + the id of the term we created);
 *  - at most one term, the default category, marked with the term meta
 *    `portfolio_studio_default_term`.
 *
 * Deactivation never deletes user content: the metadata an editor typed on their
 * portfolio items stays where it is.
 *
 * @package Portfolio_Studio
 */
class Portfolio_Studio_Lifecycle {

	/**
	 * Option holding the plugin state.
	 */
	const OPTION = 'portfolio_studio_state';

	/**
	 * Term meta marking a term created by this plugin.
	 */
	const TERM_META = 'portfolio_studio_default_term';

	/**
	 * Name of the default category.
	 */
	const DEFAULT_TERM_NAME = 'Uncategorized';

	/**
	 * Slug of the default category.
	 */
	const DEFAULT_TERM_SLUG = 'uncategorized';

	/**
	 * Run on activation.
	 *
	 * On a multisite network, a network activation must configure EVERY site of the
	 * network, not only the site the administrator happened to be on. The list of the
	 * sites is read with `get_sites()`, the same way WordPress core activates a
	 * network plugin. On a single site there is no network, and the current site is
	 * configured — which is what `$network_wide` being false already says.
	 *
	 * @param bool $network_wide Whether the plugin is network activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			$site_ids = self::network_site_ids();

			if ( ! empty( $site_ids ) ) {
				self::activate_sites( $site_ids );
				return;
			}
		}

		self::single_activate();
	}

	/**
	 * Configure the plugin on the current site.
	 *
	 * @return void
	 */
	public static function single_activate() {
		// Make sure the content types exist before the rewrite rules are rebuilt.
		Portfolio_Studio_Content_Types::register();
		Portfolio_Studio_Meta::register();

		$term_id = self::ensure_default_term();

		update_option(
			self::OPTION,
			array(
				'version'           => PORTFOLIO_STUDIO_VERSION,
				'activated'         => time(),
				'default_term'      => $term_id,
				'category_taxonomy' => Portfolio_Studio_Content_Types::taxonomy_category(),
				'tag_taxonomy'      => Portfolio_Studio_Content_Types::taxonomy_tag(),
				'owned'             => array_keys( Portfolio_Studio_Content_Types::owned() ),
			),
			false
		);

		flush_rewrite_rules();
	}

	/**
	 * Configure the plugin on every site of a list.
	 *
	 * @param array<int,int> $site_ids The ids of the sites.
	 * @return void
	 */
	public static function activate_sites( array $site_ids ) {
		foreach ( $site_ids as $site_id ) {
			if ( function_exists( 'switch_to_blog' ) ) {
				switch_to_blog( (int) $site_id );
			}

			self::single_activate();

			if ( function_exists( 'restore_current_blog' ) ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Configure the plugin on a site that has just been created.
	 *
	 * On a network, a site created after the network activation must be configured
	 * too. The site is configured ONLY when the plugin is activated for the whole
	 * network: without that condition, the plugin would write its settings and
	 * create its default category into sites of the network where it is not
	 * activated — sites that never asked for it.
	 *
	 * @param int|object $site The id of the new site, or the site itself.
	 * @return void
	 */
	public static function activate_new_site( $site = 0 ) {
		if ( ! self::is_network_active() ) {
			return;
		}

		$blog_id = 0;

		if ( is_numeric( $site ) ) {
			$blog_id = (int) $site;
		} elseif ( is_object( $site ) && isset( $site->blog_id ) ) {
			$blog_id = (int) $site->blog_id;
		}

		if ( $blog_id <= 0 ) {
			return;
		}

		if ( function_exists( 'switch_to_blog' ) ) {
			switch_to_blog( $blog_id );
		}

		self::single_activate();

		if ( function_exists( 'restore_current_blog' ) ) {
			restore_current_blog();
		}
	}

	/**
	 * Whether the plugin is activated for the whole network.
	 *
	 * The list of the network activated plugins is the network option
	 * `active_sitewide_plugins`. It is read directly, and not through
	 * `is_plugin_active_for_network()`, because that helper lives in the
	 * administration (`wp-admin/includes/plugin.php`), is not loaded when a site
	 * is created from the REST API or from the command line, and answers `false`
	 * on a site that is not a network.
	 *
	 * @return bool True when the plugin is activated for the whole network.
	 */
	public static function is_network_active() {
		$basename = defined( 'PORTFOLIO_STUDIO_BASENAME' )
			? PORTFOLIO_STUDIO_BASENAME
			: plugin_basename( dirname( __DIR__ ) . '/portfolio-studio.php' );

		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		if ( ! is_array( $network_plugins ) ) {
			return false;
		}

		return isset( $network_plugins[ $basename ] );
	}

	/**
	 * The ids of the sites of the network.
	 *
	 * The archived, the spam and the deleted sites are left out: the three flags
	 * below are handed to `get_sites()`, so `WP_Site_Query` adds `archived = 0`,
	 * `spam = 0` and `deleted = 0` to its SQL query and returns only the sites
	 * that are really in use. Without them the query would carry no filter at
	 * all — the defaults of `WP_Site_Query` are `null` — and the plugin would
	 * write its option, its category and its rewrite rules into the archived,
	 * spam and deleted sites of the network.
	 *
	 * @return array<int,int> The site ids; an empty array on a single site.
	 */
	protected static function network_site_ids() {
		if ( ! function_exists( 'get_sites' ) ) {
			return array();
		}

		$site_ids = get_sites(
			array(
				'fields'   => 'ids',
				'number'   => 0,
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
			)
		);

		if ( ! is_array( $site_ids ) ) {
			return array();
		}

		return array_map( 'intval', $site_ids );
	}

	/**
	 * Run on deactivation.
	 *
	 * @param bool $network_wide Whether the plugin is network deactivated.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide ) {
			$site_ids = self::network_site_ids();

			if ( ! empty( $site_ids ) ) {
				foreach ( $site_ids as $site_id ) {
					if ( function_exists( 'switch_to_blog' ) ) {
						switch_to_blog( (int) $site_id );
					}

					self::single_deactivate();

					if ( function_exists( 'restore_current_blog' ) ) {
						restore_current_blog();
					}
				}

				return;
			}
		}

		self::single_deactivate();
	}

	/**
	 * Clean up the current site.
	 *
	 * @return void
	 */
	public static function single_deactivate() {
		self::remove_default_terms();
		delete_option( self::OPTION );

		// The rewrite rules of the content type must not survive the deactivation.
		flush_rewrite_rules();
	}

	/**
	 * The taxonomy in which the default category was created.
	 *
	 * The taxonomy that was retained when the plugin was activated is the one that must
	 * be read at deactivation: with another portfolio plugin installed, the category
	 * lives in the taxonomy of that plugin, not in ours.
	 *
	 * @return string The taxonomy slug, or an empty string when it is unknown.
	 */
	public static function default_term_taxonomy() {
		$state = get_option( self::OPTION );

		if ( is_array( $state ) && ! empty( $state['category_taxonomy'] ) ) {
			return (string) $state['category_taxonomy'];
		}

		return Portfolio_Studio_Content_Types::taxonomy_category();
	}

	/**
	 * Create the default category if it is missing.
	 *
	 * The term is marked with our own term meta ONLY when this plugin created it. A term
	 * that was already there — the `uncategorized` category of WordPress, a category left
	 * by another plugin, a category an editor created by hand — is never marked, and is
	 * therefore never deleted by this plugin.
	 *
	 * @return int Term ID, or 0 when the plugin does not own a default category.
	 */
	public static function ensure_default_term() {
		$taxonomy = Portfolio_Studio_Content_Types::taxonomy_category();

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$existing = get_term_by( 'slug', self::DEFAULT_TERM_SLUG, $taxonomy );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$owned = get_term_meta( (int) $existing->term_id, self::TERM_META, true );

			// Already there: it belongs to somebody else unless we created it ourselves.
			return ( '1' === (string) $owned ) ? (int) $existing->term_id : 0;
		}

		$created = wp_insert_term(
			self::DEFAULT_TERM_NAME,
			$taxonomy,
			array(
				'slug'        => self::DEFAULT_TERM_SLUG,
				'description' => '',
			)
		);

		if ( is_wp_error( $created ) ) {
			return 0;
		}

		$term_id = (int) $created['term_id'];
		update_term_meta( $term_id, self::TERM_META, '1' );

		return $term_id;
	}

	/**
	 * The default category of this plugin in one taxonomy — but only if we created it.
	 *
	 * The plugin creates exactly one term, always with the slug `uncategorized`, and it
	 * marks it with our own term meta. The term is therefore found by its slug (an
	 * indexed lookup) and its ownership is then confirmed by the marker: a category that
	 * was already there — the `uncategorized` category of WordPress, a category of
	 * another plugin, a category an editor created by hand — carries no marker and is
	 * never returned here.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return WP_Term|null The term we own, or null when we own none in this taxonomy.
	 */
	public static function owned_default_term( $taxonomy ) {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = get_term_by( 'slug', self::DEFAULT_TERM_SLUG, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$owned = get_term_meta( (int) $term->term_id, self::TERM_META, true );
		if ( '1' !== (string) $owned ) {
			return null;
		}

		return $term;
	}

	/**
	 * Remove every default category created by this plugin.
	 *
	 * Only the terms that carry our own term meta are considered: a category that is
	 * already used by an item is kept (deleting it would silently detach content from
	 * the people who wrote it), and a category that this plugin did not create is never
	 * touched.
	 *
	 * @return array<int,int> The ids of the terms that were removed.
	 */
	public static function remove_default_terms() {
		$removed = array();

		foreach ( self::default_term_taxonomies() as $taxonomy ) {
			$term = self::owned_default_term( $taxonomy );
			if ( null === $term ) {
				continue;
			}

			if ( (int) $term->count > 0 ) {
				continue;
			}

			$deleted = wp_delete_term( (int) $term->term_id, $taxonomy );
			if ( ! is_wp_error( $deleted ) ) {
				$removed[] = (int) $term->term_id;
			}
		}

		return $removed;
	}

	/**
	 * The taxonomies that may hold the default category of this plugin.
	 *
	 * @return array<int,string>
	 */
	public static function default_term_taxonomies() {
		$taxonomies = array();

		$stored = self::default_term_taxonomy();
		if ( '' !== $stored ) {
			$taxonomies[] = $stored;
		}

		foreach ( Portfolio_Studio_Content_Types::known_taxonomies() as $known ) {
			$taxonomies[] = $known;
		}

		return array_values( array_unique( $taxonomies ) );
	}

	/**
	 * Make sure a taxonomy exists in the current request.
	 *
	 * During an uninstall the plugin is gone, so the taxonomy it had registered is not
	 * registered any more and WordPress refuses to delete a term of an unknown taxonomy.
	 * A minimal registration is enough to remove the term properly.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool Whether the taxonomy is available.
	 */
	public static function ensure_taxonomy_registered( $taxonomy ) {
		if ( '' === $taxonomy ) {
			return false;
		}

		if ( taxonomy_exists( $taxonomy ) ) {
			return true;
		}

		register_taxonomy(
			$taxonomy,
			Portfolio_Studio_Content_Types::POST_TYPE,
			array(
				'public'       => false,
				'hierarchical' => true,
			)
		);

		return taxonomy_exists( $taxonomy );
	}
}
