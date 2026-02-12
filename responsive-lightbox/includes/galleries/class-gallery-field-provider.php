<?php
/**
 * Responsive Lightbox Gallery Field Provider
 *
 * Shared helper for resolving Settings API gallery field definitions.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Gallery_Field_Provider class.
 */
class Responsive_Lightbox_Gallery_Field_Provider {

	/**
	 * Get gallery settings data from Settings API with legacy fallback.
	 *
	 * @return array
	 */
	public function get_gallery_settings_data() {
		$settings = [];
		$settings_data = apply_filters( 'rl_settings_data', [] );

		if ( is_array( $settings_data ) ) {
			foreach ( $settings_data as $setting_key => $setting ) {
				$fields = $this->get_settings_fields_from_data( $setting );

				if ( ! empty( $fields ) ) {
					$settings[$setting_key]['fields'] = $fields;
				}
			}
		}

		$rl = Responsive_Lightbox();
		$legacy_settings = isset( $rl->settings->settings ) && is_array( $rl->settings->settings ) ? $rl->settings->settings : [];

		if ( empty( $settings ) )
			return $legacy_settings;

		foreach ( $legacy_settings as $setting_key => $setting ) {
			if ( empty( $settings[$setting_key] ) && ! empty( $setting['fields'] ) ) {
				$settings[$setting_key] = $setting;
			}
		}

		return $settings;
	}

	/**
	 * Extract fields from Settings API data.
	 *
	 * @param array $setting
	 * @return array
	 */
	private function get_settings_fields_from_data( $setting ) {
		$fields = [];

		if ( ! empty( $setting['fields'] ) && is_array( $setting['fields'] ) ) {
			foreach ( $setting['fields'] as $field_key => $field ) {
				if ( $this->is_valid_gallery_field( $field ) )
					$fields[$field_key] = $field;
			}
		}

		if ( ! empty( $setting['sections'] ) && is_array( $setting['sections'] ) ) {
			foreach ( $setting['sections'] as $section ) {
				if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) )
					continue;

				foreach ( $section['fields'] as $field_key => $field ) {
					if ( isset( $fields[$field_key] ) )
						continue;

					if ( $this->is_valid_gallery_field( $field ) )
						$fields[$field_key] = $field;
				}
			}
		}

		return $fields;
	}

	/**
	 * Validate gallery field definition.
	 *
	 * @param mixed $field
	 * @return bool
	 */
	private function is_valid_gallery_field( $field ) {
		if ( ! is_array( $field ) )
			return false;

		if ( empty( $field['type'] ) )
			return false;

		if ( $field['type'] === 'multiple' && empty( $field['fields'] ) )
			return false;

		return true;
	}
}
