<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

// include Settings API
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-settings-api.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-settings-pages.php' );

// include settings base class
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-base.php' );

// include settings page classes
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-general.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-builder.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-folders.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-remote-library.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-capabilities.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-galleries.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-licenses.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-addons.php' );
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'class-settings-lightboxes.php' );

new Responsive_Lightbox_Settings();

/**
 * Responsive Lightbox settings class.
 *
 * @class Responsive_Lightbox_Settings
 */
class Responsive_Lightbox_Settings {

	public $settings = [];
	public $tabs = [];
	public $scripts = [];
	public $image_titles = [];

	/**
	 * Cached feature flag for Settings API mode.
	 *
	 * @var bool|null
	 */
	private $use_api_mode = null;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// set instance
		$rl = Responsive_Lightbox();
		$rl->settings = $this;

		// initialize Settings API
		$rl->settings_api = new Responsive_Lightbox_Settings_API( [
			'prefix'     => 'rl',
			'domain'     => 'responsive-lightbox',
			'plugin'     => 'Responsive Lightbox & Gallery',
			'plugin_url' => RESPONSIVE_LIGHTBOX_URL,
			'object'     => $rl,
		] );

		// initialize Settings API pages definition
		new Responsive_Lightbox_Settings_Pages();

		// initialize migrated settings pages
		new Responsive_Lightbox_Settings_General();
		new Responsive_Lightbox_Settings_Builder();
		new Responsive_Lightbox_Settings_Folders();
		new Responsive_Lightbox_Settings_Remote_Library();
		new Responsive_Lightbox_Settings_Capabilities();
		new Responsive_Lightbox_Settings_Galleries();
		new Responsive_Lightbox_Settings_Licenses();
		new Responsive_Lightbox_Settings_Addons();
		new Responsive_Lightbox_Settings_Lightboxes();

		// actions
		add_action( 'after_setup_theme', [ $this, 'load_defaults' ] );
		add_action( 'admin_init', [ $this, 'init_builder' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu_options' ] );
		add_action( 'rl_settings_sidebar', [ $this, 'settings_sidebar' ], 10, 5 );
		add_filter( 'parent_file', [ $this, 'highlight_parent_menu' ] );
		add_filter( 'submenu_file', [ $this, 'highlight_submenu' ], 10, 2 );

		// legacy add-on bridge: convert $this->settings arrays to Settings API format
		add_filter( 'rl_settings_data', [ $this, 'bridge_legacy_settings' ], 5 );
	}

	/**
	 * Render settings sidebar (legacy credits block).
	 *
	 * @param string $setting     Setting group name.
	 * @param string $page_type   Page type.
	 * @param string $url_page    Current page URL slug.
	 * @param string $tab_key     Current tab key.
	 * @param string $section_key Current section key.
	 * @return void
	 */
	public function settings_sidebar( $setting, $page_type, $url_page, $tab_key, $section_key ) {
		$rl = Responsive_Lightbox();

		echo '
				<div class="df-credits">
					<h3 class="hndle">' . esc_html__( 'Responsive Lightbox & Gallery', 'responsive-lightbox' ) . ' ' . esc_html( $rl->defaults['version'] ) . '</h3>
					<div class="inside">
						<h4 class="inner">' . esc_html__( 'Need support?', 'responsive-lightbox' ) . '</h4>
						<p class="inner">' . sprintf( esc_html__( 'If you are having problems with this plugin, please browse it\'s %s or talk about them in the %s.', 'responsive-lightbox' ), '<a href="http://www.dfactory.co/docs/responsive-lightbox/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=docs" target="_blank">' . esc_html__( 'Documentation', 'responsive-lightbox' ) . '</a>', '<a href="http://www.dfactory.co/support/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=support" target="_blank">' . esc_html__( 'Support forum', 'responsive-lightbox' ) . '</a>' ) . '</p>
						<hr />
						<h4 class="inner">' . esc_html__( 'Do you like this plugin?', 'responsive-lightbox' ) . '</h4>
						<p class="inner">' . sprintf( esc_html__( '%s on WordPress.org', 'responsive-lightbox' ), '<a href="https://wordpress.org/support/plugin/responsive-lightbox/reviews/?filter=5" target="_blank">' . esc_html__( 'Rate it 5', 'responsive-lightbox' ) . '</a>' ) . '<br />' .
							sprintf( esc_html__( 'Blog about it & link to the %s.', 'responsive-lightbox' ), '<a href="http://www.dfactory.co/products/responsive-lightbox/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=blog-about" target="_blank">' . esc_html__( 'plugin page', 'responsive-lightbox' ) . '</a>' ) . '<br />' .
							sprintf( esc_html__( 'Check out our other %s.', 'responsive-lightbox' ), '<a href="http://www.dfactory.co/products/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=other-plugins" target="_blank">' . esc_html__( 'WordPress plugins', 'responsive-lightbox' ) . '</a>' ) . '
						</p>
						<hr />
						<p class="df-link inner"><a href="http://www.dfactory.co/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="Digital Factory"><img src="//rlg-53eb.kxcdn.com/df-black-sm.png" alt="Digital Factory" /></a></p>
					</div>
				</div>';
	}

	/**
	 * Get class data.
	 *
	 * @param string $attr
	 * @return mixed
	 */
	public function get_data( $attr ) {
		// Route to appropriate data source for migrated properties
		if ( $attr === 'scripts' ) {
			return Responsive_Lightbox_Settings_Data::get_scripts();
		} elseif ( $attr === 'image_titles' ) {
			return Responsive_Lightbox_Settings_Data::get_image_titles();
		}
		
		return property_exists( $this, $attr ) ? $this->{$attr} : null;
	}

	/**
	 * Get Settings API mode flag (centralized, cached).
	 *
	 * @return bool True if Settings API should handle menus/rendering, false for legacy mode.
	 */
	public function get_use_api_mode() {
		if ( $this->use_api_mode === null ) {
			$this->use_api_mode = apply_filters( 'rl_use_settings_api_menus', true );
		}
		return $this->use_api_mode;
	}

	/**
	 * Get setting field definition from legacy settings array.
	 * 
	 * Provides safe accessor with deprecation notice for direct array access.
	 * 
	 * @since 2.6.1
	 * @param string $tab Tab/section key (e.g., 'settings', 'basicgrid_gallery')
	 * @param string $field Field key
	 * @return array|null Field definition array or null if not found
	 */
	public function get_setting_field( $tab, $field ) {
		_doing_it_wrong(
			'Direct access to Responsive_Lightbox()->settings->settings[]',
			'Use Responsive_Lightbox()->settings->get_setting_field() instead. Direct array access will be deprecated in a future version.',
			'2.6.1'
		);

		// Get all fields for tab (uses Settings API first, then legacy fallback)
		$fields = $this->get_setting_fields( $tab );
		
		if ( isset( $fields[$field] ) ) {
			return $fields[$field];
		}

		return null;
	}

	/**
	 * Get all fields for a settings tab.
	 * 
	 * @since 2.6.1
	 * @param string $tab Tab/section key
	 * @return array Fields array or empty array if not found
	 */
	public function get_setting_fields( $tab ) {
		// Try Settings API first
		$settings_data = apply_filters( 'rl_settings_data', [] );
		
		if ( isset( $settings_data[$tab] ) ) {
			$fields = [];
			
			// Extract from top-level fields
			if ( ! empty( $settings_data[$tab]['fields'] ) && is_array( $settings_data[$tab]['fields'] ) ) {
				$fields = $settings_data[$tab]['fields'];
			}
			
			// Extract from nested sections
			if ( ! empty( $settings_data[$tab]['sections'] ) && is_array( $settings_data[$tab]['sections'] ) ) {
				foreach ( $settings_data[$tab]['sections'] as $section ) {
					if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
						$fields = array_merge( $fields, $section['fields'] );
					}
				}
			}
			
			if ( ! empty( $fields ) ) {
				return $fields;
			}
		}

		// Fallback to legacy $this->settings (for add-ons)
		if ( isset( $this->settings[ $tab ]['fields'] ) ) {
			return $this->settings[ $tab ]['fields'];
		}

		return [];
	}

	/**
	 * Get script option from scripts array.
	 * 
	 * @since 2.6.1
	 * @param string $script Script key (e.g., 'swipebox', 'prettyphoto')
	 * @param string $option Option key (e.g., 'animations', 'themes')
	 * @return mixed Option value or null if not found
	 */
	public function get_script_option( $script, $option ) {
		_doing_it_wrong(
			'Direct access to Responsive_Lightbox()->settings->scripts[]',
			'Use Responsive_Lightbox()->settings->get_script_option() instead. Direct array access will be deprecated in a future version.',
			'2.6.1'
		);

		$scripts = Responsive_Lightbox_Settings_Data::get_scripts();

		if ( isset( $scripts[ $script ][ $option ] ) ) {
			return $scripts[ $script ][ $option ];
		}

		return null;
	}

	/**
	 * Get all scripts array.
	 * 
	 * @since 2.6.1
	 * @return array Scripts array
	 */
	public function get_scripts() {
		_doing_it_wrong(
			'Direct access to Responsive_Lightbox()->settings->scripts',
			'Use Responsive_Lightbox()->settings->get_scripts() instead. Direct array access will be deprecated in a future version.',
			'2.6.1'
		);

		return Responsive_Lightbox_Settings_Data::get_scripts();
	}

	/**
	 * Get image titles array.
	 * 
	 * @since 2.6.1
	 * @return array Image titles array
	 */
	public function get_image_titles() {
		_doing_it_wrong(
			'Direct access to Responsive_Lightbox()->settings->image_titles',
			'Use Responsive_Lightbox()->settings->get_image_titles() instead. Direct array access will be deprecated in a future version.',
			'2.6.1'
		);

		return Responsive_Lightbox_Settings_Data::get_image_titles();
	}

	/**
	 * Check if a settings tab exists.
	 * 
	 * @since 2.6.1
	 * @param string $tab Tab key
	 * @return bool True if tab exists, false otherwise
	 */
	public function has_setting_tab( $tab ) {
		return isset( $this->settings[ $tab ] );
	}

	/**
	 * Get settings key by option_name lookup.
	 * 
	 * Used to reverse-map from WordPress option_page to internal settings key.
	 * 
	 * @since 2.6.1
	 * @param string $option_name Option name to search for (e.g., 'responsive_lightbox_settings')
	 * @return string|null Settings key or null if not found
	 */
	public function get_settings_key_by_option( $option_name ) {
		foreach ( $this->settings as $id => $setting ) {
			if ( isset( $setting['option_name'] ) && $setting['option_name'] === $option_name ) {
				return $id;
			}
		}

		// fallback: resolve from Settings API data
		$settings_data = apply_filters( 'rl_settings_data', [] );
		if ( is_array( $settings_data ) ) {
			foreach ( $settings_data as $id => $setting ) {
				if ( isset( $setting['option_name'] ) && $setting['option_name'] === $option_name ) {
					return $id;
				}
			}
		}

		return null;
	}

	/**
	 * Get full setting definition (not just fields).
	 * 
	 * Used when needing option_name, option_group, or other metadata.
	 * 
	 * @since 2.6.1
	 * @param string $tab Tab key
	 * @return array|null Full setting definition or null if not found
	 */
	public function get_setting_definition( $tab ) {
		// Note: Accesses legacy structure directly; needed for metadata like option_name/option_group
		// that isn't part of the fields array
		if ( isset( $this->settings[ $tab ] ) ) {
			return $this->settings[ $tab ];
		}
		return null;
	}

	/**
	 * Initialize additional stuff for builder.
	 *
	 * @return void
	 */
	public function init_builder() {
		// get main instance
		$rl = Responsive_Lightbox();

		// Category options now populated by Settings API (class-settings-builder.php)
		// Legacy code removed - no longer writing to $this->settings

		// flush rewrite rules if needed
		if ( current_user_can( apply_filters( 'rl_lightbox_settings_capability', $rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options' ) ) && isset( $_POST['flush_rules'] ) && isset( $_POST['option_page'], $_POST['action'], $_POST['responsive_lightbox_builder'], $_POST['_wpnonce'] ) && $_POST['option_page'] === 'responsive_lightbox_builder' && $_POST['action'] === 'update' && ( isset( $_POST['save_rl_builder'] ) || isset( $_POST['reset_rl_builder'] ) || isset( $_POST['save_responsive_lightbox_builder'] ) || isset( $_POST['reset_responsive_lightbox_builder'] ) ) && check_admin_referer( 'responsive_lightbox_builder-options', '_wpnonce' ) !== false )
			flush_rewrite_rules();
	}

	/**
	 * Load default settings.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function load_defaults() {
		// Core settings data has been migrated to:
		// - Responsive_Lightbox_Settings_Data::get_scripts()
		// - Responsive_Lightbox_Settings_Data::get_image_titles()
		// - Settings API page classes (class-settings-*.php)
		
		// Initialize empty arrays for legacy add-on compatibility
		// Add-ons populate these via legacy patterns and bridge converts them
		$this->settings = [];
		$this->tabs = [];
		$this->scripts = [];
		$this->image_titles = [];
	}

	/**
	 * Register options page
	 *
	 * @return void
	 */
	public function admin_menu_options() {
		$rl = Responsive_Lightbox();

		// get master capability
		$capability = apply_filters( 'rl_lightbox_settings_capability', $rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options' );

		// if capabilities are active, ensure admin-level users have the required capability
		if ( $rl->options['capabilities']['active'] ) {
			$user = wp_get_current_user();

			// grant capability to users with manage_options (admins) who don't have it yet
			if ( is_a( $user, 'WP_User' ) && $user->has_cap( 'manage_options' ) && ! $user->has_cap( $capability ) ) {
				$user->add_cap( $capability );
			}
		}

		// Settings API handles menu registration via prepare_pages()
		// Filter 'rl_use_settings_api_menus' retained for backward compatibility but always true
	}

	/**
	 * Highlight parent menu for tabbed settings.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public function highlight_parent_menu( $parent_file ) {
		$page_raw = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
		$page_parts = $page_raw !== '' ? explode( '&', $page_raw, 2 ) : [ '' ];
		$page = $page_parts[0] !== '' ? sanitize_key( $page_parts[0] ) : '';

		if ( $page === 'responsive-lightbox-settings' )
			return 'responsive-lightbox-settings';

		return $parent_file;
	}

	/**
	 * Highlight submenu for tabbed settings.
	 *
	 * @param string $submenu_file Submenu file.
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public function highlight_submenu( $submenu_file, $parent_file ) {
		if ( $parent_file !== 'responsive-lightbox-settings' )
			return $submenu_file;

		$page_raw = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
		$page_parts = $page_raw !== '' ? explode( '&', $page_raw, 2 ) : [ '' ];
		$page = $page_parts[0] !== '' ? sanitize_key( $page_parts[0] ) : '';
		$page_args = [];

		if ( ! empty( $page_parts[1] ) )
			parse_str( $page_parts[1], $page_args );

		if ( $page !== 'responsive-lightbox-settings' )
			return $submenu_file;

		$tab_key = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ( isset( $page_args['tab'] ) ? sanitize_key( $page_args['tab'] ) : 'settings' );

		$target = 'responsive-lightbox-settings&tab=' . $tab_key;
		$menu = isset( $GLOBALS['submenu']['responsive-lightbox-settings'] ) ? $GLOBALS['submenu']['responsive-lightbox-settings'] : [];

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $item[2] === $target )
				return $target;
		}

		return $submenu_file;
	}

	/**
	 * Render options page stub.
	 * 
	 * Settings API now handles all rendering. This method retained for backward compatibility
	 * with add-ons or hooks that may reference it.
	 *
	 * @return void
	 */
	public function options_page() {
		// Settings API handles rendering via class-settings-api.php
		// Filter 'rl_render_settings_page' retained for backward compatibility
		$tab_key = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		
		if ( apply_filters( 'rl_render_settings_page', true, $tab_key ) )
			return;
	}

	/**
	 * Add new capability to manage options.
	 *
	 * @return string
	 */
	public function manage_options_capability() {
		return Responsive_Lightbox()->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options';
	}

	/**
	 * Legacy settings validation wrapper.
	 *
	 * @deprecated Use Settings API tab validation instead.
	 *
	 * @param array $input Input data.
	 * @return array Validated data.
	 */
	public function validate_settings( $input ) {
		$rl = Responsive_Lightbox();

		if ( isset( $rl->settings_api ) && $rl->settings_api instanceof Responsive_Lightbox_Settings_API ) {
			return $rl->settings_api->validate_settings( $input );
		}

		return $input;
	}

	/**
	 * Bridge legacy add-on settings to Settings API format.
	 * 
	 * Converts legacy $this->settings arrays (from add-ons using old registration)
	 * into Settings API-compatible data structures. Minimal implementation for
	 * backward compatibility.
	 *
	 * @param array $data Settings API data from all tabs.
	 * @return array Enhanced data with legacy settings converted.
	 */
	public function bridge_legacy_settings( $data ) {
		// get main instance
		$rl = Responsive_Lightbox();

		// iterate through legacy settings (populated by add-ons)
		foreach ( $this->settings as $tab_key => $legacy_tab ) {
			// skip if already registered by Settings API
			if ( isset( $data[$tab_key] ) )
				continue;

			// skip if no fields defined
			if ( empty( $legacy_tab['fields'] ) || ! is_array( $legacy_tab['fields'] ) )
				continue;

			// convert legacy tab to Settings API format
			$api_tab = [
				'option_name'  => ! empty( $legacy_tab['option_name'] ) ? $legacy_tab['option_name'] : 'responsive_lightbox_' . $tab_key,
				'option_group' => ! empty( $legacy_tab['option_group'] ) ? $legacy_tab['option_group'] : 'responsive_lightbox_' . $tab_key,
				'sections'     => []
			];
			if ( ! empty( $legacy_tab['callback'] ) ) {
				$api_tab['validate'] = $legacy_tab['callback'];
			}

			// normalize fields and group into sections
			if ( ! empty( $legacy_tab['sections'] ) && is_array( $legacy_tab['sections'] ) ) {
				// legacy has sections - convert each
				foreach ( $legacy_tab['sections'] as $section_id => $section ) {
					$api_tab['sections'][$section_id] = [
						'title'       => ! empty( $section['title'] ) ? $section['title'] : '',
						'description' => ! empty( $section['description'] ) ? $section['description'] : '',
						'callback'     => ! empty( $section['callback'] ) ? $section['callback'] : null,
						'fields'       => []
					];

					// add fields that belong to this section
					foreach ( $legacy_tab['fields'] as $field_id => $field ) {
						if ( ! empty( $field['section'] ) && $field['section'] === $section_id ) {
							$api_tab['sections'][$section_id]['fields'][$field_id] = $this->normalize_legacy_field( $field, $field_id, $section_id );
						}
					}
				}
			} else {
				// no sections - create default section
				$default_section = 'responsive_lightbox_' . $tab_key . '_fields';
				$api_tab['sections'][$default_section] = [
					'title'       => '',
					'description' => '',
					'fields'       => []
				];

				// convert all fields
				foreach ( $legacy_tab['fields'] as $field_id => $field ) {
					$api_tab['sections'][$default_section]['fields'][$field_id] = $this->normalize_legacy_field( $field, $field_id, $default_section );
				}
			}

			// add converted tab to data
			$data[$tab_key] = $api_tab;
		}

		return $data;
	}

	/**
	 * Normalize legacy field definition to Settings API format.
	 *
	 * @param array  $field      Legacy field definition.
	 * @param string $field_id   Field identifier.
	 * @param string $section_id Section identifier.
	 * @return array Normalized field.
	 */
	private function normalize_legacy_field( $field, $field_id, $section_id ) {
		// type normalization: legacy → API
		$type_map = [
			'switch' => 'boolean',
			'color'  => 'color_picker'
		];

		$field_type = ! empty( $field['type'] ) ? $field['type'] : 'text';
		if ( empty( $field['type'] ) )
			$field['type'] = $field_type;
		if ( isset( $type_map[$field_type] ) ) {
			$field['type'] = $type_map[$field_type];
		}

		// ensure section key is present
		$field['section'] = $section_id;

		// handle multiple fields with subfields
		if ( $field['type'] === 'multiple' && ! empty( $field['fields'] ) ) {
			foreach ( $field['fields'] as $subfield_id => $subfield ) {
				// normalize subfield types
				$subfield_type = ! empty( $subfield['type'] ) ? $subfield['type'] : 'text';
				if ( isset( $type_map[$subfield_type] ) ) {
					$field['fields'][$subfield_id]['type'] = $type_map[$subfield_type];
				}

				// preserve callback and callback_args for custom subfields
				if ( ! empty( $subfield['callback'] ) ) {
					$field['fields'][$subfield_id]['callback'] = $subfield['callback'];
					if ( ! empty( $subfield['callback_args'] ) ) {
						$field['fields'][$subfield_id]['callback_args'] = $subfield['callback_args'];
					}
				}
			}
		}

		// preserve top-level callback
		if ( ! empty( $field['callback'] ) ) {
			$field['callback'] = $field['callback'];
			if ( ! empty( $field['callback_args'] ) ) {
				$field['callback_args'] = $field['callback_args'];
			}
		}

		return $field;
	}
}
