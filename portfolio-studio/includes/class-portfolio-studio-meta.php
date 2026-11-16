<?php
/**
 * Editorial metadata: registration, sanitisation and accessors.
 *
 * @package Portfolio_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Project details stored in post meta.
 *
 * Every key is prefixed and every value has a declared type, so the data survives a
 * round trip through the REST API and a CSV export without ever changing shape.
 *
 * | Key                             | Type    | Meaning                          |
 * |---------------------------------|---------|----------------------------------|
 * | `portfolio_studio_client`       | string  | Who the project was for           |
 * | `portfolio_studio_year`         | integer | Year of the project              |
 * | `portfolio_studio_role`         | string  | Role played in the project       |
 * | `portfolio_studio_technologies` | array   | Technologies used (list of text) |
 *
 * @package Portfolio_Studio
 */
class Portfolio_Studio_Meta {

	/**
	 * Prefix shared by every key of this plugin.
	 */
	const PREFIX = 'portfolio_studio_';

	/**
	 * Client name.
	 */
	const KEY_CLIENT = 'portfolio_studio_client';

	/**
	 * Project year.
	 */
	const KEY_YEAR = 'portfolio_studio_year';

	/**
	 * Role played in the project.
	 */
	const KEY_ROLE = 'portfolio_studio_role';

	/**
	 * Technologies used.
	 */
	const KEY_TECHNOLOGIES = 'portfolio_studio_technologies';

	/**
	 * Lowest accepted year.
	 */
	const YEAR_MIN = 1970;

	/**
	 * Highest accepted year.
	 */
	const YEAR_MAX = 2100;

	/**
	 * Maximum number of technologies kept for one item.
	 */
	const TECHNOLOGIES_MAX = 50;

	/**
	 * Maximum length of one technology label.
	 */
	const TECHNOLOGY_MAX_LENGTH = 100;

	/**
	 * Register every meta key on the `portfolio` content type.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! post_type_exists( Portfolio_Studio_Content_Types::POST_TYPE ) ) {
			return;
		}

		foreach ( self::definitions() as $key => $args ) {
			register_post_meta( Portfolio_Studio_Content_Types::POST_TYPE, $key, $args );
		}
	}

	/**
	 * Definition of every meta key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions() {
		$auth = array( __CLASS__, 'auth' );

		return array(
			self::KEY_CLIENT       => array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_client' ),
				'auth_callback'     => $auth,
			),
			self::KEY_YEAR         => array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_year' ),
				'auth_callback'     => $auth,
			),
			self::KEY_ROLE         => array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_role' ),
				'auth_callback'     => $auth,
			),
			self::KEY_TECHNOLOGIES => array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_technologies' ),
				'auth_callback'     => $auth,
			),
		);
	}

	/**
	 * Every meta key of this plugin.
	 *
	 * @return array<int,string>
	 */
	public static function keys() {
		return array_keys( self::definitions() );
	}

	/**
	 * Authorisation callback: only users who can edit the item may WRITE its details.
	 *
	 * The callback guards the writing only. WordPress calls it for the `edit_post_meta`,
	 * `add_post_meta` and `delete_post_meta` capability checks; it never hides a value
	 * by itself. What the REST API publishes depends on the content type, not on this
	 * callback: a registered meta key is exposed there only if the content type declares
	 * the `custom-fields` support. The `portfolio` content type does not declare it, so
	 * WordPress does not publish the `meta` object of these items at all — the four
	 * details are not readable through the REST API today. Were that support added
	 * later, they would become readable by anyone who can read a published item.
	 * Never store anything confidential in them.
	 *
	 * @param bool   $allowed    Whether the user can add the meta. Unused.
	 * @param string $meta_key   Meta key. Unused.
	 * @param int    $object_id  Post ID.
	 * @param int    $user_id    User ID. Unused.
	 * @param string $cap        Capability being checked. Unused.
	 * @param array  $caps       Capabilities of the user. Unused.
	 * @return bool
	 */
	public static function auth( $allowed, $meta_key, $object_id, $user_id = 0, $cap = '', $caps = array() ) {
		unset( $allowed, $meta_key, $user_id, $cap, $caps );

		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Sanitise the client name.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_client( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitise the project year.
	 *
	 * Anything that is not a plausible year becomes `0` ("not set"), never a wrong year.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_year( $value ) {
		if ( is_bool( $value ) || ! is_scalar( $value ) ) {
			return 0;
		}

		$year = (int) $value;
		if ( $year < self::YEAR_MIN || $year > self::YEAR_MAX ) {
			return 0;
		}

		return $year;
	}

	/**
	 * Sanitise the role.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_role( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitise the technologies list.
	 *
	 * Accepts an array or a comma separated string, always stores an array of clean,
	 * unique, non empty labels.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int,string>
	 */
	public static function sanitize_technologies( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		$seen  = array();

		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$item = sanitize_text_field( (string) $item );
			if ( '' === $item ) {
				continue;
			}

			$item = mb_substr( $item, 0, self::TECHNOLOGY_MAX_LENGTH );

			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $item ) : strtolower( $item );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$clean[]      = $item;

			if ( count( $clean ) >= self::TECHNOLOGIES_MAX ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Read one meta value, always with its declared type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return mixed
	 */
	public static function get( $post_id, $key ) {
		$value = get_post_meta( (int) $post_id, $key, true );

		switch ( $key ) {
			case self::KEY_YEAR:
				return self::sanitize_year( $value );
			case self::KEY_TECHNOLOGIES:
				return self::sanitize_technologies( $value );
			case self::KEY_CLIENT:
				return self::sanitize_client( $value );
			case self::KEY_ROLE:
				return self::sanitize_role( $value );
		}

		return $value;
	}

	/**
	 * Read every meta value of one item.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function get_all( $post_id ) {
		$all = array();

		foreach ( self::keys() as $key ) {
			$all[ $key ] = self::get( $post_id, $key );
		}

		return $all;
	}
}
