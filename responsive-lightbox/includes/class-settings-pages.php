<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Pages class.
 *
 * Defines full Settings API page and tabs structure.
 *
 * @class Responsive_Lightbox_Settings_Pages
 */
class Responsive_Lightbox_Settings_Pages {

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'rl_settings_pages', [ $this, 'settings_pages' ], 20 );
	}

	/**
	 * Provide Settings API pages definition.
	 *
	 * @param array $pages Existing pages.
	 * @return array
	 */
	public function settings_pages( $pages ) {
		// hard break: define a single top-level page with tabs
		$pages = [];

		$rl = Responsive_Lightbox();

		$capability = apply_filters(
			'rl_lightbox_settings_capability',
			$rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options'
		);

		// determine default lightbox script
		$default_lightbox = ! empty( $rl->options['settings']['script'] ) ? $rl->options['settings']['script'] : '';
		$default_lightbox_subpage = $default_lightbox !== '' ? $default_lightbox : '';

		// lightbox scripts (subpages under Lightboxes)
		$lightbox_subpages = [];
		$scripts = Responsive_Lightbox_Settings_Data::get_scripts();
		foreach ( $scripts as $key => $script ) {
			$lightbox_subpages[$key] = [
				'label' => $script['name'] . ( $key === $default_lightbox ? ' ' . __( '(default)', 'responsive-lightbox' ) : '' )
			];
		}

		if ( $default_lightbox_subpage === '' || ! isset( $lightbox_subpages[$default_lightbox_subpage] ) ) {
			reset( $lightbox_subpages );
			$default_lightbox_subpage = key( $lightbox_subpages );
		}

		// determine default gallery type
		$default_gallery = ! empty( $rl->options['settings']['default_gallery'] ) ? $rl->options['settings']['default_gallery'] : '';
		$default_gallery_section = $default_gallery !== '' ? $default_gallery . '_gallery' : '';

		// gallery types (subpages under Galleries)
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );

		if ( isset( $gallery_types['default'] ) )
			unset( $gallery_types['default'] );

		$gallery_subpages = [];
		foreach ( $gallery_types as $key => $label ) {
			$is_default = $key === $default_gallery;
			$gallery_subpages[$key . '_gallery'] = [
				'label'		=> $label . ( $is_default ? ' ' . __( '(default)', 'responsive-lightbox' ) : '' ),
				'option_name'	=> 'responsive_lightbox_' . $key . '_gallery'
			];
		}

		if ( $default_gallery_section === '' || ! isset( $gallery_subpages[$default_gallery_section] ) ) {
			reset( $gallery_subpages );
			$default_gallery_section = key( $gallery_subpages );
		}

		// licenses tab is conditional
		$extensions = apply_filters( 'rl_settings_licenses', [] );

		$tabs = [
			'settings' => [
				'label'		=> __( 'General', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_settings'
			],
			'configuration' => [
				'label'		=> __( 'Lightboxes', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_configuration',
				'subpages'	=> $lightbox_subpages,
				'default_subpage' => $default_lightbox_subpage
			],
			'gallery' => [
				'label'		=> __( 'Galleries', 'responsive-lightbox' ),
				'subpages'	=> $gallery_subpages,
				'default_subpage' => $default_gallery_section
			],
			'builder' => [
				'label'		=> __( 'Builder', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_builder'
			],
			'folders' => [
				'label'		=> __( 'Folders', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_folders'
			],
			'capabilities' => [
				'label'		=> __( 'Capabilities', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_capabilities'
			],
			'remote_library' => [
				'label'		=> __( 'Remote Library', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_remote_library'
			]
		];

		// Backward compatibility: Convert legacy tab registrations to Settings API format
		// Add-ons using rl_settings_tabs_extra will automatically appear in Settings API UI
		$legacy_tabs = apply_filters( 'rl_settings_tabs_extra', [] );
		
		if ( ! empty( $legacy_tabs ) ) {
			foreach ( $legacy_tabs as $tab_key => $tab_data ) {
				// Skip if already registered via modern filter or if it's a core tab
				if ( isset( $tabs[$tab_key] ) )
					continue;
				
				// Convert legacy format to Settings API format
				$tabs[$tab_key] = [
					'label'		=> isset( $tab_data['name'] ) ? $tab_data['name'] : ucfirst( str_replace( '_', ' ', $tab_key ) ),
					'option_name'	=> isset( $tab_data['key'] ) ? $tab_data['key'] : 'responsive_lightbox_' . $tab_key
				];
			}
		}

		// Add licenses tab after add-on tabs (if extensions exist)
		if ( ! empty( $extensions ) ) {
			$tabs['licenses'] = [
				'label'		=> __( 'Licenses', 'responsive-lightbox' ),
				'option_name'	=> 'responsive_lightbox_licenses'
			];
		}

		// Add addons tab last
		$tabs['addons'] = [
			'label'		=> __( 'Add-ons', 'responsive-lightbox' ),
			'option_name'	=> 'responsive_lightbox_addons',
			'form'		=> [ 'buttons' => false ]
		];

		/**
		 * Allow add-ons to register tabs with Settings API.
		 *
		 * @since 2.7.0
		 * @param array $tabs Existing tabs array.
		 * @return array Modified tabs array with add-on tabs included.
		 */
		$tabs = apply_filters( 'rl_settings_api_tabs', $tabs );

		$pages['settings'] = [
			'type'		=> 'page',
			'menu_slug'	=> 'responsive-lightbox-settings',
			'page_title'	=> __( 'Responsive Lightbox & Gallery', 'responsive-lightbox' ),
			'menu_title'	=> __( 'Lightbox', 'responsive-lightbox' ),
			'capability'	=> $capability,
			'icon'		=> 'dashicons-format-image',
			'position'	=> 57,
			'tabs'		=> $tabs
		];

		return $pages;
	}
}
