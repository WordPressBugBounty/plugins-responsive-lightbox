<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

// Include gallery traits
require_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/trait-gallery-duplicate.php' );
require_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/trait-gallery-preview.php' );
require_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/trait-gallery-sanitize.php' );
require_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/trait-gallery-ajax.php' );
require_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/trait-gallery-image-methods.php' );

/**
 * Responsive Lightbox Galleries class.
 *
 * @class Responsive_Lightbox_Galleries
 */
class Responsive_Lightbox_Galleries {

	use Responsive_Lightbox_Gallery_Duplicate;
	use Responsive_Lightbox_Gallery_Preview;
	use Responsive_Lightbox_Gallery_Sanitize;
	use Responsive_Lightbox_Gallery_Ajax;
	use Responsive_Lightbox_Galleries_Image_Methods;

	public $fields;
	private $tabs;
	private $sizes;
	private $gallery_args;
	private $menu_item;
	private $revision_id;
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


	/**
	 * Class constructor.
	 *
	 * @param bool $read_only Whether is it read only mode
	 * @return void
	 */
	public function __construct( $read_only = false ) {
		// set instance
		Responsive_Lightbox()->galleries = $this;

		if ( $read_only )
			return;

		// Initialize gallery settings API - all tabs managed by adapter
		include_once( RESPONSIVE_LIGHTBOX_PATH . 'includes/galleries/class-gallery-api.php' );
		Responsive_Lightbox()->gallery_api = new Responsive_Lightbox_Gallery_API();

		// actions
		add_action( 'init', array( $this, 'init' ), 11 );
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'current_screen', array( $this, 'clear_metaboxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'after_title_nav_menu' ) );
		add_action( 'admin_footer', array( $this, 'modal_gallery_template' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'modal_gallery_template' ) );
		add_action( 'media_buttons', array( $this, 'add_gallery_button' ) );
		add_action( 'add_meta_boxes_rl_gallery', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_rl_gallery', array( $this, 'save_post' ), 10, 3 );
		add_action( 'manage_rl_gallery_posts_custom_column', array( $this, 'gallery_columns_content' ), 10, 2 );
		add_action( 'admin_action_duplicate_gallery', array( $this, 'duplicate_gallery' ) );
		add_action( 'wp_ajax_rl-get-menu-content', array( $this, 'get_menu_content' ) );
		add_action( 'wp_ajax_rl-get-preview-content', array( $this, 'get_gallery_preview_content' ) );
		add_action( 'wp_ajax_rl-post-get-galleries', array( $this, 'post_get_galleries' ) );
		add_action( 'wp_ajax_rl-post-gallery-preview', array( $this, 'post_gallery_preview' ) );
		add_action( 'wp_ajax_rl-get-gallery-page-content', array( $this, 'get_gallery_page' ) );
		add_action( 'wp_ajax_nopriv_rl-get-gallery-page-content', array( $this, 'get_gallery_page' ) );
		add_action( '_wp_put_post_revision', array( $this, 'save_revision' ) );
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
		add_action( 'shutdown', array( $this, 'shutdown_preview' ) );
		add_action( 'wp_loaded', array( $this, 'maybe_change_lightbox' ), 1 );

		// filters
		add_filter( 'manage_rl_gallery_posts_columns', array( $this, 'gallery_columns' ) );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ), 10, 3 );
		add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail_html' ), 10, 5 );
		add_filter( 'preview_post_link', array( $this, 'preview_post_link' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions_duplicate' ), 10, 2 );

		if ( ! empty( $_POST['rl_active_tab'] ) )
			add_filter( 'redirect_post_location', array( $this, 'add_active_tab' ) );
	}

	/**
	 * Get class data.
	 *
	 * @param string $attr
	 * @return mixed
	 */
	public function get_data( $attr ) {
		return property_exists( $this, $attr ) ? $this->{$attr} : null;
	}

	/**
	 * Get default gallery single image template.
	 *
	 * @param array $args Template arguments
	 * @return string
	 */
	public function get_media_item_template( $args = [] ) {
		$args = array_merge(
			array(
				'draggable'		=> false,
				'editable'		=> false,
				'removable'		=> false,
				'changeable'	=> false
			),
			$args
		);

		return '
		<li class="rl-gallery-image__MEDIA_STATUS__" data-attachment_id="__MEDIA_ID__" data-type="__MEDIA_TYPE__"' . ( $args['draggable'] ? ' style="cursor: move;"' : '' ) . '>
			<div class="rl-gallery-inner">
				<div class="centered">
					__MEDIA_DATA__
				</div>
			</div>
			<div class="rl-gallery-actions">' .
				( $args['changeable'] ? '<a href="#" class="rl-gallery-image-status dashicons dashicons-marker" title="' . esc_attr__( 'Status', 'responsive-lightbox' ) . '"></a>' : '' ) .
				( $args['editable'] ? '<a href="#" class="rl-gallery-image-edit dashicons dashicons-edit" title="' . esc_attr__( 'Edit image', 'responsive-lightbox' ) . '"></a>' : '' ) .
				( $args['removable'] ? '<a href="#" class="rl-gallery-image-remove dashicons dashicons-no" title="' . esc_attr__( 'Remove image', 'responsive-lightbox' ) . '"></a>' : '' ) . '
			</div>
		</li>';
	}

	/**
	 * Get default gallery embed template.
	 *
	 * @param bool $js
	 * @return string
	 */
	public function get_media_embed_template( $js = false ) {
		$html = '';

		if ( $js )
			$html .= '<div data-id="__EMBED_ID__" style="display: none;">';

		$html .= '
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][url]" data-type="url" value="__EMBED_URL__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][width]" data-type="width" value="__EMBED_WIDTH__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][height]" data-type="height" value="__EMBED_HEIGHT__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][thumbnail_url]" data-type="thumbnail_url" value="__EMBED_THUMBNAIL_URL__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][thumbnail_width]" data-type="thumbnail_width" value="__EMBED_THUMBNAIL_WIDTH__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][thumbnail_height]" data-type="thumbnail_height" value="__EMBED_THUMBNAIL_HEIGHT__">
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][title]" data-type="title" value="__EMBED_TITLE__">
		<textarea class="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][caption]" data-type="caption">__EMBED_DESCRIPTION__</textarea>
		<input type="hidden" name="rl_gallery[images][media][attachments][embed][__EMBED_ID__][date]" data-type="date" value="__EMBED_DATE__">';

		if ( $js )
			$html .= '</div>';

		return $html;
	}

	/**
	 * Set the current menu item for image source resolution.
	 *
	 * Used by gallery adapter to set menu_item before calling get_gallery_images().
	 *
	 * @param string $menu_item Menu item key.
	 * @return void
	 */
	public function set_menu_item( $menu_item ) {
		$this->menu_item = sanitize_key( $menu_item );
	}

	/**
	 * Get default gallery exclude input template.
	 *
	 * @param string $tab_id
	 * @param string $menu_item
	 * @param string $field_name
	 * @param mixed $excluded_value
	 * @return string
	 */
	public function get_media_exclude_input_template( $tab_id = '', $menu_item = '', $field_name = '', $excluded_value = '' ) {
		$template = '<input type="hidden" class="rl-gallery-exclude" name="rl_gallery[__MEDIA_TAB_ID__][__MEDIA_MENU_ITEM__][__MEDIA_FIELD_NAME__][exclude][]" value="__MEDIA_FIELD_VALUE__" />';

		if ( $tab_id === '' && $menu_item === '' && $field_name === '' && $excluded_value === '' )
			return str_replace( '__MEDIA_FIELD_VALUE__', '', $template );

		return str_replace(
			[
				'__MEDIA_TAB_ID__',
				'__MEDIA_MENU_ITEM__',
				'__MEDIA_FIELD_NAME__',
				'__MEDIA_FIELD_VALUE__'
			],
			[
				esc_attr( $tab_id ),
				esc_attr( $menu_item ),
				esc_attr( $field_name ),
				empty( $excluded_value ) ? '' : esc_attr( $excluded_value )
			],
			$template
		);
	}

	/**
	 * Load needed data.
	 *
	 * @return void
	 */
	public function init() {
		// register shortcode
		add_shortcode( 'rl_gallery', array( $this, 'gallery_shortcode' ) );

		// get main instance
		$rl = Responsive_Lightbox();

		// set lightbox script for infinite scroll pages
		if ( isset( $_GET['rl_gallery_no'], $_GET['rl_page'], $_GET['rl_lightbox_script'] ) )
			$rl->set_lightbox_script( sanitize_key( $_GET['rl_lightbox_script'] ) );

		$config_menu_items = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );
		$config_menu_items['default'] = __( 'Global', 'responsive-lightbox' );

		// set tabs
		$this->tabs = apply_filters(
			'rl_gallery_tabs',
			array(
				'images'	=> array(
					'label'			=> __( 'Images', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the contents of the gallery.', 'responsive-lightbox' ),
					'menu_items'	=> array(
						'media'		=> __( 'Media Library', 'responsive-lightbox' ),
						'featured'	=> __( 'Featured Content', 'responsive-lightbox' )
					)
				),
				'config'	=> array(
					'label'			=> __( 'Config', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below allow you to select a gallery type and adjust the gallery options.', 'responsive-lightbox' ),
					'menu_items'	=> $config_menu_items
				),
				'design'	=> array(
					'label'			=> __( 'Design', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the gallery design options.', 'responsive-lightbox' )
				),
				'paging'	=> array(
					'label'			=> __( 'Paging', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the gallery pagination options.', 'responsive-lightbox' )
				),
				'lightbox'	=> array(
					'label'			=> __( 'Lightbox', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust the lightbox options.', 'responsive-lightbox' ),
				),
				'misc'		=> array(
					'label'			=> __( 'Misc', 'responsive-lightbox' ),
					'description'	=> __( 'The settings below adjust miscellaneous options.', 'responsive-lightbox' ),
				)
			)
		);

		// add folders if active
		if ( $rl->options['folders']['active'] )
			$this->tabs['images']['menu_items']['folders'] = __( 'Media Folder', 'responsive-lightbox' );

		// add remote library if active
		$this->tabs['images']['menu_items']['remote_library'] = __( 'Remote Library', 'responsive-lightbox' );

		// use sizes as keys and values
		$this->sizes = $this->get_image_sizes();
		$sizes = array_combine( array_keys( $this->sizes ), array_keys( $this->sizes ) );

		// add default, custom and full image size
		$sizes['full'] = __( 'Full size', 'responsive-lightbox' );
		$sizes['global'] = __( 'Global', 'responsive-lightbox' );
		$sizes['rl_custom_size'] = __( 'Custom size', 'responsive-lightbox' );

		// positions
		$positions = array(
			'none'		=> __( 'None', 'responsive-lightbox' ),
			'top'		=> __( 'Top', 'responsive-lightbox' ),
			'bottom'	=> __( 'Bottom', 'responsive-lightbox' )
		);

		// merge titles
		$merged_titles = array( 'global' => __( 'Global', 'responsive-lightbox' ) ) + $rl->settings->get_data( 'image_titles' );

		// set fields
		$this->fields = array(
				'images' => [],
				// Field definitions for config, design, paging, lightbox, misc are populated from adapter classes below.
				// This ensures single source of truth and eliminates duplication while maintaining get_data('fields') contract.
				'config' => [],
				'design' => [],
				'paging' => [],
				'lightbox' => [],
				'misc' => []
			);

		// Populate adapter-owned tabs from their classes (single source of truth - v2.7.1+)
		// This eliminates field duplication while maintaining get_data('fields') public contract
		// for core frontend and gallery add-ons that access design/lightbox/misc fields.
		if ( isset( $rl->gallery_api ) ) {
			foreach ( [ 'images', 'config', 'design', 'paging', 'lightbox', 'misc' ] as $adapter_tab ) {
				if ( $rl->gallery_api->is_managed_tab( $adapter_tab ) ) {
					$tab_data = $rl->gallery_api->get_tab_definition( $adapter_tab );
					if ( ! empty( $tab_data ) ) {
						$this->fields[$adapter_tab] = $tab_data;
					}
				}
			}
		}
	}

	/**
	 * Add a gallery shortcode.
	 *
	 * @param array $args Shortcode arguments
	 * @return string
	 */
	public function gallery_shortcode( $args ) {
		// enable only for frontend previews
		if ( ! is_admin() && is_preview() )
			add_filter( 'get_post_metadata', array( $this, 'filter_preview_metadata' ), 10, 4 );

		// prepare defaults
		$defaults = [ 'id' => 0 ];

		// merge defaults with arguments
		$args = array_merge( $defaults, $args );

		// parse id
		$args['id'] = (int) $args['id'];

		// is it gallery?
		if ( get_post_type( $args['id'] ) !== 'rl_gallery' )
			return '';

		// private gallery?
		if ( get_post_status( $args['id'] ) === 'private' && ! current_user_can( 'read_private_posts' ) )
			return '';

		$images_args = [ 'exclude' => true ];

		if ( isset( $args['preview'] ) )
			$images_args['preview'] = (bool) $args['preview'];
		elseif( isset( $_GET['rl_gallery_revision_id'], $_GET['preview'] ) && $_GET['preview'] === 'true' )
			$images_args['preview'] = true;
		else
			$images_args['preview'] = false;

		// get images
		$images = $this->get_gallery_images( $args['id'], $images_args );

		if ( ! $images )
			return '';

		$attachments = [];

		// build config
		foreach ( $images as $image ) {
			if ( ! empty( $image['id'] ) )
				$attachments[] = $image['id'];
		}

		// get config data
		$config = get_post_meta( $args['id'], '_rl_config', true );
		if ( ! is_array( $config ) )
			$config = [];

		// get main instance
		$rl = Responsive_Lightbox();

		// available gallery types
		$gallery_types = apply_filters( 'rl_gallery_types', $rl->get_data( 'gallery_types' ) );
		if ( ! is_array( $gallery_types ) )
			$gallery_types = [];

		// prepare gallery shortcode parameters
		$fields = [];
		$gallery_type = '';
		$menu_item = ! empty( $config['menu_item'] ) ? $config['menu_item'] : 'default';
		if ( ! empty( $menu_item ) && $menu_item !== 'default' && ! array_key_exists( $menu_item, $gallery_types ) )
			$menu_item = 'default';

		// valid menu item?
		if ( ! empty( $menu_item ) ) {
			// assign data from db
			$data = isset( $config[$menu_item] ) && is_array( $config[$menu_item] ) ? $config[$menu_item] : [];

			foreach ( $rl->frontend->get_default_gallery_fields() as $field_name => $field_args ) {
				// replace default values
				if ( array_key_exists( $field_name, $data ) )
					$fields[$field_name] = $data[$field_name];
			}

			// is it default gallery type?
			if ( $menu_item === 'default' ) {
				// set new gallery type
				$gallery_type = $rl->options['settings']['builder_gallery'];

				// fallback to a valid gallery type
				if ( empty( $gallery_type ) || ! array_key_exists( $gallery_type, $gallery_types ) )
					$gallery_type = $rl->defaults['settings']['builder_gallery'];

				// assign gallery settings
			$gallery_fields = $rl->settings->get_setting_fields( $gallery_type . '_gallery' );
				// assign gallery defaults
				if ( array_key_exists( $gallery_type . '_gallery', $rl->options ) )
					$gallery_defaults = $rl->options[$gallery_type . '_gallery'];
			} else {
				$gallery_type = $menu_item;

				// fallback to a valid gallery type
				if ( empty( $gallery_type ) || ! array_key_exists( $gallery_type, $gallery_types ) )
					$gallery_type = $rl->options['settings']['builder_gallery'];

				// assign gallery settings
			$gallery_fields = $rl->settings->get_setting_fields( $menu_item . '_gallery' );
				// assign gallery defaults
				if ( array_key_exists( $menu_item . '_gallery', $rl->defaults ) )
					$gallery_defaults = $rl->defaults[$menu_item . '_gallery'];
			}

			if ( ! isset( $gallery_defaults ) && ! empty( $gallery_type ) && array_key_exists( $gallery_type . '_gallery', $rl->defaults ) )
				$gallery_defaults = $rl->defaults[$gallery_type . '_gallery'];

if ( ! isset( $gallery_fields ) && ! empty( $gallery_type ) )
				$gallery_fields = $rl->settings->get_setting_fields( $gallery_type . '_gallery' );

			if ( isset( $gallery_fields, $gallery_defaults ) ) {
				// run through all fields
				foreach ( $gallery_fields as $field_name => $field_args ) {
					if ( $field_args['type'] === 'multiple' ) {
						foreach ( $field_args['fields'] as $subfield_name => $subfield_args ) {
							// field exists in db?
							if ( array_key_exists( $subfield_name, $data ) )
								$fields[$subfield_name] = $data[$subfield_name];
							elseif ( isset( $rl->options[$gallery_type . '_gallery'][ $subfield_name ] ) )
								$fields[$subfield_name] = $rl->options[$gallery_type . '_gallery'][ $subfield_name ];
							else
								$fields[$subfield_name] = $gallery_defaults[$subfield_name];
						}
					} else {
						// field exists in db?
						if ( array_key_exists( $field_name, $data ) )
							$fields[$field_name] = $data[$field_name];
						elseif ( isset( $rl->options[$gallery_type . '_gallery'][ $field_name ] ) )
							$fields[$field_name] = $rl->options[$gallery_type . '_gallery'][ $field_name ];
						else
							$fields[$field_name] = $gallery_defaults[$field_name];
					}
				}
			}
			// add gallery type
			if ( ! empty( $gallery_type ) && array_key_exists( $gallery_type, $gallery_types ) )
				$fields['type'] = $gallery_type;
		}

		// ensure gallery type is present and valid
		if ( empty( $fields['type'] ) || ! array_key_exists( $fields['type'], $gallery_types ) ) {
			$fallback_type = $rl->options['settings']['builder_gallery'];
			if ( empty( $fallback_type ) || ! array_key_exists( $fallback_type, $gallery_types ) )
				$fallback_type = $rl->defaults['settings']['builder_gallery'];

			if ( ! empty( $fallback_type ) && array_key_exists( $fallback_type, $gallery_types ) )
				$fields['type'] = $fallback_type;
		}

		$shortcode = '';

		foreach ( $fields as $arg => $value ) {
			if ( is_array( $value ) )
				$shortcode .= ' ' . esc_attr( $arg ) . '="' . esc_attr( (string) implode( ',', $value ) ) . '"';
			else
				$shortcode .= ' ' . esc_attr( $arg ) . '="' . esc_attr( (string) $value ) . '"';
		}

		// get design data
		$design = get_post_meta( $args['id'], '_rl_design', true );
		$design = is_array( $design ) ? $design : [];

		if ( ! empty( $design['menu_item'] ) && isset( $design[$design['menu_item']] ) && is_array( $design[$design['menu_item']] ) ) {
			$design_data = $design[$design['menu_item']];

			// remove show_title to avoid shortcode attribute duplication
			if ( isset( $design_data['show_title'] ) ) {
				if ( ! isset( $design_data['design_show_title'] ) )
					$design_data['design_show_title'] = $design_data['show_title'];

				unset( $design_data['show_title'] );
			}

			// remove show_caption to avoid shortcode attribute duplication
			if ( isset( $design_data['show_caption'] ) ) {
				if ( ! isset( $design_data['design_show_caption'] ) )
					$design_data['design_show_caption'] = $design_data['show_caption'];

				unset( $design_data['show_caption'] );
			}

			foreach ( $design_data as $arg => $value ) {
				$shortcode .= ' ' . esc_attr( $arg ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		// get lightbox data
		$lightbox = get_post_meta( $args['id'], '_rl_lightbox', true );
		$lightbox = is_array( $lightbox ) ? $lightbox : [];

		if ( ! empty( $lightbox['menu_item'] ) && isset( $lightbox[$lightbox['menu_item']] ) && is_array( $lightbox[$lightbox['menu_item']] ) ) {
			foreach ( $lightbox[$lightbox['menu_item']] as $arg => $value ) {
				$shortcode .= ' ' . esc_attr( $arg ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		$forced_gallery_no = 0;

		// check forced gallery number
		if ( isset( $args['gallery_no'] ) ) {
			$args['gallery_no'] = (int) $args['gallery_no'];

			if ( $args['gallery_no'] > 0 )
				$forced_gallery_no = $args['gallery_no'];
		}

		// get content
		$content = do_shortcode( '[gallery rl_gallery_id="' . esc_attr( $args['id'] ) .'"' . ( $forced_gallery_no > 0 ? ' rl_gallery_no="' . (int) $forced_gallery_no .'"' : '' ) . ' include="' . ( empty( $attachments ) ? '' : esc_attr( implode( ',', $attachments ) ) ) . '"' . $shortcode . ']' );

		// make sure every filter is available in frontend ajax
		if ( wp_doing_ajax() )
			$content = $rl->frontend->add_lightbox( $content );

		return $content;
	}

	/**
	 * Add a gallery button.
	 *
	 * @param string $editor_id Editor ID
	 * @return void
	 */
	public function add_gallery_button( $editor_id ) {
		if ( get_post_type() === 'rl_gallery' )
			return;

		$this->enqueue_gallery_scripts_styles();

		echo '<button type="button" id="rl-insert-modal-gallery-button" class="button" data-editor="' . esc_attr( $editor_id ) . '"><span class="wp-media-buttons-icon dashicons dashicons-format-gallery"></span> ' . esc_html__( 'Add Gallery', 'responsive-lightbox' ) . '</button>';
	}

	/**
	 * Enqueue scripts and styles needed for gallery modal.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function enqueue_gallery_scripts_styles() {
		global $pagenow;

		// count how many times function was executed
		static $run = 0;

		// allow this only once
		if ( $run > 0 )
			return;

		$run++;

		// get main instance
		$rl = Responsive_Lightbox();

		wp_enqueue_script( 'responsive-lightbox-admin-gallery', RESPONSIVE_LIGHTBOX_URL . '/js/admin-gallery.js', array( 'jquery', 'underscore' ), $rl->defaults['version'], false );

		// prepare script data
		$script_data = [
			'nonce'		=> wp_create_nonce( 'rl-gallery-post' ),
			'post_id'	=> get_the_ID(),
			'page'		=> esc_url( $pagenow )
		];

		wp_add_inline_script( 'responsive-lightbox-admin-gallery', 'var rlArgsGallery = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

		wp_enqueue_style( 'responsive-lightbox-admin-gallery', RESPONSIVE_LIGHTBOX_URL . '/css/admin-gallery.css', [], $rl->defaults['version'] );
	}

	/**
	 * Modal gallery HTML template.
	 *
	 * @global string $wp_version
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function modal_gallery_template() {
		global $wp_version;
		global $pagenow;

		// display only for post edit pages
		if ( ! ( ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && get_post_type() !== 'rl_gallery' ) || ( version_compare( $wp_version, '5.8', '>=' ) && ( $pagenow === 'widgets.php' || $pagenow === 'customize.php' ) ) ) )
			return;

		// get main instance
		$rl = Responsive_Lightbox();

		$categories = '';

		// builder categories?
		if ( $rl->options['builder']['categories'] ) {
			$terms = get_terms(
				array(
					'taxonomy'		=> 'rl_category',
					'orderby'		=> 'name',
					'order'			=> 'ASC',
					'hide_empty'	=> false,
					'fields'		=> 'id=>name'
				)
			);

			// get categories dropdown
			$categories = wp_dropdown_categories(
				array(
					'orderby'			=> 'name',
					'order'				=> 'asc',
					'show_option_none'	=> empty( $terms ) ? __( 'All categories', 'responsive-lightbox' ) : '',
					'show_option_all'	=> __( 'All categories', 'responsive-lightbox' ),
					'show_count'		=> false,
					'hide_empty'		=> false,
					'option_none_value'	=> 0,
					'hierarchical'		=> true,
					'selected'			=> 0,
					'taxonomy'			=> 'rl_category',
					'hide_if_empty'		=> false,
					'echo'				=> false,
					'id'				=> 'rl-media-attachment-categories',
					'class'				=> 'attachment-filters',
					'name'				=> ''
				)
			);
		}

		echo '
		<div id="rl-modal-gallery" style="display: none;">
			<div class="media-modal wp-core-ui">
				<button type="button" class="media-modal-close"><span class="media-modal-icon"><span class="screen-reader-text">' . esc_html__( 'Close', 'responsive-lightbox' ) . '</span></span></button>
				<div class="media-modal-content">
					<div class="media-frame mode-select wp-core-ui hide-menu hide-router">
						<div class="media-frame-title">
							<h1 class="wrap">' . esc_html__( 'Insert Gallery', 'responsive-lightbox' ) . ' <a class="rl-reload-galleries page-title-action" href="#">' . esc_html__( 'Reload', 'responsive-lightbox' ). '</a><span class="rl-gallery-reload-spinner spinner"></span></h1>
						</div>
						<div class="media-frame-content" data-columns="0">
							<div class="attachments-browser">
								<div class="uploader-inline rl-no-galleries" style="display: none;">
									<div class="uploader-inline-content has-upload-message">
										<h2 class="upload-message">' . esc_html__( 'No items found.', 'responsive-lightbox' ) . '</h2>
										<div class="upload-ui">
											<h2 class="upload-instructions">' . esc_html__( 'No galleries? Create them first or try another search phrase.', 'responsive-lightbox' ) . '</h2>
										</div>
									</div>
								</div>
								<div class="media-toolbar">' . ( $rl->options['builder']['categories'] ? '
									<div class="media-toolbar-secondary"><label for="rl-media-attachment-categories" class="screen-reader-text">' . esc_html__( 'Filter by category', 'responsive-lightbox' ) . '</label>' . ( $categories !== '' ? wp_kses( $categories, $this->allowed_select_html ) : '' ) . '</div>' : '' ) . '
									<div class="media-toolbar-primary search-form">
										<label for="rl-media-search-input" class="screen-reader-text">' . esc_html__( 'Search galleries', 'responsive-lightbox' ) . '</label><input type="search" placeholder="' . esc_attr__( 'Search galleries', 'responsive-lightbox' ) . '" id="rl-media-search-input" class="search">
									</div>
								</div>
								<ul class="attachments rl-galleries-list ui-sortable ui-sortable-disabled">
								</ul>
								<div class="media-sidebar visible">
									<h2>' . esc_html__( 'Select A Gallery', 'responsive-lightbox' ) . '</h2>
									<p>' . esc_html__( 'To select a gallery simply click on one of the boxes to the left.', 'responsive-lightbox' ) . '</p>
									<p>' . esc_html__( 'To insert your gallery into the editor, click on the "Insert Gallery" button below.', 'responsive-lightbox' ) . '</p>
								</div>
							</div>
						</div>
						<div class="media-frame-toolbar">
							<div class="media-toolbar">
								<div class="media-toolbar-secondary">
									<div class="media-selection empty">
										<div class="selection-info">
											<span class="rl-gallery-count count">' . esc_html( sprintf( _n( '%s image', '%s images', 0, 'responsive-lightbox' ), 0 ) ) . '</span>
											<a href="" class="button-link rl-edit-gallery-link">' . esc_html__( 'Edit gallery', 'responsive-lightbox' ) . '</a>
										</div>
										<div class="selection-view">
											<span class="rl-gallery-images-spinner spinner" style="display: none;"></span>
											<ul class="attachments rl-attachments-list">
											</ul>
										</div>
									</div>
								</div>
								<div class="media-toolbar-primary search-form">
									<button style="display: none;" type="button" class="button media-button button-primary button-large rl-media-button-insert-gallery" disabled="disabled">' . esc_html__( 'Insert gallery into post', 'responsive-lightbox') . '</button>
									<button style="display: none;" type="button" class="button media-button button-primary button-large rl-media-button-select-gallery" disabled="disabled">' . esc_html__( 'Select gallery', 'responsive-lightbox') . '</button>
									<button type="button" class="button media-button button-secondary button-large rl-media-button-cancel-gallery">' . esc_html__( 'Cancel', 'responsive-lightbox') . '</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="media-modal-backdrop"></div>
		</div>';
	}

	/**
	 * Add menu tabs after the post title.
	 *
	 * @global array $wp_meta_boxes
	 *
	 * @param object $post Post object
	 * @return void
	 */
	public function after_title_nav_menu( $post ) {
		if ( $post->post_type !== 'rl_gallery' )
			return;

		global $wp_meta_boxes;

		// check active tab
		$active_tab = isset( $_GET['rl_active_tab'] ) ? sanitize_key( $_GET['rl_active_tab'] ) : '';
		$active_tab = ! empty( $active_tab ) && array_key_exists( $active_tab, $this->tabs ) ? $active_tab : 'images';

		echo '
		<div class="rl-settings-wrapper">
		<h2 class="nav-tab-wrapper">';

		foreach ( $this->tabs as $key => $data ) {
			echo '
			<a id="rl-gallery-tab-' . esc_attr( $key ) . '" class="rl-gallery-tab nav-tab' . ( $key === $active_tab ? ' nav-tab-active' : '' ) . '" href="#' . esc_attr( $key ) . '">' . esc_html( $data['label'] ) . '</a>';
		}

		echo '
		</h2>';

		do_meta_boxes( $post->post_type, 'responsive_lightbox_metaboxes', $post );

		echo '
		</div>';

		unset( $wp_meta_boxes[$post->post_type]['responsive_lightbox_metaboxes'] );
	}

	/**
	 * Add class to hide metabox.
	 *
	 * @param array $classes
	 * @return array
	 */
	public function hide_metabox( $classes ) {
		$classes[] = 'rl-metabox-content';
		$classes[] = 'rl-hide-metabox';

		return $classes;
	}

	/**
	 * Add class to display the metabox.
	 *
	 * @param array $classes
	 * @return array
	 */
	function display_metabox( $classes ) {
		$classes[] = 'rl-metabox-content';
		$classes[] = 'rl-display-metabox';

		return $classes;
	}

	/**
	 * Add settings wrapper class to the metabox.
	 *
	 * @param array $classes
	 * @return array
	 */
	public function add_settings_wrapper_class( $classes ) {
		if ( ! in_array( 'rl-settings', $classes, true ) )
			$classes[] = 'rl-settings';

		return $classes;
	}

	/**
	 * Add active tab to post redirect destination URL.
	 *
	 * @param string $location Destination URL
	 * @return string
	 */
	function add_active_tab( $location ) {
		// check active tab
		$active_tab = isset( $_POST['rl_active_tab'] ) ? sanitize_key( $_POST['rl_active_tab'] ) : '';

		return add_query_arg( 'rl_active_tab', ! empty( $active_tab ) && array_key_exists( $active_tab, $this->tabs ) ? $active_tab : 'images', $location );
	}

	/**
	 * Add metaboxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		// side metaboxes
		add_meta_box( 'responsive-gallery-shortcode', esc_html__( 'Gallery Code', 'responsive-lightbox' ), array( $this, 'shortcode_metabox' ), 'rl_gallery', 'side', 'core' );
	}

	/**
	 * Get number of gallery images.
	 *
	 * @param int $gallery_id
	 * @return int
	 */
	public function get_gallery_images_number( $gallery_id ) {
		return count( $this->get_gallery_images( $gallery_id, [ 'count_images' => true, 'preview' => false, 'exclude' => true ] ) );
	}

	/**
	 * Sync gallery image counts when an attachment is deleted.
	 *
	 * @param int $attachment_id
	 * @return void
	 */
	public function delete_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 )
			return;

		$galleries = get_posts( [
			'post_type' => 'rl_gallery',
			'post_status' => [ 'publish', 'private', 'draft', 'pending', 'future' ],
			'fields' => 'ids',
			'nopaging' => true,
			'meta_query' => [
				[
					'key' => '_rl_images',
					'value' => (string) $attachment_id,
					'compare' => 'LIKE'
				]
			]
		] );

		if ( empty( $galleries ) )
			return;

		foreach ( $galleries as $gallery_id ) {
			update_post_meta( $gallery_id, '_rl_images_count', $this->get_gallery_images_number( $gallery_id ) );
		}
	}

	/**
	 * Load featured content query args.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function init_admin() {
		global $pagenow;

		// check values
		$post = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
		$action = isset( $_POST['action'] ) ? sanitize_key( $_POST['action'] ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		// prepare query arguments if needed
		if ( ( $pagenow === 'post.php' && ( ( $post && get_post_type( $post ) === 'rl_gallery' ) || ( $post_id && get_post_type( $post_id ) === 'rl_gallery' ) ) ) || ( in_array( $pagenow, array( 'edit.php', 'post-new.php'), true ) && $post_type === 'rl_gallery' ) || ( $pagenow === 'admin-ajax.php' && $action && in_array( $action, array( 'rl-get-preview-content', 'rl-post-gallery-preview', 'rl-get-menu-content' ), true ) ) )
			$this->fields['images']['featured'] = $this->prepare_featured_fields( $this->fields['images']['featured'] );

		// add default thumbnail image if needed
		if ( Responsive_Lightbox()->options['builder']['gallery_builder'] && $pagenow === 'edit.php' && $post_type && $post_type === 'rl_gallery' )
			$this->maybe_generate_thumbnail();
	}

	/**
	 * Generate post thumbnail replacement.
	 *
	 * @return int
	 */
	public function maybe_generate_thumbnail() {
		// get old attachment
		$thumbnail_id = get_posts(
			array(
				'name'			 => 'responsive-lightbox-thumbnail',
				'post_type'		 => 'attachment',
				'post_status'	 => 'inherit',
				'numberposts'	 => 1,
				'fields'		 => 'ids'
			)
		);

		// no attachment?
		if ( empty( $thumbnail_id ) ) {
			// get new attachment
			$thumbnail_id = get_posts(
				array(
					'name'			 => 'responsive-lightbox-thumbnail',
					'post_type'		 => 'attachment',
					'post_status'	 => 'pending',
					'numberposts'	 => 1,
					'fields'		 => 'ids'
				)
			);

			// no attachment?
			if ( empty( $thumbnail_id ) ) {
				// get upload directory data
				$wp_upload_dir = wp_upload_dir();

				// get file path
				$filepath = str_replace( '\\', '/', RESPONSIVE_LIGHTBOX_PATH . 'images/responsive-lightbox-thumbnail.png' );

				// get file name
				$filename = basename( $filepath );

				// new filepath in upload dir
				$new_filepath = $wp_upload_dir['path'] . '/' . $filename;

				// copty file to upload dir
				copy( $filepath, $new_filepath );

				// get type of file
				$filetype = wp_check_filetype( $filename );

				// force pending status for the attachment
				add_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );

				// insert attachment
				$thumbnail_id = wp_insert_attachment(
					array(
						'guid'				=> $wp_upload_dir['url'] . '/' . $filename,
						'post_mime_type'	=> $filetype['type'],
						'post_title'		=> preg_replace( '/\.[^.]+$/', '', $filename ),
						'post_content'		=> '',
						'post_parent'		=> 0,
						'post_status'		=> 'inherit'
					),
					$new_filepath,
					0
				);

				remove_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );

				// success?
				if ( $thumbnail_id ) {
					// make sure that this file is included
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// update database with generated metadata for the attachment
					wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $new_filepath ) );
				}
			} else
				$thumbnail_id = $thumbnail_id[0];
		} else {
			// force pending status for the attachment
			add_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );

			$thumbnail_id = wp_update_post(
				array(
					'ID'			=> $thumbnail_id[0],
					'post_status'	=> 'pending'
				)
			);

			remove_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );
		}

		return (int) $thumbnail_id;
	}

	/**
	 * Get video thumbnail replacement.
	 *
	 * @param int $post_id
	 * @return int
	 */
	public function get_video_thumbnail_id( $post_id ) {
		$thumbnail_id = 0;

		// try to get video thumbnail
		$attachment_id = (int) get_post_thumbnail_id( $post_id );

		// real attachment?
		if ( wp_attachment_is_image( $attachment_id ) )
			$thumbnail_id = $attachment_id;

		// try to get default video poster image
		if ( ! $thumbnail_id ) {
			$thumbnail_id = get_posts(
				array(
					'name'			 => 'responsive-lightbox-video-thumbnail',
					'post_type'		 => 'attachment',
					'post_status'	 => 'inherit',
					'numberposts'	 => 1,
					'fields'		 => 'ids'
				)
			);
		}

		// no attachment?
		if ( ! $thumbnail_id ) {
			// get new attachment
			$thumbnail_id = get_posts(
				array(
					'name'			 => 'responsive-lightbox-video-thumbnail',
					'post_type'		 => 'attachment',
					'post_status'	 => 'pending',
					'numberposts'	 => 1,
					'fields'		 => 'ids'
				)
			);

			// no attachment?
			if ( ! $thumbnail_id ) {
				// get upload directory data
				$wp_upload_dir = wp_upload_dir();

				// get file path
				$filepath = str_replace( '\\', '/', RESPONSIVE_LIGHTBOX_PATH . 'images/responsive-lightbox-video-thumbnail.png' );

				// get file name
				$filename = basename( $filepath );

				// new filepath in upload dir
				$new_filepath = $wp_upload_dir['path'] . '/' . $filename;

				// copty file to upload dir
				copy( $filepath, $new_filepath );

				// get type of file
				$filetype = wp_check_filetype( $filename );

				// force pending status for the attachment
				add_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );

				// insert attachment
				$thumbnail_id = wp_insert_attachment(
					array(
						'guid'				=> $wp_upload_dir['url'] . '/' . $filename,
						'post_mime_type'	=> $filetype['type'],
						'post_title'		=> preg_replace( '/\.[^.]+$/', '', $filename ),
						'post_content'		=> '',
						'post_parent'		=> 0,
						'post_status'		=> 'inherit'
					),
					$new_filepath,
					0
				);

				remove_filter( 'wp_insert_attachment_data', array( $this, 'set_attachment_post_status' ) );

				// success?
				if ( $thumbnail_id ) {
					// make sure that this file is included
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// update database with generated metadata for the attachment
					wp_update_attachment_metadata( $thumbnail_id, wp_generate_attachment_metadata( $thumbnail_id, $new_filepath ) );
				}
			} else
				$thumbnail_id = $thumbnail_id[0];
		}

		return (int) $thumbnail_id;
	}

	/**
	 * Change status of new attachment thumbnail replacement.
	 *
	 * @param array $data
	 * @return array
	 */
	function set_attachment_post_status( $data ) {
		$data['post_status'] = 'pending';

		return $data;
	}

	/**
	 * Prepare featured content fields.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function prepare_featured_fields( $fields ) {
		foreach ( array( 'post_type', 'post_status', 'post_format', 'post_term', 'post_author', 'page_parent', 'page_template' ) as $option ) {
			$fields[$option]['options'] = $this->prepare_query_args( $option );
		}

		return $fields;
	}

	/**
	 * Prepare values option list.
	 *
	 * @param string $type
	 * @return array
	 */
	public function prepare_query_args( $type = '' ) {
		$html = '';

		switch( $type ) {
			case 'post_type':
				$data = $this->get_post_types();
				break;

			case 'post_status':
				$data = $this->get_post_statuses();
				break;

			case 'post_format':
				$data = $this->get_post_formats();
				break;

			case 'post_term':
				$taxonomies = $this->get_taxonomies();
				$new_terms = [];

				if ( ! empty( $taxonomies ) ) {
					foreach ( $taxonomies as $tax_id => $label ) {
						$terms = get_terms(
							array(
								'taxonomy'		=> $tax_id,
								'orderby'		=> 'name',
								'order'			=> 'ASC',
								'hide_empty'	=> false,
								'fields'		=> 'id=>name'
							)
						);

						if ( ! empty( $terms ) )
							$new_terms[$tax_id] = array(
								'label'	=> $label,
								'terms'	=> $terms
							);
					}
				}

				$data = $new_terms;
				break;

			case 'post_author':
				$data = $this->get_users();
				break;

			case 'page_parent':
				$parents = [];
				$hierarchical = get_post_types(
					array(
						'public'		=> true,
						'hierarchical'	=> true
					),
					'objects',
					'and'
				);

				if ( ! empty( $hierarchical ) ) {
					foreach ( $hierarchical as $post_type => $object ) {
						// get top level hierarchical posts
						$query = new WP_Query(
							array(
								'post_type'			=> $post_type,
								'post_status'		=> 'publish',
								'nopaging'			=> true,
								'posts_per_page'	=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC',
								'suppress_filters'	=> false,
								'no_found_rows'		=> true,
								'cache_results'		=> false,
								'post_parent'		=> 0
							)
						);

						if ( ! empty( $query->posts ) ) {
							foreach ( $query->posts as $post ) {
								$parents[$post->ID] = trim( $post->post_title ) === '' ? __( 'Untitled' ) : $post->post_title;
							}
						}
					}
				}

				$data = $parents;
				break;

			case 'page_template':
				$data = $this->get_page_templates();
				break;

			default:
				$data = [];
		}

		return apply_filters( 'rl_galleries_prepare_query_args', $data, $type );
	}

	/**
	 * Get public post types.
	 *
	 * @param bool $simple Which data should be returned
	 * @param bool $skip Which post types should be skipped
	 * @return array
	 */
	public function get_post_types( $simple = false, $skip = [ 'attachment', 'rl_gallery' ] ) {
		$post_types = get_post_types(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		$data = [];

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type => $cpt ) {
				// skip unwanted post types
				if ( in_array( $post_type, $skip, true ) )
					continue;

				if ( $simple )
					$data[] = $post_type;
				else
					$data[$post_type] = $cpt->labels->singular_name;
			}
		}

		if ( ! $simple )
			asort( $data );

		return $data;
	}

	/**
	 * Get post statuses.
	 *
	 * @return array
	 */
	public function get_post_statuses() {
		$post_statuses = get_post_stati();

		asort( $post_statuses );

		// remove inherit post status
		if ( isset( $post_statuses['inherit'] ) )
			unset( $post_statuses['inherit'] );

		return $post_statuses;
	}

	/**
	 * Get post formats.
	 *
	 * @return array
	 */
	public function get_post_formats() {
		$post_formats = array(
			'aside'		=> __( 'Aside' ),
			'audio'		=> __( 'Audio' ),
			'chat'		=> __( 'Chat' ),
			'gallery'	=> __( 'Gallery' ),
			'link'		=> __( 'Link' ),
			'photo'		=> __( 'Photo' ),
			'quote'		=> __( 'Quote' ),
			'standard'	=> __( 'Standard' ),
			'status'	=> __( 'Status' ),
			'video'		=> __( 'Video' )
		);

		asort( $post_formats );

		return $post_formats;
	}

	/**
	 * Get taxonomies.
	 *
	 * @return array
	 */
	public function get_taxonomies() {
		$taxonomies = get_taxonomies(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		// remove post format
		if ( array_key_exists( 'post_format', $taxonomies ) )
			unset( $taxonomies['post_format'] );

		// get main instance
		$rl = Responsive_Lightbox();

		// remove gallery categories
		if ( $rl->options['builder']['categories'] && array_key_exists( 'rl_category', $taxonomies ) )
			unset( $taxonomies['rl_category'] );

		// remove gallery tags
		if ( $rl->options['builder']['tags'] && array_key_exists( 'rl_tag', $taxonomies ) )
			unset( $taxonomies['rl_tag'] );

		if ( $rl->options['folders']['active'] ) {
			// remove active folders taxonomy from gallery source selector
			$active_taxonomy = $rl->folders->get_active_taxonomy();
			unset( $taxonomies[$active_taxonomy] );

			// remove media folders tags
			if ( $rl->options['folders']['media_tags'] )
				unset( $taxonomies['rl_media_tag'] );
		}

		$data = [];

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax_id => $taxonomy ) {
				$data[$tax_id] = $taxonomy->labels->singular_name;
			}
		}

		// sort taxonomies
		asort( $data );

		return $data;
	}

	/**
	 * Get users.
	 *
	 * @return array
	 */
	public function get_users() {
		$users = get_users(
			array(
				'fields' => array( 'ID', 'user_login' )
			)
		);

		$data = [];

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$data[(int) $user->ID] = $user->user_login;
			}
		}

		asort( $data );

		return $data;
	}

	/**
	 * Get page templates.
	 *
	 * @return array
	 */
	public function get_page_templates() {
		$data = [];
		$page_templates = wp_get_theme()->get_page_templates();

		if ( ! empty( $page_templates ) )
			asort( $page_templates );

		$data = array_merge( array( 'default' => apply_filters( 'default_page_template_title', __( 'Default Template' ) ) ), $page_templates );

		return $data;
	}

	/**
	 * Fix possible misplaced or hidden metaboxes do to old 'after_title' metabox and possibility to move internal metaboxes.
	 *
	 * @return void
	 */
	public function clear_metaboxes( $screen ) {
		global $pagenow;

		if ( ! ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && ! empty( $screen->post_type ) && $screen->post_type === 'rl_gallery' && empty( $_POST['rl_gallery'] ) ) )
			return;

		// get user id
		$user_id = get_current_user_id();

		// get rl metaboxes
		$order = get_user_meta( $user_id, 'meta-box-order_rl_gallery', true );

		// any metabox order? fix possible misplaced metaboxes
		if ( is_array( $order ) && ! empty( $order ) ) {
			// save metaboxes
			$_order = $order;

			// default rl metaboxes
			$rl_boxes = [ 'responsive-gallery-images', 'responsive-gallery-config', 'responsive-gallery-design', 'responsive-gallery-paging', 'responsive-gallery-lightbox', 'responsive-gallery-misc' ];

			foreach ( $_order as $group => $metaboxes ) {
				if ( $group === 'after_title' ) {
					// remove deprecated after_title metabox
					unset( $order['after_title'] );
				} elseif ( $metaboxes !== '' ) {
					$boxes = explode( ',', $metaboxes );
					$new_boxes = [];

					foreach ( $boxes as $box ) {
						if ( ! in_array( $box, $rl_boxes, true ) )
							$new_boxes[] = $box;
					}

					if ( ! empty( $new_boxes ) )
						$order[$group] = implode( ',', $new_boxes );
					else
						$order[$group] = '';
				}
			}

			// remove default metaboxes storage
			if ( array_key_exists( 'responsive_lightbox_metaboxes', $order ) )
				unset( $order['responsive_lightbox_metaboxes'] );

			// update usermeta to prevent issues with rl metaboxes
			if ( $order !== $_order )
				update_user_meta( $user_id, 'meta-box-order_rl_gallery', $order );
		}
	}

	/**
	 * Save gallery metadata.
	 *
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update Whether existing post is being updated or not
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {
		// check action
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $update || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) || ( $action === 'untrash' ) || empty( $_POST['rl_gallery'] ) )
			return;

		// save gallery
		$this->save_gallery( wp_unslash( $_POST ), $post_id );
	}

	/**
	 * Save gallery preview metadata.
	 *
	 * @param array $post_data Gallery data
	 * @param int $post_id
	 * @param bool $preview Whether is it preview
	 * @return void
	 */
	public function save_gallery( $post_data, $post_id, $preview = false ) {
		// get gallery data
		$data = $post_data['rl_gallery'];

		// prepare sanitized data
		$safedata = [];

		// get main instance
		$rl = Responsive_Lightbox();

		// sanitize all fields - iterate from $this->tabs (authoritative registry) to prevent silent skipping
		foreach ( array_keys( $this->tabs ) as $tab_id ) {
			// Retrieve menu_items from $this->fields for this tab (may be empty for adapter-only tabs)
			$menu_items = isset( $this->fields[$tab_id] ) ? $this->fields[$tab_id] : [];

			// Determine menu item for this tab
			$tab_menu_items = isset( $this->tabs[$tab_id]['menu_items'] ) && is_array( $this->tabs[$tab_id]['menu_items'] ) ? $this->tabs[$tab_id]['menu_items'] : [];
			$posted_menu_item = isset( $data[$tab_id], $data[$tab_id]['menu_item'] ) ? sanitize_key( $data[$tab_id]['menu_item'] ) : '';

			// Resolve menu item safely for tabs with and without menu navigation.
			if ( $posted_menu_item !== '' ) {
				if ( ! empty( $tab_menu_items ) && array_key_exists( $posted_menu_item, $tab_menu_items ) )
					$menu_item = $posted_menu_item;
				elseif ( array_key_exists( $posted_menu_item, $menu_items ) )
					$menu_item = $posted_menu_item;
				else {
					if ( empty( $menu_items ) )
						continue;
					$menu_item = array_key_exists( 'options', $menu_items ) ? 'options' : key( $menu_items );
				}
			} else {
				if ( empty( $menu_items ) )
					continue;
				$menu_item = array_key_exists( 'options', $menu_items ) ? 'options' : key( $menu_items );
			}

			// Resolve fields from adapter (single source for managed tabs)
			$items = [];
			if ( isset( $rl->gallery_api ) && $rl->gallery_api->is_managed_tab( $tab_id ) ) {
				$items = $rl->gallery_api->get_tab_fields_for_save( $tab_id, $menu_item );
			}

			// Defensive fallback for images tab only (not fully adapter-owned for field definitions)
			if ( $tab_id === 'images' && empty( $items ) && isset( $menu_items[$menu_item] ) && is_array( $menu_items[$menu_item] ) ) {
				$items = $menu_items[$menu_item];
			}

			if ( empty( $items ) || ! is_array( $items ) )
				continue;

			// IMAGES TAB: Minimal defensive guards (sanitizer handles data structure)
			if ( $tab_id === 'images' ) {
				// Ensure folder field has valid structure for folders menu item
				if ( $menu_item === 'folders' && isset( $data[$tab_id][$menu_item]['folder'] ) ) {
					$folder = $data[$tab_id][$menu_item]['folder'];
					if ( ! is_array( $folder ) ) {
						$data[$tab_id][$menu_item]['folder'] = [ 'id' => 0, 'children' => false ];
					} else {
						if ( ! isset( $folder['id'] ) ) {
							$data[$tab_id][$menu_item]['folder']['id'] = 0;
						}
						if ( ! isset( $folder['children'] ) ) {
							$data[$tab_id][$menu_item]['folder']['children'] = false;
						}
					}
				}
				// Note: attachments['ids'] is CSV string, sanitizer handles it at line 1997
			}

			// sanitize fields
			$safedata = $this->sanitize_fields( $items, $data, $tab_id, $menu_item );

			// tab validation hook (adapter-managed tabs)
			if ( isset( $rl->gallery_api ) && $rl->gallery_api->is_managed_tab( $tab_id ) ) {
				if ( isset( $safedata[$tab_id][$menu_item] ) && is_array( $safedata[$tab_id][$menu_item] ) ) {
					$safedata[$tab_id][$menu_item] = $rl->gallery_api->validate_tab_data(
						$tab_id,
						$menu_item,
						$safedata[$tab_id][$menu_item],
						$post_id
					);
				}
			}

			// add menu item
			$safedata[$tab_id]['menu_item'] = $menu_item;

			// preview?
			if ( $preview )
				update_metadata( 'post', $post_id, '_rl_' . $tab_id, $safedata[$tab_id] );
			else
				update_post_meta( $post_id, '_rl_' . $tab_id, $safedata[$tab_id] );
		}

		$has_featured_image_payload = isset( $post_data['rl_gallery_featured_image'] );

		// Preserve existing featured-image metadata for partial updates (e.g. Quick Edit).
		if ( ! $has_featured_image_payload ) {
			$featured_image_type = get_post_meta( $post_id, '_rl_featured_image_type', true );
			$featured_image = get_post_meta( $post_id, '_rl_featured_image', true );
			$thumbnail_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );

			if ( ! in_array( $featured_image_type, [ 'id', 'url', 'image' ], true ) )
				$featured_image_type = 'image';
		} else {
			$featured_image_type = ! empty( $post_data['rl_gallery_featured_image'] ) && in_array( $post_data['rl_gallery_featured_image'], array( 'id', 'url', 'image' ), true ) ? $post_data['rl_gallery_featured_image'] : 'id';

			switch ( $featured_image_type ) {
				// custom url
				case 'url':
					$thumbnail_id = $this->maybe_generate_thumbnail();
					$frontend = function_exists( 'Responsive_Lightbox' ) ? Responsive_Lightbox()->frontend : null;
					$custom_url = isset( $post_data['_rl_thumbnail_url'] ) ? $post_data['_rl_thumbnail_url'] : '';
					if ( $frontend && method_exists( $frontend, 'sanitize_remote_image_url' ) )
						$featured_image = $frontend->sanitize_remote_image_url( $custom_url );
					else
						$featured_image = '';

					if ( $featured_image === '' )
						$featured_image_type = 'image';
					break;

				// first image
				case 'image':
					$thumbnail_id = $this->maybe_generate_thumbnail();
					$featured_image = '';
					break;

				// attachment id
				case 'id':
				default:
					$featured_image = $thumbnail_id = isset( $post_data['_thumbnail_id'] ) ? (int) $post_data['_thumbnail_id'] : 0;
			}
		}

		// preview?
		if ( $preview ) {
			update_metadata( 'post', $post_id, '_rl_featured_image_type', $featured_image_type );
			update_metadata( 'post', $post_id, '_rl_featured_image', $featured_image );
			update_metadata( 'post', $post_id, '_thumbnail_id', $thumbnail_id );
		} else {
			// update featured image
			update_post_meta( $post_id, '_rl_featured_image_type', $featured_image_type );
			update_post_meta( $post_id, '_rl_featured_image', $featured_image );
			update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );

			// save number of images
			update_post_meta( $post_id, '_rl_images_count', $this->get_gallery_images_number( $post_id ) );
		}

		// update post excerpt
		if ( isset( $safedata['misc']['options']['gallery_description'] ) ) {
			remove_action( 'save_post_rl_gallery', [ $this, 'save_post' ], 10, 3 );

			$postdata = [
				'ID'			=> $post_id,
				'post_excerpt'	=> sanitize_textarea_field( $safedata['misc']['options']['gallery_description'] )
			];

			wp_update_post( $postdata );

			add_action( 'save_post_rl_gallery', [ $this, 'save_post' ], 10, 3 );
		}
	}

	/**
	 * Display shortcode metabox.
	 *
	 * @param object $post
	 * @return void
	 */
	public function shortcode_metabox( $post ) {
		echo '
		<p>' . esc_html__( 'You can place this gallery anywhere into your posts, pages, custom post types or widgets by using the shortcode below', 'responsive-lightbox' ) . ':</p>
		<code class="rl-shortcode" data-number="0">[rl_gallery id=&quot;' . (int) $post->ID . '&quot;]</code>
		<p>' . esc_html__( 'You can also place this gallery into your template files by using the template tag below', 'responsive-lightbox' ) . ':</p>
		<code class="rl-shortcode" data-number="1">if ( function_exists( \'rl_gallery\' ) ) { rl_gallery( \'' . (int) $post->ID . '\' ); }</code>';
	}

	/**
	 * Add new gallery listing columns.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function gallery_columns( $columns ) {
		// find title position
		$offset = array_search( 'title', array_keys( $columns ) );

		// put image column before title
		$columns = array_merge(
			array_slice( $columns, 0, $offset ),
			array(
				'image' => esc_html__( 'Gallery', 'responsive-lightbox' )
			),
			array_slice( $columns, $offset )
		);

		// put new columns after title
		$columns = array_merge(
			array_slice( $columns, 0, $offset + 2 ),
			array(
				'shortcode'	=> esc_html__( 'Shortcode', 'responsive-lightbox' ),
				'type'		=> esc_html__( 'Type', 'responsive-lightbox' ),
				'source'	=> esc_html__( 'Source', 'responsive-lightbox' )
			),
			array_slice( $columns, $offset + 2 )
		);

		return $columns;
	}

	/**
	 * Add new gallery listing columns content.
	 *
	 * @global string $pagenow
	 *
	 * @param string $column_name
	 * @param int $post_id
	 * @return void
	 */
	public function gallery_columns_content( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'image':
				// get image data, based on gallery source type
				$image = $this->get_featured_image( $post_id, 'thumbnail' );
				$images_count = (int) get_post_meta( $post_id, '_rl_images_count', true );

				// display count
				if ( ! empty( $image ) )
					echo '<span class="media-icon image-icon">' . wp_kses_post( $image ) . '</span><span>' . esc_html( sprintf( _n( '%s element', '%s elements', $images_count, 'responsive-lightbox' ), $images_count ) ) . '</span>';
				else
					echo '<span class="media-icon image-icon">' . wp_get_attachment_image( 0, array( 60, 60 ), true, array( 'alt' => '' ) ) . '</span>';
				break;

			case 'shortcode':
				echo '<code>[rl_gallery id="' . (int) $post_id . '"]</code>';
				break;

			case 'type':
				$config = get_post_meta( $post_id, '_rl_config', true );

				if ( is_array( $config ) && ! empty( $config['menu_item'] ) && array_key_exists( $config['menu_item'], $this->tabs['config']['menu_items'] ) ) {
					echo esc_html( $this->tabs['config']['menu_items'][$config['menu_item']] );

					if ( $config['menu_item'] === 'default' )
						echo esc_html( ' (' . $this->tabs['config']['menu_items'][Responsive_Lightbox()->options['settings']['builder_gallery']] . ')' );
				} else
					echo '-';
				break;

			case 'source':
				$images = get_post_meta( $post_id, '_rl_images', true );

				if ( is_array( $images ) && ! empty( $images['menu_item'] ) && array_key_exists( $images['menu_item'], $this->tabs['images']['menu_items'] ) )
					echo esc_html( $this->tabs['images']['menu_items'][$images['menu_item']] );
				else
					echo '-';
				break;
		}
	}

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global array $_wp_additional_image_sizes
	 *
	 * @return array
	 */
	public function get_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = [];

		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, [ 'thumbnail', 'medium', 'medium_large', 'large' ] ) ) {
				$sizes[$_size]['width'] = get_option( "{$_size}_size_w" );
				$sizes[$_size]['height'] = get_option( "{$_size}_size_h" );
				$sizes[$_size]['crop'] = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[$_size] ) ) {
				$sizes[$_size] = [
					'width'	 => $_wp_additional_image_sizes[$_size]['width'],
					'height' => $_wp_additional_image_sizes[$_size]['height'],
					'crop'	 => $_wp_additional_image_sizes[$_size]['crop'],
				];
			}
		}

		return $sizes;
	}

	/**
	 * Get size information for a specific image size.
	 *
	 * @param string $size The image size for which to retrieve data.
	 * @return false|array
	 */
	public function get_image_size( $size ) {
		if ( isset( $this->sizes[$size] ) )
			return $this->sizes[$size];
		else
			return false;
	}

	/**
	 * Filter the admin post thumbnail HTML markup.
	 *
	 * @param string $content
	 * @param int $post_id
	 * @param int $thumbnail_id
	 * @return string
	 */
	public function admin_post_thumbnail_html( $content, $post_id, $thumbnail_id ) {
		if ( get_post_type( $post_id ) === 'rl_gallery' ) {
			$value = get_post_meta( $post_id, '_rl_featured_image', true );
			$frontend = function_exists( 'Responsive_Lightbox' ) ? Responsive_Lightbox()->frontend : null;
			if ( $frontend && method_exists( $frontend, 'sanitize_remote_image_url' ) )
				$value = $frontend->sanitize_remote_image_url( $value );
			$type = get_post_meta( $post_id, '_rl_featured_image_type', true );
			$type = ! empty( $type ) && in_array( $type, array( 'image', 'id', 'url' ) ) ? $type : 'image';

			// force media library image
			if ( wp_doing_ajax() )
				$type = 'id';
			// post featured image is post thumbnail replacement?
			elseif ( $this->maybe_generate_thumbnail() === (int) $thumbnail_id ) {
				remove_filter( 'admin_post_thumbnail_html', array( $this, 'admin_post_thumbnail_html' ), 10 );

				$content = _wp_post_thumbnail_html( 0, $post_id );
			}

			$content = '
				<div class="rl-gallery-featured-image-options">
					<p class="howto">' . esc_html__( 'Select gallery featured image source:', 'responsive-lightbox' ) . '</p>
					<label for="rl-gallery-featured-image"><input id="rl-gallery-featured-image" type="radio" name="rl_gallery_featured_image" value="image" ' . checked( $type, 'image', false ) . ' />' . esc_html__( 'First gallery image', 'responsive-lightbox' ) . '</label><br />
					<label for="rl-gallery-featured-id"><input id="rl-gallery-featured-id" type="radio" name="rl_gallery_featured_image" value="id" ' . checked( $type, 'id', false ) . ' />' . esc_html__( 'Media Library', 'responsive-lightbox' ) . '</label><br />
					<label for="rl-gallery-featured-url"><input id="rl-gallery-featured-url" type="radio" name="rl_gallery_featured_image" value="url" ' . checked( $type, 'url', false ) . ' />' . esc_html__( 'Custom URL', 'responsive-lightbox' ) . '</label>
				</div>
				<div class="rl-gallery-featured-image-select">
					<div class="rl-gallery-featured-image-select-id"' . ( $type === 'id' ? '' : ' style="display: none;"' ) . '>' . $content . '</div>
					<div class="rl-gallery-featured-image-select-url"' . ( $type === 'url' ? '' : ' style="display: none;"' ) . '>
						<p><input id="_rl_thumbnail_url" class="large-text" name="_rl_thumbnail_url" value="' . ( $type === 'url' ? esc_url( $value ) : '' ) . '" type="text" /></p>
						<p class="howto">' . esc_html__( 'Custom featured image URL', 'responsive-lightbox' ) . '</p>
					</div>
					<div class="rl-gallery-featured-image-select-image"' . ( $type === 'image' ? '' : ' style="display: none;"' ) . '><p class="howto">' . esc_html__( 'Dynamically generated first gallery image', 'responsive-lightbox' ) . '</p></div>
				</div>
			';
		}

		return $content;
	}

	/**
	 * Modify the resulting HTML so that the feature image is set as a background property.
	 *
	 * @param string $html The HTML image tag.
	 * @param int $post_id The post whose featured image is to be printed.
	 * @param int $post_thumbnail_id The post thumbnail ID.
	 * @param array|string $size The size of the featured image.
	 * @param array $attr Additional attributes.
	 * @return string
	 */
	public function post_thumbnail_html( $html, $post_id = 0, $post_thumbnail_id = 0, $size = false, $attr = [] ) {
		if ( get_post_type( $post_id ) === 'rl_gallery' ) {
			// get featured image type
			$image_type = get_post_meta( $post_id, '_rl_featured_image_type', true );

			// break if featured image type is media library
			if ( ! $image_type || $image_type == 'id' )
				return $html;

			// get image source
			$image_src = $this->get_gallery_image_src( $this->get_featured_image_src( $post_id ) );

			// no image?
			if ( empty( $image_src ) )
				return $html;

			// add featured image as background in style tag
			$style = 'style="background:url( ' . esc_url( $image_src['url'] ) . ' ) no-repeat center center;-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size: cover;"';

			$html = str_replace( 'src=', $style . ' src=', $html );

			// fix the alt tag (if possible)
			$alt = $image_src['alt'];

			if ( isset( $attr['alt'] ) )
				$alt = $attr['alt'];

			if ( $alt ) {
				$html = str_replace( '/(alt=\'[^\']+\'\|alt="[^"]+")/', '', $html );
				$html = str_replace( 'src=', ' alt="' . esc_attr( $alt ) . '" src=', $html );
			}
		}

		return $html;
	}
}

