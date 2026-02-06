<?php
/**
 * Responsive Lightbox Settings Base Class
 *
 * Abstract base class providing common structure for all Settings API page classes.
 * Enforces consistent patterns while allowing flexibility through abstract methods.
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Base class.
 *
 * Base class for settings page migration to new Settings API.
 * Provides standardized structure and common functionality.
 *
 * @abstract
 * @class Responsive_Lightbox_Settings_Base
 */
abstract class Responsive_Lightbox_Settings_Base {

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
	 * Registers filters for Settings API integration.
	 *
	 * @return void
	 */
	public function __construct() {
		// provide settings data for this tab
		$priority = $this->get_settings_data_priority();
		add_filter( 'rl_settings_data', [ $this, 'settings_data' ], $priority );
	}

	/**
	 * Get the priority for settings_data filter.
	 *
	 * Override in child class if tab needs late loading (e.g., Remote Library uses 100).
	 * Default is 10 for standard tabs.
	 *
	 * @return int Filter priority.
	 */
	protected function get_settings_data_priority() {
		return 10;
	}

	/**
	 * Validate settings for this tab.
	 *
	 * Default implementation uses base class field sanitization.
	 * Child classes can override to provide tab-specific validation logic.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// Use base class sanitization by default
		// Child classes override for custom validation logic
		return $this->sanitize_fields( $input, static::TAB_KEY );
	}

	/**
	 * Provide settings data for this tab.
	 *
	 * Must be implemented by child class.
	 * Should return array with structure:
	 * [
	 *   'option_name'  => 'responsive_lightbox_*',
	 *   'option_group' => 'responsive_lightbox_*',
	 *   'validate'     => [ $this, 'validate' ],
	 *   'sections'     => [ ... ],
	 *   'fields'       => [ ... ] // or nested in sections
	 * ]
	 *
	 * @abstract
	 * @param array $data Settings data from other tabs.
	 * @return array Modified settings data.
	 */
	abstract public function settings_data( $data );

	/**
	 * Get the tab key.
	 *
	 * Convenience method to access TAB_KEY constant.
	 *
	 * @return string
	 */
	public function get_tab_key() {
		return static::TAB_KEY;
	}

	/**
	 * Check if this is the current tab.
	 *
	 * @return bool
	 */
	protected function is_current_tab() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		return $current_tab === static::TAB_KEY;
	}

	/**
	 * Get current section key from URL.
	 *
	 * @return string
	 */
	protected function get_current_section() {
		return isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';
	}

	/**
	 * Merge input with current saved options.
	 *
	 * Useful for preserving fields not included in the current form.
	 *
	 * @param array $input New input values.
	 * @return array Merged values.
	 */
	protected function merge_with_saved( $input ) {
		$rl = Responsive_Lightbox();
		$tab_key = static::TAB_KEY;

		if ( isset( $rl->options[$tab_key] ) && is_array( $rl->options[$tab_key] ) ) {
			return array_merge( $rl->options[$tab_key], $input );
		}

		return $input;
	}

	/**
	 * Merge input with default options.
	 *
	 * Useful for reset operations or ensuring required fields exist.
	 *
	 * @param array $input Input values.
	 * @return array Merged values with defaults.
	 */
	protected function merge_with_defaults( $input ) {
		$rl = Responsive_Lightbox();
		$tab_key = static::TAB_KEY;

		if ( isset( $rl->defaults[$tab_key] ) && is_array( $rl->defaults[$tab_key] ) ) {
			return array_merge( $rl->defaults[$tab_key], $input );
		}

		return $input;
	}

	/**
	 * Preserve specific system fields from current options.
	 *
	 * Useful for fields that should never be modified by user input
	 * (e.g., version numbers, internal flags).
	 *
	 * @param array $input     New input values.
	 * @param array $field_keys Array of field keys to preserve.
	 * @return array Input with preserved system fields.
	 */
	protected function preserve_system_fields( $input, $field_keys = [] ) {
		$rl = Responsive_Lightbox();
		$tab_key = static::TAB_KEY;

		if ( isset( $rl->options[$tab_key] ) && is_array( $rl->options[$tab_key] ) ) {
			foreach ( $field_keys as $field_key ) {
				if ( isset( $rl->options[$tab_key][$field_key] ) ) {
					$input[$field_key] = $rl->options[$tab_key][$field_key];
				}
			}
		}

		return $input;
	}

	/**
	 * Check if current request is a save operation.
	 *
	 * @param string $option_page Option page name to check.
	 * @return bool
	 */
	protected function is_save_request( $option_page = '' ) {
		if ( $option_page === '' ) {
			$option_page = 'responsive_lightbox_' . static::TAB_KEY;
		}

		$legacy_save = 'save_rl_' . static::TAB_KEY;
		$api_save = 'save_' . $option_page;

		return isset( $_POST[$legacy_save] ) || isset( $_POST[$api_save] );
	}

	/**
	 * Check if current request is a reset operation.
	 *
	 * @param string $option_page Option page name to check.
	 * @return bool
	 */
	protected function is_reset_request( $option_page = '' ) {
		if ( $option_page === '' ) {
			$option_page = 'responsive_lightbox_' . static::TAB_KEY;
		}

		$legacy_reset = 'reset_rl_' . static::TAB_KEY;
		$api_reset = 'reset_' . $option_page;

		return isset( $_POST[$legacy_reset] ) || isset( $_POST[$api_reset] );
	}

	/**
	 * Normalize fields from Settings API data structure.
	 *
	 * Flattens fields that may be nested under sections or at top-level.
	 * Returns array of [field_id => field_definition] for processing.
	 *
	 * @param array $settings_data Settings data array with sections/fields.
	 * @return array Flat array of fields.
	 */
	protected function normalize_fields( $settings_data ) {
		$fields = [];

		// collect top-level fields
		if ( ! empty( $settings_data['fields'] ) && is_array( $settings_data['fields'] ) ) {
			$fields = $settings_data['fields'];
		}

		// collect fields nested in sections (PVC-style)
		if ( ! empty( $settings_data['sections'] ) && is_array( $settings_data['sections'] ) ) {
			foreach ( $settings_data['sections'] as $section_id => $section ) {
				if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
					$fields = array_merge( $fields, $section['fields'] );
				}
			}
		}

		return $fields;
	}

	/**
	 * Sanitize all fields for this tab using local sanitization logic.
	 *
	 * Handles multiple field groups, parent mapping, checkbox arrays, and
	 * boolean/checkbox defaults. Uses local sanitize_field() method.
	 *
	 * @param array  $input         Input data from form submission.
	 * @param string $settings_key  Settings key (e.g., 'settings', 'builder').
	 * @param array  $fields        Field definitions (pass null to auto-detect from settings_data).
	 * @return array Sanitized input.
	 */
	protected function sanitize_fields( $input, $settings_key = '', $fields = null ) {
		$rl = Responsive_Lightbox();

		// auto-detect settings key if not provided
		if ( $settings_key === '' ) {
			$settings_key = static::TAB_KEY;
		}

		// auto-detect fields if not provided
		if ( $fields === null ) {
			// try to get from Settings API data
			$settings_data = apply_filters( 'rl_settings_data', [] );
			if ( isset( $settings_data[$settings_key] ) ) {
				$fields = $this->normalize_fields( $settings_data[$settings_key] );
			} else {
				// fallback to legacy settings using getter method
				if ( $rl->settings->has_setting_tab( $settings_key ) ) {
					$fields = $rl->settings->get_setting_fields( $settings_key );
				} else {
					return $input;
				}
			}
		}

		// sanitize each field
		foreach ( $fields as $field_id => $field ) {
			if ( $field['type'] === 'multiple' ) {
				// handle grouped subfields
				if ( ! empty( $field['fields'] ) ) {
					foreach ( $field['fields'] as $subfield_id => $subfield ) {
						$args = $subfield;
						$args['setting_id'] = $settings_key;
						$args['field_id'] = $field_id;
						$args['subfield_id'] = $subfield_id;

						$default_value = '';
						if ( $subfield['type'] === 'boolean' )
							$default_value = false;
						elseif ( $subfield['type'] === 'checkbox' )
							$default_value = [];
						elseif ( $subfield['type'] === 'number' )
							$default_value = 0;

						// check if subfield has parent (nested options like configuration[glightbox][loop])
						if ( ! empty( $field['fields'][$subfield_id]['parent'] ) ) {
							$field_parent = $field['fields'][$subfield_id]['parent'];

							if ( isset( $rl->defaults[$settings_key][$field_parent][$subfield_id] ) )
								$default_value = $rl->defaults[$settings_key][$field_parent][$subfield_id];

							$input[$field_parent][$subfield_id] = isset( $input[$field_parent][$subfield_id] ) 
								? $this->sanitize_field( $input[$field_parent][$subfield_id], $subfield['type'], $args )
								: ( $subfield['type'] === 'boolean' ? false : $default_value );
						} else {
							if ( isset( $rl->defaults[$settings_key][$field_id][$subfield_id] ) )
								$default_value = $rl->defaults[$settings_key][$field_id][$subfield_id];

							$input[$subfield_id] = isset( $input[$subfield_id] )
								? $this->sanitize_field( $input[$subfield_id], $subfield['type'], $args )
								: ( $subfield['type'] === 'boolean' ? false : $default_value );
						}
					}
				}
			} else {
				// handle single fields
				$args = $field;
				$args['setting_id'] = $settings_key;
				$args['field_id'] = $field_id;

				$default_value = '';
				if ( $field['type'] === 'boolean' )
					$default_value = false;
				elseif ( $field['type'] === 'checkbox' )
					$default_value = [];
				elseif ( $field['type'] === 'number' )
					$default_value = 0;

				// check if field has parent (nested options)
				if ( ! empty( $field['parent'] ) ) {
					$field_parent = $field['parent'];

					if ( isset( $rl->defaults[$settings_key][$field_parent][$field_id] ) )
						$default_value = $rl->defaults[$settings_key][$field_parent][$field_id];

					$input[$field_parent][$field_id] = isset( $input[$field_parent][$field_id] )
						? ( $field['type'] === 'checkbox' 
							? array_keys( $this->sanitize_field( $input[$field_parent][$field_id], $field['type'], $args ) )
							: $this->sanitize_field( $input[$field_parent][$field_id], $field['type'], $args ) )
						: ( in_array( $field['type'], [ 'boolean', 'checkbox' ], true ) ? false : $default_value );
				} else {
					if ( isset( $rl->defaults[$settings_key][$field_id] ) )
						$default_value = $rl->defaults[$settings_key][$field_id];

					$input[$field_id] = isset( $input[$field_id] )
						? ( $field['type'] === 'checkbox'
							? array_keys( $this->sanitize_field( $input[$field_id], $field['type'], $args ) )
							: $this->sanitize_field( $input[$field_id], $field['type'], $args ) )
						: ( in_array( $field['type'], [ 'boolean', 'checkbox' ], true ) ? false : $default_value );
				}
			}
		}

		return $input;
	}

	/**
	 * Sanitize field value.
	 *
	 * Copied from legacy class-settings.php sanitize_field() to make Base class
	 * self-contained. Applies type-specific sanitization rules.
	 *
	 * @param mixed  $value Field value to sanitize.
	 * @param string $type  Field type (boolean, checkbox, radio, textarea, etc.).
	 * @param array  $args  Field arguments (optional, for context-specific validation).
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_field( $value = null, $type = '', $args = [] ) {
		if ( is_null( $value ) )
			return null;

		switch ( $type ) {
			case 'button':
			case 'boolean':
				// handle string 'false' from new Settings API
				if ( $value === 'false' )
					$value = false;
				else
					$value = empty( $value ) ? false : true;
				break;

			case 'checkbox':
				$value = is_array( $value ) && ! empty( $value ) ? array_map( 'sanitize_key', $value ) : [];
				break;

			case 'radio':
				$value = is_array( $value ) ? false : sanitize_key( $value );
				break;

			case 'textarea':
			case 'wysiwyg':
				$value = wp_kses_post( $value );
				break;

			case 'color_picker':
				$value = sanitize_hex_color( $value );

				if ( empty( $value ) )
					$value = '#666666';
				break;

			case 'number':
				$value = (int) $value;

				// is value lower than?
				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				// is value greater than?
				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

			case 'custom':
				// do nothing
				break;

			case 'text':
				if ( ! empty( $args ) ) {
					// validate custom events
					if ( $args['setting_id'] === 'settings' ) {
						if ( $args['field_id'] === 'enable_custom_events' && $args['subfield_id'] === 'custom_events' )
							$value = preg_replace( '/[^a-z0-9\s.-]/i', '', $value );
					} elseif ( $args['setting_id'] === 'builder' ) {
						if ( $args['field_id'] === 'permalink' || $args['field_id'] === 'permalink_categories' || $args['field_id'] === 'permalink_tags' )
							$value = sanitize_title( $value );
					}
				}
				// intentional fallthrough to default sanitization
			case 'select':
			default:
				$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
				break;
		}

		return stripslashes_deep( $value );
	}
}