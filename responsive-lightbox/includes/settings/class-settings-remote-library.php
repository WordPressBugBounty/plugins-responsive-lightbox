<?php
/**
 * Responsive Lightbox Remote Library Settings
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Remote Library Settings class.
 *
 * @class Responsive_Lightbox_Settings_Remote_Library
 */
class Responsive_Lightbox_Settings_Remote_Library extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'remote_library';

	/**
	 * Get priority for settings data filter.
	 *
	 * @return int
	 */
	protected function get_settings_data_priority() {
		return 100; // load late to catch provider fields
	}

	/**
	 * Validate settings for Remote Library tab.
	 *
	 * Handles field sanitization and applies filter for provider extensions.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// check if this is a reset operation
		if ( $this->is_reset_request() ) {
			$input = $this->merge_with_defaults( [] );
			add_settings_error( 'reset_rl_remote_library', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
			return $input;
		}

		// sanitize all fields
		$input = $this->sanitize_fields( $input, 'remote_library' );

		// apply filter for provider extensions
		$input = apply_filters( 'rl_remote_library_settings', $input );

		return $input;
	}

	/**
	 * Provide settings data for this tab.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		// get main instance
		$rl = Responsive_Lightbox();

		// base fields for remote library settings section
		$base_fields = [
			'active' => [
				'title'			=> __( 'Remote Library', 'responsive-lightbox' ),
				'type'			=> 'boolean',
				'label'			=> __( 'Enable remote libraries.', 'responsive-lightbox' ),
				'description'	=> __( 'Check this to enable remote access to the following image libraries.', 'responsive-lightbox' )
			],
			'max_image_size' => [
				'title'			=> __( 'Max Image Size', 'responsive-lightbox' ),
				'type'			=> 'number',
				'min'			=> 1,
				'description'	=> __( 'Maximum allowed image size for remote downloads.', 'responsive-lightbox' ),
				'append'		=> __( 'MB', 'responsive-lightbox' )
			],
			'caching' => [
				'title'	=> __( 'Caching', 'responsive-lightbox' ),
				'type'	=> 'boolean',
				'label'	=> __( 'Enable remote library requests caching.', 'responsive-lightbox' )
			],
			'cache_expiry' => [
				'title'			=> '',
				'type'			=> 'number',
				'min'			=> 1,
				'description'	=> __( 'Enter the cache expiry time.', 'responsive-lightbox' ),
				'append'		=> __( 'hour(s)', 'responsive-lightbox' ),
				'logic'			=> [ 'field' => 'caching', 'operator' => 'is', 'value' => 'true' ],
				'animation'		=> 'slide'
			]
		];

		// get provider fields from new filter (preferred method for extensions)
		$provider_fields = apply_filters( 'rl_remote_library_provider_fields', [] );

		// fallback: get provider fields from legacy settings (for backward compatibility)
		if ( empty( $provider_fields ) && $rl->settings->has_setting_tab( 'remote_library' ) ) {
			$remote_fields = $rl->settings->get_setting_fields( 'remote_library' );
			foreach ( $remote_fields as $field_id => $field ) {
				// skip base fields, only get provider fields (those with section = providers)
				if ( isset( $field['section'] ) && $field['section'] === 'responsive_lightbox_remote_library_providers' ) {
					$provider_fields[$field_id] = $field;
				}
			}
		}

		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_remote_library',
			'option_group'	=> 'responsive_lightbox_remote_library',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_remote_library' => [
					'title'			=> __( 'Remote Library Settings', 'responsive-lightbox' ),
					'description'	=> '',
					'fields'		=> $base_fields
				],
				'responsive_lightbox_remote_library_providers' => [
					'title'		=> __( 'Media Providers', 'responsive-lightbox' ),
					'callback'	=> [ $this, 'remote_library_providers_description' ],
					'fields'	=> $provider_fields
				]
			]
		];

		return $data;
	}

	/**
	 * Remote Library Media Providers description.
	 *
	 * @return void
	 */
	public function remote_library_providers_description() {
		echo '<p class="description">' . sprintf( esc_html__( 'Below you\'ll find a list of available remote media libraries. If you\'re looking for Pixabay, Pexels, Instagram and other integrations please check the %s addon.', 'responsive-lightbox' ), '<a href="http://www.dfactory.co/products/remote-library-pro/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=addon" target="_blank">Remote Library Pro</a>' ) . '</p>';
	}
}
