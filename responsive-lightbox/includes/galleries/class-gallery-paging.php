<?php
/**
 * Responsive Lightbox Gallery Paging Settings
 *
 * Handles gallery pagination options.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Paging class.
 *
 * Gallery paging tab - implements pagination settings.
 *
 * @class Responsive_Lightbox_Gallery_Paging
 */
class Responsive_Lightbox_Gallery_Paging extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'paging';

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
				'pagination' => [
					'title' => __( 'Use Pagination', 'responsive-lightbox' ),
					'type' => 'boolean',
					'label' => __( 'Enable pagination.', 'responsive-lightbox' ),
					'default' => false,
					'description' => __( 'Enable pagination for this gallery.', 'responsive-lightbox' )
				],
				'pagination_type' => [
					'title' => __( 'Pagination Type', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select pagination type.', 'responsive-lightbox' ),
					'default' => 'paged',
					'options' => [
						'paged' => __( 'standard', 'responsive-lightbox' ),
						'ajax' => __( 'AJAX', 'responsive-lightbox' ),
						'infinite' => __( 'infinite scroll', 'responsive-lightbox' )
					]
				],
				'pagination_position' => [
					'title' => __( 'Pagination Position', 'responsive-lightbox' ),
					'type' => 'select',
					'description' => __( 'Select pagination position.', 'responsive-lightbox' ),
					'default' => 'bottom',
					'options' => [
						'bottom' => __( 'bottom', 'responsive-lightbox' ),
						'top' => __( 'top', 'responsive-lightbox' ),
						'both' => __( 'top & bottom', 'responsive-lightbox' )
					]
				],
				'images_per_page' => [
					'title' => __( 'Images Per Page', 'responsive-lightbox' ),
					'type' => 'number',
					'description' => __( 'Number of images per page.', 'responsive-lightbox' ),
					'default' => get_option( 'posts_per_page', 20 ),
					'step' => 1,
					'min' => 0
				],
				'load_more' => [
					'title' => __( 'Load More', 'responsive-lightbox' ),
					'type' => 'radio',
					'description' => __( 'Select the load more trigger (infinite scroll only).', 'responsive-lightbox' ),
					'default' => 'automatically',
					'options' => [
						'automatically' => __( 'Automatically', 'responsive-lightbox' ),
						'manually' => __( 'On click', 'responsive-lightbox' )
					]
				]
			]
		];
	}
}