<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Galleries class.
 *
 * Settings page class for Galleries tab migration to new Settings API.
 *
 * @class Responsive_Lightbox_Settings_Galleries
 */
class Responsive_Lightbox_Settings_Galleries extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'gallery';

	/**
	 * Register this tab as migrated to new API.
	 *
	 * Override - registers main gallery tab and all gallery type sub-tabs.
	 *
	 * @param array $tabs Migrated tabs.
	 * @return array
	 */
	public function register_migrated_tab( $tabs ) {
		$rl = Responsive_Lightbox();

		// register the main gallery tab for new Settings API wrapper
		$tabs[] = self::TAB_KEY;

		// get gallery types
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );

		// remove default gallery
		unset( $gallery_types['default'] );

		// register gallery types that have complete legacy definitions
		foreach ( array_keys( $gallery_types ) as $gallery_type ) {
			$settings_key = $gallery_type . '_gallery';
			if ( $rl->settings->has_setting_tab( $settings_key ) ) {
				// get full settings definition to check required metadata
				$gallery_settings = $rl->settings->get_setting_definition( $settings_key );
				if ( $gallery_settings && ! empty( $gallery_settings['option_name'] ) && ! empty( $gallery_settings['option_group'] ) ) {
					$tabs[] = $settings_key;
				}
			}
		}

		return $tabs;
	}

	/**
	 * Validate settings for gallery tabs.
	 *
	 * Resolves settings key from option_page to apply correct defaults.
	 * Handles core gallery types and add-on gallery types.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		$rl = Responsive_Lightbox();

		// determine which gallery type is being saved from option_page
		$option_page = isset( $_POST['option_page'] ) ? sanitize_key( $_POST['option_page'] ) : '';
		$settings_key = '';

		// map option_page to settings key using helper method
		$settings_key = $rl->settings->get_settings_key_by_option( $option_page );

		// fallback to a valid gallery type settings key (never "gallery")
		if ( $settings_key === null || $settings_key === '' ) {
			$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );
			if ( ! is_array( $gallery_types ) )
				$gallery_types = [];

			unset( $gallery_types['default'] );
			$fallback_type = $rl->options['settings']['builder_gallery'];

			if ( empty( $fallback_type ) || ! array_key_exists( $fallback_type, $gallery_types ) ) {
				reset( $gallery_types );
				$fallback_type = key( $gallery_types );
			}

			if ( empty( $fallback_type ) )
				$fallback_type = $rl->defaults['settings']['builder_gallery'];

			$settings_key = $fallback_type !== '' ? $fallback_type . '_gallery' : '';
		}

		// check if this is a reset operation
		if ( $this->is_reset_request( $option_page ) ) {
			if ( isset( $rl->defaults[$settings_key] ) )
				$input = $rl->defaults[$settings_key];
			else
				$input = [];
			add_settings_error( 'reset_rl_gallery', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
			return $input;
		}

		// sanitize fields using the detected settings key
		$input = $this->sanitize_fields( $input, $settings_key );

		return $input;
	}

	/**
	 * Provide settings data for migrated gallery types.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		$rl = Responsive_Lightbox();

		// provide data for the main gallery tab (dynamic routing to gallery types)
		if ( ! isset( $data[self::TAB_KEY] ) ) {
			$data[self::TAB_KEY] = [
				'option_name'  => 'responsive_lightbox_gallery', // placeholder, actual option_name determined by section
				'option_group' => 'responsive_lightbox_gallery_group',
				'validate'     => [ $this, 'validate' ],
				'sections'     => [],
				'fields'       => []
			];
		}

		// define core gallery types settings
		$data['basicgrid_gallery'] = $this->get_basicgrid_settings();
		$data['basicslider_gallery'] = $this->get_basicslider_settings();
		$data['basicmasonry_gallery'] = $this->get_basicmasonry_settings();

		// get gallery types
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );

		// remove default gallery
		unset( $gallery_types['default'] );

		// handle add-on gallery types that still use legacy definitions
		$core_galleries = [ 'basicgrid', 'basicslider', 'basicmasonry' ];
		foreach ( array_keys( $gallery_types ) as $gallery_type ) {
			// skip core galleries (already defined above)
			if ( in_array( $gallery_type, $core_galleries, true ) ) {
				continue;
			}

			$settings_key = $gallery_type . '_gallery';
		if ( $rl->settings->has_setting_tab( $settings_key ) ) {
				// get full settings definition to extract configuration
				$gallery_settings = $rl->settings->get_setting_definition( $settings_key );
				if ( $gallery_settings && ! empty( $gallery_settings['option_name'] ) && ! empty( $gallery_settings['option_group'] ) ) {
					$data[$settings_key] = [
						'option_name'  => $gallery_settings['option_name'],
						'option_group' => $gallery_settings['option_group'],
						'validate'     => [ $this, 'validate' ],
						'sections'     => $gallery_settings['sections'],
						'fields'       => $gallery_settings['fields']
					];
				}
			}
		}

		return $data;
	}

	/**
	 * Get Basic Grid gallery settings.
	 *
	 * @return array
	 */
	private function get_basicgrid_settings() {
		return [
			'option_group' => 'responsive_lightbox_basicgrid_gallery',
			'option_name'  => 'responsive_lightbox_basicgrid_gallery',
			'validate'     => [ $this, 'validate' ],
			'sections'     => [
				'responsive_lightbox_basicgrid_gallery' => [
					'title' => __( 'Basic Grid Gallery Settings', 'responsive-lightbox' )
				]
			],
			'fields'       => [
				'screen_size_columns' => [
					'title'       => __( 'Screen Sizes', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicgrid_gallery',
					'type'        => 'multiple',
					'description' => __( 'Number of columns in a gallery depending on the device screen size. (if greater than 0 overrides the Columns option)', 'responsive-lightbox' ),
					'fields'      => [
						'columns_lg' => [
							'type'   => 'number',
							'min'    => 0,
							'max'    => 6,
							'append' => __( 'large devices / desktops (&ge;1200px)', 'responsive-lightbox' )
						],
						'columns_md' => [
							'type'   => 'number',
							'min'    => 0,
							'max'    => 6,
							'append' => __( 'medium devices / desktops (&ge;992px)', 'responsive-lightbox' )
						],
						'columns_sm' => [
							'type'   => 'number',
							'min'    => 0,
							'max'    => 6,
							'append' => __( 'small devices / tablets (&ge;768px)', 'responsive-lightbox' )
						],
						'columns_xs' => [
							'type'   => 'number',
							'min'    => 0,
							'max'    => 6,
							'append' => __( 'extra small devices / phones (<768px)', 'responsive-lightbox' )
						]
					]
				],
				'gutter'              => [
					'title'       => __( 'Gutter', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicgrid_gallery',
					'type'        => 'number',
					'min'         => 0,
					'description' => __( 'Set the pixel width between the columns and rows.', 'responsive-lightbox' ),
					'append'      => 'px'
				],
				'force_height'        => [
					'title'   => __( 'Force Height', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicgrid_gallery',
					'type'    => 'boolean',
					'label' => __( 'Enable to force the thumbnail row height.', 'responsive-lightbox' )
				],
				'row_height'          => [
					'title'       => __( 'Row Height', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicgrid_gallery',
					'type'        => 'number',
					'min'         => 50,
					'description' => __( 'Enter the thumbnail row height in pixels (used if Force height is enabled). Defaults to 150px.', 'responsive-lightbox' ),
					'append'      => 'px'
				]
			]
		];
	}

	/**
	 * Get Basic Slider gallery settings.
	 *
	 * @return array
	 */
	private function get_basicslider_settings() {
		return [
			'option_group' => 'responsive_lightbox_basicslider_gallery',
			'option_name'  => 'responsive_lightbox_basicslider_gallery',
			'validate'     => [ $this, 'validate' ],
			'sections'     => [
				'responsive_lightbox_basicslider_gallery' => [
					'title' => __( 'Basic Slider Gallery Settings', 'responsive-lightbox' )
				]
			],
			'fields'       => [
				'slider_type'        => [
					'title'   => __( 'Slider Type', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'select',
					'label'   => __( 'The type of the slider.', 'responsive-lightbox' ),
					'options' => [
						'slide' => __( 'Slide', 'responsive-lightbox' ),
						'loop'  => __( 'Loop', 'responsive-lightbox' ),
						'fade'  => __( 'Fade', 'responsive-lightbox' )
					]
				],
				'height'             => [
					'title'       => __( 'Height', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 0,
					'description' => __( 'Defines the carousel max width in percentage. If set to 0 slider will adapt to slides width.', 'responsive-lightbox' ),
					'append'      => 'px'
				],
				'width'              => [
					'title'       => __( 'Width', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 0,
					'description' => __( 'Defines the slide height in pixels. If set to 0, slider height will adapt to slides height.', 'responsive-lightbox' ),
					'append'      => '%'
				],
				'speed'              => [
					'title'       => __( 'Animation Speed', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 400,
					'description' => __( 'The transition speed in milliseconds.', 'responsive-lightbox' ),
					'append'      => 'ms'
				],
				'gap'                => [
					'title'       => __( 'Slides Gap', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 20,
					'description' => __( 'The gap between slides.', 'responsive-lightbox' ),
					'append'      => 'px'
				],
				'arrows_navigation'  => [
					'title'   => __( 'Arrows Navigtion', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'boolean',
					'label' => __( 'Determines whether to create arrows or not.', 'responsive-lightbox' )
				],
				'dots_navigation'    => [
					'title'   => __( 'Dots Navigtion', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'boolean',
					'label' => __( 'Determines whether to create pagination (indicator dots) or not.', 'responsive-lightbox' )
				],
				'drag'               => [
					'title'   => __( 'Drag', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'boolean',
					'label' => __( 'Determines whether to allow the user to drag the carousel or not.', 'responsive-lightbox' )
				],
				'autoplay'           => [
					'title'   => __( 'Autoplay', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'boolean',
					'label' => __( 'Determines whether to enable autoplay or not.', 'responsive-lightbox' )
				],
				'interval'           => [
					'title'       => __( 'Interval', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 1000,
					'description' => __( 'The autoplay interval duration in milliseconds.', 'responsive-lightbox' ),
					'append'      => 'ms'
				],
				'wheel'              => [
					'title'   => __( 'Mouse Wheel', 'responsive-lightbox' ),
					'section' => 'responsive_lightbox_basicslider_gallery',
					'type'    => 'boolean',
					'label' => __( 'Determines whether to enable navigation by the mouse wheel.', 'responsive-lightbox' )
				],
				'slides_per_page'    => [
					'title'       => __( 'Per Page', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 1,
					'description' => __( 'Determines the number of slides to display in a page.', 'responsive-lightbox' )
				],
				'slides_per_move'    => [
					'title'       => __( 'Per Move', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 1,
					'description' => __( 'Determines the number of slides to move at once.', 'responsive-lightbox' )
				],
				'slides_start'       => [
					'title'       => __( 'Start', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicslider_gallery',
					'type'        => 'number',
					'min'         => 0,
					'description' => __( 'Defines the start index.', 'responsive-lightbox' )
				]
			]
		];
	}

	/**
	 * Get Basic Masonry gallery settings.
	 *
	 * @return array
	 */
	private function get_basicmasonry_settings() {
		return [
			'option_group' => 'responsive_lightbox_basicmasonry_gallery',
			'option_name'  => 'responsive_lightbox_basicmasonry_gallery',
			'validate'     => [ $this, 'validate' ],
			'sections'     => [
				'responsive_lightbox_basicmasonry_gallery' => [
					'title' => __( 'Basic Masonry Gallery Settings', 'responsive-lightbox' )
				]
			],
			'fields'       => [
				'screen_size_columns' => [
					'title'       => __( 'Screen Sizes', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicmasonry_gallery',
					'type'        => 'multiple',
					'description' => __( 'Number of columns in a gallery depending on the device screen size. (if greater than 0 overrides the Columns option)', 'responsive-lightbox' ),
					'fields'      => [
						'columns_lg' => [
							'type'    => 'number',
							'min'     => 0,
							'max'     => 6,
							'default' => 4,
							'append'  => __( 'large devices / desktops (&ge;1200px)', 'responsive-lightbox' )
						],
						'columns_md' => [
							'type'    => 'number',
							'min'     => 0,
							'max'     => 6,
							'default' => 3,
							'append'  => __( 'medium devices / desktops (&ge;992px)', 'responsive-lightbox' )
						],
						'columns_sm' => [
							'type'    => 'number',
							'min'     => 0,
							'max'     => 6,
							'default' => 2,
							'append'  => __( 'small devices / tablets (&ge;768px)', 'responsive-lightbox' )
						],
						'columns_xs' => [
							'type'    => 'number',
							'min'     => 0,
							'max'     => 6,
							'default' => 2,
							'append'  => __( 'extra small devices / phones (<768px)', 'responsive-lightbox' )
						]
					]
				],
				'gutter'              => [
					'title'       => __( 'Gutter', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicmasonry_gallery',
					'type'        => 'number',
					'description' => __( 'Horizontal space between gallery items.', 'responsive-lightbox' ),
					'append'      => 'px'
				],
				'margin'              => [
					'title'       => __( 'Margin', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicmasonry_gallery',
					'type'        => 'number',
					'description' => __( 'Vertical space between gallery items.', 'responsive-lightbox' ),
					'append'      => 'px'
				],
				'origin_left'         => [
					'title'       => __( 'Origin Left', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicmasonry_gallery',
					'type'        => 'boolean',
					'label'       => __( 'Enable left-to-right layouts.', 'responsive-lightbox' ),
					'description' => __( 'Controls the horizontal flow of the layout. By default, item elements start positioning at the left. Uncheck it for right-to-left layouts.', 'responsive-lightbox' )
				],
				'origin_top'          => [
					'title'       => __( 'Origin Top', 'responsive-lightbox' ),
					'section'     => 'responsive_lightbox_basicmasonry_gallery',
					'type'        => 'boolean',
					'label'       => __( 'Enable top-to-bottom layouts.', 'responsive-lightbox' ),
					'description' => __( 'Controls the vertical flow of the layout. By default, item elements start positioning at the top. Uncheck it for bottom-up layouts.', 'responsive-lightbox' )
				]
			]
		];
	}
}