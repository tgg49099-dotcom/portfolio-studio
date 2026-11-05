<?php
/**
 * The "Project details" editor panel.
 *
 * @package Portfolio_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic editor panel holding the four editorial fields.
 *
 * The fields are stored in post meta through `register_post_meta()`, so the same data is
 * available to the REST API, to exports and to the 1-click import without a second format.
 *
 * @package Portfolio_Studio
 */
class Portfolio_Studio_Fields {

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'portfolio_studio_save_details';

	/**
	 * Nonce field name.
	 */
	const NONCE_NAME = 'portfolio_studio_details_nonce';

	/**
	 * Meta box identifier.
	 */
	const BOX_ID = 'portfolio-studio-details';

	/**
	 * Hook the editor panel.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! post_type_exists( Portfolio_Studio_Content_Types::POST_TYPE ) ) {
			return;
		}

		add_action( 'add_meta_boxes_' . Portfolio_Studio_Content_Types::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . Portfolio_Studio_Content_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Declare the meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			self::BOX_ID,
			__( 'Project details', 'portfolio-studio' ),
			array( __CLASS__, 'render' ),
			Portfolio_Studio_Content_Types::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the panel.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$client       = Portfolio_Studio_Meta::get( $post->ID, Portfolio_Studio_Meta::KEY_CLIENT );
		$year         = Portfolio_Studio_Meta::get( $post->ID, Portfolio_Studio_Meta::KEY_YEAR );
		$role         = Portfolio_Studio_Meta::get( $post->ID, Portfolio_Studio_Meta::KEY_ROLE );
		$technologies = Portfolio_Studio_Meta::get( $post->ID, Portfolio_Studio_Meta::KEY_TECHNOLOGIES );

		?>
		<p>
			<label for="portfolio-studio-client"><strong><?php esc_html_e( 'Client', 'portfolio-studio' ); ?></strong></label><br />
			<input type="text" name="<?php echo esc_attr( Portfolio_Studio_Meta::KEY_CLIENT ); ?>" id="portfolio-studio-client" class="widefat" value="<?php echo esc_attr( $client ); ?>" />
		</p>
		<p>
			<label for="portfolio-studio-year"><strong><?php esc_html_e( 'Year', 'portfolio-studio' ); ?></strong></label><br />
			<input type="number" name="<?php echo esc_attr( Portfolio_Studio_Meta::KEY_YEAR ); ?>" id="portfolio-studio-year" class="small-text" min="<?php echo esc_attr( Portfolio_Studio_Meta::YEAR_MIN ); ?>" max="<?php echo esc_attr( Portfolio_Studio_Meta::YEAR_MAX ); ?>" value="<?php echo esc_attr( $year ? $year : '' ); ?>" />
		</p>
		<p>
			<label for="portfolio-studio-role"><strong><?php esc_html_e( 'Role', 'portfolio-studio' ); ?></strong></label><br />
			<input type="text" name="<?php echo esc_attr( Portfolio_Studio_Meta::KEY_ROLE ); ?>" id="portfolio-studio-role" class="widefat" value="<?php echo esc_attr( $role ); ?>" />
		</p>
		<p>
			<label for="portfolio-studio-technologies"><strong><?php esc_html_e( 'Technologies', 'portfolio-studio' ); ?></strong></label><br />
			<input type="text" name="<?php echo esc_attr( Portfolio_Studio_Meta::KEY_TECHNOLOGIES ); ?>" id="portfolio-studio-technologies" class="widefat" value="<?php echo esc_attr( implode( ', ', $technologies ) ); ?>" />
			<span class="description"><?php esc_html_e( 'Comma separated list.', 'portfolio-studio' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Save the panel.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Current post.
	 * @return void
	 */
	public static function save( $post_id, $post = null ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( $post && ! empty( $post->post_type ) && Portfolio_Studio_Content_Types::POST_TYPE !== $post->post_type ) {
			return;
		}

		$client = isset( $_POST[ Portfolio_Studio_Meta::KEY_CLIENT ] )
			? Portfolio_Studio_Meta::sanitize_client( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_CLIENT ] ) )
			: '';
		$year = isset( $_POST[ Portfolio_Studio_Meta::KEY_YEAR ] )
			? Portfolio_Studio_Meta::sanitize_year( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_YEAR ] ) )
			: 0;
		$role = isset( $_POST[ Portfolio_Studio_Meta::KEY_ROLE ] )
			? Portfolio_Studio_Meta::sanitize_role( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_ROLE ] ) )
			: '';
		$technologies = isset( $_POST[ Portfolio_Studio_Meta::KEY_TECHNOLOGIES ] )
			? Portfolio_Studio_Meta::sanitize_technologies( wp_unslash( $_POST[ Portfolio_Studio_Meta::KEY_TECHNOLOGIES ] ) )
			: array();

		if ( '' !== $client ) {
			update_post_meta( $post_id, Portfolio_Studio_Meta::KEY_CLIENT, $client );
		} else {
			delete_post_meta( $post_id, Portfolio_Studio_Meta::KEY_CLIENT );
		}

		if ( $year > 0 ) {
			update_post_meta( $post_id, Portfolio_Studio_Meta::KEY_YEAR, $year );
		} else {
			delete_post_meta( $post_id, Portfolio_Studio_Meta::KEY_YEAR );
		}

		if ( '' !== $role ) {
			update_post_meta( $post_id, Portfolio_Studio_Meta::KEY_ROLE, $role );
		} else {
			delete_post_meta( $post_id, Portfolio_Studio_Meta::KEY_ROLE );
		}

		if ( ! empty( $technologies ) ) {
			update_post_meta( $post_id, Portfolio_Studio_Meta::KEY_TECHNOLOGIES, $technologies );
		} else {
			delete_post_meta( $post_id, Portfolio_Studio_Meta::KEY_TECHNOLOGIES );
		}
	}
}
