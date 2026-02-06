<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Addons class.
 *
 * Settings data for Add-ons tab (Settings API).
 *
 * @class Responsive_Lightbox_Settings_Addons
 */
class Responsive_Lightbox_Settings_Addons extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'addons';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'wp_ajax_rl-get-addons-feed', [ __CLASS__, 'ajax_get_addons_feed' ] );
	}

	/**
	 * Validate settings for this tab.
	 *
	 * Override - add-ons tab has no saveable fields.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// add-ons tab has no saveable fields, return input as-is
		return $input;
	}

	/**	 * Provide settings data for Add-ons tab.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_addons',
			'option_group'	=> 'responsive_lightbox_addons',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_addons' => [
					'title'		=> __( 'Add-ons / Extensions', 'responsive-lightbox' ),
					'callback'	=> [ __CLASS__, 'render_addons_section' ]
				]
			],
			'fields'		=> []
		];

		return $data;
	}

	/**
	 * Render Add-ons section content.
	 *
	 * @return void
	 */
	public static function render_addons_section() {
		?>
		<p class="description"><?php esc_html_e( 'Enhance your website with these beautiful, easy to use extensions, designed with Responsive Lightbox & Gallery integration in mind.', 'responsive-lightbox' ); ?></p>
		<br />
		<?php
		$addons_html = self::get_cached_addons_feed();
		$has_feed = $addons_html !== '';
		$nonce = wp_create_nonce( 'rl_addons_feed' );
		$error_message = esc_html__( 'There was an error retrieving the extensions list from the server. Please try again later.', 'responsive-lightbox' );

		echo '<div id="rl-addons-feed" class="rl-addons-feed" data-nonce="' . esc_attr( $nonce ) . '" data-loaded="' . ( $has_feed ? '1' : '0' ) . '" data-error-message="' . esc_attr( $error_message ) . '">';

		if ( $has_feed ) {
			echo self::sanitize_addons_html( $addons_html );
		} else {
			echo '<div class="rl-addons-loading"><span class="spinner is-active"></span> ' . esc_html__( 'Loading extensions...', 'responsive-lightbox' ) . '</div>';
		}

		echo '</div>';
	}

	/**
	 * AJAX: Get Add-ons feed HTML.
	 *
	 * @return void
	 */
	public static function ajax_get_addons_feed() {
		$rl = Responsive_Lightbox();
		$capability = apply_filters( 'rl_lightbox_settings_capability', $rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options' );

		if ( ! current_user_can( $capability ) )
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to access this data.', 'responsive-lightbox' ) ] );

		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rl_addons_feed' ) )
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid request. Please reload the page and try again.', 'responsive-lightbox' ) ] );

		$addons_html = self::get_addons_feed_html();

		if ( $addons_html === '' )
			wp_send_json_error( [ 'message' => esc_html__( 'There was an error retrieving the extensions list from the server. Please try again later.', 'responsive-lightbox' ) ] );

		wp_send_json_success( [ 'html' => $addons_html ] );
	}

	/**
	 * Get cached Add-ons feed HTML (raw).
	 *
	 * @return string
	 */
	private static function get_cached_addons_feed() {
		$addons_html = get_transient( 'responsive_lightbox_addons_feed' );

		if ( is_string( $addons_html ) && $addons_html !== '' )
			return $addons_html;

		return '';
	}

	/**
	 * Fetch Add-ons feed HTML (raw) and cache it.
	 *
	 * @return string
	 */
	private static function fetch_addons_feed() {
		$feed = wp_remote_get( 'http://www.dfactory.co/?feed=addons&product=responsive-lightbox', [ 'sslverify' => false ] );

		if ( is_wp_error( $feed ) )
			return '';

		$body = wp_remote_retrieve_body( $feed );

		if ( ! is_string( $body ) || $body === '' )
			return '';

		set_transient( 'responsive_lightbox_addons_feed', $body, 120 );

		return $body;
	}

	/**
	 * Get Add-ons feed HTML (sanitized).
	 *
	 * @return string
	 */
	private static function get_addons_feed_html() {
		$addons_html = self::get_cached_addons_feed();

		if ( $addons_html === '' )
			$addons_html = self::fetch_addons_feed();

		if ( $addons_html === '' )
			return '';

		return self::sanitize_addons_html( $addons_html );
	}

	/**
	 * Sanitize Add-ons feed HTML.
	 *
	 * @param string $addons_html Raw HTML.
	 * @return string
	 */
	private static function sanitize_addons_html( $addons_html ) {
		$allowed_html = wp_kses_allowed_html( 'post' );

		$allowed_html['img']['srcset'] = [];
		$allowed_html['img']['sizes'] = [];

		return wp_kses( $addons_html, $allowed_html );
	}
}
