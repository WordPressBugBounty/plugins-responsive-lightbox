<?php
/**
 * Responsive Lightbox Gallery Misc Settings
 *
 * Handles miscellaneous gallery options.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Misc class.
 *
 * Gallery misc tab - implements miscellaneous settings.
 *
 * @class Responsive_Lightbox_Gallery_Misc
 */
class Responsive_Lightbox_Gallery_Misc extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'misc';

	/**
	 * Get tab data for rendering.
	 *
	 * @param string $menu_item Optional menu item (unused for this tab).
	 * @return array Tab data.
	 */
	public function get_tab_data( $menu_item = '' ) {
		return [
			'tab_key' => self::TAB_KEY,
			'options' => [
				'gallery_title_position' => [
					'title' => __( 'Title Position', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select where to display the title.', 'responsive-lightbox' ),
					'default' => 'none',
					'options' => [
						'none' => __( 'None', 'responsive-lightbox' ),
						'top' => __( 'Top', 'responsive-lightbox' ),
						'bottom' => __( 'Bottom', 'responsive-lightbox' )
					]
				],
				'gallery_description_position' => [
					'title' => __( 'Description Position', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select where to display the description.', 'responsive-lightbox' ),
					'default' => 'none',
					'options' => [
						'none' => __( 'None', 'responsive-lightbox' ),
						'top' => __( 'Top', 'responsive-lightbox' ),
						'bottom' => __( 'Bottom', 'responsive-lightbox' )
					]
				],
				'gallery_description' => [
					'title' => __( 'Gallery Description', 'responsive-lightbox' ),
					'type' => 'textarea',
					'description' => __( 'Enter the gallery description (optional).', 'responsive-lightbox' ),
					'default' => '',
					'class' => 'large-text'
				],
				'gallery_custom_class' => [
					'title' => __( 'Custom Classes', 'responsive-lightbox' ),
					'type' => 'textarea',
					'description' => __( 'Add custom, space separated CSS classes (optional).', 'responsive-lightbox' ),
					'default' => '',
					'class' => 'large-text'
				]
			]
		];
	}
}