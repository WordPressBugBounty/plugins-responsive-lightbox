<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Gallery AJAX Trait.
 *
 * Handles all AJAX endpoints for gallery operations.
 *
 * @trait Responsive_Lightbox_Gallery_Ajax
 */
trait Responsive_Lightbox_Gallery_Ajax {

	/**
	 * Check whether is it valid gallery AJAX request (rl-get-gallery-page-content action).
	 *
	 * @return bool
	 */
	public function gallery_ajax_verified() {
		return ( wp_doing_ajax() && isset( $_POST['action'], $_POST['gallery_id'], $_POST['gallery_no'], $_POST['page'], $_POST['nonce'], $_POST['preview'], $_POST['post_id'], $_POST['lightbox'] ) && $_POST['action'] === 'rl-get-gallery-page-content' && wp_verify_nonce( $_POST['nonce'], 'rl_nonce' ) );
	}

	/**
	 * Try to change lightbox in valid gallery AJAX request (rl-get-gallery-page-content action).
	 *
	 * @return void
	 */
	public function maybe_change_lightbox() {
		// check whether is it valid gallery ajax request
		if ( $this->gallery_ajax_verified() ) {
			// set new lightbox script
			Responsive_Lightbox()->set_lightbox_script( sanitize_key( $_POST['lightbox'] ) );
		}
	}

	/**
	 * Get gallery page.
	 *
	 * @param array $args
	 * @return void
	 */
	public function get_gallery_page( $args ) {
		// check whether is it valid gallery ajax request
		if ( $this->gallery_ajax_verified() ) {
			// check rate limiting (60 requests per minute)
			if ( ! Responsive_Lightbox()->check_rate_limit( 'rl_get_gallery_page', 60, 60 ) ) {
				wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'responsive-lightbox' ) );
			}

			// cast page number
			$_GET['rl_page'] = (int) $_POST['page'];

			// check preview
			$preview = ( $_POST['preview'] === 'true' );

			echo $this->gallery_shortcode(
				[
					'id'			=> (int) $_POST['gallery_id'],
					'gallery_no'	=> (int) $_POST['gallery_no'],
					'preview'		=> $preview
				]
			);
		}

		exit;
	}

	/**
	 * Generate gallery preview.
	 *
	 * @return void
	 */
	public function post_gallery_preview() {
		// check data
		if ( ! isset( $_POST['post_id'], $_POST['gallery_id'], $_POST['nonce'], $_POST['page'] ) || ! check_ajax_referer( 'rl-gallery-post', 'nonce', false ) )
			wp_send_json_error();

		// check page
		$page = preg_replace( '/[^a-z-.]/i', '', $_POST['page'] );

		// check page
		if ( ! in_array( $page, [ 'widgets.php', 'customize.php', 'post.php', 'post-new.php' ], true ) )
			wp_send_json_error();

		// check edit_post capability
		if ( ( $page === 'post.php' || $page === 'post-new.php' ) && ! current_user_can( 'edit_post', (int) $_POST['post_id'] ) )
			wp_send_json_error();

		// check edit_theme_options capability
		if ( ( $page === 'widgets.php' || $page === 'customize.php' ) && ! current_user_can( 'edit_theme_options' ) )
			wp_send_json_error();

		// parse gallery id
		$gallery_id = (int) $_POST['gallery_id'];

		// get gallery data
		$data = get_post_meta( $gallery_id, '_rl_images', true );

		// prepare data
		$attachments = $exclude = [];
		$html = '';

		// get images
		$images = $this->get_gallery_images(
			$gallery_id,
			[
				'exclude'	=> true,
				'limit'		=> 20
			]
		);

		// get number of images
		$images_count = (int) get_post_meta( $gallery_id, '_rl_images_count', true );

		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				$html .= '
				<li tabindex="0" role="checkbox" aria-label="' . esc_attr( $image['title'] ) . '" aria-checked="true" data-id="' . esc_attr( $image['id'] ) . '" class="attachment selection selected rl-status-active">
					<div class="attachment-preview js--select-attachment type-image ' . esc_attr( $image['thumbnail_orientation'] ). '">
						<div class="thumbnail">
							<div class="centered">
								<img src="' . esc_url( $image['thumbnail_url'] ) . '" draggable="false" alt="" />
							</div>
						</div>
					</div>
				</li>';
			}
		}

		// send attachments content
		wp_send_json_success(
			array(
				'attachments'	=> $html,
				'count'			=> esc_html( sprintf( _n( '%s image', '%s images', $images_count, 'responsive-lightbox' ), $images_count ) ),
				'edit_url'		=> current_user_can( 'edit_post', $gallery_id ) ? esc_url_raw( admin_url( 'post.php?post=' . $gallery_id . '&action=edit' ) ) : ''
			)
		);
	}

	/**
	 * Get all galleries.
	 *
	 * @return void
	 */
	public function post_get_galleries() {
		// check rate limiting (60 requests per minute)
		if ( ! Responsive_Lightbox()->check_rate_limit( 'rl_post_get_galleries', 60, 60 ) ) {
			wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'responsive-lightbox' ) );
		}

		// check data
		if ( ! isset( $_POST['post_id'], $_POST['search'], $_POST['nonce'], $_POST['page'] ) || ! check_ajax_referer( 'rl-gallery-post', 'nonce', false ) )
			wp_send_json_error();

		// check page
		$page = preg_replace( '/[^a-z-.]/i', '', $_POST['page'] );

		// check page
		if ( ! in_array( $page, [ 'widgets.php', 'customize.php', 'post.php', 'post-new.php' ], true ) )
			wp_send_json_error();

		// check edit_post capability
		if ( ( $page === 'post.php' || $page === 'post-new.php' ) && ! current_user_can( 'edit_post', (int) $_POST['post_id'] ) )
			wp_send_json_error();

		// check edit_theme_options capability
		if ( ( $page === 'widgets.php' || $page === 'customize.php' ) && ! current_user_can( 'edit_theme_options' ) )
			wp_send_json_error();

		$args = array(
			'post_type'			=> 'rl_gallery',
			'post_status'		=> 'publish',
			'nopaging'			=> true,
			'posts_per_page'	=> -1,
			'orderby'			=> 'title',
			'order'				=> 'ASC',
			'suppress_filters'	=> false,
			'no_found_rows'		=> true,
			'cache_results'		=> false
		);

		// check category
		$category = isset( $_POST['category'] ) ? (int) $_POST['category'] : 0;

		// specific category?
		if ( ! empty( $category ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'			=> 'rl_category',
					'field'				=> 'term_id',
					'operator'			=> 'IN',
					'include_children'	=> false,
					'terms'				=> $category
				)
			);
		}

		$search = wp_unslash( trim( $_POST['search'] ) );

		if ( $search !== '' )
			$args['s'] = $search;

		// get galleries
		$query = new WP_Query( $args );

		$html = '';
		$ids = [];

		// any galleries?
		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $gallery ) {
				// save gallery id
				$ids[] = (int) $gallery->ID;

				// get featured image
				$featured = $this->get_featured_image_src( $gallery->ID );

				if ( is_array( $featured ) && array_key_exists( 'url', $featured ) )
					$featured_image = $featured['url'];
				else
					$featured_image = '';

				// get title
				$title = $gallery->post_title !== '' ? $gallery->post_title : esc_html__( '(no title)', 'responsive-gallery' );

				$html .= '
				<li tabindex="0" role="checkbox" aria-label="' . esc_attr( $title ) . '" aria-checked="true" data-id="' . (int) $gallery->ID . '" class="attachment selection">
					<div class="attachment-preview js--select-attachment type-image ' . ( ! empty( $featured['thumbnail_orientation'] ) ? esc_attr( $featured['thumbnail_orientation'] ) : 'landscape' ) . '">
						<div class="thumbnail">
							<div class="centered" data-full-src="' . esc_url( $featured_image ) . '">
								' . $this->get_featured_image( $gallery->ID, 'thumbnail' ) . '
							</div>
							<div class="filename">
								<div>' . esc_html( $title ) . '</div>
							</div>
						</div>
					</div>
					<button type="button" class="button-link check"><span class="media-modal-icon"></span><span class="screen-reader-text">' . esc_html__( 'Deselect', 'responsive-lightbox' ) . '</span></button>
				</li>';
			}
		}

		// send galleries content
		wp_send_json_success(
			[
				'galleries'	=> $ids,
				'html'		=> $html
			]
		);
	}

	/**
	 * Get gallery content based on request.
	 *
	 * @return void
	 */
	public function get_menu_content() {
		if ( ! isset( $_POST['post_id'], $_POST['tab'], $_POST['menu_item'], $_POST['nonce'] ) || ! check_ajax_referer( 'rl-gallery', 'nonce', false ) )
			wp_send_json_error();

		// check tab
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : '';

		if ( ! array_key_exists( $tab, $this->tabs ) )
			wp_send_json_error();

		// get post id
		$post_id = (int) $_POST['post_id'];

		if ( ! current_user_can( 'edit_post', $post_id ) )
			wp_send_json_error();

		// check menu item
		$menu_item = sanitize_key( $_POST['menu_item'] );

		// get selected menu item
		$menu_item = ! empty( $menu_item ) && in_array( $menu_item, array_keys( $this->tabs[$tab]['menu_items'] ) ) ? $menu_item : key( $this->tabs[$tab]['menu_items'] );

		$content = Responsive_Lightbox()->gallery_api->render_menu_content( $post_id, $tab, $menu_item );
		wp_send_json_success( $content );
	}

	/**
	 * Get gallery preview content based on request.
	 *
	 * @return void
	 */
	public function get_gallery_preview_content() {
		// check rate limiting (60 requests per minute)
		if ( ! Responsive_Lightbox()->check_rate_limit( 'rl_get_gallery_preview_content', 60, 60 ) ) {
			wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'responsive-lightbox' ) );
		}

		// initial checks
		if ( ! isset( $_POST['post_id'], $_POST['menu_item'], $_POST['nonce'], $_POST['preview_type'] ) || ! check_ajax_referer( 'rl-gallery', 'nonce', false ) )
			wp_send_json_error();

		// cast gallery ID
		$post_id = (int) $_POST['post_id'];

		// check user privileges
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) )
			wp_send_json_error();

		// get query args
		$args = ! empty( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : [];

		// check orderby
		if ( array_key_exists( 'orderby', $args ) ) {
			$args['post_orderby'] = $args['orderby'];

			unset( $args['orderby'] );
		}

		// check order
		if ( array_key_exists( 'order', $args ) ) {
			$args['post_order'] = $args['order'];

			unset( $args['order'] );
		}

		// check preview type
		$preview_type = sanitize_key( $_POST['preview_type'] );

		// check preview type
		if ( ! in_array( $preview_type, [ 'page', 'update' ], true ) )
			$args['preview_type'] = 'page';
		else
			$args['preview_type'] = $preview_type;

		// check menu item
		$menu_item = sanitize_key( $_POST['menu_item'] );

		// Resolve menu item against available image fields to avoid stale/disabled sources.
		$image_fields = ( isset( $this->fields['images'] ) && is_array( $this->fields['images'] ) ) ? $this->fields['images'] : [];
		if ( ! empty( $menu_item ) && isset( $image_fields[$menu_item] ) && is_array( $image_fields[$menu_item] ) )
			$menu_item = $this->menu_item = $menu_item;
		elseif ( isset( $image_fields['media'] ) )
			$menu_item = $this->menu_item = 'media';
		else {
			$fallback_menu_item = key( $image_fields );
			$menu_item = $this->menu_item = is_string( $fallback_menu_item ) ? $fallback_menu_item : '';
		}

		if ( $menu_item === '' )
			wp_send_json_error();

		$preview_args = isset( $image_fields[$menu_item]['attachments']['preview'] ) && is_array( $image_fields[$menu_item]['attachments']['preview'] ) ? $image_fields[$menu_item]['attachments']['preview'] : [];
		$has_preview_pagination = ! empty( $preview_args['pagination'] );

		if ( $has_preview_pagination ) {
			if ( isset( $args['preview_page'] ) )
				$args['preview_page'] = (int) $args['preview_page'];
			else
				$args['preview_page'] = 1;
		}

		// get images
		$images = $this->get_gallery_images( $post_id, $args );

		// prepare JSON array
		$data = [];

		if ( $menu_item === 'remote_library' ) {
			// get main instance
			$rl = Responsive_Lightbox();

			$response_data = [];

			// single provider?
			if ( $args['media_provider'] !== 'all' ) {
				// get provider
				$provider = $rl->providers[$args['media_provider']];

				// add response data arguments if needed
				if ( ! empty( $provider['response_args'] ) ) {
					$response = $provider['instance']->get_response_data();

					foreach ( $provider['response_args'] as $arg ) {
						if ( array_key_exists( $arg, $response ) )
							$response_data[$provider['slug']][$arg] = base64_encode( wp_json_encode( $response[$arg] ) );
					}
				}
			} else {
				// get active providers
				$providers = $rl->remote_library->get_active_providers();

				if ( ! empty( $providers ) ) {
					foreach ( $providers as $provider ) {
						// get provider
						$provider = $rl->providers[$provider];

						// add response data arguments if needed
						if ( ! empty( $provider['response_args'] ) ) {
							$response = $provider['instance']->get_response_data();

							foreach ( $provider['response_args'] as $arg ) {
								if ( array_key_exists( $arg, $response ) )
									$response_data[$provider['slug']][$arg] = base64_encode( wp_json_encode( $response[$arg] ) );
							}
						}
					}
				}
			}

			$data['response_data'] = $response_data;
		}

		// parse excluded images
		$excluded = ! empty( $_POST['excluded'] ) && is_array( $_POST['excluded'] ) ? array_map( 'intval', $_POST['excluded'] ) : [];

		// get excluded images
		if ( ! empty( $excluded ) )
			$excluded = array_unique( array_filter( $excluded ) );

		// get media item template
		$media_item_template = $this->get_media_item_template( $preview_args );

		// build html
		$html = '';

		// any images?
		if ( ! empty( $images ) ) {
			foreach ( $images as $image ) {
				// get image content html
				$html .= $this->get_gallery_preview_image_content( $image, 'images', $menu_item, 'attachments', $media_item_template, $excluded, $image['id'] );
			}
		}

		$data['images'] = $html;

		if ( $has_preview_pagination )
			$data['pagination'] = $this->get_preview_pagination( $args['preview_page'] );

		// send JSON
		wp_send_json_success( $data );
	}

	/**
	 * Get gallery preview image content HTML.
	 *
	 * @param array $image
	 * @param string $tab_id
	 * @param string $menu_item
	 * @param string $field_name
	 * @param string $template
	 * @param array $excluded
	 * @param string|int $excluded_item
	 * @return string
	 */
	public function get_gallery_preview_image_content( $image, $tab_id, $menu_item, $field_name, $template, $excluded, $excluded_item = '' ) {
		// set flag
		if ( empty( $excluded_item ) )
			$excluded_flag = false;
		else
			$excluded_flag = in_array( $excluded_item, $excluded, true );

		if ( $image['type'] === 'embed' ) {
			// replace all embed data
			$media_html = str_replace(
				[
					'__EMBED_ID__',
					'__EMBED_URL__',
					'__EMBED_WIDTH__',
					'__EMBED_HEIGHT__',
					'__EMBED_THUMBNAIL_URL__',
					'__EMBED_THUMBNAIL_WIDTH__',
					'__EMBED_THUMBNAIL_HEIGHT__',
					'__EMBED_TITLE__',
					'__EMBED_DESCRIPTION__',
					'__EMBED_DATE__'
				],
				[
					esc_attr( $image['id'] ),
					esc_url( $image['url'] ),
					(int) $image['width'],
					(int) $image['height'],
					esc_url( $image['thumbnail_url'] ),
					(int) $image['thumbnail_width'],
					(int) $image['thumbnail_height'],
					esc_attr( $image['title'] ),
					esc_textarea( $image['caption'] ),
					esc_attr( $image['date'] )
				],
				$this->get_media_embed_template( false )
			);
		} else
			$media_html = '';

		// replace id and url of an image
		return str_replace(
			[
				'__MEDIA_DATA__',
				'__MEDIA_ID__',
				'__MEDIA_STATUS__',
				'__MEDIA_TYPE__'
			],
			[
				$this->get_media_exclude_input_template( $tab_id, $menu_item, $field_name, $excluded_flag ? $excluded_item : '' ) . $media_html . $image['thumbnail_link'],
				esc_attr( $image['id'] ),
				$excluded_flag ? ' rl-status-inactive' : ' rl-status-active',
				esc_attr( $image['type'] )
			],
			$template
		);
	}

	/**
	 * Get preview pagination.
	 *
	 * @param int $current_page
	 * @return string
	 */
	public function get_preview_pagination( $current_page = 1 ) {
		$page_links = [];
		$total_pages = $current_page + 1;
		$current = $current_page;
		$disable_first = $disable_last = $disable_prev = $disable_next = false;
		$current_url = 'preview_page';

		if ( $current === 1 ) {
			$disable_first = true;
			$disable_prev = true;
		} elseif ( $current === 2 )
			$disable_first = true;

		if ( $current === $total_pages ) {
			$disable_last = true;
			$disable_next = true;
		}

		if ( $current === $total_pages - 1 )
			$disable_last = true;

		if ( $disable_first )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url,
				esc_html__( 'First page', 'responsive-lightbox' ),
				'&laquo;'
			);
		}

		if ( $disable_prev )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . max( 1, $current - 1 ),
				esc_html__( 'Previous page', 'responsive-lightbox' ),
				'&lsaquo;'
			);
		}

		$html_current_page = sprintf(
			'%s<input disabled="disabled" class="current-page" id="current-page-selector" type="text" name="paged" value="%s" size="%d" aria-describedby="table-paging" /><span class="tablenav-paging-text">',
			'<label for="current-page-selector" class="screen-reader-text">' . esc_html__( 'Current Page', 'responsive-lightbox' ) . '</label>',
			$current,
			strlen( $total_pages )
		);

		$html_total_pages = sprintf( '<span class="total-pages">%s</span>', number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span></span>';

		if ( $disable_next )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . min( $total_pages, $current + 1 ),
				esc_html__( 'Next page', 'responsive-lightbox' ),
				'&rsaquo;'
			);
		}

		if ( $disable_last )
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		else {
			$page_links[] = sprintf(
				'<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
				$current_url . '/' . $total_pages,
				esc_html__( 'Last page', 'responsive-lightbox' ),
				'&raquo;'
			);
		}

		if ( $total_pages )
			$page_class = $total_pages < 2 ? 'one-page' : '';
		else
			$page_class = 'no-pages';

		return '<div class="rl-gallery-preview-pagination tablenav"><div class="tablenav-pages ' . esc_attr( $page_class ) . '"><span class="pagination-links">' . join( "\n", $page_links ) . '</span></div>';
	}
}
