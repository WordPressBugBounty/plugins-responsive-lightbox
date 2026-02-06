<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Lightboxes class.
 *
 * Settings page class for Lightboxes tab migration to new Settings API.
 *
 * @class Responsive_Lightbox_Settings_Lightboxes
 */
class Responsive_Lightbox_Settings_Lightboxes extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'configuration';

	/**
	 * Validate settings for Lightboxes tab.
	 *
	 * Handles script-specific configuration, ensuring only the active
	 * script is reset without affecting other lightbox scripts.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		$rl = Responsive_Lightbox();

		// check if this is a reset operation
		if ( $this->is_reset_request() ) {
			// determine which script to reset from input (e.g., ['glightbox' => []])
			$script = key( $input );

			if ( $script ) {
				$defaults = null;

				if ( isset( $rl->defaults['configuration'][$script] ) ) {
					$defaults = $rl->defaults['configuration'][$script];
				} else {
					$scripts = Responsive_Lightbox_Settings_Data::get_scripts();
					if ( isset( $scripts[$script]['reset'] ) ) {
						$defaults = $scripts[$script]['reset'];
					}
				}

				// reset only this script's settings
				if ( is_array( $defaults ) ) {
					$input[$script] = $defaults;
					// merge with saved config to preserve other scripts
					$input = array_merge( $rl->options['configuration'], $input );
					add_settings_error( 'reset_rl_configuration', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
				}
			}

			return $input;
		}

		// sanitize fields for the active script section
		$section_key = $this->get_current_section();
		if ( $section_key !== '' ) {
			$input = $this->sanitize_fields( $input, 'configuration' );
		}

		// merge with saved configuration to preserve other lightbox scripts
		$input = array_merge( $rl->options['configuration'], $input );

		return $input;
	}

	/**	 * Provide settings data for configuration.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		$rl = Responsive_Lightbox();

		// get scripts from helper class
		$scripts = Responsive_Lightbox_Settings_Data::get_scripts();

		// resolve active script from URL section parameter or fallback to saved setting
		$active_script = $rl->options['settings']['script'];
		if ( isset( $_GET['section'] ) && is_string( $_GET['section'] ) ) {
			$section = sanitize_key( $_GET['section'] );
			if ( isset( $scripts[$section] ) ) {
				$active_script = $section;
			}
		}

		$data[self::TAB_KEY] = [
			'option_name'  => 'responsive_lightbox_configuration',
			'option_group' => 'responsive_lightbox_configuration',
			'validate'     => [ $this, 'validate' ],
			'sections'     => [],
			'fields'       => []
		];

		// add section for active script only
		if ( isset( $scripts[$active_script] ) ) {
			$data[self::TAB_KEY]['sections'][$active_script] = [
				'title' => $scripts[$active_script]['name'] . ' ' . __( 'Settings', 'responsive-lightbox' )
			];

			// add fields for active script
			$script_fields = $this->get_configuration_fields( $active_script );
			foreach ( $script_fields as $field_key => $field ) {
				$data[self::TAB_KEY]['fields'][$field_key] = $field;
				$data[self::TAB_KEY]['fields'][$field_key]['section'] = $active_script;
			}
		}

		return $data;
	}

	/**
	 * Get configuration fields for a specific script.
	 *
	 * @param string $script Script name.
	 * @return array
	 */
	private function get_configuration_fields( $script ) {
		$scripts = Responsive_Lightbox_Settings_Data::get_scripts();

		switch ( $script ) {
			case 'swipebox':
				return [
					'animation' => [
						'title' => __( 'Animation Type', 'responsive-lightbox' ),
						'type' => 'radio',
						'label' => '',
						'description' => __( 'Select a method of applying a lightbox effect.', 'responsive-lightbox' ),
						'options' => isset( $scripts['swipebox']['animations'] ) ? $scripts['swipebox']['animations'] : [],
						'parent' => 'swipebox'
					],
					'force_png_icons' => [
						'title' => __( 'Force PNG Icons', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable this if you\'re having problems with navigation icons not visible on some devices.', 'responsive-lightbox' ),
						'parent' => 'swipebox'
					],
					'hide_close_mobile' => [
						'title' => __( 'Hide Close on Mobile', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Hide the close button on mobile devices.', 'responsive-lightbox' ),
						'parent' => 'swipebox'
					],
					'remove_bars_mobile' => [
						'title' => __( 'Remove Bars on Mobile', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Hide the top and bottom bars on mobile devices.', 'responsive-lightbox' ),
						'parent' => 'swipebox'
					],
					'hide_bars' => [
						'title' => __( 'Top and Bottom Bars', 'responsive-lightbox' ),
						'type' => 'multiple',
						'description' => __( 'Hide top and bottom bars after a period of time.', 'responsive-lightbox' ),
						'fields' => [
							'hide_bars' => [
								'type' => 'boolean',
								'label' => __( 'Hide top and bottom bars after a period of time.', 'responsive-lightbox' ),
								'parent' => 'swipebox'
							],
							'hide_bars_delay' => [
								'type' => 'number',
								'description' => __( 'Enter the time after which the top and bottom bars will be hidden (when hiding is enabled).', 'responsive-lightbox' ),
								'append' => 'ms',
								'parent' => 'swipebox'
							]
						],
						'parent' => 'swipebox'
					],
					'video_max_width' => [
						'title' => __( 'Video Max Width', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Enter the max video width in a lightbox.', 'responsive-lightbox' ),
						'append' => 'px',
						'parent' => 'swipebox'
					],
					'loop_at_end' => [
						'title' => __( 'Loop at End', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'True will return to the first image after the last image is reached.', 'responsive-lightbox' ),
						'parent' => 'swipebox'
					]
				];

			case 'prettyphoto':
				return [
					'animation_speed' => [
						'title' => __( 'Animation Speed', 'responsive-lightbox' ),
						'type' => 'radio',
						'label' => '',
						'description' => __( 'Select animation speed for lightbox effect.', 'responsive-lightbox' ),
						'options' => isset( $scripts['prettyphoto']['animation_speeds'] ) ? $scripts['prettyphoto']['animation_speeds'] : [],
						'parent' => 'prettyphoto'
					],
					'slideshow' => [
						'title' => __( 'Slideshow', 'responsive-lightbox' ),
						'type' => 'multiple',
						'fields' => [
							'slideshow' => [
								'type' => 'boolean',
								'label' => __( 'Display images as slideshow', 'responsive-lightbox' ),
								'parent' => 'prettyphoto'
							],
							'slideshow_delay' => [
								'type' => 'number',
								'description' => __( 'Enter time (in miliseconds).', 'responsive-lightbox' ),
								'append' => 'ms',
								'parent' => 'prettyphoto'
							]
						],
						'parent' => 'prettyphoto'
					],
					'slideshow_autoplay' => [
						'title' => __( 'Slideshow Autoplay', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Automatically start slideshow.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'opacity' => [
						'title' => __( 'Opacity', 'responsive-lightbox' ),
						'type' => 'range',
						'description' => __( 'Value between 0 and 100, 100 for no opacity.', 'responsive-lightbox' ),
						'min' => 0,
						'max' => 100,
						'parent' => 'prettyphoto'
					],
					'show_title' => [
						'title' => __( 'Show Title', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Display image title.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'allow_resize' => [
						'title' => __( 'Allow Resize Big Images', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Resize the photos bigger than viewport.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'allow_expand' => [
						'title' => __( 'Allow Expand', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Allow expanding images.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'width' => [
						'title' => __( 'Video Width', 'responsive-lightbox' ),
						'type' => 'number',
						'append' => 'px',
						'parent' => 'prettyphoto'
					],
					'height' => [
						'title' => __( 'Video Height', 'responsive-lightbox' ),
						'type' => 'number',
						'append' => 'px',
						'parent' => 'prettyphoto'
					],
					'theme' => [
						'title' => __( 'Theme', 'responsive-lightbox' ),
						'type' => 'radio',
						'description' => __( 'Select the theme for lightbox effect.', 'responsive-lightbox' ),
						'options' => isset( $scripts['prettyphoto']['themes'] ) ? $scripts['prettyphoto']['themes'] : [],
						'parent' => 'prettyphoto'
					],
					'horizontal_padding' => [
						'title' => __( 'Horizontal Padding', 'responsive-lightbox' ),
						'type' => 'number',
						'append' => 'px',
						'parent' => 'prettyphoto'
					],
					'hide_flash' => [
						'title' => __( 'Hide Flash', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Hide all the flash objects on a page. Enable this if flash appears over prettyPhoto.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'wmode' => [
						'title' => __( 'Flash Window Mode (wmode)', 'responsive-lightbox' ),
						'type' => 'radio',
						'description' => __( 'Select flash window mode.', 'responsive-lightbox' ),
						'options' => isset( $scripts['prettyphoto']['wmodes'] ) ? $scripts['prettyphoto']['wmodes'] : [],
						'parent' => 'prettyphoto'
					],
					'video_autoplay' => [
						'title' => __( 'Video Autoplay', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Automatically start videos.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'modal' => [
						'title' => __( 'Modal', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If set to true, only the close button will close the window.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'deeplinking' => [
						'title' => __( 'Deeplinking', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Allow prettyPhoto to update the url to enable deeplinking.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'overlay_gallery' => [
						'title' => __( 'Overlay Gallery', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If enabled, a gallery will overlay the fullscreen image on mouse over.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'keyboard_shortcuts' => [
						'title' => __( 'Keyboard Shortcuts', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Set to false if you open forms inside prettyPhoto.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					],
					'social' => [
						'title' => __( 'Social (Twitter, Facebook)', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Display links to Facebook and Twitter.', 'responsive-lightbox' ),
						'parent' => 'prettyphoto'
					]
				];

			case 'nivo':
				return [
					'effect' => [
						'title' => __( 'Effect', 'responsive-lightbox' ),
						'type' => 'radio',
						'description' => __( 'The effect to use when showing the lightbox.', 'responsive-lightbox' ),
						'options' => isset( $scripts['nivo']['effects'] ) ? $scripts['nivo']['effects'] : [],
						'parent' => 'nivo'
					],
					'keyboard_nav' => [
						'title' => __( 'Keyboard Navigation', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable keyboard navigation (left/right/escape).', 'responsive-lightbox' ),
						'parent' => 'nivo'
					],
					'click_overlay_to_close' => [
						'title' => __( 'Click Overlay to Close', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable to close lightbox on overlay click.', 'responsive-lightbox' ),
						'parent' => 'nivo'
					],
					'error_message' => [
						'title' => __( 'Error Message', 'responsive-lightbox' ),
						'type' => 'text',
						'class' => 'large-text',
						'label' => __( 'Error message if the content cannot be loaded.', 'responsive-lightbox' ),
						'parent' => 'nivo'
					]
				];

			case 'imagelightbox':
				return [
					'animation_speed' => [
						'title' => __( 'Animation Speed', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Animation speed.', 'responsive-lightbox' ),
						'append' => 'ms',
						'parent' => 'imagelightbox'
					],
					'preload_next' => [
						'title' => __( 'Preload Next Image', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Silently preload the next image.', 'responsive-lightbox' ),
						'parent' => 'imagelightbox'
					],
					'enable_keyboard' => [
						'title' => __( 'Enable Keyboard Keys', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable keyboard shortcuts (arrows Left/Right and Esc).', 'responsive-lightbox' ),
						'parent' => 'imagelightbox'
					],
					'quit_on_end' => [
						'title' => __( 'Quit After Last Image', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Quit after viewing the last image.', 'responsive-lightbox' ),
						'parent' => 'imagelightbox'
					],
					'quit_on_image_click' => [
						'title' => __( 'Quit On Image Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Quit when the viewed image is clicked.', 'responsive-lightbox' ),
						'parent' => 'imagelightbox'
					],
					'quit_on_document_click' => [
						'title' => __( 'Quit On Anything Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Quit when anything but the viewed image is clicked.', 'responsive-lightbox' ),
						'parent' => 'imagelightbox'
					]
				];

			case 'tosrus':
				return [
					'effect' => [
						'title' => __( 'Transition Effect', 'responsive-lightbox' ),
						'type' => 'radio',
						'description' => __( 'What effect to use for the transition.', 'responsive-lightbox' ),
						'options' => [
							'slide' => __( 'slide', 'responsive-lightbox' ),
							'fade' => __( 'fade', 'responsive-lightbox' )
						],
						'parent' => 'tosrus'
					],
					'infinite' => [
						'title' => __( 'Infinite Loop', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Whether or not to slide back to the first slide when the last has been reached.', 'responsive-lightbox' ),
						'parent' => 'tosrus'
					],
					'keys' => [
						'title' => __( 'Keyboard Navigation', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable keyboard navigation (left/right/escape).', 'responsive-lightbox' ),
						'parent' => 'tosrus'
					],
					'autoplay' => [
						'title' => __( 'Autoplay', 'responsive-lightbox' ),
						'type' => 'multiple',
						'fields' => [
							'autoplay' => [
								'type' => 'boolean',
								'label' => __( 'Automatically start slideshow.', 'responsive-lightbox' ),
								'parent' => 'tosrus'
							],
							'timeout' => [
								'type' => 'number',
								'description' => __( 'The timeout between sliding to the next slide in milliseconds.', 'responsive-lightbox' ),
								'append' => 'ms',
								'parent' => 'tosrus'
							]
						],
						'parent' => 'tosrus'
					],
					'pause_on_hover' => [
						'title' => __( 'Pause On Hover', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Whether or not to pause on hover.', 'responsive-lightbox' ),
						'parent' => 'tosrus'
					],
					'pagination' => [
						'title' => __( 'Pagination', 'responsive-lightbox' ),
						'type' => 'multiple',
						'fields' => [
							'pagination' => [
								'type' => 'boolean',
								'label' => __( 'Whether or not to add a pagination.', 'responsive-lightbox' ),
								'parent' => 'tosrus'
							],
							'pagination_type' => [
								'type' => 'radio',
								'description' => __( 'What type of pagination to use.', 'responsive-lightbox' ),
								'options' => [
									'bullets' => __( 'Bullets', 'responsive-lightbox' ),
									'thumbnails' => __( 'Thumbnails', 'responsive-lightbox' )
								],
								'parent' => 'tosrus'
							]
						],
						'parent' => 'tosrus'
					],
					'close_on_click' => [
						'title' => __( 'Overlay Close', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable to close lightbox on overlay click.', 'responsive-lightbox' ),
						'parent' => 'tosrus'
					]
				];

			case 'featherlight':
				return [
					'open_speed' => [
						'title' => __( 'Opening Speed', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Duration of opening animation.', 'responsive-lightbox' ),
						'append' => 'ms',
						'parent' => 'featherlight'
					],
					'close_speed' => [
						'title' => __( 'Closing Speed', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Duration of closing animation.', 'responsive-lightbox' ),
						'append' => 'ms',
						'parent' => 'featherlight'
					],
					'close_on_click' => [
						'title' => __( 'Close On Click', 'responsive-lightbox' ),
						'type' => 'radio',
						'label' => __( 'Select how to close lightbox.', 'responsive-lightbox' ),
						'options' => [
							'background' => __( 'background', 'responsive-lightbox' ),
							'anywhere' => __( 'anywhere', 'responsive-lightbox' ),
							'false' => __( 'false', 'responsive-lightbox' )
						],
						'parent' => 'featherlight'
					],
					'close_on_esc' => [
						'title' => __( 'Close On Esc', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Toggle if pressing Esc button closes lightbox.', 'responsive-lightbox' ),
						'parent' => 'featherlight'
					],
					'gallery_fade_in' => [
						'title' => __( 'Gallery Fade In', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Animation speed when image is loaded.', 'responsive-lightbox' ),
						'append' => 'ms',
						'parent' => 'featherlight'
					],
					'gallery_fade_out' => [
						'title' => __( 'Gallery Fade Out', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'Animation speed before image is loaded.', 'responsive-lightbox' ),
						'append' => 'ms',
						'parent' => 'featherlight'
					]
				];

			case 'magnific':
				return [
					'disable_on' => [
						'title' => __( 'Disable On', 'responsive-lightbox' ),
						'type' => 'number',
						'description' => __( 'If window width is less than the number in this option lightbox will not be opened and the default behavior of the element will be triggered. Set to 0 to disable behavior.', 'responsive-lightbox' ),
						'append' => 'px',
						'parent' => 'magnific'
					],
					'mid_click' => [
						'title' => __( 'Middle Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If option enabled, lightbox is opened if the user clicked on the middle mouse button, or click with Command/Ctrl key.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'preloader' => [
						'title' => __( 'Preloader', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If option enabled, it\'s always present in DOM only text inside of it changes.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'close_on_content_click' => [
						'title' => __( 'Close On Content Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Close popup when user clicks on content of it. It\'s recommended to enable this option when you have only image in popup.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'close_on_background_click' => [
						'title' => __( 'Close On Background Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Close the popup when user clicks on the dark overlay.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'close_button_inside' => [
						'title' => __( 'Close Button Inside', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If enabled, Magnific Popup will put close button inside content of popup.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'show_close_button' => [
						'title' => __( 'Show Close Button', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Controls whether the close button will be displayed or not.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'enable_escape_key' => [
						'title' => __( 'Enable Escape Key', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Controls whether pressing the escape key will dismiss the active popup or not.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'align_top' => [
						'title' => __( 'Align Top', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If set to true popup is aligned to top instead of to center.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					],
					'fixed_content_position' => [
						'title' => __( 'Content Position Type', 'responsive-lightbox' ),
						'type' => 'select',
						'description' => __( 'Popup content position. If set to "auto" popup will automatically disable this option when browser doesn\'t support fixed position properly.', 'responsive-lightbox' ),
						'options' => [
							'auto' => __( 'Auto', 'responsive-lightbox' ),
							'true' => __( 'Fixed', 'responsive-lightbox' ),
							'false' => __( 'Absolute', 'responsive-lightbox' )
						],
						'parent' => 'magnific'
					],
					'fixed_background_position' => [
						'title' => __( 'Fixed Background Position', 'responsive-lightbox' ),
						'type' => 'select',
						'description' => __( 'Dark transluscent overlay content position.', 'responsive-lightbox' ),
						'options' => [
							'auto' => __( 'Auto', 'responsive-lightbox' ),
							'true' => __( 'Fixed', 'responsive-lightbox' ),
							'false' => __( 'Absolute', 'responsive-lightbox' )
						],
						'parent' => 'magnific'
					],
					'auto_focus_last' => [
						'title' => __( 'Auto Focus Last', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'If set to true last focused element before popup showup will be focused after popup close.', 'responsive-lightbox' ),
						'parent' => 'magnific'
					]
				];

			case 'glightbox':
				return [
					'slide_effect' => [
						'title' => __( 'Slide Effect', 'responsive-lightbox' ),
						'type' => 'select',
						'description' => __( 'Select the slide effect.', 'responsive-lightbox' ),
						'options' => [
							'slide' => __( 'Slide', 'responsive-lightbox' ),
							'fade' => __( 'Fade', 'responsive-lightbox' ),
							'zoom' => __( 'Zoom', 'responsive-lightbox' ),
							'none' => __( 'None', 'responsive-lightbox' )
						],
						'parent' => 'glightbox'
					],
					'close_button' => [
						'title' => __( 'Close Button', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Display the close button.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					],
					'touch_navigation' => [
						'title' => __( 'Touch Navigation', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable touch navigation.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					],
					'keyboard_navigation' => [
						'title' => __( 'Keyboard Navigation', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable keyboard navigation.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					],
					'close_on_outside_click' => [
						'title' => __( 'Close on Outside Click', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Close the lightbox when clicking outside of the content.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					],
					'loop' => [
						'title' => __( 'Loop', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable loop.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					],
					'zoomable' => [
						'title' => __( 'Zoomable', 'responsive-lightbox' ),
						'type' => 'boolean',
						'label' => __( 'Enable zoomable images.', 'responsive-lightbox' ),
						'parent' => 'glightbox'
					]
				];

			default:
				$fields = apply_filters( 'rl_settings_' . $script . '_script_configuration_fields', [] );

				if ( ! empty( $fields ) && is_array( $fields ) )
					return $fields;

				// Fallback to legacy filter used by add-ons.
				$default_name = isset( $scripts[$script]['name'] ) ? $scripts[$script]['name'] : $script;
				$legacy_config = apply_filters(
					'rl_settings_' . $script . '_script_configuration',
					[
						'option_group'	=> 'responsive_lightbox_configuration',
						'option_name'	=> 'responsive_lightbox_configuration',
						'sections'		=> [
							'responsive_lightbox_configuration' => [
								'title' => sprintf( __( '%s Settings', 'responsive-lightbox' ), $default_name )
							]
						],
						'prefix'		=> 'rl',
						'fields'		=> []
					]
				);

				if ( ! empty( $legacy_config['fields'] ) && is_array( $legacy_config['fields'] ) )
					return $legacy_config['fields'];

				return [];
		}
	}
}
