<?php
/**
 * Responsive Lightbox Gallery Design Settings
 *
 * Handles gallery design options.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Design class.
 *
 * Gallery design tab - implements design settings.
 *
 * @class Responsive_Lightbox_Gallery_Design
 */
class Responsive_Lightbox_Gallery_Design extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'design';

	/**
	 * Get tab data for rendering.
	 *
	 * @param string $menu_item Optional menu item (unused for this tab).
	 * @return array Tab data.
	 */
	public function get_tab_data( $menu_item = '' ) {
		$rl = Responsive_Lightbox();

		// merge titles
		$merged_titles = array( 'global' => __( 'Global', 'responsive-lightbox' ) ) + $rl->settings->get_data( 'image_titles' );

		return [
			'tab_key' => self::TAB_KEY,
			'options' => [
				'design_show_title' => [
					'title' => __( 'Thumbnail Title', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select title for the gallery thumbnails.', 'responsive-lightbox' ),
					'default' => 'global',
					'options' => $merged_titles
				],
				'design_show_caption' => [
					'title' => __( 'Thumbnail Caption', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select caption for the gallery thumbnails.', 'responsive-lightbox' ),
					'default' => 'global',
					'options' => $merged_titles
				],
				'show_icon' => [
					'title' => __( 'Thumbnail Icon', 'responsive-lightbox' ),
					'type' => 'radio',
					'description' => __( 'Select icon for the gallery thumbnails.', 'responsive-lightbox' ),
					'default' => '0',
					'options' => [
						'0' => __( 'none', 'responsive-lightbox' ),
						'1' => '',
						'2' => '',
						'3' => '',
						'4' => '',
						'5' => '',
						'6' => '',
						'7' => '',
						'8' => '',
						'9' => '',
						'10' => ''
					]
				],
				'hover_effect' => [
					'title' => __( 'Hover Effect', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select thumbnail effect on hover.', 'responsive-lightbox' ),
					'default' => '0',
					'options' => [
						'0' => __( 'none', 'responsive-lightbox' ),
						'1' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 1 ),
						'2' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 2 ),
						'3' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 3 ),
						'4' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 4 ),
						'5' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 5 ),
						'6' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 6 ),
						'7' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 7 ),
						'8' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 8 ),
						'9' => sprintf( __( 'Effect %s', 'responsive-lightbox' ), 9 )
					]
				],
				'caption_font_size' => [
					'title' => __( 'Caption Font Size', 'responsive-lightbox' ),
					'type' => 'number',
					'default' => 13,
					'step' => 1,
					'min' => 10,
					'max' => 20,
					'append' => 'px'
				],
				'caption_padding' => [
					'title' => __( 'Caption Padding', 'responsive-lightbox' ),
					'type' => 'number',
					'default' => 20,
					'step' => 1,
					'min' => 5,
					'max' => 30,
					'append' => 'px'
				],
				'title_color' => [
					'title' => __( 'Title Color', 'responsive-lightbox' ),
					'type' => 'color_picker',
					'default' => '#ffffff'
				],
				'caption_color' => [
					'title' => __( 'Caption Color', 'responsive-lightbox' ),
					'type' => 'color_picker',
					'default' => '#cccccc'
				],
				'background_color' => [
					'title' => __( 'Background Color', 'responsive-lightbox' ),
					'type' => 'color_picker',
					'default' => '#000000'
				],
				'background_opacity' => [
					'title' => __( 'Background Opacity', 'responsive-lightbox' ),
					'type' => 'number',
					'default' => 80,
					'step' => 1,
					'min' => 0,
					'max' => 100,
					'append' => '%'
				],
				'border_color' => [
					'title' => __( 'Border Color', 'responsive-lightbox' ),
					'type' => 'color_picker',
					'default' => '#000000'
				],
				'border_width' => [
					'title' => __( 'Border Width', 'responsive-lightbox' ),
					'type' => 'number',
					'default' => 0,
					'step' => 1,
					'min' => 0,
					'max' => 100,
					'append' => 'px'
				]
			]
		];
	}
}