<?php
/**
 * Responsive Lightbox Builder Settings
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Builder Settings class.
 *
 * @class Responsive_Lightbox_Settings_Builder
 */
class Responsive_Lightbox_Settings_Builder extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'builder';

	/**
	 * Validate settings for Builder tab.
	 *
	 * Handles field sanitization including permalink sanitization.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// check if this is a reset operation
		if ( $this->is_reset_request() ) {
			$input = $this->merge_with_defaults( [] );
			add_settings_error( 'reset_rl_builder', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
			return $input;
		}

		// sanitize all fields (includes permalink sanitization via sanitize_field)
		$input = $this->sanitize_fields( $input, 'builder' );

		return $input;
	}

	/**
	 * Provide settings data for this tab.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		// get main instance
		$rl = Responsive_Lightbox();

		// build archives category options
		$archives_category_options = [
			'all' => __( 'All', 'responsive-lightbox' )
		];

		// add categories if gallery builder, categories, and archives are enabled
		if ( $rl->options['builder']['gallery_builder'] && $rl->options['builder']['categories'] && $rl->options['builder']['archives'] ) {
			$terms = get_terms( [ 'taxonomy' => 'rl_category', 'hide_empty' => false ] );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$archives_category_options[$term->slug] = $term->name;
				}
			}
		}

		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_builder',
			'option_group'	=> 'responsive_lightbox_builder',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_builder' => [
					'title'			=> __( 'Gallery Builder Settings', 'responsive-lightbox' ),
					'description'	=> '',
					'fields'		=> [
						'gallery_builder' => [
							'title'	=> __( 'Gallery Builder', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable advanced gallery builder.', 'responsive-lightbox' )
						],
						'categories' => [
							'title'			=> __( 'Categories', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable Gallery Categories.', 'responsive-lightbox' ),
							'description'	=> __( 'Enable if you want to use Gallery Categories.', 'responsive-lightbox' )
						],
						'tags' => [
							'title'			=> __( 'Tags', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable Gallery Tags.', 'responsive-lightbox' ),
							'description'	=> __( 'Enable if you want to use Gallery Tags.', 'responsive-lightbox' )
						],
						'permalink' => [
							'title'			=> __( 'Gallery Permalink', 'responsive-lightbox' ),
							'type'			=> 'text',
							'description'	=> '<code>' . site_url() . '/<strong>' . untrailingslashit( esc_html( $rl->options['builder']['permalink'] ) ) . '</strong>/</code><br />' . esc_html__( 'Enter gallery page slug.', 'responsive-lightbox' )
						],
						'permalink_categories' => [
							'title'			=> __( 'Categories Permalink', 'responsive-lightbox' ),
							'type'			=> 'text',
							'description'	=> '<code>' . site_url() . '/<strong>' . untrailingslashit( esc_html( $rl->options['builder']['permalink_categories'] ) ) . '</strong>/</code><br />' . esc_html__( 'Enter gallery categories archive page slug.', 'responsive-lightbox' )
						],
						'permalink_tags' => [
							'title'			=> __( 'Tags Permalink', 'responsive-lightbox' ),
							'type'			=> 'text',
							'description'	=> '<code>' . site_url() . '/<strong>' . untrailingslashit( esc_html( $rl->options['builder']['permalink_tags'] ) ) . '</strong>/</code><br />' . esc_html__( 'Enter gallery tags archive page slug.', 'responsive-lightbox' )
						],
						'archives' => [
							'title'	=> __( 'Archives', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable gallery archives.', 'responsive-lightbox' )
						],
						'archives_category' => [
							'title'			=> __( 'Archives Category', 'responsive-lightbox' ),
							'type'			=> 'select',
							'description'	=> __( 'Select category for gallery archives.', 'responsive-lightbox' ),
							'options'		=> $archives_category_options
						]
					]
				]
			]
		];

		return $data;
	}
}
