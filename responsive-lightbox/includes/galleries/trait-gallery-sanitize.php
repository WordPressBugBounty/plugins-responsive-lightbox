<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Gallery Sanitization Trait.
 *
 * Handles field sanitization for gallery data.
 *
 * @trait Responsive_Lightbox_Gallery_Sanitize
 */
trait Responsive_Lightbox_Gallery_Sanitize {

	/**
	 * Sanitize field based on type. Internal use only.
	 *
	 * Unified sanitization behavior aligned with settings-base for shared field types.
	 * Gallery-specific types (media_library, media_preview, taxonomy) maintain custom logic.
	 *
	 * @global string $wp_version
	 *
	 * @param string $field Field name
	 * @param mixed $value Field value
	 * @param array $args Field arguments
	 * @return mixed
	 */
	public function sanitize_field( $field, $value, $args ) {
		switch ( $args['type'] ) {
			case 'radio':
			case 'select':
				$value = array_key_exists( $value, $args['options'] ) ? $value : $args['default'];
				break;

			case 'taxonomy':
				if ( is_array( $value ) ) {
					if ( isset( $value['id'] ) )
						$value['id'] = (int) $value['id'];
					else
						$value['id'] = 0;

					$value['children'] = isset( $value['children'] );
				} else
					$value = $args['default'];

				// flatten taxonomy options if needed
				if ( ! empty( $args['options'] ) && is_array( $args['options'] ) ) {
					$terms = [];

					foreach ( $args['options'] as $option_data ) {
						if ( isset( $option_data['terms'] ) && is_array( $option_data['terms'] ) )
							$terms += $option_data['terms'];
					}

					if ( ! empty( $terms ) )
						$args['options'] = $terms;
				}

				if ( isset( $value['id'] ) && ! empty( $args['options'] ) && is_array( $args['options'] ) && ! array_key_exists( $value['id'], $args['options'] ) )
					$value['id'] = 0;

				// validate term exists
				if ( isset( $value['id'] ) && $value['id'] ) {
					$term = get_term( $value['id'], $args['taxonomy'] );
					if ( ! is_a( $term, 'WP_Term' ) )
						$value['id'] = 0;
				}
				break;
			case 'multiselect':
				if ( is_array( $value ) ) {
					if ( $field === 'post_term' ) {
						$terms = [];

						foreach ( $args['options'] as $data ) {
							$terms += $data['terms'];
						}

						$args['options'] = $terms;
					}

					$values = [];

					foreach ( $value as $subvalue ) {
						if ( array_key_exists( $subvalue, $args['options'] ) )
							$values[] = $subvalue;
					}

					$value = $values;
				} else
					$value = $args['default'];
				break;
			case 'checkbox':
				if ( is_array( $value ) && ! empty( $value ) ) {
					$sort = [];

					foreach ( $value as $sort_key => $bool ) {
						if ( array_key_exists( $sort_key, $args['options'] ) )
							$sort[] = $sort_key;
					}

					$value = $sort;
				} else
					$value = [];
				break;

			case 'boolean':
				// handle string 'false' from form submissions
				if ( $value === 'false' )
					$value = false;
				else
					$value = empty( $value ) ? false : true;
				break;

			case 'range':
			case 'number':
				$value = (int) $value;

				// is value lower than?
				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				// is value greater than?
				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

			case 'class':
				$value = trim( $value );

				// more than 1 class?
				if ( strpos( $value, ' ' ) !== false ) {
					// get unique valid HTML classes
					$value = array_unique( array_filter( array_map( 'sanitize_html_class', explode( ' ', $value ) ) ) );

					if ( ! empty( $value ) )
						$value = implode( ' ', $value );
					else
						$value = '';
				// single class
				} else
					$value = sanitize_html_class( $value );
				break;

			case 'text':
				$value = trim( sanitize_text_field( $value ) );
				break;

			case 'textarea':
				// For gallery fields, allow minimal formatting (line breaks)
				// Use wp_kses_post for consistency with settings, but strip most HTML
				$value = wp_kses_post( $value );
				break;

			case 'color_picker':
				$value = sanitize_hex_color( $value );

				// fallback to default if invalid
				if ( empty( $value ) )
					$value = isset( $args['default'] ) ? $args['default'] : '#666666';
				break;

			case 'media_library':
				if ( is_array( $value ) ) {
					$data = $args['default'];

					if ( rl_current_lightbox_supports( [ 'youtube', 'vimeo' ], 'OR' ) ) {
						$reindexed_embed = [];

						// check embed items
						if ( array_key_exists( 'embed', $value ) && is_array( $value['embed'] ) && ! empty( $value['embed'] ) ) {
							$copy = $value['embed'];

							$index = 0;

							foreach ( $value['embed'] as $embed_id => $embed_data ) {
								// check url
								if ( ! array_key_exists( 'url', $embed_data ) ) {
									unset( $copy[$embed_id] );

									continue;
								} else
									$copy[$embed_id]['url'] = esc_url_raw( $embed_data['url'] );

								// check width
								if ( ! array_key_exists( 'width', $embed_data ) )
									$copy[$embed_id]['width'] = 0;
								else
									$copy[$embed_id]['width'] = (int) $embed_data['width'];

								// check height
								if ( ! array_key_exists( 'height', $embed_data ) )
									$copy[$embed_id]['height'] = 0;
								else
									$copy[$embed_id]['height'] = (int) $embed_data['height'];

								// check thumbnail url
								if ( empty( $embed_data['thumbnail_url'] ) )
									$copy[$embed_id]['thumbnail_url'] = '';
								else
									$copy[$embed_id]['thumbnail_url'] = esc_url_raw( $embed_data['thumbnail_url'] );

								// check thumbnail width
								if ( ! array_key_exists( 'thumbnail_width', $embed_data ) )
									$copy[$embed_id]['thumbnail_width'] = 0;
								else
									$copy[$embed_id]['thumbnail_width'] = (int) $embed_data['thumbnail_width'];

								// check thumbnail height
								if ( ! array_key_exists( 'thumbnail_height', $embed_data ) )
									$copy[$embed_id]['thumbnail_height'] = 0;
								else
									$copy[$embed_id]['thumbnail_height'] = (int) $embed_data['thumbnail_height'];

								// check title
								if ( empty( $embed_data['title'] ) )
									$copy[$embed_id]['title'] = '';
								else
									$copy[$embed_id]['title'] = trim( sanitize_text_field( $embed_data['title'] ) );

								// check caption
								if ( empty( $embed_data['caption'] ) )
									$copy[$embed_id]['caption'] = '';
								else
									$copy[$embed_id]['caption'] = trim( sanitize_textarea_field( $embed_data['caption'] ) );

								// check date
								if ( empty( $embed_data['date'] ) )
									$copy[$embed_id]['date'] = '';
								else
									$copy[$embed_id]['date'] = date( 'Y-m-d H:i:s', strtotime( $embed_data['date'] ) );

								// new embed id
								$new_id = 'e' . $index;

								// add embed data
								$data['embed'][$new_id] = $copy[$embed_id];
								$data['embed'][$new_id]['id'] = $new_id;

								// add special id
								$reindexed_embed[$embed_id] = 'em' . $index++;
							}

							// last replacement is 'em' to avoid replacing same embed ids
							$reindexed_embed['em'] = 'e';

							// prepare embed additional data
							$atts_args = [
								'embed_keys'	=> array_keys( $data['embed'] ),
								'providers'		=> [ 'youtube', 'vimeo' ]
							];
						} else
							$atts_args = [];
					} else
						$atts_args = [];


					// check ids
					if ( array_key_exists( 'ids', $value ) ) {
						// prepare ids
						$ids = (string) trim( $value['ids'] );

						if ( $ids !== '' ) {
							// reindex embed
							if ( ! empty( $reindexed_embed ) )
								$ids = str_replace( array_keys( $reindexed_embed ), array_values( $reindexed_embed ), $ids );

							// get unique and non empty attachment ids only
							$data['ids'] = $this->check_attachments( array_unique( array_filter( explode( ',', $ids ) ) ), $atts_args );
						} else
							$data['ids'] = [];
					}

					// check excluded items
					if ( array_key_exists( 'exclude', $value ) && is_array( $value['exclude'] ) && ! empty( $value['exclude'] ) ) {
						// reindex embed
						if ( ! empty( $reindexed_embed ) )
							$value['exclude'] = explode( ',', str_replace( array_keys( $reindexed_embed ), array_values( $reindexed_embed ), implode( ',', array_filter( $value['exclude'] ) ) ) );

						// get unique and non empty attachment ids only
						$data['exclude'] = $this->check_attachments( array_unique( array_filter( $value['exclude'] ) ), $atts_args );
					}

					$value = $data;
				} else
					$value = $args['default'];
				break;

			case 'media_preview':
				if ( is_array( $value ) ) {
					$data = $args['default'];

					// check excluded items
					if ( array_key_exists( 'exclude', $value ) && is_array( $value['exclude'] ) && ! empty( $value['exclude'] ) ) {
						$ids = $strings = [];

						foreach ( $value['exclude'] as $exclude_item ) {
							$item = trim( $exclude_item );

							if ( is_numeric( $item ) )
								$ids[] = (int) $item;
							elseif ( $item !== '' )
								$strings[] = $item;
						}

						if ( ! empty( $ids ) ) {
							// get unique and non empty attachment ids only
							$ids = $this->check_attachments( array_unique( array_filter( $ids ) ) );
						}

						$data['exclude'] = $ids + $strings;
					}

					$value = $data;
				} else
					$value = $args['default'];
		}

		return apply_filters( 'rl_sanitize_gallery_field', $value, $args );
	}

	/**
	 * Sanitize set of fields.
	 *
	 * @param array $items Fields
	 * @param array $data POST data
	 * @param string $tab_id Gallery tab
	 * @param string $menu_item Gallery menu item
	 * @return array
	 */
	public function sanitize_fields( $items, $data, $tab_id, $menu_item ) {
		$safedata = [];

		// For config tab in Global mode, determine disabled fields
		$disabled_fields = [];
		if ( $tab_id === 'config' && $menu_item === 'default' ) {
			$rl = Responsive_Lightbox();
			$default_gallery_fields = $rl->frontend->get_default_gallery_fields();
			$disabled_fields = array_diff_key( $items, $default_gallery_fields );
		}

		foreach ( $items as $field => $item ) {
			// skip this field
			if ( isset( $item['save'] ) && ! $item['save'] )
				continue;

			// Skip disabled fields in Global config
			if ( $tab_id === 'config' && $menu_item === 'default' && isset( $disabled_fields[$field] ) )
				continue;

			// available field?
			if ( isset( $data[$tab_id], $data[$tab_id][$menu_item], $data[$tab_id][$menu_item][$field] ) )
				$safedata[$tab_id][$menu_item][$field] = $this->sanitize_field( $field, $data[$tab_id][$menu_item][$field], $item );
			// boolean field?
			elseif ( $item['type'] === 'boolean' )
				$safedata[$tab_id][$menu_item][$field] = false;
			// multiple fields?
			elseif ( $item['type'] === 'multiple' ) {
				foreach ( $item['fields'] as $subfield => $subitem ) {
					// Skip disabled subfields in Global config
					if ( $tab_id === 'config' && $menu_item === 'default' && isset( $disabled_fields[$field] ) )
						continue;

					// available subfield?
					if ( isset( $data[$tab_id], $data[$tab_id][$menu_item], $data[$tab_id][$menu_item][$subfield] ) )
						$safedata[$tab_id][$menu_item][$subfield] = $this->sanitize_field( $subfield, $data[$tab_id][$menu_item][$subfield], $subitem );
					// boolean subfield?
					elseif ( $subitem['type'] === 'boolean' )
						$safedata[$tab_id][$menu_item][$subfield] = false;
					// any other case
					else
						$safedata[$tab_id][$menu_item][$subfield] = $subitem['default'];
				}
			// any other case
			} else
				$safedata[$tab_id][$menu_item][$field] = $item['default'];
		}

		return $safedata;
	}
}
