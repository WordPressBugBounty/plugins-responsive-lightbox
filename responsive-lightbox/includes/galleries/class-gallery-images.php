<?php
/**
 * Responsive Lightbox Gallery Images Settings
 *
 * Handles gallery images tab field definitions and native field rendering.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Images class.
 *
 * Gallery images tab - provides field definitions and rendering via adapter.
 *
 * @class Responsive_Lightbox_Gallery_Images
 */
class Responsive_Lightbox_Gallery_Images extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'images';

	/**
	 * Get tab label for the Images tab.
	 *
	 * @return string Tab label.
	 */
	public function get_tab_label() {
		return __( 'Images', 'responsive-lightbox' );
	}

	/**
	 * Get default menu items for the Images tab.
	 *
	 * @return array Default menu items.
	 */
	public function get_default_menu_items() {
		return [
			'media' => __( 'Media Library', 'responsive-lightbox' ),
			'featured' => __( 'Featured Image', 'responsive-lightbox' ),
			'folders' => __( 'Folders', 'responsive-lightbox' ),
			'remote_library' => __( 'Remote Library', 'responsive-lightbox' ),
		];
	}

	/**
	 * Get menu items for the Images tab.
	 *
	 * Provides menu items with labels and disabled state based on plugin settings.
	 *
	 * @return array Menu items array with labels and disabled flags.
	 */
	public function get_menu_items() {
		// Start with adapter-owned default menu items
		$menu_items = $this->get_default_menu_items();

		// Allow add-ons to modify menu items via filter
		$menu_items = apply_filters( 'rl_gallery_images_menu_items', $menu_items );

		// Defensive: Ensure we have at least 'media' as fallback
		if ( empty( $menu_items ) || ! is_array( $menu_items ) ) {
			$menu_items = [ 'media' => __( 'Media Library', 'responsive-lightbox' ) ];
		}

		// Add disabled state information
		foreach ( $menu_items as $menu_item => $label ) {
			$menu_items[$menu_item] = [
				'label' => $label,
				'disabled' => $this->is_menu_item_disabled( $menu_item ),
				'disabled_reason' => $this->get_menu_item_disabled_reason( $menu_item )
			];
		}

		return $menu_items;
	}

	/**
	 * Check if a menu item should be disabled.
	 *
	 * @param string $menu_item Menu item key.
	 * @return bool True if disabled.
	 */
	public function is_menu_item_disabled( $menu_item ) {
		$rl = Responsive_Lightbox();

		switch ( $menu_item ) {
			case 'remote_library':
				return ! $rl->options['remote_library']['active'];
			case 'folders':
				return ! $rl->options['folders']['active'];
			default:
				return false;
		}
	}

	/**
	 * Get the reason why a menu item is disabled.
	 *
	 * @param string $menu_item Menu item key.
	 * @return string Disabled reason message.
	 */
	public function get_menu_item_disabled_reason( $menu_item ) {
		switch ( $menu_item ) {
			case 'remote_library':
				return __( 'Remote Library is disabled. Enable it in the settings.', 'responsive-lightbox' );
			case 'folders':
				return __( 'Media Folders are disabled. Enable it in the settings.', 'responsive-lightbox' );
			default:
				return '';
		}
	}

	/**
	 * Normalize menu item for AJAX requests.
	 *
	 * Ensures the menu item is valid and exists in available menu items.
	 *
	 * @param string $menu_item Requested menu item.
	 * @return string Normalized menu item.
	 */
	public function normalize_menu_item( $menu_item ) {
		$available_menu_items = array_keys( $this->get_menu_items() );

		// If no menu item specified or invalid, use first available
		if ( ! is_string( $menu_item ) || $menu_item === '' || ! in_array( $menu_item, $available_menu_items, true ) ) {
			return $available_menu_items[0] ?? 'media';
		}

		return $menu_item;
	}

	/**
	 * Get tab data for rendering.
	 *
	 * Images tab requires menu item to determine field set.
	 *
	 * @param string $menu_item Menu item (media, featured, folders, remote_library).
	 * @return array Tab data with fields.
	 */
	public function get_tab_data( $menu_item = '' ) {
		$rl = Responsive_Lightbox();

		$fields = [
			'media' => $this->get_media_fields(),
			'featured' => $this->get_featured_fields(),
		];

		if ( ! empty( $rl->options['folders']['active'] ) && isset( $rl->folders ) ) {
			$fields['folders'] = $this->get_folders_fields();
		}

		if ( ! empty( $rl->options['remote_library']['active'] ) && isset( $rl->remote_library ) ) {
			$fields['remote_library'] = $this->get_remote_library_fields();
		}

		if ( empty( $menu_item ) ) {
			return $fields;
		}

		if ( isset( $fields[$menu_item] ) ) {
			return [ $menu_item => $fields[$menu_item] ];
		}

		return [];
	}

	/**
	 * Get media library fields for the Images tab.
	 *
	 * @return array
	 */
	private function get_media_fields() {
		return [
			'attachments' => [
				'title' => '',
				'type' => 'media_library',
				'default' => [
					'ids' => [],
					'exclude' => [],
					'embed' => []
				],
				'preview' => [
					'pagination' => true,
					'draggable' => true,
					'editable' => true,
					'removable' => true,
					'changeable' => true
				]
			]
		];
	}

	/**
	 * Get featured content fields for the Images tab.
	 *
	 * @return array
	 */
	private function get_featured_fields() {
		return [
			'attachments' => [
				'title' => '',
				'type' => 'media_preview',
				'default' => [
					'exclude' => []
				],
				'preview' => [
					'pagination' => true,
					'draggable' => false,
					'editable' => true,
					'removable' => false,
					'changeable' => false
				]
			],
			'number_of_posts' => [
				'title' => __( 'Number of Posts', 'responsive-lightbox' ),
				'type' => 'number',
				'description' => __( 'Enter the number of posts.', 'responsive-lightbox' ),
				'default' => 10,
				'min' => 0
			],
			'orderby' => [
				'title' => __( 'Posts Sorting', 'responsive-lightbox' ),
				'type' => 'select',
				'description' => __( 'Select the posts sorting.', 'responsive-lightbox' ),
				'default' => 'date',
				'options' => [
					'id' => __( 'ID', 'responsive-lightbox' ),
					'author' => __( 'Author', 'responsive-lightbox' ),
					'title' => __( 'Title', 'responsive-lightbox' ),
					'name' => __( 'Slug', 'responsive-lightbox' ),
					'date' => __( 'Date', 'responsive-lightbox' ),
					'modified' => __( 'Last modified date', 'responsive-lightbox' ),
					'parent' => __( 'Parent ID', 'responsive-lightbox' ),
					'rand' => __( 'Random', 'responsive-lightbox' )
				]
			],
			'order' => [
				'title' => __( 'Posts Order', 'responsive-lightbox' ),
				'type' => 'radio',
				'description' => __( 'Select the posts order.', 'responsive-lightbox' ),
				'default' => 'asc',
				'options' => [
					'asc' => __( 'Ascending', 'responsive-lightbox' ),
					'desc' => __( 'Descending', 'responsive-lightbox' )
				]
			],
			'offset' => [
				'title' => __( 'Posts Offset', 'responsive-lightbox' ),
				'type' => 'number',
				'description' => __( 'Enter the posts offset.', 'responsive-lightbox' ),
				'default' => 0,
				'min' => 0
			],
			'image_source' => [
				'title' => __( 'Image Source', 'responsive-lightbox' ),
				'type' => 'radio',
				'description' => __( 'Select the image source.', 'responsive-lightbox' ),
				'default' => 'thumbnails',
				'options' => [
					'thumbnails' => __( 'Post Thumbnails', 'responsive-lightbox' ),
					'attached_images' => __( 'Post Attached Images', 'responsive-lightbox' )
				]
			],
			'images_per_post' => [
				'title' => __( 'Images per Post', 'responsive-lightbox' ),
				'type' => 'number',
				'description' => __( 'Enter maximum number of images for a post.', 'responsive-lightbox' ),
				'default' => 1,
				'min' => 1
			],
			'post_type' => [
				'title' => __( 'Post Type', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post types to query.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'post_status' => [
				'title' => __( 'Post Status', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post status.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'post_format' => [
				'title' => __( 'Post Format', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post format.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'post_term' => [
				'title' => __( 'Post Term', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post taxonomy terms to query.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'post_author' => [
				'title' => __( 'Post Author', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post author.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'page_parent' => [
				'title' => __( 'Page Parent', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the post parent.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			],
			'page_template' => [
				'title' => __( 'Page Template', 'responsive-lightbox' ),
				'type' => 'multiselect',
				'description' => __( 'Select the page template.', 'responsive-lightbox' ),
				'options' => [],
				'default' => []
			]
		];
	}

	/**
	 * Get folders fields for the Images tab.
	 *
	 * @return array
	 */
	private function get_folders_fields() {
		$rl = Responsive_Lightbox();
		$taxonomy = 'rl_media_folder';
		if ( isset( $rl->folders ) && method_exists( $rl->folders, 'get_active_taxonomy' ) ) {
			$taxonomy = $rl->folders->get_active_taxonomy();
		}

		return [
			'attachments' => [
				'title' => '',
				'type' => 'media_preview',
				'default' => [
					'exclude' => []
				],
				'preview' => [
					'pagination' => true,
					'draggable' => false,
					'editable' => true,
					'removable' => false,
					'changeable' => false
				]
			],
			'folder' => [
				'title' => __( 'Media Folder', 'responsive-lightbox' ),
				'type' => 'taxonomy',
				'description' => __( 'Select media folder.', 'responsive-lightbox' ),
				'default' => [
					'id' => 0,
					'children' => false
				],
				'include_children' => true,
				'taxonomy' => $taxonomy
			]
		];
	}

	/**
	 * Get remote library fields for the Images tab.
	 *
	 * @return array
	 */
	private function get_remote_library_fields() {
		$rl = Responsive_Lightbox();
		$options = [
			'all' => __( 'All Media Providers', 'responsive-lightbox' )
		];

		if ( isset( $rl->remote_library ) ) {
			$providers = $rl->remote_library->get_providers();
			$active_providers = $rl->remote_library->get_active_providers();

			foreach ( $active_providers as $provider ) {
				if ( isset( $providers[$provider]['name'] ) ) {
					$options[$provider] = $providers[$provider]['name'];
				}
			}
		}

		return [
			'attachments' => [
				'title' => '',
				'type' => 'media_preview',
				'default' => [
					'exclude' => []
				],
				'preview' => [
					'pagination' => true,
					'draggable' => false,
					'editable' => false,
					'removable' => false,
					'changeable' => false
				]
			],
			'media_search' => [
				'title' => __( 'Search String', 'responsive-lightbox' ),
				'type' => 'text',
				'description' => __( 'Enter the search phrase.', 'responsive-lightbox' ),
				'default' => ''
			],
			'media_provider' => [
				'title' => __( 'Media Provider', 'responsive-lightbox' ),
				'type' => 'select',
				'description' => __( 'Select which remote library should be used.', 'responsive-lightbox' ),
				'default' => 'all',
				'options' => $options
			],
			'response_data' => [
				'title' => '',
				'type' => 'hidden',
				'description' => '',
				'default' => '',
				'callback' => [ $rl->remote_library, 'remote_library_response_data' ]
			]
		];
	}
}
