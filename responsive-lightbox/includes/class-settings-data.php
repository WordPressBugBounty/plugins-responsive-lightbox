<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Data class.
 *
 * Centralized static helper for settings data (scripts, image titles, etc.)
 * Previously stored in class-settings.php arrays.
 *
 * @class Responsive_Lightbox_Settings_Data
 * @since 2.6.1
 */
class Responsive_Lightbox_Settings_Data {

	/**
	 * Get available lightbox scripts with metadata.
	 *
	 * @return array Scripts array with name, configurability, and reset fields.
	 */
	public static function get_scripts() {
		$rl = Responsive_Lightbox();

		return apply_filters(
			'rl_settings_scripts',
			[
				'glightbox' => [
					'name'				=> __( 'GLightbox', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'title', 'caption', 'html_caption' ],
					'reset'				=> [
						'skin'					=> 'clean',
						'width'					=> 900,
						'height'				=> 506,
						'effect'				=> 'fade',
						'slide_effect'			=> 'slide',
						'open_effect'			=> 'zoom',
						'close_effect'			=> 'zoom',
						'more_length'			=> 60,
						'more_text'				=> __( 'See more', 'responsive-lightbox' ),
						'more_toggle'			=> true,
						'loop_at_end'			=> false,
						'autoplay_videos'		=> true,
						'touchnavigation'		=> true,
						'touchFollowAxis'		=> true,
						'keyboardnavigation'	=> true,
						'close_on_click'		=> true,
						'drag_auto_snap'		=> false
					]
				],
				'swipebox' => [
					'name'				=> __( 'SwipeBox', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'title' ],
					'animations'		=> [
						'css'	=> __( 'CSS', 'responsive-lightbox' ),
						'jquery'	=> __( 'jQuery', 'responsive-lightbox' )
					],
					'reset'				=> [
						'animation'				=> 'css',
						'force_single_image'	=> false,
						'hide_bars'				=> true,
						'hide_bars_delay'		=> 5000,
						'video_max_width'		=> 1080
					]
				],
				'prettyphoto' => [
					'name'				=> __( 'prettyPhoto', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'inline', 'iframe', 'ajax', 'title', 'caption' ],
					'animation_speeds'	=> [
						'fast'		=> __( 'fast', 'responsive-lightbox' ),
						'normal'	=> __( 'normal', 'responsive-lightbox' ),
						'slow'		=> __( 'slow', 'responsive-lightbox' )
					],
					'themes'			=> [
						'pp_default'	=> __( 'default', 'responsive-lightbox' ),
						'light_rounded'	=> __( 'light rounded', 'responsive-lightbox' ),
						'dark_rounded'	=> __( 'dark rounded', 'responsive-lightbox' ),
						'light_square'	=> __( 'light square', 'responsive-lightbox' ),
						'dark_square'	=> __( 'dark square', 'responsive-lightbox' ),
						'facebook'		=> __( 'facebook', 'responsive-lightbox' )
					],
					'wmodes'			=> [
						'opaque'		=> __( 'opaque', 'responsive-lightbox' ),
						'transparent'	=> __( 'transparent', 'responsive-lightbox' ),
						'window'		=> __( 'window', 'responsive-lightbox' ),
						'direct'		=> __( 'direct', 'responsive-lightbox' )
					],
					'reset'				=> [
						'animation_speed'	=> 'normal',
						'slideshow'			=> false,
						'slideshow_delay'	=> 5000,
						'slideshow_autoplay'	=> false,
						'opacity'			=> 75,
						'show_title'		=> true,
						'allow_resize'		=> true,
						'allow_expand'		=> true,
						'width'				=> 1080,
						'height'			=> 720,
						'separator'			=> '/',
						'theme'				=> 'pp_default',
						'horizontal_padding'	=> 20,
						'hide_flash'		=> false,
						'wmode'				=> 'opaque',
						'video_autoplay'	=> false,
						'modal'				=> false,
						'deeplinking'		=> false,
						'overlay_gallery'	=> true,
						'keyboard_shortcuts'	=> true,
						'social'			=> false
					]
				],
				'nivo' => [
					'name'				=> __( 'Nivo Lightbox', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'inline', 'iframe', 'ajax', 'title' ],
					'effects'			=> [
						'fade'		=> 'fade',
						'fadeScale'	=> 'fadeScale',
						'slideLeft'	=> 'slideLeft',
						'slideRight'	=> 'slideRight',
						'slideUp'	=> 'slideUp',
						'slideDown'	=> 'slideDown',
						'fall'		=> 'fall'
					],
					'reset'				=> [
						'effect'			=> 'fade',
						'click_overlay_to_close'	=> true,
						'keyboard_nav'		=> true,
						'error_message'		=> __( 'The requested content cannot be loaded. Please try again later.', 'responsive-lightbox' )
					]
				],
				'imagelightbox' => [
					'name'				=> __( 'Image Lightbox', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [],
					'reset'				=> [
						'animation_speed'	=> 250,
						'preload_next'		=> true,
						'enable_keyboard'	=> true,
						'quit_on_end'		=> false,
						'quit_on_image_click'	=> false,
						'quit_on_document_click'	=> true
					]
				],
				'tosrus' => [
					'name'				=> __( 'TosRUs', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'inline', 'title' ],
					'reset'				=> [
						'effect'			=> 'slide',
						'infinite'			=> true,
						'keys'				=> true,
						'autoplay'			=> true,
						'pause_on_hover'	=> true,
						'timeout'			=> 4000,
						'pagination'		=> true,
						'pagination_type'	=> 'thumbnails',
						'close_on_click'	=> false
					]
				],
				'featherlight' => [
					'name'				=> __( 'Featherlight', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'inline', 'iframe', 'ajax' ],
					'reset'				=> [
						'open_speed'			=> 250,
						'close_speed'			=> 250,
						'close_on_click'		=> 'background',
						'close_on_esc'			=> true,
						'gallery_fade_in'		=> 100,
						'gallery_fade_out'		=> 300,
						'iframe'				=> true,
						'iframe_width'			=> 1080,
						'iframe_height'			=> 720
					]
				],
				'magnific' => [
					'name'				=> __( 'Magnific Popup', 'responsive-lightbox' ),
					'configurability'	=> true,
					'supports'			=> [ 'inline', 'iframe', 'ajax', 'title', 'caption' ],
					'reset'				=> [
						'disable_on'		=> 0,
						'close_on_content_click'	=> false,
						'close_on_background_click'	=> true,
						'show_close_button'	=> true,
						'enable_escape_key'	=> true,
						'align_top'			=> false,
						'fixed_content_pos'	=> 'auto',
						'fixed_background_pos'	=> false,
						'preloader'			=> true,
						'type'				=> 'image',
						'gallery'			=> true,
						'arrow_markup'		=> '<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir%"></button>',
						'close_markup'		=> '<button title="%title%" type="button" class="mfp-close">×</button>',
						'image_markup'		=> '<div class="mfp-figure"><div class="mfp-close"></div><div class="mfp-img"></div><div class="mfp-bottom-bar"><div class="mfp-title"></div><div class="mfp-counter"></div></div></div>',
						'iframe_markup'		=> '<div class="mfp-iframe-scaler"><div class="mfp-close"></div><iframe class="mfp-iframe" frameborder="0" allowfullscreen></iframe></div>',
						'main_class'		=> '',
						'iframe_patterns'	=> 'default',
						'video_autoplay'	=> false,
						'loop'				=> false,
						'animation_effect'	=> 'zoom',
						'zoom_duration'		=> 500,
						'zoom_easing'		=> 'ease-in-out',
						'zoom_opacity'		=> false
					]
				]
			]
		);
	}

	/**
	 * Get image title options.
	 *
	 * @return array Image title options array.
	 */
	public static function get_image_titles() {
		return [
			''				=> __( 'None', 'responsive-lightbox' ),
			'default'		=> __( 'Attachment Title', 'responsive-lightbox' ),
			'title'			=> __( 'Image Title', 'responsive-lightbox' ),
			'caption'		=> __( 'Image Caption', 'responsive-lightbox' ),
			'alt'			=> __( 'Image Alt Text', 'responsive-lightbox' ),
			'description'	=> __( 'Image Description', 'responsive-lightbox' )
		];
	}

	/**
	 * Get image size options.
	 *
	 * @return array Image size options array.
	 */
	public static function get_image_sizes() {
		return apply_filters(
			'rl_settings_image_sizes',
			[
				'full'		=> __( 'Full Size (default)', 'responsive-lightbox' ),
				'large'		=> __( 'Large', 'responsive-lightbox' ),
				'medium'	=> __( 'Medium', 'responsive-lightbox' ),
				'thumbnail'	=> __( 'Thumbnail', 'responsive-lightbox' )
			]
		);
	}
}
