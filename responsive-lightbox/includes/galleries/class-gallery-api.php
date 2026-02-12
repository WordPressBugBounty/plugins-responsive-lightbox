<?php
/**
 * Responsive Lightbox Gallery Settings API
 *
 * Core class for integrating Settings API patterns with gallery post meta.
 * Handles rendering, validation, and storage for gallery settings tabs.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_API class.
 *
 * Provides Settings API-like interface for gallery post meta settings.
 * Manages tab registration, rendering, and data flow.
 *
 * @class Responsive_Lightbox_Gallery_API
 */
class Responsive_Lightbox_Gallery_API {

	/**
	 * Registered gallery tabs.
	 *
	 * @var array
	 */
	private $tabs = [];

	/**
	 * Adapter-owned tab metadata (labels and descriptions).
	 *
	 * @var array
	 */
	private $tab_meta = [
		'images' => [
			'label' => 'Images',
			'description' => 'The settings below adjust the contents of the gallery.'
		],
		'config' => [
			'label' => 'Config',
			'description' => 'The settings below allow you to select a gallery type and adjust the gallery options.'
		],
		'design' => [
			'label' => 'Design',
			'description' => 'The settings below adjust the gallery design options.'
		],
		'paging' => [
			'label' => 'Paging',
			'description' => 'The settings below adjust the gallery pagination options.'
		],
		'lightbox' => [
			'label' => 'Lightbox',
			'description' => 'The settings below adjust the lightbox options.'
		],
		'misc' => [
			'label' => 'Misc',
			'description' => 'The settings below adjust miscellaneous options.'
		]
	];

	/**
	 * Class constructor.
	 *
	 * Initializes the gallery settings system.
	 *
	 * @return void
	 */
	public function __construct() {
		// Load gallery settings classes
		$this->load_gallery_settings_classes();

		// Hook into gallery rendering - use higher priority to run after default
		add_action( 'add_meta_boxes_rl_gallery', [ $this, 'replace_meta_boxes' ], 20 );

		// Enqueue assets for gallery settings
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Get normalized Images menu items from adapter.
	 *
	 * Returns menu items with labels, disabled states, and reasons from the Images tab class.
	 * Single source of truth for Images menu metadata in the adapter.
	 *
	 * @return array Normalized menu items array.
	 */
	public function get_images_menu_items() {
		if ( isset( $this->tabs['images'] ) && method_exists( $this->tabs['images'], 'get_menu_items' ) ) {
			return $this->tabs['images']->get_menu_items();
		}
		return [];
	}

	/**
	 * Load gallery settings classes.
	 *
	 * Includes all gallery settings tab classes and registers them.
	 *
	 * @return void
	 */
	private function load_gallery_settings_classes() {
		$classes = [
			'includes/settings/class-settings-base.php',
			'includes/galleries/class-gallery-field-provider.php',
			'includes/galleries/class-gallery-base.php',
			'includes/galleries/class-gallery-images.php',
			'includes/galleries/class-gallery-config.php',
			'includes/galleries/class-gallery-design.php',
			'includes/galleries/class-gallery-paging.php',
			'includes/galleries/class-gallery-lightbox.php',
			'includes/galleries/class-gallery-misc.php',
		];

		foreach ( $classes as $class_file ) {
			$file_path = RESPONSIVE_LIGHTBOX_PATH . $class_file;
			if ( file_exists( $file_path ) ) {
				include_once( $file_path );
			}
		}

		// Register tab classes
		$this->tabs = [
			'images' => new Responsive_Lightbox_Gallery_Images(),
			'paging' => new Responsive_Lightbox_Gallery_Paging(),
			'misc' => new Responsive_Lightbox_Gallery_Misc(),
			'design' => new Responsive_Lightbox_Gallery_Design(),
			'lightbox' => new Responsive_Lightbox_Gallery_Lightbox(),
			'config' => new Responsive_Lightbox_Gallery_Config(),
		];

		// Allow filtering for extensions
		$this->tabs = apply_filters( 'rl_gallery_settings_tabs', $this->tabs );
	}

	/**
	 * Replace default meta boxes with Settings API-based rendering.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function replace_meta_boxes( $post ) {
		// Determine active tab for visibility classes
		$active_tab = isset( $_GET['rl_active_tab'] ) ? sanitize_key( $_GET['rl_active_tab'] ) : '';
		$galleries = Responsive_Lightbox()->galleries;
		$all_tabs = $galleries ? (array) $galleries->get_data( 'tabs' ) : [];
		$active_tab = ! empty( $active_tab ) && array_key_exists( $active_tab, $all_tabs ) ? $active_tab : 'images';

		// Use registered adapter tabs as the enabled set (deterministic, no rollout filter)
		foreach ( $this->tabs as $tab_id => $tab_class ) {
			$tab_data = $this->build_tab_data( $tab_id, $tab_class );

			// Remove the specific default meta box for this tab
			remove_meta_box( 'responsive-gallery-' . $tab_id, 'rl_gallery', 'responsive_lightbox_metaboxes' );

			// Add wrapper class filter
			add_filter( 'postbox_classes_rl_gallery_responsive-gallery-' . $tab_id, [ $galleries, 'add_settings_wrapper_class' ] );

			// Add visibility class filter based on active tab
			if ( $active_tab === $tab_id ) {
				add_filter( 'postbox_classes_rl_gallery_responsive-gallery-' . $tab_id, [ $galleries, 'display_metabox' ] );
			} else {
				add_filter( 'postbox_classes_rl_gallery_responsive-gallery-' . $tab_id, [ $galleries, 'hide_metabox' ] );
			}

			// Add new Settings API-based meta box
			add_meta_box(
				'responsive-gallery-' . $tab_id,
				$tab_data['label'],
				[ $this, 'render_meta_box' ],
				'rl_gallery',
				'responsive_lightbox_metaboxes',
				'high',
				[ 'tab_id' => $tab_id, 'tab_data' => $tab_data ]
			);
		}
	}

	/**
	 * Build adapter-owned tab metadata for rendering.
	 *
	 * @param string $tab_id Tab ID.
	 * @param Responsive_Lightbox_Gallery_Base $tab_class Tab class instance.
	 * @return array Tab metadata.
	 */
	private function build_tab_data( $tab_id, $tab_class ) {
		$meta = isset( $this->tab_meta[$tab_id] ) ? $this->tab_meta[$tab_id] : [];
		$label = $meta['label'] ?? $tab_id;
		if ( method_exists( $tab_class, 'get_tab_label' ) ) {
			$label = $tab_class->get_tab_label();
		} else {
			$label = __( $label, 'responsive-lightbox' );
		}
		$description = '';
		if ( ! empty( $meta['description'] ) ) {
			$description = __( $meta['description'], 'responsive-lightbox' );
		}

		$tab_data = [
			'label' => $label,
			'description' => $description
		];

		if ( $tab_id === 'images' ) {
			$images_menu_items = $this->get_images_menu_items();
			$menu_items = [];
			foreach ( $images_menu_items as $menu_key => $menu_data ) {
				$menu_items[$menu_key] = $menu_data['label'] ?? $menu_key;
			}
			if ( empty( $menu_items ) ) {
				$menu_items = [ 'media' => __( 'Media Library', 'responsive-lightbox' ) ];
			}
			$tab_data['menu_items'] = $menu_items;
		}

		if ( method_exists( $tab_class, 'get_tab_data' ) ) {
			$tab_fields = $tab_class->get_tab_data();
			if ( isset( $tab_fields['menu_items'] ) && is_array( $tab_fields['menu_items'] ) ) {
				$tab_data['menu_items'] = $tab_fields['menu_items'];
			}
		}

		return $tab_data;
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post $post Post object.
	 * @param array $args Callback args.
	 * @return void
	 */
	public function render_meta_box( $post, $args ) {
		$tab_id = $args['args']['tab_id'];
		$tab_data = $args['args']['tab_data'];

		if ( ! isset( $this->tabs[$tab_id] ) ) {
			return;
		}

		$tab_class = $this->tabs[$tab_id];
		$saved_data = $this->get_saved_data( $post->ID, $tab_id );

		// Wrap with PicoCSS settings wrapper
		echo '<div class="rl-settings-form" data-settings-prefix="rl">';

		// Preserve legacy hidden input used by admin-galleries.js helpers.
		if ( $tab_id === 'images' ) {
			$active_tab = isset( $_GET['rl_active_tab'] ) ? sanitize_key( $_GET['rl_active_tab'] ) : '';
			if ( $active_tab === '' ) {
				$active_tab = 'images';
			}

			echo '<input type="hidden" name="rl_active_tab" value="' . esc_attr( $active_tab ) . '" />';
		}

		// Render tab description
		if ( ! empty( $tab_data['description'] ) ) {
			echo '<p class="description">' . esc_html( $tab_data['description'] ) . '</p>';
		}

		// Handle menu items (sub-navigation)
		if ( ! empty( $tab_data['menu_items'] ) ) {
			$this->render_menu_navigation( $tab_id, $tab_data, $saved_data, $post->ID );
		} else {
			$this->render_tab_fields( $tab_class, $saved_data, $post->ID, $tab_id );
		}

		echo '</div>';
	}

	/**
	 * Render menu navigation specifically for Images tab using adapter-owned metadata.
	 *
	 * @param array $menu_items Menu items from adapter.
	 * @param string $current_menu_item Current active menu item.
	 * @param array $saved_data Saved data.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_images_menu_navigation( $menu_items, $current_menu_item, $saved_data, $post_id ) {
		// Menu navigation - use radio buttons for images tab
		// CRITICAL: rl-gallery-tab-menu-images is required by admin-galleries.js for menu item detection
		// rl-gallery-menu-nav provides base styling (margin/border)
		echo '<div class="rl-gallery-tab-menu rl-gallery-tab-menu-images rl-gallery-menu-nav rl-gallery-menu-nav-radio">';
		
		foreach ( $menu_items as $menu_item => $item_data ) {
			$label = $item_data['label'] ?? $menu_item;
			$checked = ( $menu_item === $current_menu_item ) ? ' checked' : '';
			$disabled = $item_data['disabled'] ?? false;
			$title = $disabled ? ( $item_data['disabled_reason'] ?? '' ) : '';
			
			$id = 'rl-menu-images-' . $menu_item;
			$disabled_attr = $disabled ? ' disabled="disabled"' : '';
			$title_attr = $title && $disabled ? ' title="' . esc_attr( $title ) . '"' : '';
			$label_class_attr = $disabled ? ' class="rl-disabled"' : '';
			
			echo '<input type="radio" class="rl-gallery-tab-menu-item" name="rl_gallery[images][menu_item]" value="' . esc_attr( $menu_item ) . '"' . $checked . $disabled_attr . ' id="' . esc_attr( $id ) . '" />';
			echo '<label for="' . esc_attr( $id ) . '"' . $label_class_attr . $title_attr . '>' . esc_html( $label ) . '</label>';
		}
		
		// Add spinner for Images tab AJAX loading
		if ( $this->should_show_spinner( 'images' ) ) {
			echo '<span class="spinner" style="display: none;"></span>';
		}
		
		echo '</div>';

		// Render content for current menu item
		if ( isset( $this->tabs['images'] ) ) {
			echo '<div class="rl-gallery-tab-content">';
			
			$content_class = 'rl-gallery-tab-inside rl-gallery-tab-inside-images-' . esc_attr( $current_menu_item );
			
			// Add loading class if menu item is disabled
			$menu_item_state = $this->get_menu_item_state( 'images', $current_menu_item );
			if ( $menu_item_state['disabled'] ) {
				$content_class .= ' rl-loading-content';
			}
			
			echo '<div class="' . esc_attr( $content_class ) . '">';
			$this->render_tab_fields( $this->tabs['images'], $saved_data, $post_id, 'images', $current_menu_item );
			echo '</div>';
			
			echo '</div>'; // .rl-gallery-tab-content
		}
	}

	/**
	 * Get menu item disabled state and reason for a tab.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return array Array with 'disabled' and 'disabled_reason' keys.
	 */
	private function get_menu_item_state( $tab_id, $menu_item ) {
		$disabled = false;
		$disabled_reason = '';

		// For images tab, delegate to images class for adapter-owned logic
		if ( $tab_id === 'images' && isset( $this->tabs['images'] ) ) {
			$disabled = $this->tabs['images']->is_menu_item_disabled( $menu_item );
			$disabled_reason = $this->tabs['images']->get_menu_item_disabled_reason( $menu_item );
		}

		return [ 'disabled' => $disabled, 'disabled_reason' => $disabled_reason ];
	}

	/**
	 * Get menu item title for display (used for config tab default labels).
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param array $tab_data Tab configuration.
	 * @return string Title string.
	 */
	private function get_menu_item_title( $tab_id, $menu_item, $tab_data ) {
		$title = '';

		// Config tab: add default gallery type label
		if ( $tab_id === 'config' && $menu_item === 'default' ) {
			$rl = Responsive_Lightbox();
			$builder_gallery = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
			if ( isset( $tab_data['menu_items'][$builder_gallery] ) ) {
				$title = ' (' . esc_html( $tab_data['menu_items'][$builder_gallery] ) . ')';
			}
		}

		return $title;
	}

	/**
	 * Check if a tab should show a spinner (AJAX loading indicator).
	 *
	 * @param string $tab_id Tab ID.
	 * @return bool Whether to show spinner.
	 */
	private function should_show_spinner( $tab_id ) {
		return $tab_id === 'images';
	}

	/**
	 * Render menu navigation for tabs with sub-items.
	 *
	 * @param string $tab_id Tab ID.
	 * @param array $tab_data Tab configuration.
	 * @param array $saved_data Saved data.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_menu_navigation( $tab_id, $tab_data, $saved_data, $post_id ) {
		// For Images tab, use adapter-owned menu metadata exclusively
		if ( $tab_id === 'images' ) {
			$images_menu_items = $this->get_images_menu_items();
			if ( empty( $images_menu_items ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Images tab menu metadata is unavailable. Please refresh the page or check the adapter configuration.', 'responsive-lightbox' ) . '</p></div>';
				return;
			}
			$current_menu_item = isset( $_GET['rl_menu_item'] ) ? sanitize_key( $_GET['rl_menu_item'] ) : ( $saved_data['menu_item'] ?? key( $images_menu_items ) );
			
			// Normalize current menu item using Images class method
			if ( isset( $this->tabs[$tab_id] ) && method_exists( $this->tabs[$tab_id], 'normalize_menu_item' ) ) {
				$current_menu_item = $this->tabs[$tab_id]->normalize_menu_item( $current_menu_item );
			}
			
			$this->render_images_menu_navigation( $images_menu_items, $current_menu_item, $saved_data, $post_id );
			return;
		}

		// For other tabs, use legacy tab_data approach
		$current_menu_item = isset( $_GET['rl_menu_item'] ) ? sanitize_key( $_GET['rl_menu_item'] ) : ( $saved_data['menu_item'] ?? key( $tab_data['menu_items'] ) );

		// Ensure the current menu item exists in the available menu items
		if ( ! isset( $tab_data['menu_items'][$current_menu_item] ) ) {
			$current_menu_item = key( $tab_data['menu_items'] );
		}

		// Menu navigation - use radio buttons for config tab
		$nav_class = 'rl-gallery-tab-menu rl-gallery-tab-menu-' . sanitize_html_class( $tab_id );
		if ( $tab_id === 'config' ) {
			$nav_class .= ' rl-gallery-menu-nav-radio';
			echo '<div class="' . esc_attr( $nav_class ) . '">';
			
			$rl = Responsive_Lightbox();
			
			foreach ( $tab_data['menu_items'] as $menu_item => $label ) {
				$checked = ( $menu_item === $current_menu_item ) ? ' checked' : '';
				$menu_item_state = $this->get_menu_item_state( $tab_id, $menu_item );
				$disabled = $menu_item_state['disabled'];
				$title = $this->get_menu_item_title( $tab_id, $menu_item, $tab_data );
				
				// Override title with disabled reason if disabled
				if ( $disabled ) {
					$title = $menu_item_state['disabled_reason'];
				}
				
				$id = 'rl-menu-' . $tab_id . '-' . $menu_item;
				$disabled_attr = $disabled ? ' disabled="disabled"' : '';
				$title_attr = $title && $disabled ? ' title="' . esc_attr( $title ) . '"' : '';
				
				echo '<input type="radio" class="rl-gallery-tab-menu-item" name="rl_gallery[' . esc_attr( $tab_id ) . '][menu_item]" value="' . esc_attr( $menu_item ) . '"' . $checked . $disabled_attr . ' id="' . esc_attr( $id ) . '" />';
				// For disabled items, title is tooltip only; for config default, title is visual label suffix
				echo '<label for="' . esc_attr( $id ) . '"' . $title_attr . '>' . esc_html( $label ) . ( ! $disabled ? $title : '' ) . '</label>';
			}
			
			echo '</div>';
		} else {
			echo '<div class="' . esc_attr( $nav_class ) . '">';
			foreach ( $tab_data['menu_items'] as $menu_item => $label ) {
				$active_class = ( $menu_item === $current_menu_item ) ? ' nav-tab-active' : '';
				$url = add_query_arg( [ 'rl_active_tab' => $tab_id, 'rl_menu_item' => $menu_item ] );
				echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</div>';
		}

		// Render fields for current menu item
		if ( isset( $this->tabs[$tab_id] ) ) {
			$tab_class = $this->tabs[$tab_id];
			if ( $tab_id === 'config' ) {
				// For config tab, render all menu items' content for radio switching
				foreach ( $tab_data['menu_items'] as $menu_item => $label ) {
					$display_style = ( $menu_item === $current_menu_item ) ? '' : ' style="display: none;"';
					echo '<div class="rl-gallery-tab-inside rl-gallery-tab-inside-' . esc_attr( $tab_id ) . '-' . esc_attr( $menu_item ) . '"' . $display_style . '>';
					$this->render_tab_fields( $tab_class, $saved_data, $post_id, $tab_id, $menu_item );
					echo '</div>';
				}
			} else {
				// For other tabs, wrap content with .rl-gallery-tab-content for AJAX target
				echo '<div class="rl-gallery-tab-content">';
				
				$content_class = 'rl-gallery-tab-inside rl-gallery-tab-inside-' . esc_attr( $tab_id ) . '-' . esc_attr( $current_menu_item );
				
				echo '<div class="' . esc_attr( $content_class ) . '">';
				$this->render_tab_fields( $tab_class, $saved_data, $post_id, $tab_id, $current_menu_item );
				echo '</div>';
				
				echo '</div>'; // .rl-gallery-tab-content
			}
		}
	}

	/**
	 * Render tab fields.
	 *
	 * @param Responsive_Lightbox_Gallery_Base $tab_class Tab class instance.
	 * @param array $saved_data Saved data.
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return void
	 */
	private function render_tab_fields( $tab_class, $saved_data, $post_id, $tab_id, $menu_item = '' ) {
		// Get tab data, passing menu_item for tabs that support it
		$tab_data = $tab_class->get_tab_data( $menu_item );

		$fields = $tab_data['options'] ?? $tab_data['fields'] ?? $tab_data;

		if ( $tab_id === 'images' ) {
			if ( method_exists( $tab_class, 'normalize_menu_item' ) ) {
				$menu_item = $tab_class->normalize_menu_item( $menu_item );
			} elseif ( $menu_item === '' ) {
				$menu_item = 'media';
			}
		}

		// Default menu_item to 'options' for legacy compatibility
		if ( empty( $menu_item ) ) {
			$menu_item = 'options';
		}

		// For config tab, fields are already flattened by gallery type
		// For other tabs, check if fields are nested under menu_item
		if ( $tab_id !== 'config' && isset( $fields[$menu_item] ) ) {
			$fields = $fields[$menu_item];
		}

		// Defensive: Ensure filter result is valid array
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return;
		}

		// Start output buffering to capture HTML for legacy content filter
		ob_start();

		echo '<table class="form-table rl-galleries-table">';

		// Render hidden menu_item input for legacy save compatibility (skip for config tab as radios provide the value)
		if ( $menu_item && $tab_id !== 'config' ) {
			$menu_item_name = sprintf( 'rl_gallery[%s][menu_item]', $tab_id );
			echo '<input type="hidden" name="' . esc_attr( $menu_item_name ) . '" value="' . esc_attr( $menu_item ) . '" />';
		}

		foreach ( $fields as $field_key => $field ) {
			// Defensive: Skip malformed field entries from filtered data BEFORE any array access
			if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
				continue;
			}

			$value = $this->resolve_field_value( $field_key, $field, $saved_data, $tab_id, $menu_item );

			// For multiple fields, build resolved value map for subfields
			if ( $field['type'] === 'multiple' && ! empty( $field['fields'] ) ) {
				$resolved_values = [];
				$global_settings = $this->get_context_global_settings( $tab_id, $menu_item );
				$legacy_defaults = $this->get_context_defaults( $tab_id, $menu_item );

				foreach ( $field['fields'] as $subfield_key => $subfield ) {
					// Precedence: global locked -> saved data -> field default -> legacy defaults
					if ( isset( $subfield['is_global_locked'] ) && $subfield['is_global_locked'] ) {
						$resolved_values[$subfield_key] = $global_settings[$subfield_key] ?? $subfield['default'] ?? $legacy_defaults[$subfield_key] ?? '';
					} else {
						$resolved_values[$subfield_key] = $this->resolve_field_value( $subfield_key, $subfield, $saved_data, $tab_id, $menu_item );
					}
				}
				$value = $resolved_values;
			} else {
				// For disabled fields in config tab, use global settings value
				if ( $tab_id === 'config' && isset( $field['disabled'] ) && $field['disabled'] ) {
					$rl = Responsive_Lightbox();
					$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
					$default_gallery_key = $default_gallery_type . '_gallery';
					$global_settings = $rl->options[$default_gallery_key] ?? [];
					$value = $global_settings[$field_key] ?? $value;
				}
			}

			$this->render_field_row( $field_key, $field, $value, $post_id, $tab_id, $menu_item );
		}

		echo '</table>';
		
		// Capture rendered HTML for legacy content filter
		$html = ob_get_clean();
		
		echo $html;
	}

	/**
	 * Resolve field value with fallback chain.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param array $saved_data Saved gallery data.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return mixed Resolved value.
	 */
	private function resolve_field_value( $field_key, $field, $saved_data, $tab_id, $menu_item ) {
		$legacy_defaults = $this->get_context_defaults( $tab_id, $menu_item );
		$global_settings = $this->get_context_global_settings( $tab_id, $menu_item );

		// For config tab, check menu_item subarray first
		if ( $tab_id === 'config' && $menu_item && isset( $saved_data[$menu_item] ) ) {
			$value = $saved_data[$menu_item][$field_key] ?? null;
			if ( $value !== null ) {
				return $value;
			}
		}

		// Check menu_item-specific saved data (used by Images tab and legacy-compatible structures).
		if ( $menu_item && isset( $saved_data[$menu_item] ) && is_array( $saved_data[$menu_item] ) ) {
			$value = $saved_data[$menu_item][$field_key] ?? null;
			if ( $value !== null ) {
				return $value;
			}
		}

		// Check saved options
		$value = $saved_data['options'][$field_key] ?? null;
		if ( $value !== null ) {
			return $value;
		}

		// Check field default
		if ( isset( $field['default'] ) ) {
			return $field['default'];
		}

		// Check legacy defaults
		if ( isset( $legacy_defaults[$field_key] ) ) {
			return $legacy_defaults[$field_key];
		}

		// For disabled fields in config tab, use global settings
		if ( $tab_id === 'config' && isset( $field['disabled'] ) && $field['disabled'] ) {
			return $global_settings[$field_key] ?? '';
		}

		return '';
	}

	/**
	 * Get context-aware defaults for a tab and menu item.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return array Defaults array.
	 */
	private function get_context_defaults( $tab_id, $menu_item ) {
		$rl = Responsive_Lightbox();

		if ( $tab_id === 'config' ) {
			if ( $menu_item === 'default' ) {
				// For Global config, use builder gallery defaults
				$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
				$default_gallery_key = $default_gallery_type . '_gallery';
				return $rl->defaults[$default_gallery_key] ?? [];
			} else {
				// For specific gallery types, use their own defaults
				$gallery_key = $menu_item . '_gallery';
				return $rl->defaults[$gallery_key] ?? [];
			}
		}

		// For other tabs, use builder gallery defaults as fallback
		$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
		$default_gallery_key = $default_gallery_type . '_gallery';
		return $rl->defaults[$default_gallery_key] ?? [];
	}

	/**
	 * Get context-aware global settings for a tab and menu item.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return array Global settings array.
	 */
	private function get_context_global_settings( $tab_id, $menu_item ) {
		$rl = Responsive_Lightbox();

		if ( $tab_id === 'config' ) {
			// Always use builder gallery global settings for config tab
			$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
			$default_gallery_key = $default_gallery_type . '_gallery';
			return $rl->options[$default_gallery_key] ?? [];
		}

		// For other tabs, use builder gallery global settings
		$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
		$default_gallery_key = $default_gallery_type . '_gallery';
		return $rl->options[$default_gallery_key] ?? [];
	}

	/**
	 * Render individual field row.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param mixed $value Current value.
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return void
	 */
	private function render_field_row( $field_key, $field, $value, $post_id, $tab_id, $menu_item ) {
		// Skip hidden fields
		if ( isset( $field['hidden'] ) && $field['hidden'] ) {
			return;
		}

		$name = $this->get_field_name( $tab_id, $menu_item, $field_key );
		$id = sanitize_key( $name );
		$field_type = isset( $field['type'] ) ? $field['type'] : '';
		$is_images_full_width = ( $tab_id === 'images' && in_array( $field_type, [ 'media_library', 'media_preview' ], true ) );

		// For notice fields, use full-width row without label
		if ( isset( $field['type'] ) && $field['type'] === 'notice' ) {
			echo '<tr class="rl-gallery-field-disabled-notice">';
			echo '<td colspan="2">';
		} else {
			$row_classes = [];
			$row_classes[] = 'rl-gallery-field-' . sanitize_html_class( $tab_id . '-' . $menu_item . '-' . $field_key );
			$row_classes[] = 'rl-gallery-field-' . sanitize_html_class( $field_type );
			if ( ! empty( $field['disabled'] ) ) {
				$row_classes[] = 'rl-disabled';
				$row_classes[] = 'rl-gallery-field-disabled';
			}

			$row_attrs = ' class="' . esc_attr( implode( ' ', array_filter( $row_classes ) ) ) . '"';
			$row_attrs .= ' data-field_type="' . esc_attr( $field_type ) . '"';
			$row_attrs .= ' data-field_name="' . esc_attr( $field_key ) . '"';

			echo '<tr' . $row_attrs . '>';
			if ( $is_images_full_width ) {
				echo '<td colspan="2" class="rl-colspan">';
			} else {
				echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['title'] ?? $field_key ) . '</label></th>';
				echo '<td>';
			}
		}

		$use_api_wrapper = ! $is_images_full_width && (bool) apply_filters( 'rl_gallery_settings_use_api_wrapper', true, $tab_id, $field_key, $field );
		if ( $use_api_wrapper ) {
			$wrapper_attrs = $this->get_field_wrapper_attrs( $field, $tab_id, $menu_item, $field_key );
			echo '<div' . $wrapper_attrs . '>';
		}

		// Use tab class to render field.
		$tab_class = isset( $this->tabs[$tab_id] ) ? $this->tabs[$tab_id] : null;
		if ( $tab_class ) {

			if ( $field['type'] === 'multiple' && ! empty( $field['fields'] ) ) {
				$resolved_values = is_array( $value ) ? $value : [];

				foreach ( $field['fields'] as $subfield_key => $subfield ) {
					$subvalue = $resolved_values[$subfield_key] ?? $subfield['default'] ?? '';

					// Apply disabled flag to subfield if parent is locked
					$subfield_for_render = $subfield;
					if ( isset( $field['is_global_locked'] ) && $field['is_global_locked'] ) {
						$subfield_for_render['disabled'] = true;
					}

					echo '<div class="rl-gallery-subfield">';
					echo $tab_class->render_field_html( $subfield_key, $subfield_for_render, $subvalue, $post_id, $tab_id, $menu_item );
					echo '</div> ';
				}
			} else {
				echo $tab_class->render_field_html( $field_key, $field, $value, $post_id, $tab_id, $menu_item );
			}
		}

		// Field description
		if ( ! empty( $field['description'] ) && ! $is_images_full_width ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}

		if ( $use_api_wrapper ) {
			echo '</div>';
		}

		if ( isset( $field['type'] ) && $field['type'] === 'notice' ) {
			echo '</td>';
		} else {
			echo '</td>';
		}
		echo '</tr>';
	}

	/**
	 * Build Settings API-like wrapper attributes for a field.
	 *
	 * @param array  $field Field configuration.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param string $field_key Field key.
	 * @return string Wrapper attributes string.
	 */
	private function get_field_wrapper_attrs( $field, $tab_id, $menu_item, $field_key ) {
		$wrapper_classes = [ 'rl-field', 'rl-field-type-' . sanitize_html_class( $field['type'] ) ];

		if ( $field['type'] === 'color_picker' ) {
			$wrapper_classes[] = 'rl-field-type-color';
		}

		if ( ! empty( $field['class'] ) ) {
			$wrapper_classes[] = $field['class'];
		}

		if ( ! empty( $field['disabled'] ) ) {
			$wrapper_classes[] = 'rl-disabled';
		}

		$wrapper_id = 'rl-gallery-' . $tab_id . '-' . ( $menu_item ?: 'options' ) . '-' . $field_key . '-setting';
		$wrapper_id = sanitize_html_class( str_replace( '_', '-', $wrapper_id ) );

		return ' id="' . esc_attr( $wrapper_id ) . '" class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';
	}

	/**
	 * Get field name for form input.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param string $field_key Field key.
	 * @return string Field name.
	 */
	private function get_field_name( $tab_id, $menu_item, $field_key ) {
		if ( $menu_item ) {
			return sprintf( 'rl_gallery[%s][%s][%s]', $tab_id, $menu_item, $field_key );
		}
		return sprintf( 'rl_gallery[%s][%s]', $tab_id, $field_key );
	}

	/**
	 * Get saved data for a tab.
	 *
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @return array Saved data.
	 */
	private function get_saved_data( $post_id, $tab_id ) {
		$data = get_post_meta( $post_id, '_rl_' . $tab_id, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Enqueue assets for gallery settings.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'rl_gallery' || $screen->base !== 'post' ) {
			return;
		}

		// Enqueue admin-galleries styles (includes merged admin-theme styles)
		wp_enqueue_style(
			'responsive-lightbox-admin-galleries',
			RESPONSIVE_LIGHTBOX_URL . '/css/admin-galleries.css',
			[],
			Responsive_Lightbox()->defaults['version']
		);
	}

	/**
	 * Check if a tab is managed by the adapter.
	 *
	 * @param string $tab_id Tab ID.
	 * @return bool True if managed by adapter.
	 */
	public function is_managed_tab( $tab_id ) {
		return isset( $this->tabs[$tab_id] );
	}

	/**
	 * Render menu content for AJAX requests.
	 *
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return string Rendered HTML.
	 */
	public function render_menu_content( $post_id, $tab_id, $menu_item ) {
		if ( ! isset( $this->tabs[$tab_id] ) ) {
			return '';
		}

		$tab_class = $this->tabs[$tab_id];
		$saved_data = $this->get_saved_data( $post_id, $tab_id );

		// For Images tab, normalize menu_item using adapter-owned logic.
		if ( $tab_id === 'images' ) {
			// Use adapter-owned normalize_menu_item method.
			if ( isset( $this->tabs[$tab_id] ) && method_exists( $this->tabs[$tab_id], 'normalize_menu_item' ) ) {
				$menu_item = $this->tabs[$tab_id]->normalize_menu_item( $menu_item );
			} else {
				// Fallback only if adapter class unavailable (should not happen in normal operation).
				$menu_item = 'media';
			}
		}

		// Start output buffering
		ob_start();

		// Render only the inner content for .rl-gallery-tab-content replacement
		// Contract: return only <div class="rl-gallery-tab-inside rl-gallery-tab-inside-{tab_id}-{menu_item}">...</div>
		echo '<div class="rl-gallery-tab-inside rl-gallery-tab-inside-' . esc_attr( $tab_id ) . '-' . esc_attr( $menu_item ) . '">';
		$this->render_tab_fields( $tab_class, $saved_data, $post_id, $tab_id, $menu_item );
		echo '</div>';

		// Get the rendered HTML (filter already applied in render_tab_fields)
		return ob_get_clean();
	}

	/**
	 * Get tab field definitions for compatibility with $rl->galleries->get_data('fields').
	 *
	 * Returns the full field structure for a tab in the same format as legacy $this->fields,
	 * enabling seamless access via get_data('fields') from frontend code.
	 *
	 * @since 2.7.1
	 * @param string $tab_id Tab ID.
	 * @return array Field definitions in legacy format.
	 */
	public function get_tab_definition( $tab_id ) {
		if ( ! isset( $this->tabs[$tab_id] ) ) {
			return [];
		}

		$tab_class = $this->tabs[$tab_id];
		$tab_data = $tab_class->get_tab_data();

		// Images tab returns legacy menu-item field structure directly.
		if ( $tab_id === 'images' && is_array( $tab_data ) ) {
			return $tab_data;
		}

		// Return in legacy format: [ 'options' => [ field_key => field_def, ... ] ]
		// Tab classes may use 'sections' wrapper or direct 'options' key.
		if ( isset( $tab_data['sections'] ) && is_array( $tab_data['sections'] ) ) {
			// Find the primary section (usually 'options' or first section)
			$primary_section = isset( $tab_data['sections']['options'] ) ? 'options' : key( $tab_data['sections'] );

			if ( isset( $tab_data['sections'][$primary_section]['fields'] ) ) {
				return [ $primary_section => $tab_data['sections'][$primary_section]['fields'] ];
			}
		}

		// Direct format: tab data has 'options' key with field definitions
		if ( isset( $tab_data['options'] ) && is_array( $tab_data['options'] ) ) {
			return [ 'options' => $tab_data['options'] ];
		}

		return [];
	}

	/**
	 * Get tab fields for save pipeline.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return array
	 */
	public function get_tab_fields_for_save( $tab_id, $menu_item = '' ) {
		if ( ! isset( $this->tabs[$tab_id] ) ) {
			return [];
		}

		$tab_class = $this->tabs[$tab_id];
		$tab_data = $tab_class->get_tab_data( $menu_item );
		$fields = $tab_data['options'] ?? $tab_data['fields'] ?? $tab_data;

		if ( $tab_id === 'images' ) {
			if ( method_exists( $tab_class, 'normalize_menu_item' ) ) {
				$menu_item = $tab_class->normalize_menu_item( $menu_item );
			} elseif ( $menu_item === '' ) {
				$menu_item = 'media';
			}
		}

		if ( empty( $menu_item ) ) {
			$menu_item = 'options';
		}

		if ( $tab_id !== 'config' && isset( $fields[$menu_item] ) ) {
			$fields = $fields[$menu_item];
		}

		return is_array( $fields ) ? $fields : [];
	}

	/**
	 * Validate sanitized data for a tab.
	 *
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param array $input Sanitized data for the menu item.
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function validate_tab_data( $tab_id, $menu_item, $input, $post_id = 0 ) {
		if ( ! isset( $this->tabs[$tab_id] ) ) {
			return is_array( $input ) ? $input : [];
		}

		$tab_class = $this->tabs[$tab_id];
		if ( method_exists( $tab_class, 'validate_tab' ) ) {
			$input = $tab_class->validate_tab( $input, $tab_id, $menu_item, $post_id );
		} elseif ( method_exists( $tab_class, 'validate' ) ) {
			$input = $tab_class->validate( $input );
		}

		if ( ! is_array( $input ) ) {
			$input = [];
		}

		return apply_filters( 'rl_gallery_validate_tab_data', $input, $tab_id, $menu_item, $post_id );
	}

}
