<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_General class.
 *
 * General settings tab for the new Settings API.
 * Handles main plugin settings like lightbox script selection and general options.
 *
 * @class Responsive_Lightbox_Settings_General
 */
class Responsive_Lightbox_Settings_General extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'settings';

	/**
	 * Validate settings for General tab.
	 *
	 * Handles field sanitization, WooCommerce auto-enable rule,
	 * and system field preservation (update_notice, update_version).
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// check if this is a reset operation
		if ( $this->is_reset_request() ) {
			$input = $this->merge_with_defaults( [] );
			// preserve system fields even on reset
			$input = $this->preserve_system_fields( $input, [ 'update_version', 'update_notice' ] );
			add_settings_error( 'reset_rl_settings', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
			return $input;
		}

		// sanitize all fields
		$input = $this->sanitize_fields( $input, 'settings' );

		// merge with saved options to preserve fields not in current form
		$input = $this->merge_with_saved( $input );

		// preserve system fields (version tracking, update notices)
		$input = $this->preserve_system_fields( $input, [ 'update_notice' ] );

		// business rule: WooCommerce lightbox must be enabled when using RL gallery
		if ( isset( $input['default_woocommerce_gallery'] ) && $input['default_woocommerce_gallery'] !== 'default' ) {
			$input['woocommerce_gallery_lightbox'] = true;
		}

		return $input;
	}

	/**	 * Provide settings data for the General settings tab.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		$rl = Responsive_Lightbox();

		// get scripts from helper class
		$scripts = [];
		foreach ( Responsive_Lightbox_Settings_Data::get_scripts() as $key => $value ) {
			$scripts[$key] = $value['name'];
		}

		// get image sizes
		$sizes = apply_filters(
			'image_size_names_choose',
			[
				'thumbnail'	=> __( 'Thumbnail', 'responsive-lightbox' ),
				'medium'	=> __( 'Medium', 'responsive-lightbox' ),
				'large'		=> __( 'Large', 'responsive-lightbox' ),
				'full'		=> __( 'Full Size', 'responsive-lightbox' )
			]
		);

		// get gallery types
		$gallery_types = $rl->get_data( 'gallery_types' );
		$galleries = $builder_galleries = wp_parse_args( apply_filters( 'rl_gallery_types', [] ), $gallery_types );
		unset( $builder_galleries['default'] );

		// image title options from helper class
		$image_titles = Responsive_Lightbox_Settings_Data::get_image_titles();

		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_settings',
			'option_group'	=> 'responsive_lightbox_settings',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_settings' => [
					'title'			=> __( 'General Settings', 'responsive-lightbox' ),
					'description'	=> '',
					'fields'		=> [
						'tour' => [
							'title'			=> __( 'Introduction Tour', 'responsive-lightbox' ),
							'type'			=> 'button',
							'label'			=> __( 'Start Tour', 'responsive-lightbox' ),
							'description'	=> __( 'Take this tour to quickly learn about the use of this plugin.', 'responsive-lightbox' ),
							'class'			=> 'button-primary button-hero',
							'url'			=> wp_nonce_url( admin_url( 'admin.php?page=responsive-lightbox-settings&tab=settings&rl_start_tour=1' ), 'rl-start-tour', 'rl_nonce' )
						],
						'script' => [
							'title'			=> __( 'Default Lightbox', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> sprintf( __( 'Select your preferred ligthbox effect script or get one from our <a href="%s">premium extensions</a>.', 'responsive-lightbox' ), wp_nonce_url( add_query_arg( [ 'action' => 'rl-hide-notice' ], admin_url( 'admin.php?page=responsive-lightbox-settings&tab=addons' ) ), 'rl_action', 'rl_nonce' ) ),
							'options'		=> $scripts
						],
						'selector' => [
							'title'			=> __( 'Selector', 'responsive-lightbox' ),
							'type'			=> 'text',
							'description'	=> __( 'Enter the rel selector lightbox effect will be applied to.', 'responsive-lightbox' )
						],
						'image_links' => [
							'title'	=> __( 'Images', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable lightbox for WordPress image links.', 'responsive-lightbox' )
						],
						'image_title' => [
							'title'			=> __( 'Single Image Title', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select title for single images.', 'responsive-lightbox' ),
							'options'		=> $image_titles
						],
						'image_caption' => [
							'title'			=> __( 'Single Image Caption', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select caption for single images (if supported by selected lightbox and/or gallery).', 'responsive-lightbox' ),
							'options'		=> $image_titles
						],
						'images_as_gallery' => [
							'title'	=> __( 'Single Images as Gallery', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Display single post images as a gallery.', 'responsive-lightbox' )
						],
						'galleries' => [
							'title'	=> __( 'Galleries', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable lightbox for WordPress image galleries.', 'responsive-lightbox' )
						],
						'default_gallery' => [
							'title'			=> __( 'Default Gallery', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> sprintf( __( 'Select your preferred default gallery style or get one from our <a href="%s">premium extensions</a>.', 'responsive-lightbox' ), wp_nonce_url( add_query_arg( [ 'action' => 'rl-hide-notice' ], admin_url( 'admin.php?page=responsive-lightbox-settings&tab=addons' ) ), 'rl_action', 'rl_nonce' ) ),
							'options'		=> $galleries
						],
						'builder_gallery' => [
							'title'			=> __( 'Builder Gallery', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select your preferred default builder gallery style.', 'responsive-lightbox' ),
							'options'		=> $builder_galleries
						],
						'default_woocommerce_gallery' => [
							'title'			=> __( 'WooCommerce Gallery', 'responsive-lightbox' ),
							'type'			=> 'select',
							'disabled'		=> ! class_exists( 'WooCommerce' ),
							'description'	=> __( 'Select your preferred gallery style for WooCommerce product gallery.', 'responsive-lightbox' ),
							'options'		=> $galleries
						],
						'gallery_image_size' => [
							'title'			=> __( 'Gallery Image Size', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select image size for gallery image links.', 'responsive-lightbox' ),
							'options'		=> $sizes
						],
						'gallery_image_title' => [
							'title'			=> __( 'Gallery Image Title', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select title for the gallery images.', 'responsive-lightbox' ),
							'options'		=> $image_titles
						],
						'gallery_image_caption' => [
							'title'			=> __( 'Gallery Image Caption', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select caption for the gallery images (if supported by selected lightbox and/or gallery).', 'responsive-lightbox' ),
							'options'		=> $image_titles
						],
						'videos' => [
							'title'	=> __( 'Videos', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable lightbox for YouTube and Vimeo video links.', 'responsive-lightbox' )
						],
						'widgets' => [
							'title'	=> __( 'Widgets', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable lightbox for widgets content.', 'responsive-lightbox' )
						],
						'comments' => [
							'title'	=> __( 'Comments', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable lightbox for comments content.', 'responsive-lightbox' )
						],
						'force_custom_gallery' => [
							'title'	=> __( 'Force Lightbox', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Try to force lightbox for custom WP gallery replacements, like Jetpack or Visual Composer galleries.', 'responsive-lightbox' )
						],
						'woocommerce_gallery_lightbox' => [
							'title'		=> __( 'WooCommerce Lightbox', 'responsive-lightbox' ),
							'type'		=> 'boolean',
							'label'		=> __( 'Replace WooCommerce product gallery lightbox.', 'responsive-lightbox' ),
							'disabled'	=> ! class_exists( 'WooCommerce' ) || Responsive_Lightbox()->options['settings']['default_woocommerce_gallery'] !== 'default'
						],
						'enable_custom_events' => [
							'title'	=> __( 'Custom Events', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable triggering lightbox on custom jQuery events.', 'responsive-lightbox' )
						],
						'custom_events' => [
							'title'			=> '',
							'type'			=> 'text',
							'description'	=> __( 'Enter a space separated list of custom jQuery events.', 'responsive-lightbox' ),
							'logic'			=> [ 'field' => 'enable_custom_events', 'operator' => 'is', 'value' => 'true' ],
							'animation'		=> 'slide'
						],
						'loading_place' => [
							'title'			=> __( 'Loading Place', 'responsive-lightbox' ),
							'type'			=> 'radio',
							'description'	=> __( 'Select where all the lightbox scripts should be placed.', 'responsive-lightbox' ),
							'options'		=> [
								'header'	=> __( 'Header', 'responsive-lightbox' ),
								'footer'	=> __( 'Footer', 'responsive-lightbox' )
							]
						],
						'conditional_loading' => [
							'title'	=> __( 'Conditional Loading', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable to load scripts and styles only on pages that have images or galleries in post content.', 'responsive-lightbox' )
						],
						'deactivation_delete' => [
							'title'			=> __( 'Delete Data', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Delete all plugin settings on deactivation.', 'responsive-lightbox' ),
							'description'	=> __( 'Enable this to delete all plugin settings and also delete all plugin capabilities from all users on deactivation.', 'responsive-lightbox' )
						]
					]
				]
			]
		];

		return $data;
	}

}
