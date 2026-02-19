<?php
/**
 * Responsive Lightbox Gallery Settings Base Class
 *
 * Abstract base class for gallery post meta settings tabs.
 * Adapts Settings API patterns for post meta storage instead of options.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Base class.
 *
 * Base class for gallery settings tabs using post meta storage.
 * Extends Settings_Base but overrides storage methods for post meta.
 *
 * @abstract
 * @class Responsive_Lightbox_Gallery_Base
 */
abstract class Responsive_Lightbox_Gallery_Base extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * Must be defined in child class.
	 *
	 * @var string
	 */
	const TAB_KEY = '';

	/**
	 * Class constructor.
	 *
	 * Registers filters for gallery settings integration.
	 *
	 * @return void
	 */
	public function __construct() {
		// provide settings data for this tab
		add_filter( 'rl_gallery_settings_tabs', [ $this, 'register_tab' ] );
	}

	/**
	 * Register this tab with the gallery settings system.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_tab( $tabs ) {
		$tabs[static::TAB_KEY] = $this;
		return $tabs;
	}

	/**
	 * Get tab data for rendering.
	 *
	 * Must be implemented by child class.
	 * Should return array with structure compatible with gallery fields.
	 *
	 * @param string $menu_item Optional menu item for tabs with sub-navigation.
	 * @return array Tab data.
	 */
	abstract public function get_tab_data( $menu_item = '' );

	/**
	 * Provide settings data for Settings API.
	 *
	 * Required by base class but not used in gallery context.
	 * Gallery tabs don't contribute to global settings.
	 *
	 * @param array $data Settings data.
	 * @return array Modified settings data.
	 */
	public function settings_data( $data ) {
		return $data;
	}

	/**
	 * Get field name for form input.
	 *
	 * Adapts Settings API field names to gallery post meta structure.
	 *
	 * @param string $tab_id Tab identifier.
	 * @param string $menu_item Menu item identifier (optional).
	 * @param string $field_key Field key.
	 * @return string Form field name.
	 */
	protected function get_field_name( $tab_id, $menu_item, $field_key ) {
		if ( $menu_item ) {
			return sprintf( 'rl_gallery[%s][%s][%s]', $tab_id, $menu_item, $field_key );
		}
		return sprintf( 'rl_gallery[%s][%s]', $tab_id, $field_key );
	}

	/**
	 * Render field HTML.
	 *
	 * Adapts Settings API field rendering for gallery context.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param mixed $value Current value.
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return string HTML output.
	 */
	public function render_field_html( $field_key, $field, $value, $post_id, $tab_id, $menu_item ) {
		$name = $this->get_field_name( $tab_id, $menu_item, $field_key );
		// Keep legacy ID contract for Images tab (admin-galleries.js relies on #rl-{tab}-{menu}-{field} selectors).
		if ( $tab_id === 'images' ) {
			$id = sanitize_html_class( 'rl-' . $tab_id . '-' . $menu_item . '-' . $field_key );
		} else {
			$id = sanitize_key( $name );
		}
		$disabled = ! empty( $field['disabled'] ) ? ' disabled="disabled"' : '';

		// Handle field types
		switch ( $field['type'] ) {
			case 'text':
			case 'color':
				$input_html = sprintf(
					'<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
					esc_attr( $field['type'] ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					$disabled
				);

				// Add append text if specified
				if ( isset( $field['append'] ) ) {
					$input_html .= '<span class="rl-field-append">' . esc_html( $field['append'] ) . '</span>';
				}

				return $input_html;

			case 'number':
				$attrs = '';
				if ( isset( $field['min'] ) ) {
					$attrs .= ' min="' . esc_attr( $field['min'] ) . '"';
				}
				if ( isset( $field['max'] ) ) {
					$attrs .= ' max="' . esc_attr( $field['max'] ) . '"';
				}
				if ( isset( $field['step'] ) ) {
					$attrs .= ' step="' . esc_attr( $field['step'] ) . '"';
				}

				$input_html = sprintf(
					'<input type="number" id="%s" name="%s" value="%s" class="small-text"%s%s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					$attrs,
					$disabled
				);

				if ( isset( $field['append'] ) ) {
					$input_html .= '<span class="rl-field-append">' . esc_html( $field['append'] ) . '</span>';
				}

				return $input_html;

			case 'textarea':
				$class = isset( $field['class'] ) ? $field['class'] : 'large-text';
				return sprintf(
					'<textarea id="%s" name="%s" class="%s"%s>%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $class ),
					$disabled,
					esc_textarea( $value )
				);

			case 'select':
				$options = '';
				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $option_value => $option_label ) {
						$options .= sprintf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $option_value ),
							selected( $value, $option_value, false ),
							esc_html( $option_label )
						);
					}
				}
				return sprintf(
					'<select id="%s" name="%s"%s>%s</select>',
					esc_attr( $id ),
					esc_attr( $name ),
					$disabled,
					$options
				);

			case 'checkbox':
				$checked_value = false;
				if ( is_array( $value ) ) {
					$checked_value = in_array( 1, $value, true ) || in_array( '1', $value, true ) || in_array( true, $value, true );
				} else {
					$checked_value = ( $value === 1 || $value === '1' || $value === true );
				}

				return sprintf(
					'<input type="checkbox" id="%s" name="%s" value="1" %s%s />',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $checked_value, true, false ),
					$disabled
				);

			case 'boolean':
				$label = ! empty( $field['label'] ) ? wp_kses_post( $field['label'] ) : '';

				$html = '';
				if ( empty( $field['disabled'] ) ) {
					$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="false" />';
				}

				$html .= '<label><input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="true" ' . checked( (bool) $value, true, false ) . $disabled . ' />' . $label . '</label>';

				return $html;

			case 'color_picker':
				$color_value = is_string( $value ) && $value !== '' ? $value : ( $field['default'] ?? '#000000' );
				$color_value = esc_attr( $color_value );
				$color_name = esc_attr( $name );
				$input_id = esc_attr( $id );
				$input_class = 'small-text rl-color-input';
				$swatch_style = ' style="background-color: ' . $color_value . ';"';

				return '<div class="rl-color-control">'
					. '<input id="' . $input_id . '" type="text" name="' . $color_name . '" value="' . $color_value . '" class="' . esc_attr( $input_class ) . '"' . $disabled . ' />'
					. '<button type="button" class="rl-color-swatch"' . $swatch_style . ' aria-label="' . esc_attr__( 'Open color picker', 'responsive-lightbox' ) . '" aria-expanded="false"' . $disabled . '></button>'
					. '<div class="rl-color-popover" aria-hidden="true"><hex-color-picker class="rl-hex-color-picker" color="' . $color_value . '"></hex-color-picker></div>'
					. '</div>';

			case 'radio':
				$radios = '';
				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					$display_type = ! empty( $field['display_type'] ) && in_array( $field['display_type'], [ 'horizontal', 'vertical' ], true )
						? $field['display_type']
						: 'horizontal';
					$group_classes = 'rl-field-group rl-radio-group ' . $display_type;
					$label_class = 'rl-' . sanitize_html_class( $tab_id . '-' . $menu_item . '-' . $field_key );
					$radios .= '<div class="' . esc_attr( $group_classes ) . '">';

					foreach ( $field['options'] as $option_value => $option_label ) {
						$option_id = 'rl-' . $tab_id . '-' . $menu_item . '-' . $field_key . '-' . $option_value;
						$option_id = sanitize_html_class( $option_id );
						$radios .= sprintf(
							'<label class="%s" for="%s"><input id="%s" type="radio" name="%s" value="%s" %s%s /> %s</label> ',
							esc_attr( $label_class ),
							esc_attr( $option_id ),
							esc_attr( $option_id ),
							esc_attr( $name ),
							esc_attr( $option_value ),
							checked( $value, $option_value, false ),
							$disabled,
							esc_html( $option_label )
						);
					}

					$radios .= '</div>';
				}
				return $radios;

			case 'multiselect':
				$options = '';
				$selected_values = is_array( $value ) ? array_map( 'strval', $value ) : [];

				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $option_value => $option_label ) {
						// Support grouped options format used by Featured -> Post Term:
						// [taxonomy] => [ 'label' => 'Taxonomy Name', 'terms' => [ term_id => term_name ] ].
						if ( is_array( $option_label ) && isset( $option_label['terms'] ) && is_array( $option_label['terms'] ) ) {
							$group_label = isset( $option_label['label'] ) && is_scalar( $option_label['label'] )
								? (string) $option_label['label']
								: (string) $option_value;
							$group_options = '';

							foreach ( $option_label['terms'] as $term_value => $term_label ) {
								if ( ! is_scalar( $term_label ) )
									continue;

								$selected = in_array( (string) $term_value, $selected_values, true ) ? 'selected="selected"' : '';
								$group_options .= sprintf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $term_value ),
									$selected,
									esc_html( (string) $term_label )
								);
							}

							if ( $group_options !== '' ) {
								$options .= sprintf(
									'<optgroup label="%s">%s</optgroup>',
									esc_attr( $group_label ),
									$group_options
								);
							}

							continue;
						}

						if ( ! is_scalar( $option_label ) ) {
							if ( is_array( $option_label ) && isset( $option_label['label'] ) && is_scalar( $option_label['label'] ) )
								$option_label = (string) $option_label['label'];
							else
								continue;
						}

						$selected = in_array( (string) $option_value, $selected_values, true ) ? 'selected="selected"' : '';
						$options .= sprintf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $option_value ),
							$selected,
							esc_html( (string) $option_label )
						);
					}
				}
				return sprintf(
					'<select class="select2" id="%s" name="%s[]" multiple="multiple" style="height: 100px;"%s>%s</select>',
					esc_attr( $id ),
					esc_attr( $name ),
					$disabled,
					$options
				);

			case 'taxonomy':
				return $this->render_taxonomy_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item, $name, $id, $disabled );

			case 'media_preview':
				return $this->render_media_preview_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item );

			case 'media_library':
				return $this->render_media_library_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item, $name );

			case 'custom':
				if ( ! empty( $field['callback'] ) && is_callable( $field['callback'] ) ) {
					$callback_args = [
						'name' => $name,
						'html_id' => $id,
						'value' => $value,
						'field' => $field,
						'field_id' => $field_key,
						'tab_id' => $tab_id,
						'menu_item' => $menu_item,
						'post_id' => $post_id
					];

					if ( ! empty( $field['callback_args'] ) && is_array( $field['callback_args'] ) )
						$callback_args = array_merge( $callback_args, $field['callback_args'] );

					return call_user_func( $field['callback'], $callback_args );
				}

				return '';

			case 'hidden':
				$hidden_input = sprintf(
					'<input type="hidden" id="%s" name="%s" value="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);

				if ( ! empty( $field['callback'] ) && is_callable( $field['callback'] ) ) {
					$callback_args = [
						'field' => $field_key,
						'tab_id' => $tab_id,
						'menu_item' => $menu_item,
						'value' => $value,
						'post_id' => $post_id
					];

					$callback_output = '';
					try {
						if ( is_array( $field['callback'] ) ) {
							$reflection = new ReflectionMethod( $field['callback'][0], $field['callback'][1] );
						} else {
							$reflection = new ReflectionFunction( $field['callback'] );
						}

						$callback_output = $reflection->getNumberOfParameters() <= 1
							? call_user_func( $field['callback'], $callback_args )
							: call_user_func( $field['callback'], $field_key, $field, $value, $post_id, $tab_id, $menu_item );
					} catch ( Exception $e ) {
						$callback_output = call_user_func( $field['callback'], $field_key, $field, $value, $post_id, $tab_id, $menu_item );
					}

					$allowed_html = [
						'span' => [
							'id' => true,
							'class' => true,
							'data-provider' => true,
							'data-name' => true,
							'data-value' => true
						]
					];

					return $hidden_input . wp_kses( (string) $callback_output, $allowed_html );
				}

				return $hidden_input;

			case 'notice':
				$content = ! empty( $field['content'] ) ? wp_kses_post( $field['content'] ) : '';
				$class = ! empty( $field['class'] ) ? ' ' . esc_attr( $field['class'] ) : '';
				return '<div class="rl-field-notice' . $class . '">' . $content . '</div>';

			default:
				// Allow add-ons to provide custom rendering for unrecognized field types.
				// Returns empty string by default; non-empty return replaces text input fallback.
				$custom_output = apply_filters( 'rl_gallery_render_field', '', $field_key, $field, $value, $post_id, $tab_id, $menu_item, $name, $id );

				if ( $custom_output !== '' ) {
					return $custom_output;
				}

				// Unknown field types: render as text input.
				return sprintf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}
	}

	/**
	 * Render a taxonomy dropdown field.
	 *
	 * Used by the Images tab 'folders' menu item for media folder selection.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param mixed $value Current value (array with 'id' and 'children' keys).
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param string $name Input name.
	 * @param string $id Input id.
	 * @param string $disabled Disabled attribute string.
	 * @return string HTML output.
	 */
	private function render_taxonomy_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item, $name, $id, $disabled ) {
		$html = '';
		$taxonomy = isset( $field['taxonomy'] ) ? $field['taxonomy'] : '';
		$value_id = is_array( $value ) && isset( $value['id'] ) ? $value['id'] : 0;
		$value_children = is_array( $value ) && isset( $value['children'] ) ? $value['children'] : false;

		if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$html = wp_dropdown_categories( [
				'orderby'			=> 'name',
				'order'				=> 'asc',
				'show_option_none'	=> __( 'Root Folder', 'responsive-lightbox' ),
				'show_option_all'	=> false,
				'show_count'		=> false,
				'hide_empty'		=> false,
				'option_none_value'	=> 0,
				'hierarchical'		=> true,
				'selected'			=> $value_id,
				'taxonomy'			=> $taxonomy,
				'hide_if_empty'		=> false,
				'echo'				=> false,
				'id'				=> $id,
				'name'				=> $name . '[id]'
			] );
		} else {
			$html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]"><option value="0">' . esc_html__( 'Root Folder', 'responsive-lightbox' ) . '</option></select> ';
		}

		if ( $disabled ) {
			$html = preg_replace( '/<select /', '<select disabled="disabled" ', $html, 1 );
		}

		if ( ! empty( $field['include_children'] ) ) {
			$children_id = esc_attr( $id . '-include-children' );

			$html .= '<label class="' . esc_attr( $id ) . '-include-children" for="' . $children_id . '"><input id="' . $children_id . '" type="checkbox" name="' . esc_attr( $name ) . '[children]" value="true" ' . checked( $value_children, true, false ) . $disabled . ' />' . esc_html__( 'Include children.', 'responsive-lightbox' ) . '</label>';
		}

		return $html;
	}

	/**
	 * Render a media preview field.
	 *
	 * Used by Images tab menu items (featured, posts, folders, remote_library)
	 * to display a gallery preview with update button and pagination.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param mixed $value Current value (array with 'exclude' key).
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @return string HTML output.
	 */
	private function render_media_preview_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item ) {
		$rl = Responsive_Lightbox();

		if ( ! isset( $rl->galleries ) )
			return '';

		$galleries = $rl->galleries;

		// set menu_item so get_gallery_images() uses the correct image source
		$galleries->set_menu_item( $menu_item );

		// get images
		$images = $galleries->get_gallery_images( $post_id );

		// get media item template
		$preview = isset( $field['preview'] ) ? $field['preview'] : [];
		$media_item_template = $galleries->get_media_item_template( $preview );

		$html = '
				<div class="rl-gallery-preview-inside">
					<a href="#" class="rl-gallery-update-preview button button-secondary">' . esc_html__( 'Update preview', 'responsive-lightbox' ) . '</a><span class="spinner" style="display: none;"></span>
					<p class="description">' . esc_html__( 'Use this button after any change of the options below to see updated gallery preview.', 'responsive-lightbox' ) . '</p>
				</div>
				<div class="rl-gallery-content">
					<ul class="rl-gallery-images rl-gallery-images-' . esc_attr( $menu_item ) . '">';

		$exclude = [];
		if ( is_array( $value ) && isset( $value['exclude'] ) ) {
			if ( is_array( $value['exclude'] ) ) {
				$exclude = $value['exclude'];
			} elseif ( is_string( $value['exclude'] ) && $value['exclude'] !== '' ) {
				$exclude = array_filter( array_map( 'trim', explode( ',', $value['exclude'] ) ), 'strlen' );
			}
		}

		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				if ( empty( $image['id'] ) ) {
					$excluded_item = $image['url'];
					$image['id'] = 0;
				} else
					$excluded_item = $image['id'];

				// get image content html
				$html .= $galleries->get_gallery_preview_image_content( $image, $tab_id, $menu_item, $field_key, $media_item_template, $exclude, $excluded_item );
			}
		}

		$html .= '
					</ul>
				</div>';

		if ( ! empty( $preview ) && isset( $preview['pagination'] ) && $preview['pagination'] )
			$html .= $galleries->get_preview_pagination();

		return $html;
	}

	/**
	 * Render a media library field.
	 *
	 * Used by the Images tab 'media' menu item for image/video selection,
	 * drag-and-drop reordering, and embed video management.
	 *
	 * @param string $field_key Field key.
	 * @param array $field Field configuration.
	 * @param mixed $value Current value (array with 'ids', 'exclude', 'embed' keys).
	 * @param int $post_id Post ID.
	 * @param string $tab_id Tab ID.
	 * @param string $menu_item Menu item ID.
	 * @param string $name Input name.
	 * @return string HTML output.
	 */
	private function render_media_library_field( $field_key, $field, $value, $post_id, $tab_id, $menu_item, $name ) {
		$rl = Responsive_Lightbox();

		if ( ! isset( $rl->galleries ) )
			return '';

		$galleries = $rl->galleries;
		$data = get_post_meta( $post_id, '_rl_images', true );
		$data = is_array( $data ) ? $data : [];

		// load images only when saved menu_item is 'media' or we're not doing an AJAX menu switch
		if ( ( ! empty( $data['menu_item'] ) && $data['menu_item'] === 'media' ) || ! ( wp_doing_ajax() && isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-menu-content' ) )
			$images = $galleries->get_gallery_images( $post_id );
		else
			$images = [];

		// get media item template
		$preview = isset( $field['preview'] ) ? $field['preview'] : [];
		$media_item_template = $galleries->get_media_item_template( $preview );

		// build buttons
		$buttons = [];
		$buttons_desc = '';

		// video support?
		if ( rl_current_lightbox_supports( 'video' ) ) {
			$buttons[] = '<a href="#" class="rl-gallery-select button button-secondary">' . __( 'Select images & videos', 'responsive-lightbox' ) . '</a>';
		} else {
			$buttons[] = '<a href="#" class="rl-gallery-select button button-secondary">' . __( 'Select images', 'responsive-lightbox' ) . '</a>';
			$buttons[] = '<a href="#" class="rl-gallery-select button button-disabled" disabled="true">' . __( 'Select images & videos', 'responsive-lightbox' ) . '</a>';
			$buttons_desc_args = [ '<a href="http://www.dfactory.co/products/fancybox-pro/" target="_blank">Fancybox Pro</a>', '<a href="http://www.dfactory.co/products/lightgallery-lightbox/" target="_blank">Lightgallery Lightbox</a>', '<a href="http://www.dfactory.co/products/lightcase-lightbox/" target="_blank">Lightcase Lightbox</a>' ];
			$buttons_desc = '<p class="description">' . wp_sprintf( __( 'HTML5 Videos and Embed Videos available only in %l.', 'responsive-lightbox' ), $buttons_desc_args ) . '</p>';
		}

		// hidden IDs input
		$ids = [];
		if ( is_array( $value ) && isset( $value['ids'] ) ) {
			if ( is_array( $value['ids'] ) ) {
				$ids = $value['ids'];
			} elseif ( is_string( $value['ids'] ) && $value['ids'] !== '' ) {
				$ids = array_filter( array_map( 'trim', explode( ',', $value['ids'] ) ), 'strlen' );
			}
		}
		$ids_value = implode( ',', $ids );

		$html = '
				<input type="hidden" class="rl-gallery-ids" name="' . esc_attr( $name ) . '[ids]" value="' . esc_attr( $ids_value ) . '">';

		// embed video support?
		if ( rl_current_lightbox_supports( [ 'youtube', 'vimeo' ], 'OR' ) )
			$buttons[] = '<a href="#" class="rl-gallery-select-videos button button-secondary">' . esc_html__( 'Embed videos', 'responsive-lightbox' ) . '</a>';
		else
			$buttons[] = '<a href="#" class="rl-gallery-select-videos button button-disabled" disabled="true">' . esc_html__( 'Embed videos', 'responsive-lightbox' ) . '</a>';

		// buttons container
		$html .= '
				<div class="rl-gallery-buttons">'
					. implode( '', $buttons )
					. $buttons_desc .
				'</div>';

		// gallery content
		$html .= '
				<div class="rl-gallery-content">
					<ul class="rl-gallery-images rl-gallery-images-media">';

		$exclude = [];
		if ( is_array( $value ) && isset( $value['exclude'] ) ) {
			if ( is_array( $value['exclude'] ) ) {
				$exclude = $value['exclude'];
			} elseif ( is_string( $value['exclude'] ) && $value['exclude'] !== '' ) {
				$exclude = array_filter( array_map( 'trim', explode( ',', $value['exclude'] ) ), 'strlen' );
			}
		}

		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				if ( $image['id'] === 0 )
					$excluded_item = $image['url'];
				else
					$excluded_item = $image['id'];

				// get image content html
				$html .= $galleries->get_gallery_preview_image_content( $image, $tab_id, $menu_item, $field_key, $media_item_template, $exclude, $excluded_item );
			}
		}

		$html .= '
					</ul>
				</div>';

		return $html;
	}

	/**
	 * Check whether a field type self-manages its description output.
	 *

	 * Sanitize fields for this tab.
	 *
	 * Uses base class sanitization but adapted for post meta context.
	 *
	 * @param array $input Input data.
	 * @param string $tab_id Tab identifier.
	 * @param string $menu_item Menu item identifier.
	 * @return array Sanitized data.
	 */
	public function sanitize_tab_fields( $input, $tab_id, $menu_item = '' ) {
		$fields = $this->get_tab_data();

		if ( $menu_item && isset( $fields[$menu_item] ) ) {
			$fields = $fields[$menu_item];
		}

		return $this->sanitize_fields( $input, $tab_id, $fields );
	}

	/**
	 * Validate settings for this tab (base signature compatibility).
	 *
	 * @param array $input Sanitized data.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		return is_array( $input ) ? $input : [];
	}

	/**
	 * Validate tab data after sanitization for gallery context.
	 *
	 * Child classes can override to enforce tab-specific validation rules.
	 *
	 * @param array $input Sanitized data for the current tab/menu item.
	 * @param string $tab_id Tab identifier.
	 * @param string $menu_item Menu item identifier.
	 * @param int $post_id Post ID.
	 * @return array Validated data.
	 */
	public function validate_tab( $input, $tab_id, $menu_item = '', $post_id = 0 ) {
		return is_array( $input ) ? $input : [];
	}
}
