<?php
/**
 * Responsive Lightbox Folders Settings
 *
 * @package Responsive_Lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Folders Settings class.
 *
 * @class Responsive_Lightbox_Settings_Folders
 */
class Responsive_Lightbox_Settings_Folders extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'folders';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		// allow to load old taxonomies from settings page
		add_action( 'wp_ajax_rl-folders-load-old-taxonomies', [ $this, 'load_old_taxonomies' ] );
	}

	/**
	 * Validate settings for Folders tab.
	 *
	 * Handles field sanitization for folder settings.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// check if this is a reset operation
		if ( $this->is_reset_request() ) {
			$input = $this->merge_with_defaults( [] );
			add_settings_error( 'reset_rl_folders', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
			return $input;
		}

		// sanitize all fields
		$input = $this->sanitize_fields( $input, 'folders' );
		
		// validate folders_source against filtered options
		$allowed_sources = array_keys( apply_filters( 'rl_folders_source_options', [
			'rl_media_folder'	=> __( 'RLG Folder (recommended)', 'responsive-lightbox' ),
			'custom_taxonomy'	=> __( 'Custom Taxonomy', 'responsive-lightbox' )
		] ) );
		
		if ( ! in_array( $input['folders_source'], $allowed_sources, true ) ) {
			$input['folders_source'] = 'rl_media_folder';
		}
		
	// validate media_taxonomy only when using custom_taxonomy mode
	if ( $input['folders_source'] === 'custom_taxonomy' ) {
		// check if media_taxonomy is empty or invalid
		if ( empty( $input['media_taxonomy'] ) || $input['media_taxonomy'] === 'rl_media_folder' ) {
			$input['folders_source'] = 'rl_media_folder';
			add_settings_error( 'media_taxonomy', 'media_taxonomy_fallback', esc_html__( 'Media Folders selection is required when using Custom Taxonomy. Reverted to RLG Folder.', 'responsive-lightbox' ), 'error' );
		} else {
			// validate against DB taxonomies + registered hierarchical attachment taxonomies
			$db_taxonomies = $this->get_taxonomies();
			$db_taxonomies = is_array( $db_taxonomies ) ? $db_taxonomies : [];

			$registered_taxonomies = get_taxonomies(
				[
					'object_type'	=> [ 'attachment' ],
					'hierarchical'	=> true,
					'_builtin'		=> false
				],
				'names',
				'and'
			);
			$registered_taxonomies = is_array( $registered_taxonomies ) ? $registered_taxonomies : [];

			$available_taxonomies = array_values( array_unique( array_merge( $db_taxonomies, $registered_taxonomies ) ) );
			$available_taxonomies = array_values( array_diff( $available_taxonomies, [ 'rl_media_folder', 'rl_media_tag', 'language' ] ) );
			$available_taxonomies = array_map( 'sanitize_key', $available_taxonomies );
			
			if ( ! in_array( $input['media_taxonomy'], $available_taxonomies, true ) ) {
				$input['folders_source'] = 'rl_media_folder';
				add_settings_error( 'media_taxonomy', 'media_taxonomy_invalid', esc_html__( 'Selected taxonomy does not exist. Reverted to RLG Folder.', 'responsive-lightbox' ), 'error' );
			}
		}
	}
	
	// enforce RLG-only features when using custom taxonomy
	if ( $input['folders_source'] === 'custom_taxonomy' ) {
		$input['media_tags'] = false;
		$input['show_in_menu'] = false;
		$input['folders_removal'] = false;
		$input['jstree_wholerow'] = false;
	}

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

		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_folders',
			'option_group'	=> 'responsive_lightbox_folders',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_folders' => [
					'title'			=> __( 'Folders Settings', 'responsive-lightbox' ),
					'description'	=> '',
					'fields'		=> [
						'active' => [
							'title'	=> __( 'Folders', 'responsive-lightbox' ),
							'type'	=> 'boolean',
							'label'	=> __( 'Enable media folders.', 'responsive-lightbox' )
						],
						'folders_source' => [
							'title'			=> __( 'Folders Source', 'responsive-lightbox' ),
							'type'			=> 'radio',
							'description'	=> __( 'Select the source for organizing media and Media Folder galleries.', 'responsive-lightbox' ) . ' ' . __( 'If you have ever used categories or custom taxonomies for media library you may try to <a id="rl-folders-load-old-taxonomies" href="#">load and use them.</a>', 'responsive-lightbox' ),
							'options'		=> apply_filters( 'rl_folders_source_options', [
								'rl_media_folder'	=> __( 'RLG Folder (recommended)', 'responsive-lightbox' ),
								'custom_taxonomy'	=> __( 'Custom Taxonomy', 'responsive-lightbox' )
							] )
						],
						'media_taxonomy' => [
							'title'			=> '',
							'type'			=> 'select',
							'description'	=> __( 'Select the data source media folders.', 'responsive-lightbox' ),
							'after_field'	=> '<span class="spinner rl-spinner"></span>',
							'options'		=> [],
							'logic'			=> [
								'field'		=> 'folders_source',
								'operator'	=> 'is',
								'value'		=> 'custom_taxonomy',
								'action'	=> 'show',
							],
							'animation'		=> 'slide'
						],
						'media_ui' => [
							'title'			=> __( 'Media Library UI', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable Media Library folders UI.', 'responsive-lightbox' ),
							'description'	=> __( 'Disable to keep taxonomy available for galleries without adding Media Library UI.', 'responsive-lightbox' )
						],
						'media_tags' => [
							'title'			=> __( 'Media Tags', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable media tags.', 'responsive-lightbox' ),
							'description'	=> __( 'Enable if you want to use media tags.', 'responsive-lightbox' ),
							'logic'			=> [
								'field'		=> 'media_ui',
								'operator'	=> 'isnot',
								'value'		=> 'true',
								'action'	=> 'disable',
							]
						],
						'show_in_menu' => [
							'title'			=> __( 'Show in Menu', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable to show the taxonomy in the admin menu.', 'responsive-lightbox' ),
							'logic'			=> [
								'field'		=> 'media_ui',
								'operator'	=> 'isnot',
								'value'		=> 'true',
								'action'	=> 'disable',
							]
						],
						'folders_removal' => [
							'title'			=> __( 'Subfolder Removal', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Select to remove subfolders when parent folder is deleted.', 'responsive-lightbox' ),
							'logic'			=> [
								'field'		=> 'media_ui',
								'operator'	=> 'isnot',
								'value'		=> 'true',
								'action'	=> 'disable',
							]
						],
						'jstree_wholerow' => [
							'title'			=> __( 'Whole Row', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable to highlight folder\'s row as a clickable area.', 'responsive-lightbox' ),
							'logic'			=> [
								'field'		=> 'media_ui',
								'operator'	=> 'isnot',
								'value'		=> 'true',
								'action'	=> 'disable',
							]
						]
					]
				]
			]
		];

		return $data;
	}

	/**
	 * Load previously used media taxonomies via AJAX.
	 *
	 * @return void
	 */
	public function load_old_taxonomies() {
		// check capability
		if ( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'responsive-lightbox' ) ] );

		// no data?
		if ( ! isset( $_POST['taxonomies'], $_POST['nonce'] ) )
			wp_send_json_error( [ 'message' => __( 'Missing required data.', 'responsive-lightbox' ) ] );

		// invalid taxonomies format?
		if ( ! is_array( $_POST['taxonomies'] ) )
			wp_send_json_error( [ 'message' => __( 'Invalid data format.', 'responsive-lightbox' ) ] );

		// invalid nonce?
		if ( ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-taxonomies-nonce' ) )
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'responsive-lightbox' ) ] );

		// validate taxonomies are strings
		$taxonomies_input = array_filter( $_POST['taxonomies'], 'is_string' );
		$taxonomies_input = array_map( 'sanitize_key', $taxonomies_input );

		// get taxonomies used by attachments (DB) and currently registered
		$db_taxonomies = $this->get_taxonomies();
		$db_taxonomies = is_array( $db_taxonomies ) ? $db_taxonomies : [];

		$registered_taxonomies = get_taxonomies(
			[
				'object_type'	=> [ 'attachment' ],
				'hierarchical'	=> true,
				'_builtin'		=> false
			],
			'names',
			'and'
		);
		$registered_taxonomies = is_array( $registered_taxonomies ) ? $registered_taxonomies : [];

		// combine DB-discovered and currently registered taxonomies
		$fields = array_values( array_unique( array_merge( $db_taxonomies, $registered_taxonomies ) ) );

		// any results?
		if ( ! empty( $fields ) ) {
			// remove main taxonomy
			if ( ( $key = array_search( 'rl_media_folder', $fields, true ) ) !== false )
				unset( $fields[$key] );

			// remove media tags
			if ( ( $key = array_search( 'rl_media_tag', $fields, true ) ) !== false )
				unset( $fields[$key] );

			// remove polylang taxonomy if present
			if ( ( $key = array_search( 'language', $fields, true ) ) !== false )
				unset( $fields[$key] );

			// sanitize and normalize
			$fields = array_map( 'sanitize_key', $fields );
			$fields = array_filter( $fields, 'is_string' );
			$fields = array_values( array_unique( $fields ) );
		}
		
		// save discovered taxonomies to options (merge with existing, preserve on scan failure)
		$options = get_option( 'responsive_lightbox_folders', [] );
		$existing = isset( $options['custom_taxonomies'] ) && is_array( $options['custom_taxonomies'] ) ? $options['custom_taxonomies'] : [];
		
		if ( ! empty( $fields ) ) {
			$options['custom_taxonomies'] = array_values( array_unique( array_merge( $existing, $fields ) ) );
			update_option( 'responsive_lightbox_folders', $options );
		}
		// Note: We don't clear custom_taxonomies when scan returns nothing to avoid
		// wiping stored taxonomies on transient DB failures or when no new taxonomies exist

		// send taxonomies with counts and message
		$count = count( $fields );
		$new_taxonomies = array_values( array_diff( $fields, $taxonomies_input ) );
		$count_new = count( $new_taxonomies );

		if ( $count > 0 ) {
			if ( $count_new > 0 ) {
				$message = sprintf(
					/* translators: 1: total taxonomies, 2: newly added taxonomies */
					__( '%1$d custom taxonomies available. %2$d new added to the list.', 'responsive-lightbox' ),
					$count,
					$count_new
				);
			} else {
				$message = sprintf(
					_n(
						'%d custom taxonomy available (registered or previously loaded). No new taxonomies were added.',
						'%d custom taxonomies available (registered or previously loaded). No new taxonomies were added.',
						$count,
						'responsive-lightbox'
					),
					$count
				);
			}
		} else {
			$message = __( 'No custom taxonomies found.', 'responsive-lightbox' );
		}
			
		wp_send_json_success( [ 
			'taxonomies'	=> array_values( $fields ),
			'count'			=> $count,
			'count_new'		=> $count_new,
			'message'		=> $message
		] );
	}

	/**
	 * Get all previously used media taxonomies.
	 *
	 * @global object $wpdb
	 *
	 * @return array
	 */
	private function get_taxonomies() {
		global $wpdb;

		// query
		$fields = $wpdb->get_col( "
			SELECT DISTINCT tt.taxonomy
			FROM " . $wpdb->prefix . "term_taxonomy tt
			LEFT JOIN " . $wpdb->prefix . "term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			LEFT JOIN " . $wpdb->prefix . "posts p ON p.ID = tr.object_id
			WHERE p.post_type = 'attachment'
			ORDER BY tt.taxonomy ASC"
		);

		if ( ! empty( $fields ) ) {
			// remove polylang taxonomy
			if ( ( $key = array_search( 'language', $fields, true ) ) !== false )
				unset( $fields[$key] );
		}

		return $fields;
	}
}
