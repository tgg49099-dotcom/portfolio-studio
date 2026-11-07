<?php
/**
 * Content type registration.
 *
 * @package Portfolio_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `portfolio` content type and its taxonomies.
 *
 * COEXISTENCE RULE (the whole point of this class)
 * ------------------------------------------------
 * The WordPress registry is global: `portfolio` is a widely used content type slug and
 * several plugins already register it. `register_post_type()` and `register_taxonomy()`
 * do NOT warn when a name is already taken: they silently replace the previous
 * registration. A plugin that does that is not "coexisting", it is overwriting someone
 * else's configuration — and the overwrite is invisible in the logs.
 *
 * So this class never registers a name that already exists. It registers only what is
 * missing, and it *builds on* whatever is already there:
 *
 *  - the content type is registered only if `portfolio` is free;
 *  - each taxonomy is registered only if its name is free;
 *  - taxonomies are always attached to the `portfolio` content type (attachment is
 *    idempotent in WordPress core);
 *  - taxonomies registered by a theme under a hyphenated alias (`portfolio-category`,
 *    `portfolio-tag`) are attached too, so no existing content is orphaned.
 *
 * @package Portfolio_Studio
 */
class Portfolio_Studio_Content_Types {

	/**
	 * Content type slug.
	 */
	const POST_TYPE = 'portfolio';

	/**
	 * Hierarchical taxonomy slug registered by this plugin when nothing else exists.
	 */
	const TAXONOMY_CATEGORY = 'portfolio_category';

	/**
	 * Flat taxonomy slug registered by this plugin when nothing else exists.
	 */
	const TAXONOMY_TAG = 'portfolio_tag';

	/**
	 * Hyphenated taxonomy slugs used by some themes and by other portfolio plugins.
	 *
	 * They are NEVER registered by this plugin: if such a taxonomy already exists on the
	 * site, it is reused and attached to the content type, so its content keeps working
	 * and the editor never shows a second list of categories.
	 */
	const COMPAT_TAXONOMY_CATEGORY = 'portfolio-category';

	/**
	 * Hyphenated tag taxonomy slug.
	 */
	const COMPAT_TAXONOMY_TAG = 'portfolio-tag';

	/**
	 * Hyphenated aliases used by some themes.
	 *
	 * @deprecated Kept for backward compatibility, use the two constants above.
	 */
	const COMPAT_TAXONOMIES = array( self::COMPAT_TAXONOMY_CATEGORY, self::COMPAT_TAXONOMY_TAG );

	/**
	 * Names this plugin registered itself, per request.
	 *
	 * @var array<string,bool>
	 */
	protected static $owned = array();

	/**
	 * Names that were already registered by somebody else, per request.
	 *
	 * @var array<string,bool>
	 */
	protected static $foreign = array();

	/**
	 * Taxonomy actually used for each role, per request.
	 *
	 * @var array<string,string>
	 */
	protected static $resolved = array(
		'category' => '',
		'tag'      => '',
	);

	/**
	 * Register everything that is still missing.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_post_type();
		self::register_taxonomies();
		self::attach_taxonomies();
	}

	/**
	 * Register the `portfolio` content type, unless somebody already did.
	 *
	 * @return void
	 */
	protected static function register_post_type() {
		if ( post_type_exists( self::POST_TYPE ) ) {
			self::$foreign[ self::POST_TYPE ] = true;
			return;
		}

		register_post_type( self::POST_TYPE, self::post_type_args() );
		self::$owned[ self::POST_TYPE ] = true;
	}

	/**
	 * Taxonomy slugs that can play a given role, in the order they are preferred.
	 *
	 * The hyphenated names come first: they are the ones used by the portfolio plugins
	 * that are already installed on the sites this plugin has to live with. The
	 * underscored names are ours, and are registered only when no candidate exists.
	 *
	 * @param string $role Either `category` or `tag`.
	 * @return array<int,string>
	 */
	public static function taxonomy_candidates( $role ) {
		if ( 'tag' === $role ) {
			return array( self::COMPAT_TAXONOMY_TAG, self::TAXONOMY_TAG );
		}

		return array( self::COMPAT_TAXONOMY_CATEGORY, self::TAXONOMY_CATEGORY );
	}

	/**
	 * The taxonomy that plays a given role, reusing an existing one when possible.
	 *
	 * @param string $role Either `category` or `tag`.
	 * @return string The taxonomy slug, or an empty string when none exists yet.
	 */
	public static function resolve_taxonomy( $role ) {
		foreach ( self::taxonomy_candidates( $role ) as $candidate ) {
			if ( taxonomy_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * The taxonomy used for the categories.
	 *
	 * @return string
	 */
	public static function taxonomy_category() {
		if ( '' !== self::$resolved['category'] ) {
			return self::$resolved['category'];
		}

		return self::resolve_taxonomy( 'category' );
	}

	/**
	 * The taxonomy used for the tags.
	 *
	 * @return string
	 */
	public static function taxonomy_tag() {
		if ( '' !== self::$resolved['tag'] ) {
			return self::$resolved['tag'];
		}

		return self::resolve_taxonomy( 'tag' );
	}

	/**
	 * Every taxonomy this plugin knows about.
	 *
	 * @return array<int,string>
	 */
	public static function known_taxonomies() {
		return array(
			self::COMPAT_TAXONOMY_CATEGORY,
			self::COMPAT_TAXONOMY_TAG,
			self::TAXONOMY_CATEGORY,
			self::TAXONOMY_TAG,
		);
	}

	/**
	 * Register the taxonomies, unless somebody already did.
	 *
	 * One taxonomy per role: the first candidate that already exists is reused, and our
	 * own slug is registered only when no candidate exists at all. That is what keeps a
	 * single list of categories in the editor.
	 *
	 * @return void
	 */
	protected static function register_taxonomies() {
		$map = array(
			'category' => 'category',
			'tag'      => 'tag',
		);

		foreach ( $map as $role => $kind ) {
			$existing = self::resolve_taxonomy( $role );

			if ( '' !== $existing ) {
				self::$foreign[ $existing ] = true;
				self::$resolved[ $role ]   = $existing;
				continue;
			}

			$own = ( 'tag' === $role ) ? self::TAXONOMY_TAG : self::TAXONOMY_CATEGORY;

			register_taxonomy( $own, self::POST_TYPE, self::taxonomy_args( $own, $kind ) );
			self::$owned[ $own ] = true;
			self::$resolved[ $role ] = $own;
		}
	}

	/**
	 * Attach every taxonomy we know about to the `portfolio` content type.
	 *
	 * `register_taxonomy_for_object_type()` is idempotent in WordPress core: it checks
	 * `in_array()` on the taxonomy side before appending, so calling it twice is harmless.
	 *
	 * @return void
	 */
	protected static function attach_taxonomies() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		$taxonomies = array();

		foreach ( array( 'category', 'tag' ) as $role ) {
			$resolved = self::$resolved[ $role ];

			if ( '' === $resolved ) {
				$resolved = self::resolve_taxonomy( $role );
			}

			if ( '' !== $resolved ) {
				$taxonomies[] = $resolved;
			}
		}

		// A taxonomy that exists under another name is attached too, so that the content
		// an editor already published in it stays reachable.
		foreach ( self::known_taxonomies() as $known ) {
			if ( taxonomy_exists( $known ) ) {
				$taxonomies[] = $known;
			}
		}

		foreach ( array_unique( $taxonomies ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			register_taxonomy_for_object_type( $taxonomy, self::POST_TYPE );
		}
	}

	/**
	 * Arguments of the content type.
	 *
	 * @return array<string,mixed>
	 */
	public static function post_type_args() {
		$labels = array(
			'name'                  => _x( 'Portfolio', 'post type general name', 'portfolio-studio' ),
			'singular_name'         => _x( 'Portfolio Item', 'post type singular name', 'portfolio-studio' ),
			'menu_name'             => _x( 'Portfolio', 'admin menu', 'portfolio-studio' ),
			'name_admin_bar'        => _x( 'Portfolio Item', 'add new on admin bar', 'portfolio-studio' ),
			'add_new'               => __( 'Add New Item', 'portfolio-studio' ),
			'add_new_item'          => __( 'Add New Portfolio Item', 'portfolio-studio' ),
			'new_item'              => __( 'Add New Portfolio Item', 'portfolio-studio' ),
			'edit_item'             => __( 'Edit Portfolio Item', 'portfolio-studio' ),
			'view_item'             => __( 'View Item', 'portfolio-studio' ),
			'all_items'             => __( 'All Portfolio Items', 'portfolio-studio' ),
			'search_items'          => __( 'Search Portfolio', 'portfolio-studio' ),
			'parent_item_colon'     => __( 'Parent Portfolio Item:', 'portfolio-studio' ),
			'not_found'             => __( 'No portfolio items found', 'portfolio-studio' ),
			'not_found_in_trash'    => __( 'No portfolio items found in trash', 'portfolio-studio' ),
			'filter_items_list'     => __( 'Filter portfolio items list', 'portfolio-studio' ),
			'items_list'            => __( 'Portfolio items list', 'portfolio-studio' ),
			'items_list_navigation' => __( 'Portfolio items list navigation', 'portfolio-studio' ),
		);

		return array(
			'labels'             => $labels,
			'description'        => __( 'Editorial portfolio projects.', 'portfolio-studio' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-portfolio',
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'author', 'revisions' ),
			'rewrite'            => array(
				'slug'       => 'portfolio',
				'with_front' => true,
			),
		);
	}

	/**
	 * Arguments of a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $kind     Either `category` or `tag`.
	 * @return array<string,mixed>
	 */
	public static function taxonomy_args( $taxonomy, $kind ) {
		$hierarchical = ( 'category' === $kind );

		if ( $hierarchical ) {
			$labels = array(
				'name'                       => _x( 'Portfolio Categories', 'taxonomy general name', 'portfolio-studio' ),
				'singular_name'              => _x( 'Portfolio Category', 'taxonomy singular name', 'portfolio-studio' ),
				'menu_name'                  => __( 'Portfolio Categories', 'portfolio-studio' ),
				'all_items'                  => __( 'All Portfolio Categories', 'portfolio-studio' ),
				'edit_item'                  => __( 'Edit Portfolio Category', 'portfolio-studio' ),
				'view_item'                  => __( 'View Category', 'portfolio-studio' ),
				'update_item'                => __( 'Update Portfolio Category', 'portfolio-studio' ),
				'add_new_item'               => __( 'Add New Portfolio Category', 'portfolio-studio' ),
				'new_item_name'              => __( 'New Portfolio Category Name', 'portfolio-studio' ),
				'parent_item'                => __( 'Parent Portfolio Category', 'portfolio-studio' ),
				'parent_item_colon'          => __( 'Parent Portfolio Category:', 'portfolio-studio' ),
				'search_items'               => __( 'Search Portfolio Categories', 'portfolio-studio' ),
				'not_found'                  => __( 'No portfolio categories found.', 'portfolio-studio' ),
				'popular_items'              => __( 'Popular Portfolio Categories', 'portfolio-studio' ),
				'separate_items_with_commas' => __( 'Separate portfolio categories with commas', 'portfolio-studio' ),
				'add_or_remove_items'        => __( 'Add or remove portfolio categories', 'portfolio-studio' ),
				'choose_from_most_used'      => __( 'Choose from the most used portfolio categories', 'portfolio-studio' ),
				'items_list'                 => __( 'Portfolio categories list', 'portfolio-studio' ),
				'items_list_navigation'      => __( 'Portfolio categories list navigation', 'portfolio-studio' ),
			);
		} else {
			$labels = array(
				'name'                       => _x( 'Portfolio Tags', 'taxonomy general name', 'portfolio-studio' ),
				'singular_name'              => _x( 'Portfolio Tag', 'taxonomy singular name', 'portfolio-studio' ),
				'menu_name'                  => __( 'Portfolio Tags', 'portfolio-studio' ),
				'all_items'                  => __( 'All Portfolio Tags', 'portfolio-studio' ),
				'edit_item'                  => __( 'Edit Portfolio Tag', 'portfolio-studio' ),
				'view_item'                  => __( 'View Tag', 'portfolio-studio' ),
				'update_item'                => __( 'Update Portfolio Tag', 'portfolio-studio' ),
				'add_new_item'               => __( 'Add New Portfolio Tag', 'portfolio-studio' ),
				'new_item_name'              => __( 'New Portfolio Tag Name', 'portfolio-studio' ),
				'parent_item'                => __( 'Parent Portfolio Tag', 'portfolio-studio' ),
				'parent_item_colon'          => __( 'Parent Portfolio Tag:', 'portfolio-studio' ),
				'search_items'               => __( 'Search Portfolio Tags', 'portfolio-studio' ),
				'not_found'                  => __( 'No portfolio tags found.', 'portfolio-studio' ),
				'separate_items_with_commas' => __( 'Separate portfolio tags with commas', 'portfolio-studio' ),
				'add_or_remove_items'        => __( 'Add or remove portfolio tags', 'portfolio-studio' ),
				'choose_from_most_used'      => __( 'Choose from the most used portfolio tags', 'portfolio-studio' ),
				'popular_items'              => __( 'Popular Portfolio Tags', 'portfolio-studio' ),
				'items_list'                 => __( 'Portfolio tags list', 'portfolio-studio' ),
				'items_list_navigation'      => __( 'Portfolio tags list navigation', 'portfolio-studio' ),
			);
		}

		return array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => $hierarchical,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => $taxonomy,
				'with_front' => true,
			),
		);
	}

	/**
	 * Names registered by this plugin during this request.
	 *
	 * @return array<string,bool>
	 */
	public static function owned() {
		return self::$owned;
	}

	/**
	 * Names that were already registered by another plugin during this request.
	 *
	 * @return array<string,bool>
	 */
	public static function foreign() {
		return self::$foreign;
	}

	/**
	 * Whether this plugin registered the given name itself.
	 *
	 * @param string $name Post type or taxonomy slug.
	 * @return bool
	 */
	public static function owns( $name ) {
		return ! empty( self::$owned[ $name ] );
	}
}
