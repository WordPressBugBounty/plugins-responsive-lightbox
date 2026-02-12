<?php
/**
 * Responsive Lightbox Gallery Config Settings
 *
 * Handles gallery configuration settings (gallery type selection).
 * Adapts existing Settings API galleries tab for post meta context.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Config class.
 *
 * Gallery configuration tab - adapts settings API galleries fields.
 *
 * @class Responsive_Lightbox_Gallery_Config
 */
class Responsive_Lightbox_Gallery_Config extends Responsive_Lightbox_Gallery_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'config';

	/**
	 * Get tab data for rendering.
	 *
	 * Returns configuration fields adapted from Settings API.
	 *
	 * @param string $menu_item Optional menu item to get fields for.
	 * @return array Tab data.
	 */
	public function get_tab_data( $menu_item = '' ) {
		$rl = Responsive_Lightbox();
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );
		if ( ! is_array( $gallery_types ) ) {
			$gallery_types = [];
		}

		$config_data = [
			'tab_key' => self::TAB_KEY,
			'menu_items' => []
		];

		// Build menu items: Global first, then other gallery types
		$config_data['menu_items']['default'] = __( 'Global', 'responsive-lightbox' );
		foreach ( $gallery_types as $type => $type_data ) {
			if ( $type === 'default' ) {
				continue; // Skip default as we handle it as Global
			}
			if ( is_array( $type_data ) ) {
				$label = $type_data['label'] ?? ( $type_data['name'] ?? $type );
			} else {
				$label = (string) $type_data;
			}
			$config_data['menu_items'][$type] = $label;
		}

		// If menu_item is specified, return fields for that gallery type
		// Otherwise, use the first available gallery type as default
		$selected_type = ! empty( $menu_item ) && array_key_exists( $menu_item, $config_data['menu_items'] ) ? $menu_item : 'default';
		$config_data['options'] = $this->get_gallery_type_fields( $selected_type );

		return $config_data;
	}

	/**
	 * Get fields for a specific gallery type.
	 *
	 * Adapts Settings API galleries fields for post meta, emulating legacy logic.
	 *
	 * @param string $gallery_type Gallery type.
	 * @return array Fields configuration.
	 */
	public function get_gallery_type_fields( $gallery_type ) {
		$rl = Responsive_Lightbox();

		// Get gallery types for label resolution
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );

		// Get default gallery fields (universal base)
		$default_gallery_fields = $rl->frontend->get_default_gallery_fields();

		// Build settings data using shared provider
		$provider = new Responsive_Lightbox_Gallery_Field_Provider();
		$settings = $provider->get_gallery_settings_data();
		$defaults = $rl->defaults;
		$default_gallery_type = isset( $rl->options['settings']['builder_gallery'] ) ? $rl->options['settings']['builder_gallery'] : 'basicgrid';
		$default_gallery_key = $default_gallery_type . '_gallery';
		$builder_gallery_fields = [];
		$global_settings = [];

		// Ensure default_gallery exists in settings
		if ( ! array_key_exists( 'default_gallery', $settings ) ) {
			$settings['default_gallery'] = [];
		}

		// Get builder gallery fields
		if ( ! empty( $settings[$default_gallery_key]['fields'] ) ) {
			$builder_gallery_fields = $settings[$default_gallery_key]['fields'];
		} elseif ( $rl->settings && method_exists( $rl->settings, 'get_setting_fields' ) ) {
			$legacy_fields = $rl->settings->get_setting_fields( $default_gallery_key );
			if ( ! empty( $legacy_fields ) && is_array( $legacy_fields ) ) {
				$builder_gallery_fields = $legacy_fields;
			}
		}

		if ( ! empty( $rl->options[$default_gallery_key] ) && is_array( $rl->options[$default_gallery_key] ) ) {
			$global_settings = $rl->options[$default_gallery_key];
		}

		// Assign default values for default_gallery
		foreach ( $default_gallery_fields as $field => $field_args ) {
			$defaults['default_gallery'][$field] = $field_args['default'];
		}

		$fields = [];
		$disabled_fields = [];

		if ( $gallery_type === 'default' ) {
			// For Global: merge base + builder gallery fields, disable non-base fields
			$fields = $default_gallery_fields;
			if ( ! empty( $builder_gallery_fields ) ) {
				$fields = $rl->frontend->get_unique_fields( $default_gallery_fields, $builder_gallery_fields );
				foreach ( $defaults[$default_gallery_key] as $field => $default_value ) {
					if ( ! array_key_exists( $field, $defaults['default_gallery'] ) ) {
						$defaults['default_gallery'][$field] = $default_value;
					}
				}
			}
			$disabled_fields = array_diff_key( $fields, $default_gallery_fields );
		} else {
			// For specific types: merge base + type fields
			if ( array_key_exists( $gallery_type . '_gallery', $settings ) ) {
				$type_fields = $settings[$gallery_type . '_gallery']['fields'] ?? [];
				if ( empty( $type_fields ) && $rl->settings && method_exists( $rl->settings, 'get_setting_fields' ) ) {
					$type_fields = $rl->settings->get_setting_fields( $gallery_type . '_gallery' );
				}
				if ( ! empty( $type_fields ) ) {
					$fields = $rl->frontend->get_unique_fields( $default_gallery_fields, $type_fields );
				}
				// Add default gallery default values
				foreach ( $default_gallery_fields as $field => $field_args ) {
					$defaults[$gallery_type . '_gallery'][$field] = $field_args['default'];
				}
			}
		}

		// Adapt fields for gallery context
		$adapted_fields = [];
		foreach ( $fields as $field_key => $field ) {
			// Skip fields that don't make sense in per-gallery context
			if ( in_array( $field_key, [ 'active' ], true ) ) {
				continue;
			}

			$adapted_field = $field;

			// Mark disabled fields for Global
			if ( $gallery_type === 'default' && isset( $disabled_fields[$field_key] ) ) {
				$adapted_field['disabled'] = true;
				$adapted_field['is_global_locked'] = true;

				// Propagate disabled to subfields for multiple fields
				if ( isset( $field['type'] ) && $field['type'] === 'multiple' && ! empty( $field['fields'] ) ) {
					foreach ( $field['fields'] as $subfield_key => $subfield ) {
						$adapted_field['fields'][$subfield_key] = $subfield;
						$adapted_field['fields'][$subfield_key]['disabled'] = true;
						$adapted_field['fields'][$subfield_key]['is_global_locked'] = true;
					}
				}
			}

			// Adapt field names and defaults for gallery context
			if ( isset( $field['default'] ) ) {
				// Keep defaults as-is for now
			}

			$adapted_fields[$field_key] = $adapted_field;
		}

		// Add disabled notice for Global if there are disabled fields
		if ( $gallery_type === 'default' && ! empty( $disabled_fields ) ) {
			$settings_menu = '';
			$settings_tab_label = '';
			$settings_section = '';

			if ( ! empty( $rl->settings_api ) && method_exists( $rl->settings_api, 'get_pages' ) ) {
				$settings_pages = $rl->settings_api->get_pages();
				if ( ! empty( $settings_pages['settings'] ) ) {
					$settings_menu = ! empty( $settings_pages['settings']['menu_title'] ) ? $settings_pages['settings']['menu_title'] : '';
					if ( ! empty( $settings_pages['settings']['tabs']['gallery']['label'] ) ) {
						$settings_tab_label = $settings_pages['settings']['tabs']['gallery']['label'];
					}
				}
			}
			if ( ! empty( $settings[$default_gallery_key]['sections'] ) && is_array( $settings[$default_gallery_key]['sections'] ) ) {
				$first_section = reset( $settings[$default_gallery_key]['sections'] );
				if ( ! empty( $first_section['title'] ) ) {
					$settings_section = $first_section['title'];
				}
			}

			if ( $settings_section === '' && ! empty( $gallery_types[$default_gallery_type] ) ) {
				if ( is_array( $gallery_types[$default_gallery_type] ) ) {
					$settings_section = $gallery_types[$default_gallery_type]['label'] ?? $gallery_types[$default_gallery_type]['name'] ?? $default_gallery_type;
				} else {
					$settings_section = (string) $gallery_types[$default_gallery_type];
				}
			}

			if ( $settings_section === '' ) {
				$settings_section = ucwords( str_replace( '_', ' ', $default_gallery_key ) );
			}

			$settings_url = admin_url( 'admin.php?page=responsive-lightbox-settings&tab=gallery' );
			if ( $settings_menu === '' ) {
				$settings_menu = __( 'Lightbox', 'responsive-lightbox' );
			}
			if ( $settings_tab_label === '' ) {
				$settings_tab_label = __( 'Galleries', 'responsive-lightbox' );
			}

			$disabled_notice = [
				'type' => 'notice',
				'content' => '<div class="rl-gallery-disabled-notice"><p>' . wp_kses_post( sprintf( __( 'Settings below are controlled globally in %1$s &rarr; %2$s &rarr; %3$s.', 'responsive-lightbox' ), esc_html( $settings_menu ), esc_html( $settings_tab_label ), esc_html( $settings_section ) ) ) . '</p><a class="rl-gallery-disabled-notice-link" href="' . esc_url( $settings_url ) . '">' . esc_html( sprintf( __( 'Edit Global %s Settings ->', 'responsive-lightbox' ), $settings_section ) ) . '</a></div>',
				'class' => 'rl-gallery-field-disabled-notice'
			];

			// Insert notice before the first disabled field using key-preserving method
			$new_fields = [];
			$notice_inserted = false;
			foreach ( $adapted_fields as $key => $field ) {
				if ( !$notice_inserted && isset( $field['is_global_locked'] ) && $field['is_global_locked'] ) {
					$new_fields['disabled_notice'] = $disabled_notice;
					$notice_inserted = true;
				}
				$new_fields[$key] = $field;
			}
			$adapted_fields = $new_fields;
		}

		return $adapted_fields;
	}

}
