<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox folders class.
 *
 * @class Responsive_Lightbox_Folders
 */
class Responsive_Lightbox_Folders {
	private $rl;

	private $mode = '';
	private $rl_media_tag_terms = [];
	private $term_counters = [
		'keys'		=> [],
		'values'	=> []
	];
	private $allowed_select_html = [
		'select'	=> [
			'name'				=> true,
			'id'				=> true,
			'class'				=> true,
			'required'			=> true,
			'tabindex'			=> true,
			'aria-describedby'	=> true
		],
		'option'	=> [
			'value'		=> true,
			'class'		=> true,
			'selected'	=> true
		]
	];
	private $cached_taxonomies = null;

	/**
	 * Class constructor.
	 *
	 * @param Responsive_Lightbox|null $rl Plugin instance.
	 * @return void
	 */
	public function __construct( $rl = null ) {
		$this->rl = ( $rl instanceof Responsive_Lightbox ) ? $rl : Responsive_Lightbox();

		// set instance
		$this->rl->folders = $this;

		// settings data filter (works regardless of UI mode)
		add_filter( 'rl_settings_data', [ $this, 'filter_folders_settings_data' ], 20 );

		$read_only = ( ! $this->rl->options['folders']['active'] || ! $this->rl->options['folders']['media_ui'] );

		if ( $read_only )
			return;

		// actions
		add_action( 'init', [ $this, 'detect_library_mode' ], 11 );
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_posts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'add_library_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'add_library_scripts' ] );
		add_action( 'pre-upload-ui', [ $this, 'pre_upload_ui' ] );
		add_action( 'post-upload-ui', [ $this, 'post_upload_ui' ] );
		add_action( 'add_attachment', [ $this, 'add_attachment' ] );
		add_action( 'wp_ajax_save-attachment-compat', [ $this, 'ajax_save_attachment_compat' ], 0 );
		add_action( 'wp_ajax_rl-folders-delete-term', [ $this, 'delete_term' ] );
		add_action( 'wp_ajax_rl-folders-rename-term', [ $this, 'rename_term' ] );
		add_action( 'wp_ajax_rl-folders-add-term', [ $this, 'add_term' ] );
		add_action( 'wp_ajax_rl-folders-move-term', [ $this, 'move_term' ] );
		add_action( 'wp_ajax_rl-folders-move-attachments', [ $this, 'move_attachments' ] );
		add_action( 'wp_ajax_rl-folders-get-counters', [ $this, 'get_counters' ] );
		add_action( 'wp_ajax_rl-folders-select-term', [ $this, 'select_term' ] );

		// filters
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
		add_filter( 'parse_query', [ $this, 'parse_query' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'ajax_query_attachments_args' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields_to_edit' ], 10, 2 );
		add_filter( 'rl_count_attachments', [ $this, 'count_attachments' ], 10 );
	}

	/**
	 * Get the active taxonomy based on folders_source setting.
	 *
	 * Returns 'rl_media_folder' when folders_source is 'rl_media_folder' or empty/invalid.
	 * Returns media_taxonomy value when folders_source is 'custom_taxonomy'.
	 *
	 * @return string Active taxonomy slug
	 */
	public function get_active_taxonomy() {
		$folders_source = ! empty( $this->rl->options['folders']['folders_source'] ) ? $this->rl->options['folders']['folders_source'] : 'rl_media_folder';
		
		if ( $folders_source === 'custom_taxonomy' ) {
			$media_taxonomy = ! empty( $this->rl->options['folders']['media_taxonomy'] ) ? $this->rl->options['folders']['media_taxonomy'] : 'rl_media_folder';
			return $media_taxonomy;
		}
		
		return 'rl_media_folder';
	}

	/**
	 * Initialize folders.
	 *
	 * @return void
	 */
	public function init_folders() {
		// end if in read only mode
		if ( ! $this->rl->options['folders']['active'] )
			return;

		// register media taxonomy
		$this->register_media_taxonomy( 'rl_media_folder' );

		// register media tags
		if ( $this->rl->options['folders']['media_tags'] ) {
			$show_ui = $this->rl->options['folders']['media_ui'];
			$show_in_menu = ( $show_ui && $this->rl->options['folders']['show_in_menu'] );

			register_taxonomy(
				'rl_media_tag',
				'attachment',
				[
					'public'				=> true,
					'hierarchical'			=> false,
					'labels'				=> [
						'name'				=> _x( 'Media Tags', 'taxonomy general name', 'responsive-lightbox' ),
						'singular_name'	=> _x( 'Media Tag', 'taxonomy singular name', 'responsive-lightbox' ),
						'search_items'	=> __( 'Search Tags', 'responsive-lightbox' ),
						'all_items'		=> __( 'All Tags', 'responsive-lightbox' ),
						'edit_item'		=> __( 'Edit Tag', 'responsive-lightbox' ),
						'update_item'	=> __( 'Update Tag', 'responsive-lightbox' ),
						'add_new_item'	=> __( 'Add New Tag', 'responsive-lightbox' ),
						'new_item_name'	=> __( 'New Tag Name', 'responsive-lightbox' ),
						'not_found'		=> __( 'No tags found.', 'responsive-lightbox' ),
						'menu_name'		=> _x( 'Tags', 'taxonomy general name', 'responsive-lightbox' ),
					],
					'show_ui'				=> $show_ui,
					'show_in_menu'			=> $show_in_menu,
					'show_in_nav_menus'		=> false,
					'show_in_quick_edit'	=> $show_ui,
					'show_tagcloud'			=> false,
					'show_admin_column'		=> $show_in_menu,
					'update_count_callback'	=> '_update_generic_term_count',
					'query_var'				=> false,
					'rewrite'				=> false
				]
			);
		}

		$media_taxonomies = $this->get_media_taxonomy_options();

		if ( isset( $this->rl->settings->settings['folders']['fields']['media_taxonomy']['options'] ) )
			$this->rl->settings->settings['folders']['fields']['media_taxonomy']['options'] = $media_taxonomies;
	}

	/**
	 * Filter folders settings data to inject available media taxonomies.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function filter_folders_settings_data( $data ) {
		if ( ! $this->rl->options['folders']['active'] )
			return $data;

		if ( ! is_array( $data ) || empty( $data['folders'] ) || ! is_array( $data['folders'] ) )
			return $data;

		$media_taxonomies = $this->get_media_taxonomy_options();

		if ( isset( $data['folders']['sections']['responsive_lightbox_folders']['fields']['media_taxonomy'] ) ) {
			$data['folders']['sections']['responsive_lightbox_folders']['fields']['media_taxonomy']['options'] = $media_taxonomies;
			
			// disable dropdown if no custom taxonomies were found in the DB
			if ( empty( $media_taxonomies ) ) {
				$data['folders']['sections']['responsive_lightbox_folders']['fields']['media_taxonomy']['disabled'] = true;
			}
		} elseif ( isset( $data['folders']['fields']['media_taxonomy'] ) ) {
			$data['folders']['fields']['media_taxonomy']['options'] = $media_taxonomies;
			
			// disable dropdown if no custom taxonomies were found in the DB
			if ( empty( $media_taxonomies ) ) {
				$data['folders']['fields']['media_taxonomy']['disabled'] = true;
			}
		}

		return $data;
	}

	/**
	 * Get available media taxonomy options.
	 *
	 * @return array
	 */
	private function get_media_taxonomy_options() {
		// taxonomies actually used by attachments (DB-driven source of truth)
		$db_taxonomies = $this->get_taxonomies();
		$db_taxonomies = is_array( $db_taxonomies ) ? $db_taxonomies : [];

		// exclude RLG taxonomies from the DB list
		$db_taxonomies = array_values( array_diff( $db_taxonomies, [ 'rl_media_folder', 'rl_media_tag' ] ) );

		// make DB-discovered taxonomies available even if not currently registered
		foreach ( $db_taxonomies as $db_taxonomy ) {
			if ( ! taxonomy_exists( $db_taxonomy ) )
				$this->register_media_taxonomy( $db_taxonomy );
		}

		// get non-builtin hierarchical taxonomies
		$taxonomies = get_taxonomies(
			[
				'object_type'	=> [ 'attachment' ],
				'hierarchical'	=> true,
				'_builtin'		=> false
			],
			'objects',
			'and'
		);

		$media_taxonomies = [];

		foreach ( $taxonomies as $taxonomy => $object ) {
			// exclude RLG taxonomies from the taxonomies list
			if ( in_array( $taxonomy, [ 'rl_media_folder', 'rl_media_tag', 'language' ] , true ) )
				continue;
				
			$media_taxonomies[$taxonomy] = $taxonomy . ' (' . $object->labels->menu_name . ')';
		}

		// Get the currently configured media_taxonomy (not active taxonomy)
		$tax = $this->rl->options['folders']['media_taxonomy'];

		// selected hierarchical taxonomy does not exists?
		if ( ! array_key_exists( $tax, $media_taxonomies ) ) {
			// skip adding RLG taxonomies back
			if ( ! in_array( $tax, [ 'rl_media_folder', 'rl_media_tag' ], true ) ) {
				// only consider selected taxonomy if it was found in the DB
				if ( ! in_array( $tax, $db_taxonomies, true ) ) {
					$this->rl->options['folders']['media_taxonomy'] = $this->rl->defaults['folders']['media_taxonomy'];
					update_option( 'responsive_lightbox_folders', $this->rl->options['folders'] );
					return $media_taxonomies;
				}

				// check taxonomy existence
				if ( ( $taxonomy = get_taxonomy( $tax ) ) !== false ) {
					// update
					$media_taxonomies[$tax] = $tax . ' (' . $taxonomy->labels->menu_name . ')';
				// is it really old taxonomy?
				} elseif ( in_array( $tax, $db_taxonomies, true ) ) {
					$this->register_media_taxonomy( $tax );

					$media_taxonomies[$tax] = $tax;
				// use default taxonomy
				} else {
					$this->rl->options['folders']['media_taxonomy'] = $this->rl->defaults['folders']['media_taxonomy'];

					update_option( 'responsive_lightbox_folders', $this->rl->options['folders'] );
				}
			}
		}

		return $media_taxonomies;
	}

	/**
	 * Register media taxonomy.
	 *
	 * @return void
	 */
	public function register_media_taxonomy( $taxonomy ) {
		$show_ui = $this->rl->options['folders']['media_ui'];
		$active_taxonomy = $this->get_active_taxonomy();
		
		// Show menu only for the active taxonomy
		$show_in_menu = ( $show_ui && $this->rl->options['folders']['show_in_menu'] && $taxonomy === $active_taxonomy );
		
		// Show taxonomy UI only for the active taxonomy
		$show_tax_ui = ( $show_ui && $taxonomy === $active_taxonomy );

		register_taxonomy(
			$taxonomy,
			'attachment',
			[
				'public'				=> true,
				'hierarchical'			=> true,
				'labels'				=> [
					'name'				=> _x( 'Media Folders', 'taxonomy general name', 'responsive-lightbox' ),
					'singular_name'		=> _x( 'Media Folder', 'taxonomy singular name', 'responsive-lightbox' ),
					'search_items'		=> __( 'Search Folders', 'responsive-lightbox' ),
					'all_items'			=> __( 'All Files', 'responsive-lightbox' ),
					'parent_item'		=> __( 'Parent Folder', 'responsive-lightbox' ),
					'parent_item_colon'	=> __( 'Parent Folder:', 'responsive-lightbox' ),
					'edit_item'			=> __( 'Edit Folder', 'responsive-lightbox' ),
					'update_item'		=> __( 'Update Folder', 'responsive-lightbox' ),
					'add_new_item'		=> __( 'Add New Folder', 'responsive-lightbox' ),
					'new_item_name'		=> __( 'New Folder Name', 'responsive-lightbox' ),
					'not_found'			=> __( 'No folders found.', 'responsive-lightbox' ),
					'menu_name'			=> _x( 'Folders', 'taxonomy general name', 'responsive-lightbox' ),
				],
				'show_ui'				=> $show_tax_ui,
				'show_in_menu'			=> $show_in_menu,
				'show_in_nav_menus'		=> false,
				'show_in_quick_edit'	=> $show_ui,
				'show_tagcloud'			=> false,
				'show_admin_column'		=> $show_in_menu,
				'update_count_callback'	=> '_update_generic_term_count',
				'query_var'				=> false,
				'rewrite'				=> false
			]
		);
	}


	/**
	 * Read user option safely.
	 *
	 * `get_user_option()` can call `get_userdata()`. Guard this path because
	 * some hooks can run before pluggable user functions are loaded.
	 *
	 * @param string $option Option key.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	private function get_user_option_safe( $option, $default = false ) {
		if ( ! function_exists( 'get_user_option' ) || ! function_exists( 'get_userdata' ) )
			return $default;

		$value = get_user_option( $option );

		if ( $value === false )
			return $default;

		return ( is_scalar( $value ) || $value === null ? $value : $default );
	}

	/**
	 * Read stored selected folder term safely.
	 *
	 * `get_user_option()` can call `get_userdata()`. Guard this path because
	 * `parse_query` may run before pluggable user functions are loaded.
	 *
	 * @return string|false
	 */
	private function get_selected_term_option() {
		return $this->get_user_option_safe( 'rl_folders_selected_term', false );
	}


	/**
	 * Detect library mode (list or grid).
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function detect_library_mode() {
		global $pagenow;

		if ( $pagenow === 'upload.php' ) {
			// available modes
			$modes = [ 'grid', 'list' ];

			// sanitize mode
			if ( isset( $_GET['mode'] ) )
				$mode = sanitize_key( $_GET['mode'] );
			else
				$mode = '';

			// check mode
			if ( ! ( $mode && ctype_lower( $mode ) && in_array( $mode, $modes, true ) ) ) {
				// get user mode
				$user_mode = (string) $this->get_user_option_safe( 'media_library_mode', '' );

				// valid user mode?
				if ( in_array( $user_mode, $modes, true ) )
					$mode = $user_mode;
				// default wp mode
				else
					$mode = 'grid';
			}

			// store mode
			$this->mode = $mode;
		}

		if ( $pagenow === 'upload.php' || wp_doing_ajax() ) {
			$this->rl_media_tag_terms = get_terms(
				[
					'taxonomy'		=> 'rl_media_tag',
					'hide_empty'	=> false,
					'orderby'		=> 'name',
					'order'			=> 'asc',
					'number'		=> 0,
					'fields'		=> 'id=>name',
					'hierarchical'	=> false
				]
			);
		}
	}

	/**
	 * Admin body classes.
	 *
	 * @global string $pagenow
	 *
	 * @param array $classes Admin body classes
	 * @return array
	 */
	public function admin_body_class( $classes ) {
		global $pagenow;

		if ( $pagenow === 'upload.php' ) {
			// append class
			$classes .= ' rl-folders-upload-' . $this->mode . '-mode';

			// pending state until jsTree hydrates the server-rendered shell
			$classes .= ' rl-folders-root-pending';
		}

		return $classes;
	}

	/**
	 * Get folders dropdown HTML.
	 *
	 * @param string $taxonomy Folders taxonomy
	 * @param string $selected Folders taxonomy ID
	 * @return string
	 */
	private function get_folders( $taxonomy, $selected = 0 ) {
		// get only 1 term to check if taxonomy is empty
		$any_terms = get_terms(
			[
				'taxonomy'		=> $taxonomy,
				'hide_empty'	=> false,
				'fields'		=> 'ids',
				'hierarchical'	=> false,
				'number'		=> 1
			]
		);

		// prepare dropdown categories parameters
		$args = [
			'orderby'			=> 'name',
			'order'				=> 'asc',
			'show_option_all'	=> __( 'Root Folder', 'responsive-lightbox' ),
			'show_count'		=> false,
			'hide_empty'		=> false,
			'hierarchical'		=> true,
			'hide_if_empty'		=> false,
			'echo'				=> false,
			'selected'			=> (int) $selected,
			'id'				=> 'rl_folders_upload_files',
			'name'				=> 'rl_folders_upload_files_term_id',
			'taxonomy'			=> $taxonomy
		];

		// no terms?
		if ( ! is_wp_error( $any_terms ) && empty( $any_terms ) ) {
			$args['show_option_none'] = __( 'Root Folder', 'responsive-lightbox' );
			$args['option_none_value'] = 0;
		}

		return wp_dropdown_categories( $args );
	}

	/**
	 * Add filter to add media folder id to the uploader
	 *
	 * @return void
	 */
	public function pre_upload_ui() {
		add_filter( 'upload_post_params', [ $this, 'upload_post_params' ] );
	}

	/**
	 * Add media folder id param to the uploader.
	 *
	 * @param array $params Plupload parameters
	 * @return array
	 */
	public function upload_post_params( $params ) {
		$params['rl_folders_upload_files_term_id'] = 0;

		return $params;
	}

	/**
	 * Display dropdown at media upload UI screen.
	 *
	 * @return void
	 */
	public function post_upload_ui() {
		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// get only 1 term to check if taxonomy is empty
		$any_terms = get_terms(
			[
				'taxonomy'		=> $taxonomy,
				'hide_empty'	=> false,
				'fields'		=> 'ids',
				'hierarchical'	=> false,
				'number'		=> 1
			]
		);

		// prepare dropdown categories parameters
		$args = [
			'orderby'			=> 'name',
			'order'				=> 'asc',
			'show_option_all'	=> __( 'Root Folder', 'responsive-lightbox' ),
			'show_count'		=> false,
			'hide_empty'		=> false,
			'hierarchical'		=> true,
			'hide_if_empty'		=> false,
			'echo'				=> false,
			'selected'			=> isset( $_GET[$taxonomy] ) ? (int) $_GET[$taxonomy] : 0,
			'id'				=> 'rl_folders_upload_files',
			'name'				=> 'rl_folders_upload_files_term_id',
			'taxonomy'			=> $taxonomy
		];

		// no terms?
		if ( ! is_wp_error( $any_terms ) && empty( $any_terms ) ) {
			$args['show_option_none'] = __( 'Root Folder', 'responsive-lightbox' );
			$args['option_none_value'] = 0;
		}

		// display select
		echo '<p><label>' . esc_html__( 'Upload files to', 'responsive-lightbox' ) . ': ' . wp_kses( wp_dropdown_categories( $args ), $this->allowed_select_html ) . '</label></p>';
	}

	/**
	 * Assign attachment to given term.
	 *
	 * @param int $post_id Current attachment ID
	 * @return void
	 */
	public function add_attachment( $post_id ) {
		if ( isset( $_POST['rl_folders_upload_files_term_id'] ) ) {
			// cast term id
			$term_id = (int) $_POST['rl_folders_upload_files_term_id'];

			// get active taxonomy
			$taxonomy = $this->get_active_taxonomy();

			// valid term?
			if ( is_array( term_exists( $term_id, $taxonomy ) ) )
				wp_set_object_terms( $post_id, $term_id, $taxonomy, false );
		}
	}

	/**
	 * Add filterable dropdown to media library.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function restrict_manage_posts() {
		global $pagenow;

		if ( $pagenow === 'upload.php' ) {
			// get active taxonomy
			$taxonomy = $this->get_active_taxonomy();

			$html = wp_dropdown_categories(
				[
					'orderby'			=> 'name',
					'order'				=> 'asc',
					'id'				=> 'media-attachment-rl-folders-filters',
					'show_option_all'	=> __( 'All Files', 'responsive-lightbox' ),
					'show_count'		=> false,
					'hide_empty'		=> false,
					'hierarchical'		=> true,
					'selected'			=> ( isset( $_GET[$taxonomy] ) ? (int) $_GET[$taxonomy] : 0 ),
					'name'				=> $taxonomy,
					'taxonomy'			=> $taxonomy,
					'hide_if_empty'		=> true,
					'echo'				=> false
				]
			);

			if ( $html === '' )
				echo '<select name="' . esc_attr( $taxonomy ) . '" id="media-attachment-rl-folders-filters" class="postform"><option>' . esc_html__( 'All Files', 'responsive-lightbox' ) . '</option></select> ';
			else
				echo wp_kses( $html, $this->allowed_select_html );
		}
	}

	/**
	 * Change query to adjust taxonomy if needed.
	 *
	 * @global string $pagenow
	 *
	 * @param object $query WP Query
	 * @return object
	 */
	public function parse_query( $query ) {
		global $pagenow;

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		if ( $pagenow === 'upload.php' ) {
			// explicit URL filter has priority; fall back to stored user preference
			if ( isset( $_GET[$taxonomy] ) ) {
				$term_id = (int) $_GET[$taxonomy];
			} else {
				$stored = $this->get_selected_term_option();

				if ( $stored === false || $stored === '' || $stored === 'all' )
					return $query;

				$term_id = (int) $stored;

				// JS stores root folder as '0'; parse_query uses -1
				if ( $term_id === 0 )
					$term_id = -1;
			}

			// get tax query
			$tax_query = $query->get( 'tax_query' );

			if ( empty( $tax_query ) || ! is_array( $tax_query ) )
				$tax_query = [];

			// -1 === root, 0 === all files, >0 === term_id

			if ( $term_id !== 0 && ( $query->is_main_query() || empty( $query->query['rl_folders_root'] ) ) ) {
				$tax = [
					'taxonomy'	=> $taxonomy,
					'field'		=> 'id'
				];

				// root folder?
				if ( $term_id === -1 ) {
					$tax['terms'] = 0;
					$tax['operator'] = 'NOT EXISTS';
					$tax['include_children'] = false;
				// specified term id
				} else {
					$tax['terms'] = $term_id;
					$tax['include_children'] = false;
				}

				// add new tax query
				$tax_query[] = [ 'relation' => 'AND', $tax ];

				// set new tax query
				$query->set( 'tax_query', $tax_query );
			}
		}

		return $query;
	}

	/**
	 * Change AJAX query parameters to adjust taxonomy in the media library if needed.
	 *
	 * @param array $query Query arguments
	 * @return array
	 */
	public function ajax_query_attachments_args( $query ) {
		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// explicit filter in POST has priority; fall back to stored user preference
		if ( isset( $_POST['query'][$taxonomy] ) ) {
			$term_id = sanitize_key( $_POST['query'][$taxonomy] );

			if ( $term_id === 'all' )
				return $query;

			$term_id = (int) $term_id;

			if ( $term_id < 0 )
				return $query;
		} else {
			// no filter sent -- apply stored user preference for the initial grid query
			$stored = $this->get_selected_term_option();

			if ( $stored === false || $stored === '' || $stored === 'all' )
				return $query;

			$term_id = (int) $stored;

			if ( $term_id < 0 )
				return $query;
		}

		if ( empty( $query['tax_query'] ) || ! is_array( $query['tax_query'] ) )
			$query['tax_query'] = [];

		$query['tax_query'][] = [
			'relation' => 'AND',
			[
				'taxonomy'			=> $taxonomy,
				'field'				=> 'id',
				'terms'				=> $term_id,
				'include_children'	=> ( ! ( isset( $_POST['query']['include_children'] ) && $_POST['query']['include_children'] === 'false' ) ),
				'operator'		 	=> ( $term_id === 0 ? 'NOT EXISTS' : 'IN' )
			]
		];

		return $query;
	}

	/**
	 * Filter the array of attachment fields that are displayed when editing an attachment.
	 *
	 * @param array $fields Attachment fields
	 * @param object $post Post object
	 * @return array
	 */
	public function attachment_fields_to_edit( $fields, $post ) {
		$rl = Responsive_Lightbox();

		if ( ! $rl->options['folders']['active'] || ! $rl->options['folders']['media_ui'] )
			return $fields;

		if ( wp_doing_ajax() ) {
			// get active taxonomy
			$taxonomy = $this->get_active_taxonomy();

			// get taxonomy object
			$tax = (array) get_taxonomy( $taxonomy );

			if ( ! empty( $tax ) ) {
				if ( ! $tax['public'] || ! $tax['show_ui'] )
					return $fields;

				if ( empty( $tax['args'] ) )
					$tax['args'] = [];

				$ids = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );

				// get select HTML
				$dropdown = wp_dropdown_categories(
					[
						'orderby'			=> 'name',
						'order'				=> 'asc',
						'show_option_none'	=> __( 'Root Folder', 'responsive-lightbox' ),
						'show_option_all'	=> false,
						'show_count'		=> false,
						'hide_empty'		=> false,
						'hierarchical'		=> true,
						'selected'			=> ( ! empty( $ids ) ? reset( $ids ) : 0 ),
						'name'				=> $taxonomy . '_term',
						'taxonomy'			=> $taxonomy,
						'hide_if_empty'		=> false,
						'echo'				=> false
					]
				);

				$tax['input'] = 'html';
				$tax['html'] = wp_kses( $dropdown, $this->allowed_select_html );

				$fields[$taxonomy] = $tax;
			}

			if ( $rl->options['folders']['media_tags'] && taxonomy_exists( 'rl_media_tag' ) ) {
				// get taxonomy object
				$tax = (array) get_taxonomy( 'rl_media_tag' );

				if ( ! empty( $tax ) ) {
					if ( ! $tax['public'] || ! $tax['show_ui'] )
						return $fields;

					if ( empty( $tax['args'] ) )
						$tax['args'] = [];

					$tags_html = '';

					// get terms
					$tags = wp_get_post_terms( $post->ID, 'rl_media_tag', [ 'fields' => 'id=>name' ] );

					// valid terms?
					if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
						foreach ( $tags as $tag_name ) {
							$tags_html .= '<option value="' . esc_attr( $tag_name ) . '" selected="selected">' . esc_html( $tag_name ) . '</li>';
						}
					} else
						$tags = [];

					// update input
					$tax['input'] = 'html';

					// $tags_html is already escaped here
					$tax['html'] = '
					<select class="rl-media-tag-select2" multiple="multiple" name="attachments[' . (int) $post->ID . '][rl_media_tag]">
						' . $tags_html . '
					</select>';

					// update taxonomy
					$fields['rl_media_tag'] = $tax;
				}
			}
		}

		return $fields;
	}

	/**
	 * Assign new term IDs to given attachment ID via AJAX in modal attachment edit screen.
	 *
	 * @return void
	 */
	function ajax_save_attachment_compat() {
		// no attachment id?
		if ( ! isset( $_REQUEST['id'] ) )
			wp_send_json_error();

		$id = (int) $_REQUEST['id'];

		// invalid id?
		if ( $id <= 0 )
			wp_send_json_error();

		// no valid data?
		if ( empty( $_REQUEST['attachments'][$id] ) || ! is_array( $_REQUEST['attachments'][$id] ) )
			wp_send_json_error();

		// no sanitization like in wordpress core: wp_ajax_save_attachment_compat() function
		$attachment_data = $_REQUEST['attachments'][$id];

		// check nonce
		check_ajax_referer( 'update-post_' . $id, 'nonce' );

		if ( ! current_user_can( 'edit_post', $id ) )
			wp_send_json_error();

		// get post
		$post = get_post( $id, ARRAY_A );

		if ( empty( $post ) || $post['post_type'] !== 'attachment' )
			wp_send_json_error();

		// update attachment data if needed
		$post = apply_filters( 'attachment_fields_to_save', $post, $attachment_data );

		if ( isset( $post['errors'] ) )
			wp_send_json_error();

		// update attachment
		wp_update_post( $post );

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// first if needed?
		if ( isset( $attachment_data[$taxonomy] ) )
			wp_set_object_terms( $id, (int) reset( array_map( 'trim', $attachment_data[$taxonomy] ) ), $taxonomy, false );
		elseif ( isset( $_REQUEST[$taxonomy . '_term'] ) )
			wp_set_object_terms( $id, (int) $_REQUEST[$taxonomy . '_term'], $taxonomy, false );
		else
			wp_set_object_terms( $id, '', $taxonomy, false );

		// check media tags
		if ( isset( $attachment_data['rl_media_tag'] ) && is_string( $attachment_data['rl_media_tag'] ) ) {
			$media_tags = explode( ',', $attachment_data['rl_media_tag'] );

			if ( ! empty( $media_tags ) && is_array( $media_tags ) )
				$media_tags = array_filter( array_map( 'sanitize_title', $media_tags ) );

			// any media tags?
			if ( ! empty( $media_tags ) )
				wp_set_object_terms( $id, $media_tags, 'rl_media_tag', false );
			else
				wp_set_object_terms( $id, '', 'rl_media_tag', false );
		}

		// get attachment data
		$attachment = wp_prepare_attachment_for_js( $id );

		// invalid attachment?
		if ( ! $attachment )
			wp_send_json_error();

		// finally send success
		wp_send_json_success( $attachment );
	}

	/**
	 * AJAX action to delete term.
	 *
	 * @return void
	 */
	public function delete_term() {
		// check rate limiting (30 requests per minute for destructive operations)
		if ( ! Responsive_Lightbox()->check_rate_limit( 'rl_delete_term', 30, 60 ) ) {
			wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'responsive-lightbox' ) );
		}

		// no data?
		if ( ! isset( $_POST['term_id'], $_POST['nonce'], $_POST['children'] ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// sanitize term id
		$term_id = (int) $_POST['term_id'];

		if ( $term_id <= 0 )
			wp_send_json_error();

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		$remove_children = (int) $_POST['children'];

		// delete children?
		if ( $remove_children === 1 ) {
			// get term children
			$children = get_term_children( $term_id, $taxonomy );

			// found any children?
			if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
				// reverse array to delete terms with no children first
				foreach ( array_reverse( $children ) as $child_id ) {
					// delete child
					wp_delete_term( $child_id, $taxonomy );
				}
			}
		}

		// delete parent
		if ( is_wp_error( wp_delete_term( $term_id, $taxonomy ) ) )
			wp_send_json_error();
		else
			wp_send_json_success( $this->get_folders( $taxonomy ) );
	}

	/**
	 * AJAX action to assign new parent of the term.
	 *
	 * @return void
	 */
	public function move_term() {
		// no data?
		if ( ! isset( $_POST['parent_id'], $_POST['term_id'], $_POST['nonce'] ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// update term
		$update = wp_update_term( (int) $_POST['term_id'], $taxonomy, [ 'parent' => (int) $_POST['parent_id'] ] );

		// error?
		if ( is_wp_error( $update ) )
			wp_send_json_error();
		else
			wp_send_json_success( $this->get_folders( $taxonomy ) );
	}

	/**
	 * AJAX action to add new term.
	 *
	 * @return void
	 */
	public function add_term() {
		// no data?
		if ( ! isset( $_POST['parent_id'], $_POST['name'], $_POST['nonce'] ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// prepare data
		$original_slug = $slug = sanitize_title( $_POST['name'] );
		$name = sanitize_text_field( $_POST['name'] );
		$parent_id = (int) $_POST['parent_id'];

		// empty name?
		if ( $original_slug === '' || $name === '' )
			wp_send_json_error();

		// get all term slugs
		$terms = get_terms(
			[
				'taxonomy'		=> $taxonomy,
				'hide_empty'	=> false,
				'number'		=> 0,
				'fields'		=> 'id=>slug',
				'hierarchical'	=> true
			]
		);

		// any terms?
		if ( ! is_wp_error( $terms ) && is_array( $terms ) && ! empty( $terms ) ) {
			$i = 2;

			// slug already exists? create unique one
			while ( in_array( $slug, $terms, true ) ) {
				$slug = $original_slug . '-' . $i ++;
			}
		}

		// add new term, name is sanitized inside wp_insert_term with sanitize_term function
		$term = wp_insert_term(
			$name,
			$taxonomy,
			[
				'parent'	=> $parent_id,
				'slug'		=> $slug
			]
		);

		// error?
		if ( is_wp_error( $term ) )
			wp_send_json_error();

		$term = get_term( $term['term_id'], $taxonomy );

		// error?
		if ( is_wp_error( $term ) )
			wp_send_json_error();
		else {
			wp_send_json_success(
				[
					'name'		=> $term->name,
					'term_id'	=> $term->term_id,
					'url'		=> admin_url( 'upload.php?mode=' . $this->mode . '&' . $taxonomy . '=' . $term->term_id ),
					'select'	=> $this->get_folders( $taxonomy, $term->term_id )
				]
			);
		}
	}

	/**
	 * AJAX action to rename term.
	 *
	 * @return void
	 */
	public function rename_term() {
		// no data?
		if ( ! isset( $_POST['term_id'], $_POST['name'], $_POST['nonce'] ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// sanitize term id
		$term_id = (int) $_POST['term_id'];

		if ( $term_id <= 0 )
			wp_send_json_error();

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// update term, name is sanitized inside wp_update_term with sanitize_term function
		$update = wp_update_term( $term_id, $taxonomy, [ 'name' => $_POST['name'] ] );

		// error?
		if ( is_wp_error( $update ) )
			wp_send_json_error();

		$term = get_term( $term_id, $taxonomy );

		// error?
		if ( is_wp_error( $term ) )
			wp_send_json_error();
		else {
			wp_send_json_success(
				[
					'name'		=> $term->name,
					'select'	=> $this->get_folders( $taxonomy, $term_id )
				]
			);
		}
	}

	/**
	 * AJAX action to assign new term to attachments.
	 *
	 * @return void
	 */
	public function move_attachments() {
		// no data?
		if ( ! isset( $_POST['attachment_ids'], $_POST['old_term_id'], $_POST['new_term_id'], $_POST['nonce'] ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// not empty attachment ids array?
		if ( empty( $_POST['attachment_ids'] ) || ! is_array( $_POST['attachment_ids'] ) )
			wp_send_json_error();

		// prepare data
		$ids = $all_terms = [];
		$attachments = [
			'success'		=> [],
			'failure'		=> [],
			'duplicated'	=> []
		];

		// filter unwanted data
		$ids = array_unique( array_filter( array_map( 'intval', $_POST['attachment_ids'] ) ) );

		// no ids?
		if ( empty( $ids ) )
			wp_send_json_error();

		// prepare term ids
		$old_term_id = (int) $_POST['old_term_id'];
		$new_term_id = (int) $_POST['new_term_id'];

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// moving to root folder?
		if ( $new_term_id === 0 ) {
			foreach ( $ids as $id ) {
				// get attachment term ids
				$all_terms[$id] = wp_get_object_terms( $id, $taxonomy, [ 'fields' => 'ids' ] );

				// remove all terms assigned to attachment
				if ( ! is_wp_error( wp_set_object_terms( $id, null, $taxonomy, false ) ) )
					$attachments['success'][] = $id;
				else
					$attachments['failure'][] = $id;
			}
		} else {
			foreach ( $ids as $id ) {
				// get attachment term ids
				$terms = wp_get_object_terms( $id, $taxonomy, [ 'fields' => 'ids' ] );

				// got terms?
				if ( ! is_wp_error( $terms ) ) {
					// save existing term (attachment already assigned to this term)
					if ( in_array( $new_term_id, $terms, true ) )
						$attachments['duplicated'][] = $id;

					// update attachment's term
					if ( ! is_wp_error( wp_set_object_terms( $id, $new_term_id, $taxonomy, false ) ) )
						$attachments['success'][] = $id;
					else
						$attachments['failure'][] = $id;
				}
			}
		}

		if ( empty( $attachments['success'] ) )
			wp_send_json_error();
		else {
			wp_send_json_success(
				[
					'attachments'	=> $attachments,
					'terms'			=> $all_terms
				]
			);
		}
	}

	/**
	 * AJAX action to get fresh tree counters.
	 *
	 * @return void
	 */
	public function get_counters() {
		global $wpdb;

		// invalid capability?
		if ( ! current_user_can( 'upload_files' ) )
			wp_send_json_error();

		// invalid nonce?
		if ( ! isset( $_POST['nonce'] ) || ! ctype_alnum( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rl-folders-ajax-library-nonce' ) )
			wp_send_json_error();

		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// taxonomy does not exist?
		if ( ! taxonomy_exists( $taxonomy ) )
			wp_send_json_error();

		$counter_statuses = apply_filters( 'rl_folders_counter_post_statuses', [ 'inherit' ], $taxonomy );
		$counter_statuses = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) $counter_statuses ),
				static function( $status ) {
					return $status !== '';
				}
			)
		);

		if ( empty( $counter_statuses ) )
			$counter_statuses = [ 'inherit' ];

		$counters = [
			'all' => (int) apply_filters( 'rl_count_attachments', 0 ),
			'0' => 0
		];

		$term_ids = get_terms(
			[
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'fields' => 'ids'
			]
		);

		if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				$counters[(string) (int) $term_id] = 0;
			}
		}

		$status_placeholders = implode( ', ', array_fill( 0, count( $counter_statuses ), '%s' ) );
		$query_values = $counter_statuses;
		$query_values[] = $taxonomy;

		$term_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id AS term_id, COUNT(DISTINCT p.ID) AS term_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE p.post_type = 'attachment'
					AND p.post_status IN (" . $status_placeholders . ")
					AND tt.taxonomy = %s
				GROUP BY tt.term_id",
				$query_values
			),
			ARRAY_A
		);

		if ( ! empty( $term_counts ) ) {
			foreach ( $term_counts as $term_count ) {
				$term_id = isset( $term_count['term_id'] ) ? (int) $term_count['term_id'] : 0;

				if ( $term_id > 0 )
					$counters[(string) $term_id] = isset( $term_count['term_count'] ) ? (int) $term_count['term_count'] : 0;
			}
		}

		// root folder query (attachments without any folder term assigned)
		$root_query = new WP_Query(
			apply_filters(
				'rl_root_folder_query_args',
				[
					'rl_folders_root' => true,
					'posts_per_page' => 1,
					'post_type' => 'attachment',
					'post_status' => $counter_statuses,
					'fields' => 'ids',
					'no_found_rows' => false,
					'tax_query' => [
						[
							'relation' => 'AND',
							[
								'taxonomy' => $taxonomy,
								'field' => 'id',
								'terms' => 0,
								'include_children' => false,
								'operator' => 'NOT EXISTS'
							]
						]
					]
				]
			)
		);

		$counters['0'] = (int) $root_query->found_posts;

		wp_send_json_success(
			[
				'counters' => $counters
			]
		);
	}

	/**
	 * Persist selected folder term as user option via AJAX.
	 *
	 * @return void
	 */
	public function select_term() {
		check_ajax_referer( 'rl-folders-ajax-library-nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) )
			wp_send_json_error( 'No permission.' );

		$term_id = isset( $_POST['term_id'] ) ? sanitize_key( $_POST['term_id'] ) : 'all';

		update_user_option( get_current_user_id(), 'rl_folders_selected_term', $term_id );

		wp_send_json_success();
	}

	/**
	 * Change wp_list_categories HTML link.
	 *
	 * @param array $matches Matched elements
	 * @return string
	 */
	public function replace_folders_href( $matches ) {
		// get active taxonomy
		$taxonomy = $this->get_active_taxonomy();

		// set 'all files' folder
		$term_id = -1;
		$url_term_id = 0;

		// any matches?
		if ( ! empty( $matches[1] ) ) {
			$params = parse_url( html_entity_decode( urldecode( $matches[1] ) ) );

			if ( isset( $params['query'] ) ) {
				// parse query string
				parse_str( $params['query'], $atts );

				if ( isset( $atts['term'] ) ) {
					// get term
					$term = get_term_by( 'slug', $atts['term'], $taxonomy );

					// valid term?
					if ( $term !== false ) {
						$this->term_counters['keys'][] = $term->term_id;

						// set term id
						$url_term_id = $term_id = $term->term_id;
					}
				}
			}
		}

		// escape url early
		return 'href="' . esc_url( apply_filters( 'rl_folders_media_folder_url', add_query_arg( [ 'mode' => $this->mode, $taxonomy => $url_term_id ] ), $matches, $this->mode, $url_term_id ) ) . '" data-term_id="' . (int) $term_id . '"';
	}

	/**
	 * Change wp_list_categories HTML link by adding attachment counter.
	 *
	 * @param array $matches Matched elements
	 * @return string
	 */
	public function replace_folders_count( $matches ) {
		if ( isset( $matches[1] ) ) {
			$count = (int) str_replace( [ ' ', '&nbsp;' ], '', $matches[1] );
			$this->term_counters['values'][] = $count;

			return ' (' . $count . ')</a>';
		}

		return '</a>';
	}

	/**
	 * Change wp_list_categories HTML output by adding jsTree attributes if needed.
	 *
	 * @param array $matches Matched elements
	 * @return string
	 */
	public function open_folders( $matches ) {
		if ( isset( $matches[0] ) ) {
			// open parent term
			if ( isset( $matches[0] ) && strpos( $matches[0], 'current-cat-ancestor' ) !== false )
				return $matches[0] . ' data-jstree=\'{ "opened": true }\'';

			// select current term
			if ( strpos( $matches[0], 'current-cat' ) !== false )
				return $matches[0] . ' data-jstree=\'{ "selected": true }\'';
		}
	}

	/**
	 * Enqueue all needed scripts and styles for media library and modal screens.
	 *
	 * @global string $pagenow
	 * @global object $wp_list_table
	 * @global array $_wp_admin_css_colors
	 *
	 * @param string $page Current page similar to $pagenow depends on from which filter function was called
	 * @return void
	 */
	public function add_library_scripts( $page ) {
		// count how many times function was executed, allow this only once
		static $run = 0;

		// allow only wp media scripts (empty $page), upload.php or media-new.php
		if ( ! ( ( $page === '' || $page === 'upload.php' || $page === 'media-new.php' ) && $run < 1 ) )
			return;

		global $pagenow;

		$run++;

		// change page for wp_enqueue_media
		if ( $page === '' ) {
			if ( $pagenow === 'upload.php' )
				$page = 'upload.php';
			else
				$page = 'media';
		}

		// get main instance
		$rl = Responsive_Lightbox();

		// include select2 styles
		wp_enqueue_style( 'responsive-lightbox-admin-select2', RESPONSIVE_LIGHTBOX_URL . '/assets/select2/select2' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.css', [], $rl->defaults['version'] );

		// filterable media folders taxonomy
		$taxonomy = get_taxonomy( $this->get_active_taxonomy() );

		// no taxonomy? it should be available here, updated in init_folders method
		if ( $taxonomy === false )
			return;

		// main script dependencies
		$dependencies = [ 'jquery', 'underscore', 'jquery-ui-draggable', 'jquery-ui-droppable', 'media-models', 'tags-suggest' ];

		// create folder counters
		$counters = [];

		// Only load full tree/jsTree for upload.php (not media-new.php or media modal)
		// media-new.php needs only the upload destination dropdown functionality
		if ( $page === 'upload.php' ) {
			// prepare variables
			$no_items = '';
			$childless = false;

			// include styles
			wp_enqueue_style( 'responsive-lightbox-folders-admin-css', RESPONSIVE_LIGHTBOX_URL . '/css/admin-folders.css' );
			wp_enqueue_style( 'responsive-lightbox-folders-perfect-scrollbar', RESPONSIVE_LIGHTBOX_URL . '/assets/perfect-scrollbar/perfect-scrollbar' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.css', [], '1.5.3' );
			wp_enqueue_style( 'responsive-lightbox-folders-jstree', RESPONSIVE_LIGHTBOX_URL . '/assets/jstree/themes/default/style' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.css', [], '3.3.17' );

			// get color scheme global
			global $_wp_admin_css_colors;

			// set default color;
			$color = '0,160,210';

			if ( ! empty( $_wp_admin_css_colors ) ) {
				// get current admin color scheme name
				$current_color_scheme = $this->get_user_option_safe( 'admin_color', '' );

				if ( empty( $current_color_scheme ) )
					$current_color_scheme = 'fresh';

				// color exists? some schemes don't have 4 colors
				if ( isset( $_wp_admin_css_colors[$current_color_scheme] ) && property_exists( $_wp_admin_css_colors[$current_color_scheme], 'colors' ) && isset( $_wp_admin_css_colors[$current_color_scheme]->colors[3] ) ) {
					// convert color
					$rgb = $rl->hex2rgb( $_wp_admin_css_colors[$current_color_scheme]->colors[3] );

					// valid color?
					if ( $rgb !== false )
						$color = implode( ',', $rgb );
				}
			}

			// $color is already validated, no escaping
			wp_add_inline_style(
				'responsive-lightbox-folders-jstree',
				'#rl-folders-tree-container .jstree .rl-folders-state-active.rl-folders-state-hover {
					background: #fff !important;
				}
				#rl-folders-tree-container .jstree-container-ul .jstree-wholerow-clicked,
				#rl-folders-tree-container .jstree-container-ul:not(.jstree-wholerow-ul) .jstree-clicked {
					background: rgba(' . esc_attr( $color ) . ', 0.15);
				}
				#rl-folders-tree-container .jstree-container-ul .jstree-wholerow-hovered,
				#rl-folders-tree-container .jstree-container-ul:not(.jstree-wholerow-ul) .jstree-hovered {
					background: rgba(' . esc_attr( $color ) . ', 0.05);
				}'
			);

			// list categories parameters
			$categories = [
				'orderby'				=> 'name',
				'order'					=> 'asc',
				'show_count'			=> true,
				'show_option_all'		=> '',
				'show_option_none'		=> '',
				'use_desc_for_title'	=> false,
				'title_li'				=> '',
				'hide_empty'			=> false,
				'hierarchical'			=> true,
				'taxonomy'				=> $taxonomy->name,
				'hide_title_if_empty'	=> true,
				'echo'					=> false
			];

			// get current term id: explicit URL filter > stored user preference > default (all)
			if ( isset( $_GET[$taxonomy->name] ) ) {
				$term_id = (int) $_GET[$taxonomy->name];
			} else {
				$stored_term = $this->get_selected_term_option();

				if ( $stored_term !== false && $stored_term !== '' && $stored_term !== 'all' ) {
					$term_id = (int) $stored_term;

					// JS stores root folder as '0'; PHP tree uses -1
					if ( $term_id === 0 )
						$term_id = -1;
				} else
					$term_id = 0;
			}

			// list mode?
			if ( $this->mode === 'list' ) {
				// get global wp list table instance
				global $wp_list_table;

				// empty instance?
				if ( is_null( $wp_list_table ) )
					$wp_list_table = _get_list_table( 'WP_Media_List_Table' );

				// start buffering
				ob_start();

				// display "no media" table row
				echo '<tr class="no-items"><td class="colspanchange" colspan="' . (int) $wp_list_table->get_column_count() . '">';

				$wp_list_table->no_items();

				echo '</td></tr>';

				// save "no media" table row
				$no_items = ob_get_contents();

				// clear the buffer
				ob_end_clean();

				// valid term?
				if ( $term_id > 0 ) {
					$children = get_term_children( $term_id, $taxonomy->name );

					// found any children?
					$childless = ! ( ! empty( $children ) && ! is_wp_error( $children ) );
				}
			}

			// set current term id
			if ( $term_id > 0 )
				$categories['current_category'] = $term_id;

			// hide filter for grid
			if ( $this->mode !== 'list' ) {
				wp_add_inline_style(
					'responsive-lightbox-folders-admin-css',
					'#media-attachment-rl-folders-filters { display: none; }
					.media-modal-content .media-frame select.attachment-filters {
						max-width: 100%;
						min-width: auto;
					}'
				);
			}

			// get taxonomy html output
			$html = wp_list_categories( $categories );

			if ( $html !== '' ) {
				// fix for urls
				$html = preg_replace_callback( '/href=(?:\'|")(.*?)(?:\'|")/', [ $this, 'replace_folders_href' ], $html );

				// fix for counters
				$html = preg_replace_callback( '/<\/a> \(((?:\d+|&nbsp;)+)\)/', [ $this, 'replace_folders_count' ], $html );

				// open all needed folders at start
				if ( $term_id > 0 )
					$html = preg_replace_callback( '/class="cat-item cat-item-(\d+)(?:[a-z\s0-9-]+)?"/', [ $this, 'open_folders' ], $html );

				// counters are calculated elsewhere; UI does not currently consume them
			}

			// root folder query
			$root_query = new WP_Query(
				apply_filters(
					'rl_root_folder_query_args',
					[
						'rl_folders_root'	=> true,
						'posts_per_page'	=> -1,
						'post_type'			=> 'attachment',
						'post_status'		=> 'inherit,private',
						'fields'			=> 'ids',
						'no_found_rows'		=> false,
						'tax_query'			=> [
							[
								'relation' => 'AND',
								[
									'taxonomy'			=> $taxonomy->name,
									'field'				=> 'id',
									'terms'				=> 0,
									'include_children'	=> false,
									'operator'			=> 'NOT EXISTS'
								]
							]
						]
					]
				)
			);

			// set number of all attachments
			$counters[-1] = (int) apply_filters( 'rl_count_attachments', 0 );

			// set number of root attachments (not categorized)
			$counters[0] = (int) $root_query->post_count;

			$html = '
			<ul>
				<li class="cat-item cat-item-all"' . ( $term_id === 0 ? ' data-jstree=\'{ "selected": true }\'' : '' ) . '>
					<a href="' . esc_url( apply_filters( 'rl_folders_media_folder_url', add_query_arg( [ 'mode' => $this->mode, $taxonomy->name => 0 ] ), null, $this->mode, 0 ) ) . '" data-term_id="all">' . esc_html__( 'All Files', 'responsive-lightbox' ) . ' (' . (int) $counters[-1] . ')</a>
				</li>
				<li class="cat-item cat-item-0" data-jstree=\'{ "opened": true' . ( $term_id === -1 ? ', "selected": true ' : '' ) . ' }\'>
					<a href="' . esc_url( apply_filters( 'rl_folders_media_folder_url', add_query_arg( [ 'mode' => $this->mode, $taxonomy->name => -1 ] ), null, $this->mode, -1 ) ) . '" data-term_id="0">' . esc_html__( 'Root Folder', 'responsive-lightbox' ) . ' (' . (int) $counters[0] . ')</a>
				<ul>' . $html . '</ul>
				</li>
			</ul>';

			// register scripts
			wp_register_script( 'responsive-lightbox-folders-jstree', RESPONSIVE_LIGHTBOX_URL . '/assets/jstree/jstree' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', [], '3.3.17', false );
			wp_register_script( 'responsive-lightbox-folders-perfect-scrollbar', RESPONSIVE_LIGHTBOX_URL . '/assets/perfect-scrollbar/perfect-scrollbar' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', [], '1.5.3', false );

			$dependencies[] = 'responsive-lightbox-folders-jstree';
			$dependencies[] = 'responsive-lightbox-folders-perfect-scrollbar';
		}

		wp_enqueue_script( 'responsive-lightbox-admin-select2', RESPONSIVE_LIGHTBOX_URL . '/assets/select2/select2.full' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', [ 'jquery' ], $rl->defaults['version'], false );

		wp_enqueue_script( 'responsive-lightbox-folders-admin', RESPONSIVE_LIGHTBOX_URL . '/js/admin-folders.js', $dependencies, $rl->defaults['version'], false );

		$is_upload_screen = ( $page === 'upload.php' );
		$sidebar_width_default = 272;
		$sidebar_width_min = 220;
		$sidebar_width_max = 420;
		$sidebar_width_collapsed = 0;

		// Minimal script data for media modal
		if ( $page === 'media' ) {
			// prepare script data
			$script_data = [
				'taxonomy'	=> $taxonomy->name,
				'page'		=> $page,
				'root'		=> esc_html__( 'Root Folder', 'responsive-lightbox' ),
				'terms'		=> wp_dropdown_categories(
					[
						'orderby'			=> 'name',
						'order'				=> 'asc',
						'show_option_all'	=> esc_html__( 'All Files', 'responsive-lightbox' ),
						'show_count'		=> false,
						'hide_empty'		=> false,
						'hierarchical'		=> true,
						'selected'			=> ( isset( $_GET[$taxonomy->name] ) ? (int) $_GET[$taxonomy->name] : 0 ),
						'name'				=> $taxonomy->name,
						'taxonomy'			=> $taxonomy->name,
						'hide_if_empty'		=> true,
						'echo'				=> false
					]
				)
			];
		} elseif ( $page === 'media-new.php' ) {
			// Minimal script data for media-new.php - only needs upload destination sync
			// No tree/jsTree needed, just page flag for JS to skip initialization
			$script_data = [
				'taxonomy'	=> $taxonomy->name,
				'page'		=> $page,
				'root'		=> esc_html__( 'Root Folder', 'responsive-lightbox' ),
				'terms'		=> wp_dropdown_categories(
					[
						'orderby'			=> 'name',
						'order'				=> 'asc',
						'show_option_all'	=> esc_html__( 'All Files', 'responsive-lightbox' ),
						'show_count'		=> false,
						'hide_empty'		=> false,
						'hierarchical'		=> true,
						'selected'			=> 0,
						'name'				=> $taxonomy->name,
						'taxonomy'			=> $taxonomy->name,
						'hide_if_empty'		=> true,
						'echo'				=> false
					]
				)
			];
		} else {
			// prepare script data
			$script_data = [
				'remove_children'	=> $rl->options['folders']['folders_removal'],
				'wholerow'			=> $rl->options['folders']['jstree_wholerow'],
				'theme'				=> 'default',
				'counters'			=> $counters,
				'no_media_items'	=> $no_items,
				'taxonomy'			=> $taxonomy->name,
				'page'				=> $page,
				'mode'				=> $this->mode,
				'root'				=> esc_html__( 'Root Folder', 'responsive-lightbox' ),
				'all_terms'			=> esc_html__( 'All Files', 'responsive-lightbox' ),
				'new_folder'		=> esc_html__( 'New Folder', 'responsive-lightbox' ),
				'delete_term'		=> esc_html__( 'Are you sure you want to delete this folder?', 'responsive-lightbox' ),
				'delete_terms'		=> esc_html__( 'Are you sure you want to delete this folder with all subfolders?', 'responsive-lightbox' ),
				'nonce'				=> wp_create_nonce( 'rl-folders-ajax-library-nonce' ),
				'terms'				=> wp_dropdown_categories(
					[
						'orderby'			=> 'name',
						'order'				=> 'asc',
						'show_option_all'	=> esc_html__( 'All Files', 'responsive-lightbox' ),
						'show_count'		=> false,
						'hide_empty'		=> false,
						'hierarchical'		=> true,
						'selected'			=> ( isset( $_GET[$taxonomy->name] ) ? (int) $_GET[$taxonomy->name] : 0 ),
						'name'				=> $taxonomy->name,
						'taxonomy'			=> $taxonomy->name,
						'hide_if_empty'		=> true,
						'echo'				=> false
					]
				),
				'template'			=> '
					<div id="rl-folders-tree-container">
						<div class="media-toolbar wp-filter">
							<div class="view-switch rl-folders-action-links">
								<a href="#" title="' . esc_attr( $taxonomy->labels->add_new_item ) . '" class="dashicons dashicons-plus rl-folders-add-new-folder"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Save new %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-yes rl-folders-save-new-folder" style="display: none;"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Cancel adding new %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-no rl-folders-cancel-new-folder" style="display: none;"></a>
								<a href="#" title="' . esc_attr( $taxonomy->labels->edit_item ) . '" class="dashicons dashicons-edit rl-folders-rename-folder' . ( $this->mode === 'list' && $term_id > 0 ? '' : ' disabled-link' ) . '"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Save %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-yes rl-folders-save-folder" style="display: none;"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Cancel renaming %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-no rl-folders-cancel-folder" style="display: none;"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Delete %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-trash rl-folders-delete-folder' . ( $this->mode === 'list' && $term_id > 0 ? '' : ' disabled-link' ) . '"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Expand %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-arrow-down-alt2 rl-folders-expand-folder' . ( $this->mode === 'list' && ! $childless && ( $term_id === -1 || $term_id > 0 ) ? '' : ' disabled-link' ) . '"></a>
								<a href="#" title="' . esc_attr( sprintf( __( 'Collapse %s', 'responsive-lightbox' ), $taxonomy->labels->singular_name ) ) . '" class="dashicons dashicons-arrow-up-alt2 rl-folders-collapse-folder' . ( $this->mode === 'list' && ! $childless && ( $term_id === -1 || $term_id > 0 ) ? '' : ' disabled-link' ) . '"></a>
							</div>
						</div>
						<div id="rl-folders-tree">' . wp_kses_post( $html ) . '</div>
					</div>'
			];
		}

		if ( $is_upload_screen && isset( $script_data['template'] ) ) {
			$script_data['template'] = '
				<div id="rl-folders-tree-root">
					<div id="rl-folders-tree-shell" class="rl-folders-tree-shell">
						<div class="rl-folders-tree-header">
							<span class="rl-folders-tree-title">' . esc_html__( 'Media Folders', 'responsive-lightbox' ) . '</span>
							<button type="button" class="button button-secondary rl-folders-header-add-new-folder">' . esc_html__( 'New Folder', 'responsive-lightbox' ) . '</button>
						</div>
						<div class="rl-folders-tree-viewport">
							<div class="rl-folders-tree-viewport-inner">' . $script_data['template'] . '</div>
							<span class="rl-folders-tree-cover" aria-hidden="true"></span>
						</div>
					</div>
					<div class="rl-folders-sidebar-separator" role="separator" aria-label="' . esc_attr__( 'Resize folders sidebar', 'responsive-lightbox' ) . '" aria-orientation="vertical" tabindex="0" aria-valuemin="' . esc_attr( $sidebar_width_min ) . '" aria-valuemax="' . esc_attr( $sidebar_width_max ) . '" aria-valuenow="' . esc_attr( $sidebar_width_default ) . '">
						<button type="button" class="button-link rl-folders-sidebar-toggle" aria-expanded="true" aria-controls="rl-folders-tree-shell" title="' . esc_attr__( 'Toggle folders sidebar', 'responsive-lightbox' ) . '">
							<span class="dashicons dashicons-menu-alt2" aria-hidden="true"></span>
							<span class="screen-reader-text">' . esc_html__( 'Toggle folders sidebar', 'responsive-lightbox' ) . '</span>
						</button>
					</div>
				</div>';
		}

		$script_data['sidebar_width_default'] = $sidebar_width_default;
		$script_data['sidebar_width_min'] = $sidebar_width_min;
		$script_data['sidebar_width_max'] = $sidebar_width_max;
		$script_data['sidebar_width_collapsed'] = $sidebar_width_collapsed;

		// pass resolved selected term to JS (uses $term_id already resolved from URL > stored > default)
		if ( $is_upload_screen ) {
			if ( $term_id === 0 )
				$script_data['selected_term'] = 'all';
			elseif ( $term_id === -1 )
				$script_data['selected_term'] = '0';
			else
				$script_data['selected_term'] = (string) $term_id;
		}

		wp_add_inline_script( 'responsive-lightbox-folders-admin', 'var rlFoldersArgs = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

		add_action( 'admin_print_styles', [ $this, 'admin_print_media_styles' ] );
	}

	/**
	 * CSS fix for media folders checklist.
	 *
	 * @return void
	 */
	public function admin_print_media_styles() {
		echo '<style>.rl_media_folder li .selectit input[type="checkbox"] { margin: 0 3px; }</style>';
	}

	/**
	 * Count attachments.
	 *
	 * @return int
	 */
	public function count_attachments() {
		$count = wp_count_posts( 'attachment' );

		return (int) $count->inherit;
	}

	/**
	 * Get all previously used media taxonomies.
	 *
	 * @global object $wpdb
	 *
	 * @return array
	 */
	public function get_taxonomies( $force_refresh = false ) {
		if ( ! $force_refresh && is_array( $this->cached_taxonomies ) )
			return $this->cached_taxonomies;

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

		$this->cached_taxonomies = is_array( $fields ) ? array_values( $fields ) : [];

		return $this->cached_taxonomies;
	}
}
