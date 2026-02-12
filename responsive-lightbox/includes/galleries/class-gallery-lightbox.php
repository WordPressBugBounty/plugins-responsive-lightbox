<?php
/**
 * Responsive Lightbox Gallery Lightbox Settings
 *
 * Handles gallery lightbox options.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Lightbox class.
 *
 * Gallery lightbox tab - implements lightbox settings.
 *
 * @class Responsive_Lightbox_Gallery_Lightbox
 */
class Responsive_Lightbox_Gallery_Lightbox extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'lightbox';

	/**
	 * Get tab data for rendering.
	 *
	 * @param string $menu_item Optional menu item (unused for this tab).
	 * @return array Tab data.
	 */
	public function get_tab_data( $menu_item = '' ) {
		$rl = Responsive_Lightbox();

		// use sizes as keys and values
		$sizes = array_combine( array_keys( $rl->galleries->get_data( 'sizes' ) ), array_keys( $rl->galleries->get_data( 'sizes' ) ) );

		// add default, custom and full image size
		$sizes['full'] = __( 'Full size', 'responsive-lightbox' );
		$sizes['global'] = __( 'Global', 'responsive-lightbox' );
		$sizes['rl_custom_size'] = __( 'Custom size', 'responsive-lightbox' );

		// merge titles
		$merged_titles = array( 'global' => __( 'Global', 'responsive-lightbox' ) ) + $rl->settings->get_data( 'image_titles' );

		return [
			'tab_key' => self::TAB_KEY,
			'options' => [
				'lightbox_enable' => [
					'title' => __( 'Enable Lightbox', 'responsive-lightbox' ),
					'type' => 'boolean',
					'label' => __( 'Enable lightbox effect for the gallery.', 'responsive-lightbox' ),
					'default' => true
				],
				'lightbox_image_size' => [
					'title' => __( 'Image Size', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select image size for gallery lightbox.', 'responsive-lightbox' ),
					'default' => 'global',
					'options' => $sizes
				],
				'lightbox_custom_size' => [
					'title' => __( 'Custom Size', 'responsive-lightbox' ),
					'type' => 'multiple',
					'description' => __( 'Choose the custom image size for gallery lightbox (used if Custom Image size is selected).', 'responsive-lightbox' ),
					'fields' => [
						'lightbox_custom_size_width' => [
							'type' => 'number',
							'append' => __( 'width in px', 'responsive-lightbox' ),
							'default' => (int) get_option( 'large_size_w' )
						],
						'lightbox_custom_size_height' => [
							'type' => 'number',
							'append' => __( 'height in px', 'responsive-lightbox' ),
							'default' => (int) get_option( 'large_size_h' )
						]
					]
				],
				'lightbox_image_title' => [
					'title' => __( 'Image Title', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select image title for gallery lightbox.', 'responsive-lightbox' ),
					'default' => 'global',
					'options' => $merged_titles
				],
				'lightbox_image_caption' => [
					'title' => __( 'Image Caption', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select image caption for gallery lightbox (used if supported by selected lightbox effect).', 'responsive-lightbox' ),
					'default' => 'global',
					'options' => $merged_titles
				]
			]
		];
	}
}