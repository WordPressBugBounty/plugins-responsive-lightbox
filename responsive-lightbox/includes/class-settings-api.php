<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_API class.
 *
 * Settings API handler adapted from Post Views Counter.
 * Provides standardized settings registration, rendering, and validation.
 *
 * @class Responsive_Lightbox_Settings_API
 */
class Responsive_Lightbox_Settings_API {

	private $settings = [];
	private $input_settings = [];
	private $validated_settings = [];
	private $pages = [];
	private $page_types = [];
	private $pages_ready = false;
	private $prefix = '';
	private $slug = '';
	private $domain = '';
	private $plugin = '';
	private $plugin_url = '';
	private $object;
	private $nested = false;

	/**
	 * Class constructor.
	 *
	 * @param array $args Configuration arguments.
	 * @return void
	 */
	public function __construct( $args ) {
		// set initial data
		$this->prefix = $args['prefix'];
		$this->domain = $args['domain'];
		$this->nested = isset( $args['nested'] ) ? (bool) $args['nested'] : false;

		// empty slug?
		if ( empty( $args['slug'] ) )
			$this->slug = $args['domain'];
		else
			$this->slug = $args['slug'];

		$this->object = $args['object'];
		$this->plugin = $args['plugin'];
		$this->plugin_url = $args['plugin_url'];

		// skip hooks if running in bridge mode (menus handled by legacy system)
		$skip_hooks = isset( $args['skip_hooks'] ) ? (bool) $args['skip_hooks'] : false;

		if ( ! $skip_hooks ) {
			// actions
			add_action( 'admin_menu', [ $this, 'admin_menu_options' ], 11 );
			add_action( 'admin_init', [ $this, 'register_settings' ], 11 );
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		} else {
			// in bridge mode, only register settings (menus handled externally)
			add_action( 'admin_init', [ $this, 'register_settings' ], 11 );
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		}
	}

	/**
	 * Get prefix.
	 *
	 * @return string
	 */
	public function get_prefix() {
		return $this->prefix;
	}

	/**
	 * Get pages.
	 *
	 * @return array
	 */
	public function get_pages() {
		$this->prepare_pages();

		return $this->pages;
	}

	/**
	 * Prepare Settings API pages and types.
	 *
	 * @param bool $register_menus Whether to register menu pages.
	 * @return void
	 */
	public function prepare_pages( $register_menus = true ) {
		if ( $this->pages_ready )
			return;

		$this->pages = apply_filters( $this->prefix . '_settings_pages', [] );

		$types = [
			'page'			=> [],
			'subpage'		=> [],
			'settings_page'	=> []
		];

		foreach ( $this->pages as $page => $data ) {
			// skip invalid page types
			if ( empty( $data['type'] ) || ! array_key_exists( $data['type'], $types ) )
				continue;

			if ( $data['type'] === 'page' ) {
				if ( $register_menus ) {
					add_menu_page(
						$data['page_title'],
						$data['menu_title'],
						$data['capability'],
						$data['menu_slug'],
						[ $this, 'options_page' ],
						! empty( $data['icon'] ) ? $data['icon'] : '',
						! empty( $data['position'] ) ? $data['position'] : null
					);

					// Phase 8: Register visible submenus for each tab (compatibility shim)
					if ( ! empty( $data['tabs'] ) ) {
						foreach ( $data['tabs'] as $tab_key => $tab_data ) {
							add_submenu_page(
								$data['menu_slug'],
								$tab_data['label'],
								$tab_data['label'],
								$data['capability'],
								$data['menu_slug'] . '&tab=' . $tab_key,
								[ $this, 'options_page' ]
							);
						}

						// Remove first duplicate submenu entry
						remove_submenu_page( $data['menu_slug'], $data['menu_slug'] );
					}
				}

				// add page type
				$types['page'][$data['menu_slug']] = $page;
			// menu subpage?
			} elseif ( $data['type'] === 'subpage' ) {
				if ( $register_menus ) {
					add_submenu_page(
						$data['parent_slug'],
						$data['page_title'],
						$data['menu_title'],
						$data['capability'],
						$data['menu_slug'],
						[ $this, 'options_page' ]
					);
				}

				// add subpage type
				$types['subpage'][$data['menu_slug']] = $page;
			// menu settings page?
			} elseif ( $data['type'] === 'settings_page' ) {
				if ( $register_menus ) {
					add_options_page(
						$data['page_title'],
						$data['menu_title'],
						$data['capability'],
						$data['menu_slug'],
						[ $this, 'options_page' ]
					);
				}

				// add settings type
				$types['settings_page'][$data['menu_slug']] = $page;
			}
		}

		// set page types
		$this->page_types = $types;
		$this->pages_ready = true;
	}

	/**
	 * Load default scripts and styles.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$handler = $this->prefix . '-settings-api-style';

		wp_register_style( $handler, false );
		wp_enqueue_style( $handler );

		wp_add_inline_style( $handler, '
			.nav-tab-wrapper span.nav-span-disabled {
				cursor: not-allowed;
				float: left;
			}
			body.rtl .nav-tab-wrapper span.nav-span-disabled {
				float: right;
			}
			.nav-tab-wrapper a.nav-tab.nav-tab-disabled {
				pointer-events: none;
			}
			.nav-tab-wrapper a.nav-tab.nav-tab-disabled:hover {
				cursor: not-allowed;
			}
		' );
	}

	/**
	 * Add menu pages.
	 *
	 * @return void
	 */
	public function admin_menu_options() {
		$this->prepare_pages();
	}

	/**
	 * Render settings page.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function options_page() {
		global $pagenow;

		$valid_page = false;
		$page_args = [];
		$page_slug = '';
		$matched_slug = '';

		// get current screen
		$screen = get_current_screen();

		$page_raw = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
		$page_parts = $page_raw !== '' ? explode( '&', $page_raw, 2 ) : [ '' ];
		$page_slug = $page_parts[0] !== '' ? sanitize_key( $page_parts[0] ) : '';

		if ( ! empty( $page_parts[1] ) )
			parse_str( $page_parts[1], $page_args );

		// fallback for menu slugs with query args (legacy menus with tab routing)
		if ( ! $valid_page && $pagenow === 'admin.php' && $page_slug !== '' ) {
			if ( isset( $this->page_types['page'][$page_slug] ) ) {
				$valid_page = true;
				$page_type = 'page';
				$url_page = 'admin.php';
				$matched_slug = $page_slug;
			} elseif ( isset( $this->page_types['subpage'][$page_slug] ) ) {
				$valid_page = true;
				$page_type = 'subpage';
				$url_page = 'admin.php';
				$matched_slug = $page_slug;
			}
		}

		// display top level settings page?
		if ( ! $valid_page && $pagenow === 'admin.php' && preg_match( '/^toplevel_page_(' . implode( '|', $this->page_types['page'] ) . ')$/', $screen->base, $matches ) === 1 && ! empty( $matches[1] ) ) {
			$valid_page = true;
			$page_type = 'page';
			$url_page = 'admin.php';
			$matched_slug = $matches[1];
		}

		// display subpage?
		if ( ! $valid_page && $pagenow === 'admin.php' && ! empty( $this->page_types['subpage'] ) ) {
			foreach ( $this->page_types['subpage'] as $menu_slug => $page_key ) {
				if ( preg_match( '/^lightbox_page_(' . preg_quote( $menu_slug, '/' ) . ')$/', $screen->base, $matches ) === 1 ) {
					$valid_page = true;
					$page_type = 'subpage';
					$url_page = 'admin.php';
					$matched_slug = $matches[1];
					break;
				}
			}
		}

		// display settings page?
		if ( ! $valid_page && $pagenow === 'options-general.php' && preg_match( '/^(?:settings_page_)(' . implode( '|', array_keys( $this->page_types['settings_page'] ) ) . ')$/', $screen->base, $matches ) === 1 ) {
			$valid_page = true;
			$page_type = 'settings_page';
			$url_page = 'options-general.php';
			$matched_slug = $matches[1];
		}

		// skip invalid pages
		if ( ! $valid_page )
			return;

		$page_key = isset( $this->page_types[$page_type][$matched_slug] ) ? $this->page_types[$page_type][$matched_slug] : '';

		if ( empty( $page_key ) || empty( $this->pages[$page_key] ) || ! is_array( $this->pages[$page_key] ) )
			return;
		$tab_key = '';
		$section_key = '';
		$tabs = [];
		$sections = [];

		// any tabs?
		if ( array_key_exists( 'tabs', $this->pages[$page_key] ) && is_array( $this->pages[$page_key]['tabs'] ) ) {
			// get tabs
			$tabs = $this->pages[$page_key]['tabs'];

			// reset tabs
			reset( $tabs );

			// get first default tab
			$first_tab = key( $tabs );

			// get current tab
			$tab_key = ! empty( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? $_GET['tab'] : ( ! empty( $page_args['tab'] ) && array_key_exists( $page_args['tab'], $tabs ) ? $page_args['tab'] : $first_tab );

			// check current tab
			if ( ! empty( $_GET['tab'] ) )
				$tab_key = sanitize_key( $_GET['tab'] );
			elseif ( ! empty( $page_args['tab'] ) )
				$tab_key = sanitize_key( $page_args['tab'] );

			// invalid tab?
			if ( ! array_key_exists( $tab_key, $tabs ) )
				$tab_key = $first_tab;

			$tab_label = ! empty( $tabs[$tab_key]['label'] ) ? $tabs[$tab_key]['label'] : '';
			$tab_heading = ! empty( $tabs[$tab_key]['heading'] ) ? $tabs[$tab_key]['heading'] : '';

			// check for subpages (dynamic sections like lightbox scripts or gallery types)
			if ( ! empty( $tabs[$tab_key]['subpages'] ) ) {
				$sections = $tabs[$tab_key]['subpages'];

				// reset sections
				reset( $sections );

				// get first section as fallback
				$first_section = key( $sections );

				// use default_subpage if defined and valid, otherwise first section
				$default_section = ! empty( $tabs[$tab_key]['default_subpage'] ) && array_key_exists( $tabs[$tab_key]['default_subpage'], $sections ) 
					? $tabs[$tab_key]['default_subpage'] 
					: $first_section;

				// get current section from URL, fallback to default section
				$section_key = ! empty( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : ( ! empty( $page_args['section'] ) ? sanitize_key( $page_args['section'] ) : $default_section );

				// invalid section?
				if ( ! array_key_exists( $section_key, $sections ) )
					$section_key = $default_section;
			}
		} else {
			$tab_label = '';
		}

		if ( empty( $tabs ) )
			$tab_heading = '';

		$heading = $this->plugin !== '' ? __( $this->plugin, $this->domain ) : '';

		echo '
		<div class="wrap ' . esc_attr( $this->prefix ) . '-settings-wrapper responsive-lightbox-settings" data-settings-prefix="' . esc_attr( $this->prefix ) . '">';

		// render header with breadcrumbs
		// $this->render_header( $heading, $page_key, $tab_key, $section_key, $sections );

		// render tabs navigation
		if ( ! empty( $tabs ) ) {
			echo '
			<nav class="nav-tab-wrapper">';

			foreach ( $tabs as $key => $tab ) {
				if ( ! empty( $tab['disabled'] ) )
					$url = '';
				else
					$url = admin_url( $url_page . '?page=' . $matched_slug . '&tab=' . $key );

				if ( ! empty( $tab['disabled'] ) )
					echo '<span class="nav-span-disabled">';

				echo '
				<a class="nav-tab' . ( $tab_key === $key ? ' nav-tab-active' : '' ) . ( ! empty( $tab['disabled'] ) ? ' nav-tab-disabled' : '' ) . ( ! empty( $tab['class'] ) ? ' ' . esc_attr( $tab['class'] ) : '' ) . '" href="' . ( $url !== '' ? esc_url( $url ) : '#' ) . '">' . esc_html( $tab['label'] ) . '</a>';

				if ( ! empty( $tab['disabled'] ) )
					echo '</span>';
			}

			echo '
			</nav>';
		}

		// render subpage/section navigation (for Lightboxes/Galleries)
		if ( ! empty( $sections ) ) {
			echo '
			<div class="nav-sub-wrapper">
			<ul class="subsubsub">';

			$section_count = count( $sections );
			$i = 0;

			foreach ( $sections as $key => $section ) {
				$url = admin_url( $url_page . '?page=' . $matched_slug . '&tab=' . $tab_key . '&section=' . $key );

				echo '<li><a href="' . esc_url( $url ) . '"' . ( $section_key === $key ? ' class="current"' : '' ) . '>' . esc_html( $section['label'] ) . '</a></li>';
			}

			echo '
			</ul>
			</div>
			<div class="clear"></div>';
		}

		echo '
			<div class="content-wrapper">
			<h1 class="screen-reader-text">' . esc_html( $heading ) . '</h1>';

		// skip for internal options page
		if ( $page_type !== 'settings_page' )
			settings_errors();

		// get settings page classes
		$settings_class = apply_filters( $this->prefix . '_settings_page_class', [ $this->slug . '-settings', $tab_key . '-settings', $this->prefix . '-settings' ] );

		// sanitize settings page classes
		$settings_class = array_unique( array_filter( array_map( 'sanitize_html_class', $settings_class ) ) );

		// resolve setting group for sidebar/form
		if ( ! empty( $section_key ) && ! empty( $tabs[$tab_key]['subpages'][$section_key]['option_name'] ) ) {
			// subpage has its own option name
			$setting = $tabs[$tab_key]['subpages'][$section_key]['option_name'];
		} elseif ( ! empty( $tab_key ) ) {
			if ( ! empty( $tabs[$tab_key]['option_name'] ) ) {
				$setting = $tabs[$tab_key]['option_name'];
			} else {
				$setting = $this->prefix . '_' . $tab_key . '_settings';
			}
		} else {
			$setting = $this->prefix . '_' . $page_key . '_settings';
		}

		// capture sidebar output
		ob_start();
		do_action( $this->prefix . '_settings_sidebar', $setting, $page_type, $url_page, $tab_key, $section_key );
		$sidebar_html = trim( ob_get_clean() );

		// add has-sidebar class if sidebar has content
		if ( ! empty( $sidebar_html ) )
			$settings_class[] = 'has-sidebar';

		echo '
			<div class="' . implode( ' ', array_map( 'esc_attr', $settings_class ) ) . '">';

		$display_form = true;

		// determine the settings lookup key for form settings
		// for gallery tab, use section_key (e.g., basicgrid_gallery); for other tabs, use tab_key
		$form_lookup_key = $tab_key;
		if ( $tab_key === 'gallery' && ! empty( $section_key ) && isset( $this->settings[$section_key] ) ) {
			$form_lookup_key = $section_key;
		}

		// check form attribute
		if ( ! empty( $tab_key ) && ! empty( $tabs[$tab_key]['form'] ) ) {
			$form = $tabs[$tab_key]['form'];

			if ( isset( $form['buttons'] ) && ! $form['buttons'] )
				$display_form = false;
		} elseif ( ! empty( $form_lookup_key ) && isset( $this->settings[$form_lookup_key]['form'] ) ) {
			$form = $this->settings[$form_lookup_key]['form'];

			if ( isset( $form['buttons'] ) && ! $form['buttons'] )
				$display_form = false;
		} elseif ( $page_key !== '' && isset( $this->settings[$page_key]['form'] ) ) {
			$form = $this->settings[$page_key]['form'];

			if ( isset( $form['buttons'] ) && ! $form['buttons'] )
				$display_form = false;
		}

		if ( $display_form ) {
			echo '
				<form action="options.php" method="post" novalidate class="' . esc_attr( $this->prefix ) . '-settings-form">';
		}

		settings_fields( $setting );

		if ( $display_form )
			do_action( $this->prefix . '_settings_form', $setting, $page_type, $url_page, $tab_key, $section_key );

		// filter sections by tab and subpage if present
		global $wp_settings_sections;

		$original_sections = $wp_settings_sections;

		// determine the settings key - for gallery tab, use section_key (e.g., basicgrid_gallery)
		// for other tabs, use tab_key directly
		$settings_lookup_key = $tab_key;
		if ( $tab_key === 'gallery' && ! empty( $section_key ) && isset( $this->settings[$section_key] ) ) {
			$settings_lookup_key = $section_key;
		}

		if ( ! empty( $settings_lookup_key ) && isset( $this->settings[$settings_lookup_key]['sections'] ) ) {
			$filtered_sections = [];

			foreach ( $this->settings[$settings_lookup_key]['sections'] as $section_id => $section ) {
				// check tab match
				$tab_match = empty( $section['tab'] ) || $section['tab'] === $tab_key;

				// check subpage/section match
				$section_match = empty( $section['subpage'] ) || $section['subpage'] === $section_key;

				// include sections matching both criteria
				if ( $tab_match && $section_match ) {
					if ( isset( $wp_settings_sections[$setting][$section_id] ) ) {
						$filtered_sections[$section_id] = $wp_settings_sections[$setting][$section_id];
					}
				}
			}

			// replace with filtered sections
			if ( isset( $wp_settings_sections[$setting] ) ) {
				$wp_settings_sections[$setting] = $filtered_sections;
			}
		}

		do_settings_sections( $setting );

		// restore original sections
		$wp_settings_sections = $original_sections;

		if ( $display_form ) {
			$setting_hyphenated = str_replace( '_', '-', $setting );

			echo '
					<p class="submit">';

			submit_button( '', 'primary save-' . esc_attr( $setting_hyphenated ), 'save_' . $setting, false, [ 'id' => 'save-' . esc_attr( $setting_hyphenated ) ] );

			submit_button( __( 'Reset to defaults', 'responsive-lightbox' ), 'outline reset-' . esc_attr( $setting_hyphenated ), 'reset_' . $setting, false, [ 'id' => 'reset-' . esc_attr( $setting_hyphenated ) ] );

			echo '
					</p>
				</form>';
		}

		// output sidebar if it has content
		if ( ! empty( $sidebar_html ) ) {
			echo '
			<div class="' . esc_attr( $this->prefix ) . '-sidebar">' . $sidebar_html . '</div>';
		}

		echo '
			</div>
			</div>';

		echo '
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Render header with breadcrumbs.
	 *
	 * @param string $heading Main heading text.
	 * @param string $page_key Current page key.
	 * @param string $tab_key Current tab key.
	 * @param string $section_key Current section key.
	 * @param array $sections Available sections.
	 * @return void
	 */
	private function render_header( $heading, $page_key, $tab_key, $section_key, $sections ) {
		echo '
			<div class="header-wrapper">
				<span class="header-title">' . esc_html( $heading ) . '</span>';

		// render breadcrumbs if we have context
		if ( ! empty( $tab_key ) || ! empty( $section_key ) ) {
			echo '
				<div class="' . esc_attr( $this->prefix ) . '-breadcrumbs-container">';

			$breadcrumbs = [];

			// add tab to breadcrumbs
			if ( ! empty( $tab_key ) && ! empty( $this->pages[$page_key]['tabs'][$tab_key]['label'] ) ) {
				$breadcrumbs[] = $this->pages[$page_key]['tabs'][$tab_key]['label'];
			}

			// add section to breadcrumbs
			if ( ! empty( $section_key ) && ! empty( $sections[$section_key]['label'] ) ) {
				$section_label = $sections[$section_key]['label'];
				if ( $tab_key === 'gallery' ) {
					$default_suffix = ' ' . __( '(default)', 'responsive-lightbox' );
					if ( substr( $section_label, -strlen( $default_suffix ) ) === $default_suffix )
						$section_label = substr( $section_label, 0, -strlen( $default_suffix ) );
				}
				$breadcrumbs[] = $section_label;
			}

			if ( ! empty( $breadcrumbs ) ) {
				echo implode( ' <span class="rl-breadcrumb-separator">&rsaquo;</span> ', array_map( 'esc_html', $breadcrumbs ) );
			}

			echo '
				</div>';
		}

		echo '
			</div>';
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		$this->settings = apply_filters( $this->prefix . '_settings_data', [] );

		// check settings
		foreach ( $this->settings as $setting_id => $setting ) {
			// tabs?
			if ( is_array( $setting['option_name'] ) ) {
				foreach ( $setting['option_name'] as $tab => $option_name ) {
					$this->register_setting_fields( $tab, $setting, $option_name );
				}
			} else {
				$this->register_setting_fields( $setting_id, $setting );
			}
		}
	}

	/**
	 * Register setting with sections and fields.
	 *
	 * @param string $setting_id Setting identifier.
	 * @param array $setting Setting configuration.
	 * @param string $option_name Option name override.
	 * @return void
	 */
	public function register_setting_fields( $setting_id, $setting, $option_name = '' ) {
		if ( empty( $option_name ) )
			$option_name = $setting['option_name'];

		// add capability filter for option page (matches legacy behavior)
		add_filter( 'option_page_capability_' . $option_name, [ $this, 'manage_options_capability' ] );

		// register setting
		register_setting( $option_name, $option_name, ! empty( $setting['validate'] ) ? $setting['validate'] : [ $this, 'validate_settings' ] );

		// register setting sections
		if ( ! empty( $setting['sections'] ) ) {
			foreach ( $setting['sections'] as $section_id => $section ) {
				// skip unwanted sections
				if ( ! empty( $section['tab'] ) && $section['tab'] !== $setting_id )
					continue;

				// auto-generate section classes and wrapper
				$base_slug = sanitize_html_class( str_replace( '_', '-', $section_id ) );
				$section_prefix = apply_filters( $this->prefix . '_settings_section_prefix', $this->prefix );
				$section_prefix = sanitize_html_class( $section_prefix );
				$section_classes = $section_prefix . '-section ' . $section_prefix . '-section-' . $base_slug;

				$section_args = [
					'section_class'		=> $section_classes,
					'before_section'	=> '<section id="' . $section_prefix . '-section-' . $base_slug . '" class="%s">',
					'after_section'		=> '</section>',
				];

				add_settings_section(
					$section_id,
					! empty( $section['title'] ) ? esc_html( $section['title'] ) : '',
					! empty( $section['callback'] ) ? $section['callback'] : null,
					! empty( $section['page'] ) ? $section['page'] : $option_name,
					$section_args
				);
			}
		}

		// register setting fields - check both top-level and section-nested fields
		$all_fields = [];

		// collect fields from top-level 'fields' array
		if ( ! empty( $setting['fields'] ) ) {
			foreach ( $setting['fields'] as $field_key => $field ) {
				$all_fields[$field_key] = $field;
			}
		}

		// collect fields from sections (PVC-style nested structure)
		if ( ! empty( $setting['sections'] ) ) {
			foreach ( $setting['sections'] as $section_id => $section ) {
				if ( ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field_key => $field ) {
						// auto-assign section if not specified
						if ( empty( $field['section'] ) )
							$field['section'] = $section_id;

						$all_fields[$field_key] = $field;
					}
				}
			}
		}

		// register all collected fields
		if ( ! empty( $all_fields ) ) {
			foreach ( $all_fields as $field_key => $field ) {
				// skip unwanted fields
				if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
					continue;

				// set field ID
				$field_id = implode( '_', [ $this->prefix, $setting_id, $field_key ] );

				// skip rendering this field?
				if ( ! empty( $field['skip_rendering'] ) )
					continue;

				// prepare field args
				$args = array_merge( $this->prepare_field_args( $field, $field_id, $field_key, $setting_id, $option_name ), $field );
				$args['setting_id'] = $setting_id;
				$class = sanitize_html_class( str_replace( '_', '-', $field_id ) );
				$classes = [ $class ];

				if ( ! empty( $args['class'] ) ) {
					$extra_classes = preg_split( '/\s+/', trim( $args['class'] ) );
					$extra_classes = array_filter( $extra_classes );
					$extra_classes = array_map( 'sanitize_html_class', $extra_classes );
					$classes = array_merge( $classes, $extra_classes );
				}

				$classes = array_values( array_unique( array_filter( $classes ) ) );

				$field_class = implode( ' ', $classes );
				$wrapper_class = $class !== '' ? $class . '-row' : '';

				// preserve original field class for button/special types (before adding to wrapper)
				$args['original_class'] = ! empty( $field['class'] ) ? $field['class'] : '';

				// preserve user classes in wrapper - but not for button type (those are for the button element only)
				if ( ! empty( $field['class'] ) && ( ! isset( $field['type'] ) || $field['type'] !== 'button' ) )
					$wrapper_class .= ' ' . $field['class'];

				$args['class'] = $wrapper_class;
				$args['field_class'] = $field_class;
				$args['css_id'] = $class;

				add_settings_field(
					$field_id,
					! empty( $field['title'] ) ? esc_html( $field['title'] ) : '',
					[ $this, 'render_field' ],
					$option_name,
					! empty( $field['section'] ) ? esc_attr( $field['section'] ) : '',
					$args
				);
			}
		}
	}

	/**
	 * Prepare field arguments.
	 *
	 * @param array $field Field configuration.
	 * @param string $field_id Field identifier.
	 * @param string $field_key Field key.
	 * @param string $setting_id Setting identifier.
	 * @param string $setting_name Setting name/option name.
	 * @return array Prepared field arguments.
	 */
	public function prepare_field_args( $field, $field_id, $field_key, $setting_id, $setting_name ) {
		// get field type
		$field_type = ! empty( $field['type'] ) ? $field['type'] : '';

		// default lookup path
		$value = null;
		$default = null;
		$name = $setting_name . '[' . $field_key . ']';

		// check for parent (nested fields like configuration[glightbox][loop])
		if ( ! empty( $field['parent'] ) ) {
			$name = $setting_name . '[' . $field['parent'] . '][' . $field_key . ']';

			// try with setting_id first
			if ( isset( $this->object->options[$setting_id][$field['parent']][$field_key] ) ) {
				$value = $this->object->options[$setting_id][$field['parent']][$field_key];
			} elseif ( isset( $this->object->options[$field['parent']][$field_key] ) ) {
				// try without setting_id
				$value = $this->object->options[$field['parent']][$field_key];
			}

			// defaults
			if ( isset( $this->object->defaults[$setting_id][$field['parent']][$field_key] ) ) {
				$default = $this->object->defaults[$setting_id][$field['parent']][$field_key];
			} elseif ( isset( $this->object->defaults[$field['parent']][$field_key] ) ) {
				$default = $this->object->defaults[$field['parent']][$field_key];
			}
		} else {
			// nested mode?
			if ( $this->nested ) {
				$name = $setting_name . '[' . $setting_id . '][' . $field_key . ']';

				if ( isset( $this->object->options[$setting_id][$field_key] ) )
					$value = $this->object->options[$setting_id][$field_key];

				if ( isset( $this->object->defaults[$setting_id][$field_key] ) )
					$default = $this->object->defaults[$setting_id][$field_key];
			} else {
				// flat structure
				if ( isset( $this->object->options[$setting_id][$field_key] ) ) {
					$value = $this->object->options[$setting_id][$field_key];
				} elseif ( isset( $this->object->options[$field_key] ) ) {
					$value = $this->object->options[$field_key];
				}

				// defaults
				if ( isset( $this->object->defaults[$setting_id][$field_key] ) ) {
					$default = $this->object->defaults[$setting_id][$field_key];
				} elseif ( isset( $this->object->defaults[$field_key] ) ) {
					$default = $this->object->defaults[$field_key];
				}
			}
		}

		// use field-provided default if no default found in core defaults
		// (allows add-ons to specify defaults in field definitions)
		if ( $default === null && isset( $field['default'] ) )
			$default = $field['default'];

		if ( $field_type === 'custom' ) {
			$value = null;
			$default = null;
		}

		// for radio/select, ensure a usable value is always set
		if ( in_array( $field_type, [ 'radio', 'select' ], true ) ) {
			$options = ! empty( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : [];
			$value_in_options = ! empty( $options ) && ( array_key_exists( $value, $options ) || array_key_exists( (string) $value, $options ) );
			$needs_value = $value === null || $value === '' || $value === false || is_array( $value ) || ! $value_in_options;
		} else {
			$needs_value = false;
		}

		if ( $needs_value ) {
			$has_default = $default !== null && $default !== '' && ! is_array( $default );
			if ( $has_default ) {
				$value = $default;
			}

			$value_in_options = ! empty( $options ) && ( array_key_exists( $value, $options ) || array_key_exists( (string) $value, $options ) );

			if ( $value === null || $value === '' || $value === false || is_array( $value ) || ! $value_in_options ) {
				if ( ! empty( $options ) ) {
					reset( $options );
					$value = key( $options );
				}
			}
		}

		return [
			'id'				=> $field_id,
			'html_id'			=> sanitize_html_class( str_replace( '_', '-', $field_id ) ),
			'name'				=> $name,
			'class'				=> ! empty( $field['class'] ) ? $field['class'] : '',
			'type'				=> $field_type,
			'label'				=> ! empty( $field['label'] ) ? $field['label'] : '',
			'description'		=> ! empty( $field['description'] ) ? $field['description'] : '',
			'text'				=> ! empty( $field['text'] ) ? $field['text'] : '',
			'min'				=> isset( $field['min'] ) ? (int) $field['min'] : 0,
			'max'				=> isset( $field['max'] ) ? (int) $field['max'] : 0,
			'options'			=> ! empty( $field['options'] ) ? $field['options'] : [],
			'callback'			=> ! empty( $field['callback'] ) ? $field['callback'] : null,
			'validate'			=> ! empty( $field['validate'] ) ? $field['validate'] : null,
			'callback_args'		=> ! empty( $field['callback_args'] ) ? $field['callback_args'] : [],
			'default'			=> $default,
			'value'				=> $value,
			'setting_id'		=> $setting_id,
			'parent'			=> ! empty( $field['parent'] ) ? $field['parent'] : '',
			'subpage'			=> ! empty( $field['subpage'] ) ? $field['subpage'] : '',
			'animation'			=> ! empty( $field['animation'] ) ? $field['animation'] : '',
			'logic'				=> ! empty( $field['logic'] ) ? $field['logic'] : null,
			'fallback_option'	=> ! empty( $field['fallback_option'] ) ? sanitize_key( $field['fallback_option'] ) : '',
			'classname'			=> ! empty( $field['classname'] ) ? $field['classname'] : '',
			'url'				=> ! empty( $field['url'] ) ? $field['url'] : '',
			'prepend'			=> ! empty( $field['prepend'] ) ? $field['prepend'] : '',
			'append'			=> ! empty( $field['append'] ) ? $field['append'] : '',
			'fields'			=> ! empty( $field['fields'] ) ? $field['fields'] : []
		];
	}

	/**
	 * Render settings field.
	 *
	 * @param array $args Field arguments.
	 * @return void|string
	 */
	public function render_field( $args ) {
		if ( empty( $args ) || ! is_array( $args ) )
			return;

		// build wrapper classes
		$wrapper_classes = [ $this->prefix . '-field', $this->prefix . '-field-type-' . $args['type'] ];

		if ( $args['type'] === 'color_picker' )
			$wrapper_classes[] = $this->prefix . '-field-type-color';

		if ( ! empty( $args['class'] ) )
			$wrapper_classes[] = $args['class'];

		// add disabled class if field is disabled
		if ( ! empty( $args['disabled'] ) && $args['disabled'] === true && empty( $args['available'] ) )
			$wrapper_classes[] = $this->prefix . '-disabled';

		// build wrapper attributes
		$wrapper_attrs = ' id="' . esc_attr( str_replace( '_', '-', $args['id'] ) ) . '-setting" class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';

		// add conditional data attributes
		$data_attrs = '';
		$conditions = [];
		$fallback_option = ! empty( $args['fallback_option'] ) ? $args['fallback_option'] : '';

		if ( ! empty( $args['logic'] ) && is_array( $args['logic'] ) ) {
			if ( isset( $args['logic']['field'] ) ) {
				$conditions = [ $args['logic'] ];
			} else {
				$conditions = $args['logic'];
			}
		}

		if ( ! empty( $conditions ) ) {
			$data_attr_prefix = sanitize_html_class( $this->prefix );
			$normalized_conditions = [];

			foreach ( $conditions as $condition ) {
				if ( empty( $condition['field'] ) || empty( $condition['operator'] ) )
					continue;

				$field = $condition['field'];

				if ( strpos( $field, '-' ) === false && ! empty( $args['setting_id'] ) ) {
					$field_id = implode( '_', [ $this->prefix, $args['setting_id'], $field ] );
					$field = sanitize_html_class( str_replace( '_', '-', $field_id ) );
				}

				$normalized_conditions[] = [
					'field'		=> $field,
					'operator'	=> $condition['operator'],
					'value'		=> isset( $condition['value'] ) ? $condition['value'] : '',
					'scope'		=> ! empty( $condition['scope'] ) ? sanitize_key( $condition['scope'] ) : '',
					'action'	=> ! empty( $condition['action'] ) ? sanitize_key( $condition['action'] ) : '',
					'target'	=> ! empty( $condition['target'] ) ? sanitize_text_field( $condition['target'] ) : '',
					'container'	=> ! empty( $condition['container'] ) ? sanitize_text_field( $condition['container'] ) : '',
				];
			}

			if ( ! empty( $normalized_conditions ) && $data_attr_prefix !== '' ) {
				if ( ! empty( $args['animation'] ) && in_array( $args['animation'], [ 'fade', 'slide' ], true ) ) {
					$data_attrs .= ' data-' . $data_attr_prefix . '-animation="' . esc_attr( $args['animation'] ) . '"';
				}
				$data_attrs .= ' data-' . $data_attr_prefix . '-logic="' . esc_attr( wp_json_encode( $normalized_conditions ) ) . '"';
			}
		}

		if ( $fallback_option !== '' )
			$data_attrs .= ' data-' . $this->prefix . '-fallback-option="' . esc_attr( $fallback_option ) . '"';

		$wrapper_attrs .= $data_attrs;

		$html = '<div' . $wrapper_attrs . '>';

		if ( ! empty( $args['before_field'] ) )
			$html .= $args['before_field'];

		switch ( $args['type'] ) {
			case 'boolean':
				if ( empty( $args['disabled'] ) )
					$html .= '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="false" />';

				$html .= '<label><input id="' . esc_attr( $args['html_id'] ) . '" type="checkbox" role="switch" name="' . esc_attr( $args['name'] ) . '" value="true" ' . checked( (bool) $args['value'], true, false ) . ' ' . disabled( empty( $args['disabled'] ), false, false ) . ' />' . wp_kses_post( $args['label'] ) . '</label>';
				break;

			case 'button':
				// Use original_class (preserved before wrapper overwrites class)
				$button_class = 'button';
				if ( ! empty( $args['original_class'] ) ) {
					$button_class .= ' ' . $args['original_class'];
				} elseif ( ! empty( $args['classname'] ) ) {
					$button_class .= ' ' . $args['classname'];
				} else {
					$button_class .= ' button-secondary';
				}

				$button_id = ! empty( $args['button_id'] ) ? $args['button_id'] : $args['html_id'];

				// prepend
				if ( ! empty( $args['prepend'] ) )
					$html .= '<span>' . esc_html( $args['prepend'] ) . '</span> ';

				// link button (legacy pattern) or actual button
				if ( ! empty( $args['url'] ) ) {
					$html .= '<a href="' . esc_url( $args['url'] ) . '" id="' . esc_attr( $button_id ) . '" class="' . esc_attr( $button_class ) . '">' . esc_html( $args['label'] ) . '</a>';
				} else {
					$html .= '<button type="button" id="' . esc_attr( $button_id ) . '" class="' . esc_attr( $button_class ) . '"' . disabled( ! empty( $args['disabled'] ), true, false ) . '>' . esc_html( $args['label'] ) . '</button>';
				}

				// append
				if ( ! empty( $args['append'] ) )
					$html .= ' <span>' . esc_html( $args['append'] ) . '</span>';

				break;

			case 'radio':
				if ( empty( $args['options'] ) || ! is_array( $args['options'] ) )
					break;

				$display_type = ! empty( $args['display_type'] ) && in_array( $args['display_type'], [ 'horizontal', 'vertical' ], true ) ? $args['display_type'] : 'vertical';
				$disabled_keys = ( isset( $args['disabled'] ) && is_array( $args['disabled'] ) ) ? $args['disabled'] : [];
				$selected_key = $args['value'];
				$selected_valid = array_key_exists( $selected_key, $args['options'] ) || array_key_exists( (string) $selected_key, $args['options'] );

				if ( $selected_valid && ! empty( $disabled_keys ) && in_array( $selected_key, $disabled_keys, true ) )
					$selected_valid = false;

				if ( ! $selected_valid ) {
					$selected_key = null;
					foreach ( $args['options'] as $key => $name ) {
						if ( empty( $disabled_keys ) || ! in_array( $key, $disabled_keys, true ) ) {
							$selected_key = $key;
							break;
						}
					}
				}

				if ( count( $args['options'] ) > 1 )
					$html .= '<div class="' . esc_attr( $this->prefix ) . '-field-group ' . esc_attr( $this->prefix ) . '-radio-group ' . $display_type . '">';

				foreach ( $args['options'] as $key => $name ) {
					$is_disabled = ! empty( $args['disabled'] ) && ( is_array( $args['disabled'] ) && in_array( $key, $args['disabled'], true ) || $args['disabled'] === true );
					$label_classes = [];

					if ( $is_disabled && is_array( $args['disabled'] ) )
						$label_classes[] = $this->prefix . '-disabled';

					$label_class = ! empty( $label_classes ) ? ' class="' . implode( ' ', $label_classes ) . '"' : '';
					$display_name = esc_html( $name );

					$html .= '<label for="' . esc_attr( $args['html_id'] . '-' . $key ) . '"' . $label_class . '><input id="' . esc_attr( $args['html_id'] . '-' . $key ) . '" type="radio" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $key ) . '" ' . checked( $key, $selected_key, false ) . ' ' . disabled( $is_disabled, true, false ) . ' />' . $display_name . '</label> ';
				}

				if ( count( $args['options'] ) > 1 )
					$html .= '</div>';
				break;

			case 'checkbox':
				// possible "empty" value
				if ( $args['value'] === 'empty' )
					$args['value'] = [];

				// ensure value is an array
				if ( ! is_array( $args['value'] ) )
					$args['value'] = [];

				$display_type = ! empty( $args['display_type'] ) && in_array( $args['display_type'], [ 'horizontal', 'vertical' ], true ) ? $args['display_type'] : 'vertical';

				$html .= '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="empty" />';

				if ( empty( $args['options'] ) || ! is_array( $args['options'] ) )
					break;

				if ( count( $args['options'] ) > 1 )
					$html .= '<div class="' . esc_attr( $this->prefix ) . '-field-group ' . esc_attr( $this->prefix ) . '-checkbox-group ' . $display_type . '">';

				foreach ( $args['options'] as $key => $name ) {
					$is_disabled = ! empty( $args['disabled'] ) && ( is_array( $args['disabled'] ) && in_array( $key, $args['disabled'], true ) || $args['disabled'] === true );
					$label_classes = [];

					if ( $is_disabled && is_array( $args['disabled'] ) )
						$label_classes[] = $this->prefix . '-disabled';

					$label_class = ! empty( $label_classes ) ? ' class="' . implode( ' ', $label_classes ) . '"' : '';
					$display_name = esc_html( $name );

					$html .= '<label' . $label_class . '><input id="' . esc_attr( $args['html_id'] . '-' . $key ) . '" type="checkbox" name="' . esc_attr( $args['name'] ) . '[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $args['value'] ), true, false ) . ' ' . disabled( $is_disabled, true, false ) . ' />' . $display_name . '</label>';
				}

				if ( count( $args['options'] ) > 1 )
					$html .= '</div>';
				break;

			case 'select':
				$html .= '<select id="' . esc_attr( $args['html_id'] ) . '" name="' . esc_attr( $args['name'] ) . '" ' . disabled( empty( $args['disabled'] ), false, false ) . '>';

				foreach ( $args['options'] as $key => $name ) {
					$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $args['value'], $key, false ) . '>' . esc_html( $name ) . '</option>';
				}

				$html .= '</select>';
				break;

			case 'multiple':
				if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
					$html .= '<fieldset>';

					$count = 1;
					$count_fields = count( $args['fields'] );

					foreach ( $args['fields'] as $subfield_id => $subfield ) {
					// check if subfield has parent (e.g., configuration[lightcase][transition])
					$subfield_parent = ! empty( $subfield['parent'] ) ? $subfield['parent'] : null;
					
					// resolve default and value with parent awareness
					$subfield_default = null;
					$subfield_value = null;
					
					if ( $subfield_parent ) {
						// nested with parent: defaults[configuration][lightcase][transition]
						if ( isset( $this->object->defaults[$args['setting_id']][$subfield_parent][$subfield_id] ) )
							$subfield_default = $this->object->defaults[$args['setting_id']][$subfield_parent][$subfield_id];
						
						if ( isset( $this->object->options[$args['setting_id']][$subfield_parent][$subfield_id] ) )
							$subfield_value = $this->object->options[$args['setting_id']][$subfield_parent][$subfield_id];
					} else {
						// flat: defaults[configuration][transition]
						if ( isset( $this->object->defaults[$args['setting_id']][$subfield_id] ) )
							$subfield_default = $this->object->defaults[$args['setting_id']][$subfield_id];
						
						if ( isset( $this->object->options[$args['setting_id']][$subfield_id] ) )
							$subfield_value = $this->object->options[$args['setting_id']][$subfield_id];
					}
					
					// use field-level default if available
					if ( $subfield_default === null && isset( $subfield['default'] ) )
						$subfield_default = $subfield['default'];
					
					// for radio/select in multiple fields, ensure value is set (safety fallback)
					$subfield_type = ! empty( $subfield['type'] ) ? $subfield['type'] : 'text';
					$subfield_options = ! empty( $subfield['options'] ) && is_array( $subfield['options'] ) ? $subfield['options'] : [];
					$subfield_value_in_options = ! empty( $subfield_options ) && ( array_key_exists( $subfield_value, $subfield_options ) || array_key_exists( (string) $subfield_value, $subfield_options ) );

					if ( in_array( $subfield_type, [ 'radio', 'select' ], true ) && ( $subfield_value === null || $subfield_value === '' || $subfield_value === false || is_array( $subfield_value ) || ! $subfield_value_in_options ) ) {
						$has_sub_default = $subfield_default !== null && $subfield_default !== '' && ! is_array( $subfield_default );
						if ( $has_sub_default ) {
							$subfield_value = $subfield_default;
						}

						if ( $subfield_value === null || $subfield_value === '' || $subfield_value === false || is_array( $subfield_value ) || ! $subfield_value_in_options ) {
							if ( ! empty( $subfield_options ) ) {
								reset( $subfield_options );
								$subfield_value = key( $subfield_options );
							}
						}
					}

					$base_name = preg_replace( '/\[[^\]]+\]$/', '', $args['name'] );
					$subfield_name = $subfield_parent ? $base_name . '[' . $subfield_parent . '][' . $subfield_id . ']' : $base_name . '[' . $subfield_id . ']';

					// prepare subfield args
					$subfield_args = [
						'id'			=> $args['id'] . '-' . $subfield_id,
						'html_id'		=> $args['html_id'] . '-' . sanitize_html_class( str_replace( '_', '-', $subfield_id ) ),
						'name'			=> $subfield_name,
						'type'			=> $subfield_type,
						'label'			=> ! empty( $subfield['label'] ) ? $subfield['label'] : '',
						'description'	=> ! empty( $subfield['description'] ) ? $subfield['description'] : '',
						'class'			=> ! empty( $subfield['class'] ) ? $subfield['class'] : '',
						'disabled'		=> ! empty( $subfield['disabled'] ),
						'options'		=> $subfield_options,
						'default'		=> $subfield_default,
						'value'			=> $subfield_value,
						'setting_id'	=> $args['setting_id'],
						'parent'		=> $subfield_parent ? $subfield_parent : '',
						'return'		=> true
					];

					// pass callback/callback_args for custom subfields (enables Conditional Logic, etc.)
					if ( ! empty( $subfield['callback'] ) )
						$subfield_args['callback'] = $subfield['callback'];
					if ( ! empty( $subfield['callback_args'] ) )
						$subfield_args['callback_args'] = $subfield['callback_args'];
					
					// pass logic and animation for conditional visibility
					if ( ! empty( $subfield['logic'] ) )
						$subfield_args['logic'] = $subfield['logic'];
					if ( ! empty( $subfield['animation'] ) )
						$subfield_args['animation'] = $subfield['animation'];

					$html .= $this->render_field( $subfield_args );

					if ( $count < $count_fields )
						$html .= '<br />';

					$count++;
				}

				$html .= '</fieldset>';
		}
		break;

		case 'number':
		$html .= ( ! empty( $args['prepend'] ) ? wp_kses_post( $args['prepend'] ) : '' );
		$html .= '<input id="' . esc_attr( $args['html_id'] ) . '" type="text" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '" class="small-text" />';
		$html .= ( ! empty( $args['append'] ) ? wp_kses_post( $args['append'] ) : '' );
		break;

		case 'range':
			$range_attrs = '';

			if ( isset( $args['min'] ) && $args['min'] !== 0 )
				$range_attrs .= ' min="' . esc_attr( (int) $args['min'] ) . '"';

			if ( isset( $args['max'] ) && $args['max'] !== 0 )
				$range_attrs .= ' max="' . esc_attr( (int) $args['max'] ) . '"';

			$html .= '<div class="' . $this->prefix . '-range-control">';
			$html .= '<input id="' . esc_attr( $args['html_id'] ) . '" type="range" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '"' . $range_attrs . ' ' . disabled( empty( $args['disabled'] ), false, false ) . ' />';
			$html .= '</div>';
			break;

	case 'color':
	case 'color_picker':
		$color_value = esc_attr( $args['value'] );
		$color_name = esc_attr( $args['name'] );
		$input_id = esc_attr( $args['html_id'] );
		$input_class = $this->prefix . '-color-input';

		if ( ! empty( $args['subclass'] ) )
			$input_class .= ' ' . esc_attr( $args['subclass'] );

		$swatch_style = ' style="background-color: ' . $color_value . ';"';

		$html .= '<div class="' . $this->prefix . '-color-control">';
		$html .= '<input id="' . $input_id . '" type="text" name="' . $color_name . '" value="' . $color_value . '" class="small-text ' . $input_class . '" />';
		$html .= '<button type="button" class="' . $this->prefix . '-color-swatch"' . $swatch_style . ' aria-label="' . esc_attr__( 'Open color picker', 'responsive-lightbox' ) . '" aria-expanded="false"></button>';
		$html .= '<div class="' . $this->prefix . '-color-popover" aria-hidden="true"><hex-color-picker class="' . $this->prefix . '-hex-color-picker" color="' . $color_value . '"></hex-color-picker></div>';
		$html .= '</div>';
		break;

	case 'custom':
		if ( ! empty( $args['callback'] ) && is_callable( $args['callback'] ) )
			$html .= call_user_func( $args['callback'], $args );
		break;

	case 'info':
		$html .= '<span' . ( ! empty( $args['subclass'] ) ? ' class="' . esc_attr( $args['subclass'] ) . '"' : '' ) . '>' . esc_html( $args['text'] ) . '</span>';
		break;

	case 'class':
	case 'input':
	case 'text':
	default:
		$empty_disabled = empty( $args['disabled'] );

		$html .= ( ! empty( $args['prepend'] ) ? wp_kses_post( $args['prepend'] ) : '' );
		$html .= '<input id="' . esc_attr( $args['html_id'] ) . '"' . ( ! empty( $args['subclass'] ) ? ' class="' . esc_attr( $args['subclass'] ) . '"' : '' ) . ' type="text" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '" ' . disabled( $empty_disabled, false, false ) . '/>';
		$html .= ( ! empty( $args['append'] ) ? wp_kses_post( $args['append'] ) : '' );

		if ( ! $empty_disabled )
			$html .= '<input' . ( $empty_disabled ? '' : ' class="hidden"' ) . ' type="text" value="' . esc_attr( $args['value'] ) . '" name="' . esc_attr( $args['name'] ) . '">';
	}

		if ( ! empty( $args['after_field'] ) )
			$html .= $args['after_field'];

		if ( ! empty( $args['description'] ) )
			$html .= '<p class="description">' . $args['description'] . '</p>';

		$html .= '</div>';

		if ( ! empty( $args['return'] ) )
			return $html;
		else
			echo $html;
	}

	/**
	 * Validate settings field.
	 *
	 * @param mixed $value Field value.
	 * @param string $type Field type.
	 * @param array $args Field arguments.
	 * @return mixed Validated value.
	 */
	public function validate_field( $value = null, $type = '', $args = [] ) {
		if ( is_null( $value ) )
			return null;

		switch ( $type ) {
			case 'boolean':
				$value = ( $value === 'true' || $value === true );
				break;

			case 'radio':
				$value = is_array( $value ) ? $args['default'] : sanitize_key( $value );

				// disallow disabled radios
				if ( ! empty( $args['disabled'] ) && in_array( $value, $args['disabled'], true ) )
					$value = $args['default'];
				break;

			case 'checkbox':
				if ( $value === 'empty' )
					$value = [];
				else {
					if ( is_array( $value ) && ! empty( $value ) ) {
						$value = array_map( 'sanitize_key', $value );
						$values = [];

						foreach ( $value as $single_value ) {
							if ( array_key_exists( $single_value, $args['options'] ) )
								$values[] = $single_value;
						}

						$value = $values;
					} else {
						$value = [];
					}
				}
				break;

			case 'number':
				$value = (int) $value;

				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

			case 'range':
				$value = (int) $value;

				if ( isset( $args['min'] ) && $value < $args['min'] )
					$value = $args['min'];

				if ( isset( $args['max'] ) && $value > $args['max'] )
					$value = $args['max'];
				break;

	case 'color':
	case 'color_picker':
				$value = sanitize_text_field( $value );

				if ( ! preg_match( '/^#[a-f0-9]{3,6}$/i', $value ) )
					$value = $args['default'] ?? '#000000';
				break;

	case 'info':
		$value = '';
		break;

	case 'custom':
		// handled by custom validate callback
		break;

	case 'class':
		$value = trim( $value );

		if ( strpos( $value, ' ' ) !== false ) {
			$value = array_unique( array_filter( array_map( 'sanitize_html_class', explode( ' ', $value ) ) ) );

			if ( ! empty( $value ) )
				$value = implode( ' ', $value );
			else
				$value = '';
		} else {
			$value = sanitize_html_class( $value, $args['default'] );
		}
		break;

	case 'input':
	case 'text':
			case 'select':
	default:
				$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
				break;
		}

		return stripslashes_deep( $value );
	}

	/**
	 * Validate settings.
	 *
	 * @param array $input Input data.
	 * @return array Validated data.
	 */
	public function validate_settings( $input ) {
		// check capability
		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		// check option page
		if ( empty( $_POST['option_page'] ) )
			return $input;

		// try to get setting name and ID
		foreach ( $this->settings as $id => $setting ) {
			// tabs?
			if ( is_array( $setting['option_name'] ) ) {
				foreach ( $setting['option_name'] as $tab => $option_name ) {
					// found valid setting?
					if ( $option_name === $_POST['option_page'] ) {
						$setting_id = $tab;
						$setting_name = $option_name;
						$setting_key = $id;
						break 2;
					}
				}
			} else {
				// found valid setting?
				if ( $setting['option_name'] === $_POST['option_page'] ) {
					$setting_key = $setting_id = $id;
					$setting_name = $setting['option_name'];
					break;
				}
			}
		}

		// check setting id
		if ( empty( $setting_id ) )
			return $input;

		// save settings
		if ( isset( $_POST['save_' . $setting_name] ) ) {
			$input = $this->validate_input_settings( $setting_id, $setting_key, $input );

			add_settings_error( $setting_name, 'settings_saved', __( 'Settings saved.', 'responsive-lightbox' ), 'updated' );
		// reset settings
		} elseif ( isset( $_POST['reset_' . $setting_name] ) ) {
			// get default values
			$input = $this->object->defaults[$setting_id];

			// check custom reset functions
			if ( ! empty( $this->settings[$setting_key]['fields'] ) ) {
				foreach ( $this->settings[$setting_key]['fields'] as $field_id => $field ) {
					// skip invalid tab field if any
					if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
						continue;

					// custom reset function?
					if ( ! empty( $field['reset'] ) ) {
						if ( $this->callback_function_exists( $field['reset'] ) ) {
							if ( $field['type'] === 'custom' )
								$input = call_user_func( $field['reset'], $input, $field );
							else
								$input[$field_id] = call_user_func( $field['reset'], $input[$field_id], $field );
						}
					}
				}
			}

			add_settings_error( $setting_name, 'settings_restored', __( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
		}

		do_action( $this->prefix . '_configuration_updated', 'settings', $input );

		return $input;
	}

	/**
	 * Validate input settings.
	 *
	 * @param string $setting_id Setting identifier.
	 * @param string $setting_key Setting key.
	 * @param array $input Input data.
	 * @return array Validated data.
	 */
	public function validate_input_settings( $setting_id, $setting_key, $input ) {
		$all_fields = [];

		// collect fields from top-level 'fields' array
		if ( ! empty( $this->settings[$setting_key]['fields'] ) ) {
			foreach ( $this->settings[$setting_key]['fields'] as $field_key => $field ) {
				$all_fields[$field_key] = $field;
			}
		}

		// collect fields from sections (PVC-style nested structure)
		if ( ! empty( $this->settings[$setting_key]['sections'] ) ) {
			foreach ( $this->settings[$setting_key]['sections'] as $section_id => $section ) {
				if ( ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field_key => $field ) {
						// auto-assign section if not specified
						if ( empty( $field['section'] ) )
							$field['section'] = $section_id;

						$all_fields[$field_key] = $field;
					}
				}
			}
		}

		if ( ! empty( $all_fields ) ) {
			foreach ( $all_fields as $field_id => $field ) {
				// skip saving this field?
				if ( ! empty( $field['skip_saving'] ) )
					continue;

				// skip invalid tab field if any
				if ( ! empty( $field['tab'] ) && $field['tab'] !== $setting_id )
					continue;

				$field_type = ! empty( $field['type'] ) ? $field['type'] : 'text';

				// custom validate function?
				if ( ! empty( $field['validate'] ) ) {
					if ( $this->callback_function_exists( $field['validate'] ) ) {
						if ( $field['type'] === 'custom' )
							$input = call_user_func( $field['validate'], $input, $field );
						else
							$input[$field_id] = isset( $input[$field_id] ) ? call_user_func( $field['validate'], $input[$field_id], $field ) : $this->object->defaults[$setting_id][$field_id];
					} else {
						$input[$field_id] = $this->object->defaults[$setting_id][$field_id] ?? ( $field['default'] ?? null );
					}
				} else {
					// field data?
					if ( isset( $input[$field_id] ) ) {
						// make sure default value is available
						if ( ! isset( $field['default'] ) )
							$field['default'] = $this->object->defaults[$setting_id][$field_id];

						$input[$field_id] = $this->validate_field( $input[$field_id], $field['type'], $field );
					} else {
						if ( $field_type === 'boolean' ) {
							$input[$field_id] = false;
						} elseif ( $field_type === 'checkbox' ) {
							$input[$field_id] = [];
						} else {
							$input[$field_id] = $this->object->defaults[$setting_id][$field_id] ?? ( $field['default'] ?? null );
						}
					}
				}

				// update input data
				$this->input_settings = $input;

				// add this field as validated
				$this->validated_settings[] = $field_id;
			}
		}

		return $input;
	}

	/**
	 * Check whether callback is a valid function.
	 *
	 * @param string|array $callback Callback to check.
	 * @return bool Whether callback exists.
	 */
	public function callback_function_exists( $callback ) {
		if ( is_array( $callback ) ) {
			list( $object, $function ) = $callback;
			$function_exists = method_exists( $object, $function );
		} elseif ( is_string( $callback ) ) {
			$function_exists = function_exists( $callback );
		} else {
			$function_exists = false;
		}

		return $function_exists;
	}

	/**
	 * Get value based on minimum and maximum.
	 *
	 * @param array $data Data array.
	 * @param string $setting_name Setting name.
	 * @param int $default Default value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int Validated integer value.
	 */
	public function get_int_value( $data, $setting_name, $default, $min, $max ) {
		$value = array_key_exists( $setting_name, $data ) ? (int) $data[$setting_name] : $default;

		if ( $value > $max || $value < $min )
			$value = $default;

		return $value;
	}

	/**
	 * Add new capability to manage options.
	 *
	 * @return string Required capability.
	 */
	public function manage_options_capability() {
		$rl = Responsive_Lightbox();

		return $rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options';
	}
}

