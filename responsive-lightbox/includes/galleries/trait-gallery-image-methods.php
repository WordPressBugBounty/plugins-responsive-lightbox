<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Galleries Image Methods Trait.
 *
 * Handles all image query, source resolution, and pagination operations
 * for the Responsive_Lightbox_Galleries class.
 *
 * @trait Responsive_Lightbox_Galleries_Image_Methods
 */
trait Responsive_Lightbox_Galleries_Image_Methods {

	/**
	 * Get gallery images.
	 *
	 * @param int $gallery_id Gallery ID
	 * @param array $args Additional arguments
	 * @return array
	 */
	public function get_gallery_images( $gallery_id = 0, $args = [] ) {
		$images = [];
		$excluded = [];

		// get main instance
		$rl = Responsive_Lightbox();

		// get args
		$defaults = array(
			'count_images'			=> false,
			'exclude'				=> false,
			'posts_per_page'		=> -1,
			'images_per_page'		=> 0,
			'page'					=> 1,
			'limit'					=> 0,
			'nopaging'				=> true,
			'image_size'			=> 'large',
			'thumbnail_size'		=> 'thumbnail',
			'pagination_type'		=> 'paged',
			'pagination_position'	=> 'bottom',
			'orderby'				=> 'menu_order',
			'order'					=> 'asc',
			'preview'				=> is_admin(),
			'preview_type'			=> 'update',
			'preview_page'			=> 1,
			'preview_per_page'		=> 20,
			'taxonomy'				=> $rl->folders->get_active_taxonomy(),
			'folder'				=> array(
				'id'		=> 0,
				'children'	=> null // do not change!
			)
		);

		// parse arguments
		$args = wp_parse_args( apply_filters( 'rl_get_gallery_images_args', $args, $gallery_id ), $defaults );

		// disable counting mode
		if ( $args['preview'] )
			$args['count_images'] = false;

		// sanitize args
		$args['exclude'] = (bool) ! empty( $args['exclude'] );
		$args['posts_per_page'] = ! empty( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;
		$args['nopaging'] = (bool) ! empty( $args['nopaging'] );

		// check gallery post type
		$valid_gallery_type = ( get_post_type( $gallery_id ) === 'rl_gallery' );

		// is it rl_gallery? skip when counting mode is enabled
		if ( $valid_gallery_type && ! $args['count_images'] ) {
			$paging = get_post_meta( $gallery_id, '_rl_paging', true );

			if ( is_array( $paging ) && ! empty( $paging['menu_item'] ) && isset( $paging[$paging['menu_item']] ) && is_array( $paging[$paging['menu_item']] ) ) {
				$pagination = $paging[$paging['menu_item']];

				if ( ! empty( $pagination['pagination'] ) ) {
					$args['nopaging'] = false;
					$args['images_per_page'] = isset( $pagination['images_per_page'] ) ? $pagination['images_per_page'] : $args['images_per_page'];
					$args['pagination_type'] = isset( $pagination['pagination_type'] ) ? $pagination['pagination_type'] : $args['pagination_type'];

					// infinite type?
					if ( $args['pagination_type'] === 'infinite' )
						$args['pagination_position'] = 'bottom';
					else
						$args['pagination_position'] = isset( $pagination['pagination_position'] ) ? $pagination['pagination_position'] : $args['pagination_position'];
				} else
					$args['nopaging'] = true;
			}
		}

		global $pagenow;

		// is it preview?
		if ( ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && $gallery_id ) || ( isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-preview-content' ) || ( wp_doing_ajax() && isset( $_POST['action'] ) && ( $_POST['action'] === 'rl-post-gallery-preview' || $_POST['action'] === 'rl-get-menu-content' ) ) )
			$args['images_per_page'] = 0;

		if ( isset( $_GET['rl_page'] ) )
			$args['page'] = (int) $_GET['rl_page'];
		else
			$args['page'] = (int) $args['page'];

		// is it rl_gallery?
		if ( $valid_gallery_type ) {
			// no need order in counting mode
			if ( ! $args['count_images'] ) {
				// get config metadata
				$config_meta = get_post_meta( $gallery_id, '_rl_config', true );

				// config order
				if ( is_array( $config_meta ) && ! empty( $config_meta['menu_item'] ) && isset( $config_meta[$config_meta['menu_item']] ) && is_array( $config_meta[$config_meta['menu_item']] ) ) {
					$config = $config_meta[$config_meta['menu_item']];

					if ( isset( $config['orderby'] ) )
						$args['orderby'] = $config['orderby'];

					if ( isset( $config['order'] ) )
						$args['order'] = $config['order'];
				}
			}

			// get images metadata
			$data = get_post_meta( $gallery_id, '_rl_images', true );

			// array?
			if ( ! is_array( $data ) )
				$data = [];

			// get menu item
			if ( ! empty( $this->menu_item ) )
				$menu_item = $this->menu_item;
			elseif ( array_key_exists( 'menu_item', $data ) )
				$menu_item = $data['menu_item'];
			else
				$menu_item = 'media';

			// Normalize stale/unavailable sources (e.g. saved "folders" while folders are disabled).
			$image_fields = ( isset( $this->fields['images'] ) && is_array( $this->fields['images'] ) ) ? $this->fields['images'] : [];
			if ( ! isset( $image_fields[$menu_item] ) || ! is_array( $image_fields[$menu_item] ) ) {
				if ( isset( $image_fields['media'] ) )
					$menu_item = 'media';
				else {
					$fallback_menu_item = key( $image_fields );
					$menu_item = is_string( $fallback_menu_item ) ? $fallback_menu_item : '';
				}
			}

			if ( $menu_item === '' )
				return [];

			// valid data?
			if ( ! array_key_exists( $menu_item, $data ) )
				$data[$menu_item] = [];

			$has_preview_pagination = ! empty( $image_fields[$menu_item]['attachments']['preview']['pagination'] );
			if ( $args['preview'] && $has_preview_pagination ) {
				if ( isset( $args['preview_page'] ) )
					$args['preview_page'] = (int) $args['preview_page'];
				else
					$args['preview_page'] = 1;

				$args['preview_per_page'] = (int) $args['preview_per_page'];
			}

			switch ( $menu_item ) {
				case 'media':
					// check embed data
					if ( ! empty( $data[$menu_item]['attachments']['embed'] ) ) {
						$atts_args = [
							'embed_keys'	=> array_keys( $data[$menu_item]['attachments']['embed'] ),
							'providers'		=> [ 'youtube', 'vimeo' ]
						];
					} else
						$atts_args = [];

					// get attachment ids
					$attachments = ! empty( $data[$menu_item]['attachments']['ids'] ) ? $this->check_attachments( array_unique( array_filter( $data[$menu_item]['attachments']['ids'] ) ), $atts_args ) : [];

					// filter attachments
					$attachments = apply_filters( 'rl_get_gallery_images_attachments', $attachments, $atts_args );

					// exclude any attachments?
					if ( $args['exclude'] && ! empty( $data[$menu_item]['attachments']['exclude'] ) )
						$attachments = array_diff( $attachments, $data[$menu_item]['attachments']['exclude'] );

					// check filtered attachments
					$attachments = $this->check_attachments( $attachments, $atts_args );

					// any attachments?
					if ( $attachments ) {
						if ( $args['limit'] )
							$counter = 0;

						foreach ( $attachments as $attachment_id ) {
							// for counting mode get attachment id only
							if ( $args['count_images'] )
								$images[] = $attachment_id;
							else {
								// embed?
								if ( preg_match( '/^e\d+$/', $attachment_id ) === 1 ) {
									$attachment_data = $data[$menu_item]['attachments']['embed'][$attachment_id];
									$attachment_data['type'] = 'embed';
								} else
									$attachment_data = $attachment_id;

								// get attachment image data
								$images[] = $this->get_gallery_image_src( $attachment_data, $args['image_size'], $args['thumbnail_size'] );

								// limit attachments?
								if ( $args['limit'] ) {
									$counter++;

									// limit reached?
									if ( $counter === $args['limit'] )
										break;
								}
							}
						}
					}
					break;

				case 'featured':
					// only for featured frontend galleries
					if ( ! is_admin() || wp_doing_ajax() ) {
						// prepare featured fields
						$this->fields['images']['featured'] = $this->prepare_featured_fields( $this->fields['images']['featured'] );
					}

					// copy arguments
					$query_args = $args;

					// skip order for counting mode
					if ( ! $args['count_images'] ) {
						// prevent duplicating images order (config tab) with posts order (images tab), query will handle empty strings
						if ( array_key_exists( 'post_orderby', $args ) )
							$query_args['orderby'] = $args['post_orderby'];
						elseif ( array_key_exists( 'orderby', $data[$menu_item] ) )
							$query_args['orderby'] = $data[$menu_item]['orderby'];
						else
							$query_args['orderby'] = '';

						if ( array_key_exists( 'post_order', $args ) )
							$query_args['order'] = $args['post_order'];
						elseif ( array_key_exists( 'order', $data[$menu_item] ) )
							$query_args['order'] = $data[$menu_item]['order'];
						else
							$query_args['order'] = '';
					}

					// get attachment ids
					$attachments = $this->gallery_query( array_merge( $data[$menu_item], $query_args ) );

					// filter attachments
					$attachments = apply_filters( 'rl_get_gallery_images_attachments', $attachments );

					// exclude any attachments?
					if ( $args['exclude'] && ! empty( $data[$menu_item]['attachments']['exclude'] ) )
						$attachments = array_diff( $attachments, $data[$menu_item]['attachments']['exclude'] );

					// any attachments?
					if ( $attachments ) {
						if ( $args['limit'] )
							$counter = 0;

						foreach ( $attachments as $attachment_id ) {
							// real attachment?
							if ( ! wp_attachment_is_image( $attachment_id ) )
								continue;

							// for counting mode get attachment id only
							if ( $args['count_images'] )
								$images[] = $attachment_id;
							else {
								// get attachment image data
								$images[] = $this->get_gallery_image_src( $attachment_id, $args['image_size'], $args['thumbnail_size'] );

								// limit attachments?
								if ( $args['limit'] ) {
									$counter++;

									// limit reached?
									if ( $counter === $args['limit'] )
										break;
								}
							}
						}
					}
					break;

				case 'folders':
					// is folders active?
					if ( ! $rl->options['folders']['active'] )
						break;

					if ( ! array_key_exists( 'folder', $data[$menu_item] ) )
						$data[$menu_item]['folder'] = $defaults['folder'];

					// ajax requests
					if ( is_string( $args['folder']['id'] ) )
						$args['folder']['id'] = (int) $args['folder']['id'];

					// not empty folder term id?
					if ( ! empty( $args['folder']['id'] ) ) {
						// get term
						$term = get_term( $args['folder']['id'], $args['taxonomy'] );

						// valid term?
						if ( is_a( $term, 'WP_Term' ) )
							$folder_id = (int) $term->term_id;
						else
							$folder_id = (int) $data[$menu_item]['folder']['id'];
					} else {
						if ( isset( $_POST['action'] ) && $_POST['action'] === 'rl-get-preview-content' )
							$folder_id = $args['folder']['id'];
						else
							$folder_id = (int) $data[$menu_item]['folder']['id'];
					}

					if ( $folder_id >= 0 ) {
						$include_children = false;

						// null means folder was not changed
						if ( $args['folder']['children'] === null ) {
							if ( array_key_exists( 'children', $data[$menu_item]['folder'] ) && $data[$menu_item]['folder']['children'] === true )
								$include_children = true;
						// overwritten by args
						} else {
							if ( is_string( $args['folder']['children'] ) ) {
								if ( $args['folder']['children'] === 'true' )
									$include_children = true;
							} elseif ( is_bool( $args['folder']['children'] ) ) {
								if ( $args['folder']['children'] )
									$include_children = true;
							}
						}

						if ( $folder_id === 0 ) {
							if ( $include_children ) {
								$all_folders = get_terms(
									array(
										'taxonomy'		=> $args['taxonomy'],
										'hide_empty'	=> false,
										'fields'		=> 'ids',
										'hierarchical'	=> false,
										'number'		=> 0
									)
								);

								$tax_query = array(
									array(
										'relation' => 'OR',
										array(
											'taxonomy'			=> $args['taxonomy'],
											'field'				=> 'term_id',
											'terms'				=> ( ! is_wp_error( $all_folders ) ) ? $all_folders : $folder_id,
											'include_children'	=> $include_children,
											'operator'			=> 'IN'
										),
										array(
											'taxonomy'			=> $args['taxonomy'],
											'field'				=> 'term_id',
											'terms'				=> $folder_id,
											'include_children'	=> $include_children,
											'operator'			=> 'NOT EXISTS'
										)
									)
								);
							} else {
								$tax_query = array(
									array(
										'taxonomy'			=> $args['taxonomy'],
										'field'				=> 'term_id',
										'terms'				=> $folder_id,
										'include_children'	=> $include_children,
										'operator'			=> 'NOT EXISTS'
									)
								);
							}
						} else {
							$tax_query = array(
								array(
									'taxonomy'			=> $args['taxonomy'],
									'field'				=> 'term_id',
									'terms'				=> $folder_id,
									'include_children'	=> $include_children,
									'operator'			=> 'IN'
								)
							);
						}

						// prepare query arguments
						$wp_query_args = array(
							'post_type'			=> 'attachment',
							'post_status'		=> 'inherit',
							'post_mime_type'	=> array( 'image/jpeg', 'image/gif', 'image/png' ),
							'nopaging'			=> true,
							'posts_per_page'	=> -1,
							'fields'			=> 'ids',
							'tax_query'			=> $tax_query
						);

						// is it preview?
						if ( $args['preview'] ) {
							$wp_query_args['posts_per_page'] = $args['preview_per_page'];
							$wp_query_args['offset'] = ( $args['preview_page'] - 1 ) * $args['preview_per_page'];
							$wp_query_args['nopaging'] = false;
						}

						// run query
						$query = new WP_Query( apply_filters( 'rl_folders_query_args', $wp_query_args ) );

						// get attachment ids
						$attachments = $query->get_posts();

						// valid attachments?
						if ( ! is_wp_error( $attachments ) ) {
							// cast ids to int
							$attachments = array_map( 'intval', $attachments );

							// make sure to skip duplicates
							$attachments = array_unique( $attachments );

							// filter attachments
							$attachments = apply_filters( 'rl_get_gallery_images_attachments', $attachments );

							// exclude any attachments?
							if ( $args['exclude'] && ! empty( $data[$menu_item]['attachments']['exclude'] ) )
								$attachments = array_diff( $attachments, $data[$menu_item]['attachments']['exclude'] );

							// any attachments?
							if ( $attachments ) {
								if ( $args['limit'] )
									$counter = 0;

								foreach ( $attachments as $attachment_id ) {
									// real attachment?
									if ( ! wp_attachment_is_image( $attachment_id ) )
										continue;

									// for counting mode get attachment id only
									if ( $args['count_images'] )
										$images[] = $attachment_id;
									else {
										// get attachment image data
										$images[] = $this->get_gallery_image_src( $attachment_id, $args['image_size'], $args['thumbnail_size'] );

										// limit attachments?
										if ( $args['limit'] ) {
											$counter++;

											// limit reached?
											if ( $counter === $args['limit'] )
												break;
										}
									}
								}
							}
						}
					}
					break;

				case 'remote_library':
					// is remote library active?
					if ( ! $rl->options['remote_library']['active'] )
						break;

					// no media search phrase?
					if ( ! isset( $args['media_search'] ) )
						$args['media_search'] = isset( $data[$menu_item]['media_search'] ) ? $data[$menu_item]['media_search'] : '';

					// no media provider?
					if ( ! isset( $args['media_provider'] ) )
						$args['media_provider'] = isset( $data[$menu_item]['media_provider'] ) ? $data[$menu_item]['media_provider'] : 'all';

					// get remote images
					$images = $rl->remote_library->get_remote_library_images( $args );
					break;
			}
		}

		// skip order for counting mode
		if ( ! $args['count_images'] ) {
			// config sort order
			switch ( $args['orderby'] ) {
				case 'id':
					$sort = [];

					foreach ( $images as $key => $image ) {
						// set sorting value
						$sort[$key] = $image['id'];
					}

					// sort
					array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, SORT_NUMERIC, $images );
					break;

				case 'title':
					$sort = [];

					foreach ( $images as $key => $image ) {
						$title = isset( $image['title'] ) ? (string) $image['title'] : '';

						// Fallback for attachments without a populated title in the payload.
						if ( $title === '' && isset( $image['id'] ) && is_numeric( $image['id'] ) )
							$title = (string) get_the_title( (int) $image['id'] );

						// set sorting value
						$sort[$key] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
					}

					// sort
					array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, SORT_STRING, $images );
					break;

				case 'post_date':
					$sort = [];

					foreach ( $images as $key => $image ) {
						// set sorting value
						$sort[$key] = $image['date'];
					}

					// sort
					array_multisort( $sort, $args['order'] === 'asc' ? SORT_ASC : SORT_DESC, $images );
					break;

				case 'menu_order':
					// do nothing
					break;

				case 'rand':
					shuffle( $images );
					break;
			}
		}

		// filter images
		$images = apply_filters( 'rl_get_gallery_images_array', $images, $gallery_id, $args );

		// count number of images
		$images_count = count( $images );

		// no preview?
		if ( ! $args['preview'] && ! $args['count_images'] && $args['limit'] === 0 )
			update_post_meta( $gallery_id, '_rl_images_count', $images_count );

		// images pagination?
		if ( $images && ! $args['nopaging'] && $args['images_per_page'] > 0 && ! $args['count_images'] ) {
			// get part of images
			$images = array_slice( $images, ( $args['page'] - 1 ) * $args['images_per_page'], $args['images_per_page'], true );

			// pass gallery args
			$this->gallery_args = $args;
			$this->gallery_args['total'] = (int) ceil( $images_count / $args['images_per_page'] );

			// remove actions to avoid issues with multiple galleries on single page
			remove_action( 'rl_before_gallery', [ $this, 'do_pagination' ], 10 );
			remove_action( 'rl_after_gallery', [ $this, 'do_pagination' ], 10 );

			// pagination position
			if ( $args['pagination_position'] === 'top' )
				add_action( 'rl_before_gallery', [ $this, 'do_pagination' ], 10, 2 );
			elseif ( $args['pagination_position'] === 'bottom' )
				add_action( 'rl_after_gallery', [ $this, 'do_pagination' ], 10, 2 );
			else {
				add_action( 'rl_before_gallery', [ $this, 'do_pagination' ], 10, 2 );
				add_action( 'rl_after_gallery', [ $this, 'do_pagination' ], 10, 2 );
			}
		}

		return apply_filters( 'rl_get_gallery_images', array_values( $images ), $gallery_id, $args );
	}

	/**
	 * Create gallery pagination.
	 *
	 * @global object $wp
	 *
	 * @param array $args
	 * @param int $gallery_id
	 * @return void
	 */
	public function do_pagination( $args, $gallery_id ) {
		global $wp;

		// get main instance
		$rl = Responsive_Lightbox();

		// get current action
		$current_action = current_action();

		if ( $current_action === 'rl_before_gallery' )
			$class = 'rl-pagination-top';
		elseif ( $current_action === 'rl_after_gallery' )
			$class = 'rl-pagination-bottom';
		else
			$class = '';

		// set base arguments
		$base_args = [ 'rl_gallery_no' => $rl->frontend->get_data( 'gallery_no' ), 'rl_page' => '%#%' ];

		if ( empty( $args['pagination_type'] ) )
			$args['pagination_type'] = 'paged';

		// infinite scroll?
		if ( $args['pagination_type'] === 'infinite' )
			$base_args['rl_lightbox_script'] = $rl->get_data( 'current_script' );

		echo
		'<div class="rl-pagination ' . esc_attr( $class ) . '"' . ( $args['pagination_type'] === 'infinite' ? ' data-button="' . esc_attr( $args['load_more'] ) . '"' : '' ) .'>' .
		paginate_links(
			[
				'format' => '?rl_page=%#%',
				'base' => add_query_arg( $base_args, $args['pagination_type'] !== 'paged' ? get_permalink( $gallery_id ) : home_url( $wp->request ) ),
				'total' => $this->gallery_args['total'],
				'current' => $this->gallery_args['page'],
				'show_all' => false,
				'end_size' => 1,
				'mid_size' => 2,
				'prev_next' => true,
				'prev_text' => esc_html__( '&laquo; Previous', 'responsive-lightbox' ),
				'next_text' => esc_html__( 'Next &raquo;', 'responsive-lightbox' ),
				'type' => 'plain',
				'add_args' => '',
				'add_fragment' => '',
				'before_page_number' => '',
				'after_page_number' => ''
			]
		) .
		'</div>' . ( $args['pagination_type'] === 'infinite' && $args['load_more'] === 'manually' ? '<div class="rl-gallery-button"><button class="rl-button rl-load-more">' . esc_html__( 'Load more', 'responsive-lightbox' ) . '</button></div>' : '' );
	}

	/**
	 * Get gallery image link.
	 *
	 * @param array $image Image data
	 * @param string $size Image size
	 * @param array $attr Image attributes
	 * @return string
	 */
	public function get_gallery_image_link( $image, $size = 'thumbnail', $attr = [] ) {
		$link = '';

		if ( $size === 'thumbnail' ) {
			$url = $image['thumbnail_url'];
			$width = $image['thumbnail_width'];
			$height = $image['thumbnail_height'];
		} else {
			$url = $image['url'];
			$width = $image['width'];
			$height = $image['height'];
		}

		if ( ! empty( $image['url'] ) ) {
			$size_class = $size;

			if ( is_array( $size_class ) )
				$size_class = join( 'x', $size_class );

			// combine attributes
			$attr = wp_parse_args(
				$attr,
				array(
					'src'	=> $url,
					'class'	=> 'attachment-' . $size_class . ' size-' . $size_class . ' format-' . ( $height > $width ? 'portrait' : 'landscape' ),
					'alt'	=> $image['alt']
				)
			);

			// apply filters if any
			$attr = apply_filters( 'rl_get_gallery_image_attributes', $attr, $image, $size );

			// start link output
			$link = rtrim( '<img ' . image_hwstring( $width, $height ) );

			// add attributes
			foreach ( $attr as $name => $value ) {
				$link .= ' ' . esc_attr( $name ) . '="' . ( $name === 'src' ? esc_url( $value ) : esc_attr( $value ) ) . '"';
			}

			// end link output
			$link .= ' />';
		}

		return apply_filters( 'rl_get_gallery_image_link', $link, $image, $size );
	}

	/**
	 * Get attachment image source.
	 *
	 * @param int|string|array $image Attachment ID, image URL or array of image data
	 * @param string $image_size Image size
	 * @param string $thumbnail_size Thumbnail size
	 * @return array
	 */
	public function get_gallery_image_src( $image, $image_size = 'large', $thumbnail_size = 'thumbnail' ) {
		$imagedata = [];

		// check difference in size between image and thumbnail
		$diff_sizes = $thumbnail_size !== $image_size;

		// attachment id?
		if ( is_int( $image ) ) {
			if ( $image ) {
				$type = 'image';
				$width = 0;
				$height = 0;

				// image src
				if ( wp_attachment_is_image( $image ) ) {
					$image_src = wp_get_attachment_image_src( $image, $image_size, false );

					// different image and thumbnail sizes?
					if ( $diff_sizes )
						$thumbnail_src = wp_get_attachment_image_src( $image, $thumbnail_size, false );
					else
						$thumbnail_src = $image_src;

					$file_url = $image_src[0];
					$width = $image_src[1];
					$height = $image_src[2];
					$thumbnail_url = $thumbnail_src[0];
					$thumbnail_width = $thumbnail_src[1];
					$thumbnail_height = $thumbnail_src[2];
				// video, blank thumbnail src
				} elseif ( rl_current_lightbox_supports( 'video' ) && wp_attachment_is( 'video', $image ) ) {
					$type = 'video';
					$thumbnail_id = $this->get_video_thumbnail_id( $image );
					$thumbnail_src = wp_get_attachment_image_src( $thumbnail_id, $image_size, false );

					// get video metadata
					$meta = wp_get_attachment_metadata( $image );

					if ( $meta ) {
						$width = $meta['width'];
						$height = $meta['height'];
					} else {
						$width = $thumbnail_src[1];
						$height = $thumbnail_src[2];
					}

					// different image and thumbnail sizes?
					if ( $diff_sizes )
						$thumbnail_src = wp_get_attachment_image_src( $thumbnail_id, $thumbnail_size, false );

					// file url
					$file_url = wp_get_attachment_url( $image );
					$thumbnail_url = $thumbnail_src[0];
					$thumbnail_width = $thumbnail_src[1];
					$thumbnail_height = $thumbnail_src[2];
				}

				// get alternative text
				$alt = get_post_meta( $image, '_wp_attachment_image_alt', true );

				// allow only strings
				if ( ! is_string( $alt ) )
					$alt = '';

				$imagedata = array(
					'id'				=> $image,
					'title'				=> get_the_title( $image ),
					'date'				=> get_the_date( 'Y-m-d H:i:s', $image ),
					'caption'			=> '',
					'alt'				=> $alt,
					'url'				=> $file_url, // $image_src[0],
					'width'				=> $width,
					'height'			=> $height,
					'orientation'		=> $height > $width ? 'portrait' : 'landscape',
					'thumbnail_url'		=> $thumbnail_url,
					'thumbnail_width'	=> $thumbnail_width,
					'thumbnail_height'	=> $thumbnail_height,
					'type'				=> $type
				);

				if ( $diff_sizes )
					$imagedata['thumbnail_orientation'] = $thumbnail_src[2] > $thumbnail_src[1] ? 'portrait' : 'landscape';
				else
					$imagedata['thumbnail_orientation'] = $imagedata['orientation'];
			}
		// image url
		} elseif ( is_string( $image ) ) {
			$imagedata['url'] = $image;

			@list( $imagedata['width'], $imagedata['height'] ) = rl_get_image_size_by_url( $imagedata['url'] );

			$imagedata = array(
				'id'				=> 0,
				'title'				=> '',
				'date'				=> '',
				'caption'			=> '',
				'alt'				=> '',
				'url'				=> $imagedata['url'],
				'width'				=> $imagedata['width'],
				'height'			=> $imagedata['height'],
				'orientation'		=> $imagedata['height'] > $imagedata['width'] ? 'portrait' : 'landscape',
				'thumbnail_url'		=> $imagedata['url'],
				'thumbnail_width'	=> $imagedata['width'],
				'thumbnail_height'	=> $imagedata['height'],
				'type'				=> 'image'
			);

			$imagedata['thumbnail_orientation'] = $imagedata['orientation'];
		// full image array
		} elseif ( is_array( $image ) ) {
			// set width and height from url, if not available
			if ( empty( $image['width'] ) || empty( $image['height'] ) )
				@list( $image['width'], $image['height'] ) = rl_get_image_size_by_url( $image['url'] );

			// set thumbnail data, if not available
			if ( empty( $image['thumbnail_url'] ) ) {
				$image['thumbnail_url'] = $image['url'];
				$image['thumbnail_width'] = $image['width'];
				$image['thumbnail_height'] = $image['height'];
			} else {
				// set thumbnail width and height from url, if not available
				if ( empty( $image['thumbnail_width'] ) || empty( $image['thumbnail_height'] ) )
					@list( $image['thumbnail_width'], $image['thumbnail_height'] ) = rl_get_image_size_by_url( $image['thumbnail_url'] );
			}

			$imagedata = array(
				'id'				=> ! empty( $image['id'] ) ? ( preg_match( '/^e\d+$/', $image['id'] ) === 1 ? $image['id'] : (int) $image['id'] ) : 0,
				'title'				=> ! empty( $image['title'] ) ? ( $image['title'] ) : '',
				'date'				=> ! empty( $image['date'] ) ? ( $image['date'] ) : '',
				'caption'			=> ! empty( $image['caption'] ) ? ( $image['caption'] ) : '',
				'alt'				=> ! empty( $image['alt'] ) ? ( $image['alt'] ) : '',
				'url'				=> ! empty( $image['url'] ) ? esc_url_raw( $image['url'] ) : '',
				'width'				=> ! empty( $image['width'] ) ? (int) $image['width'] : 0,
				'height'			=> ! empty( $image['height'] ) ? (int) $image['height'] : 0,
				'thumbnail_url'		=> ! empty( $image['thumbnail_url'] ) ? esc_url_raw( $image['thumbnail_url'] ) : '',
				'thumbnail_width'	=> ! empty( $image['thumbnail_width'] ) ? (int) $image['thumbnail_width'] : 0,
				'thumbnail_height'	=> ! empty( $image['thumbnail_height'] ) ? (int) $image['thumbnail_height'] : 0,
				'link'				=> ! empty( $image['link'] ) ? esc_url_raw( $image['link'] ) : '',
				'thumbnail_link'	=> ! empty( $image['thumbnail_link'] ) ? esc_url_raw( $image['thumbnail_link'] ) : '',
				'type'				=> ! empty( $image['type'] ) ? ( $image['type'] ) : 'image'
			);

			$imagedata['orientation'] = $imagedata['height'] > $imagedata['width'] ? 'portrait' : 'landscape';
			$imagedata['thumbnail_orientation'] = $imagedata['thumbnail_height'] > $imagedata['thumbnail_width'] ? 'portrait' : 'landscape';
		}

		if ( ! empty( $imagedata ) ) {
			// link does not exist?
			if ( empty( $imagedata['link'] ) )
				$imagedata['link'] = $this->get_gallery_image_link( $imagedata, $image_size );

			// thumbnail link does not exist?
			if ( empty( $imagedata['thumbnail_link'] ) ) {
				// different image and thumbnail sizes?
				if ( $diff_sizes )
					$imagedata['thumbnail_link'] = $this->get_gallery_image_link( $imagedata, $thumbnail_size );
				else
					$imagedata['thumbnail_link'] = $imagedata['link'];
			}
		}

		return apply_filters( 'rl_get_gallery_image_src', $imagedata, $image, $image_size, $thumbnail_size );
	}

	/**
	 * Get gallery featured image.
	 *
	 * @param int $gallery_id
	 * @param string $size Image size
	 * @param array $attr Image attributes
	 * @return string
	 */
	public function get_featured_image( $gallery_id, $size = 'thumbnail', $attr = [] ) {
		$image = $this->get_featured_image_src( $gallery_id );
		$html = '';

		if ( $image )
			$html = $this->get_gallery_image_link( $this->get_gallery_image_src( $image, 'large', $size ), $size, $attr );

		return apply_filters( 'rl_get_featured_image', $html, $gallery_id, $size );
	}

	/**
	 * Get gallery featured image data.
	 *
	 * @param int $gallery_id
	 * @return array
	 */
	public function get_featured_image_src( $gallery_id ) {
		// get featured image data
		$featured_image_type = get_post_meta( $gallery_id, '_rl_featured_image_type', true );
		$featured_image = get_post_meta( $gallery_id, '_rl_featured_image', true );

		switch ( $featured_image_type ) {
			// custom url
			case 'url':
				$frontend = function_exists( 'Responsive_Lightbox' ) ? Responsive_Lightbox()->frontend : null;
				if ( $frontend && method_exists( $frontend, 'sanitize_remote_image_url' ) )
					$featured_image = $frontend->sanitize_remote_image_url( $featured_image );
				else
					$featured_image = '';

				if ( $featured_image !== '' ) {
					$image = esc_url( $featured_image );
					break;
				}

				$image = $this->get_first_gallery_featured_image( $gallery_id );
				break;

			// attachment id
			case 'id':
				$featured_image = (int) $featured_image;
				$image = wp_attachment_is_image( $featured_image ) ? $featured_image : $this->maybe_generate_thumbnail();
				break;

			// first image
			case 'image':
			default:
				$image = $this->get_first_gallery_featured_image( $gallery_id );
		}

		return apply_filters( 'rl_get_featured_image_src', $image, $gallery_id, $featured_image_type, $featured_image );
	}

	/**
	 * Helper to fetch the first gallery image data.
	 *
	 * @param int $gallery_id
	 * @return array|int
	 */
	protected function get_first_gallery_featured_image( $gallery_id ) {
		$images = $this->get_gallery_images(
			$gallery_id,
			[
				'exclude'	=> true,
				'limit'		=> 1
			]
		);

		if ( $images )
			return reset( $images );

		return 0;
	}

	/**
	 * Get featured gallery attachments.
	 *
	 * @param array $args
	 * @return array
	 */
	public function gallery_query( $args ) {
		$attachments = [];

		// get fields
		$fields = $this->fields['images']['featured'];

		// force these settings
		$args['fields'] = 'ids';
		$args['tax_query'] = [];
		$args['meta_query'] = [];
		$args['author__in'] = [];
		$args['post_parent__in'] = [];

		// get image source
		$args['image_source'] = isset( $args['image_source'] ) && array_key_exists( $args['image_source'], $fields['image_source']['options'] ) ? $args['image_source'] : $fields['image_source']['default'];

		// get images per post
		$args['images_per_post'] = isset( $args['images_per_post'] ) ? absint( $args['images_per_post'] ) : $fields['images_per_post']['default'];

		// get number of posts
		$args['number_of_posts'] = isset( $args['number_of_posts'] ) ? (int) $args['number_of_posts'] : $fields['number_of_posts']['default'];

		// get all posts?
		if ( $args['number_of_posts'] <= 0 )
			$args['number_of_posts'] = -1;

		// convert to wp query arg
		$args['posts_per_page'] = $args['number_of_posts'];

		$args['order'] = isset( $args['order'] ) && array_key_exists( $args['order'], $fields['order']['options'] ) ? $args['order'] : $fields['order']['default'];
		$args['orderby'] = isset( $args['orderby'] ) && array_key_exists( $args['orderby'], $fields['orderby']['options'] ) ? $args['orderby'] : $fields['orderby']['default'];
		$args['offset'] = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$tax_queries = array(
			'post_format'	=> [],
			'post_term'		=> []
		);

		$meta_queries = array(
			'page_template'	=> [],
			'image_source'	=> []
		);

		// post type
		if ( ! empty( $args['post_type'] ) ) {
			// assign post types
			$post_types = $args['post_type'];

			// clear post types
			$args['post_type'] = [];

			foreach ( $post_types as $post_type ) {
				if ( array_key_exists( $post_type, $fields['post_type']['options'] ) )
					$args['post_type'][] = $post_type;
			}
		} else
			$args['post_type'] = $this->get_post_types( true );

		// post status
		if ( ! empty( $args['post_status'] ) ) {
			// assign post statuses
			$post_statuses = $args['post_status'];

			// clear post statuses
			$args['post_status'] = [];

			foreach ( $post_statuses as $post_status ) {
				if ( array_key_exists( $post_status, $fields['post_status']['options'] ) )
					$args['post_status'][] = $post_status;
			}
		} else {
			// Keep legacy behavior: empty selection means no post-status filtering in UI terms.
			$args['post_status'] = array_keys( $fields['post_status']['options'] );
		}

		// Defensive fallback if options could not be prepared yet.
		if ( empty( $args['post_status'] ) ) {
			$args['post_status'] = [ 'publish' ];
		}

		// post format
		if ( ! empty( $args['post_format'] ) ) {
			// assign post formats
			$post_formats = $args['post_format'];

			foreach ( $post_formats as $post_format ) {
				if ( array_key_exists( $post_format, $fields['post_format']['options'] ) ) {
					// standard format?
					if ( $post_format === 'standard' ) {
						$tax_queries['post_format'][] = array(
							'relation' => 'OR',
							array(
								'taxonomy' => 'post_format',
								'field' => 'slug',
								'terms' => array( 'post-format-standard' )
							),
							array(
								'taxonomy' => 'post_format',
								'field' => 'slug',
								'operator' => 'NOT EXISTS'
							)
						);
					} else {
						$tax_queries['post_format'][] = array(
							'taxonomy' => 'post_format',
							'field' => 'slug',
							'terms' => array( 'post-format-' . $post_format )
						);
					}
				}
			}

			unset( $args['post_format'] );
		}

		// page template
		if ( ! empty( $args['page_template'] ) ) {
			foreach ( $args['page_template'] as $page_template ) {
				if ( array_key_exists( $page_template, $fields['page_template']['options'] ) ) {
					if ( $page_template === 'default' ) {
						$meta_queries['page_template'][] = array(
							'relation' => 'OR',
							array(
								'key' => '_wp_page_template',
								'value' => 'default'
							),
							array(
								'key' => '_wp_page_template',
								'value' => ''
							),
							array(
								'key' => '_wp_page_template',
								'compare' => 'NOT EXISTS'
							)
						);
					} else {
						$meta_queries['page_template'][] = array(
							'key' => '_wp_page_template',
							'value' => $page_template
						);
					}
				}
			}
		}

		// post author
		if ( ! empty( $args['post_author'] ) ) {
			foreach ( $args['post_author'] as $post_author ) {
				if ( array_key_exists( $post_author, $fields['post_author']['options'] ) )
					$args['author__in'][] = $post_author;
			}
		}

		// page parent
		if ( ! empty( $args['page_parent'] ) ) {
			foreach ( $args['page_parent'] as $page_parent ) {
				if ( array_key_exists( $page_parent, $fields['page_parent']['options'] ) )
					$args['post_parent__in'][] = $page_parent;
			}
		}

		// post term
		if ( ! empty( $args['post_term'] ) ) {
			$terms = [];

			// get all terms
			if ( ! empty( $fields['post_term']['options'] ) ) {
				foreach ( $fields['post_term']['options'] as $tax => $data ) {
					$terms = array_merge( $terms, array_map( 'intval', array_keys( $data['terms'] ) ) );
				}
			}

			foreach ( $args['post_term'] as $post_term ) {
				if ( in_array( $post_term, $terms ) ) {
					$term = get_term( $post_term );

					$tax_queries['post_term'][] = array(
						'taxonomy' => $term->taxonomy,
						'field' => 'term_id',
						'terms' => (int) $post_term
					);
				}
			}
		}

		switch ( $args['image_source'] ) {
			case 'thumbnails':
				$meta_queries['image_source'][] = array(
					'relation' => 'OR',
					array(
						'key' => '_thumbnail_id',
						'compare' => 'EXISTS'
					)
				);
		}

		// any tax queries?
		if ( ! empty( $tax_queries['post_term'] ) || ! empty( $tax_queries['post_format'] ) ) {
			$args['tax_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $tax_queries['post_term'] ) )
				$args['tax_query'][] = array( 'relation' => 'OR' ) + $tax_queries['post_term'];

			if ( ! empty( $tax_queries['post_format'] ) )
				$args['tax_query'][] = array( 'relation' => 'OR' ) + $tax_queries['post_format'];
		}

		// any tax queries?
		if ( ! empty( $meta_queries['page_template'] ) || ! empty( $meta_queries['image_source'] ) ) {
			$args['meta_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $meta_queries['page_template'] ) )
				$args['meta_query'][] = array( 'relation' => 'OR', $meta_queries['page_template'] );

			if ( ! empty( $meta_queries['image_source'] ) )
				$args['meta_query'][] = array( 'relation' => 'OR', $meta_queries['image_source'] );
		}

		// get posts
		$query = new WP_Query( apply_filters( 'rl_gallery_query_args', $args ) );

		// get attachments
		if ( $query->have_posts() )
			$attachments = $this->get_gallery_query_attachments( $query->posts, $args );

		return $attachments;
	}

	/**
	 * Get query attachments.
	 *
	 * @param array $posts Post IDs, array or objects
	 * @param array $args Additional arguments
	 * @return array
	 */
	public function get_gallery_query_attachments( $posts, $args ) {
		$attachments = [];

		// any posts?
		if ( ! empty( $posts ) ) {
			switch ( $args['image_source'] ) {
				case 'thumbnails':
					$nop = count( $posts ) - 1;

					foreach ( $posts as $number => $post_id ) {
						$attachment_id = (int) get_post_thumbnail_id( $post_id );

						// real attachment?
						if ( wp_attachment_is_image( $attachment_id ) )
							$attachments[] = $attachment_id;
						else
							continue;

						if ( $args['preview'] ) {
							$attachments = array_unique( $attachments );
							$noa = count( $attachments );

							if ( ( $noa >= ( $args['preview_per_page'] * $args['preview_page'] ) ) || $nop === $number ) {
								$attachments = array_slice( $attachments, ( $args['preview_page'] - 1 ) * $args['preview_per_page'], $args['preview_per_page'], false );

								break;
							}
						}
					}
					break;

				case 'attached_images':
					$nop = count( $posts ) - 1;

					foreach ( $posts as $number => $post_id ) {
						// get attached images, do not use get_attached_media here!
						$attachment_ids = (array) get_children(
							array(
								'post_parent' => $post_id,
								'post_status' => 'inherit',
								'post_type' => 'attachment',
								'post_mime_type' => 'image',
								'posts_per_page' => $args['images_per_post'],
								'order' => 'ASC',
								'orderby' => 'menu_order',
								'nopaging' => false,
								'page' => 1,
								'fields' => 'ids'
							)
						);

						if ( $attachment_ids ) {
							foreach ( $attachment_ids as $attachment_id ) {
								if ( ! empty( $attachment_id ) ) {
									$attachments[] = $attachment_id;
								}
							}
						}

						if ( $args['preview'] ) {
							$attachments = array_unique( $attachments );
							$noa = count( $attachments );

							if ( ( $noa >= ( $args['preview_per_page'] * $args['preview_page'] ) ) || $nop === $number ) {
								$attachments = array_slice( $attachments, ( $args['preview_page'] - 1 ) * $args['preview_per_page'], $args['preview_per_page'], false );

								break;
							}
						}
					}
			}
		}

		return apply_filters( 'rl_get_gallery_query_attachments', array_unique( $attachments ), $posts, $args );
	}

	/**
	 * Check attachments.
	 *
	 * @param array $attachments Attachment IDs
	 * @param array $args Additional arguments
	 * @return array
	 */
	public function check_attachments( $attachments, $args = [] ) {
		// no attachments?
		if ( empty( $attachments ) || ! is_array( $attachments ) )
			return [];

		// check providers support
		if ( ! empty( $args['providers'] ) )
			$embed = rl_current_lightbox_supports( $args['providers'], 'OR' );
		else
			$embed = false;

		// no embed data?
		if ( ! $embed )
			$copy = array_map( 'intval', $attachments );
		else
			$copy = $attachments;

		// check attachments
		foreach ( $attachments as $key => $attachment_id ) {
			// embed?
			if ( $embed && preg_match( '/^e\d+$/', $attachment_id ) === 1 ) {
				if ( ! in_array( $attachment_id, $args['embed_keys'], true ) )
					unset( $copy[$key] );
			// video support?
			} elseif ( rl_current_lightbox_supports( 'video' ) ) {
				// is it an image or video?
				if ( ! wp_attachment_is( 'video', $attachment_id ) && ! wp_attachment_is( 'image', $attachment_id ) )
					unset( $copy[$key] );
				// make sure it's integer
				elseif ( $embed )
					$copy[$key] = (int) $copy[$key];
			} else {
				// is it an image?
				if ( ! wp_attachment_is_image( $attachment_id ) )
					unset( $copy[$key] );
				// make sure it's integer
				elseif ( $embed )
					$copy[$key] = (int) $copy[$key] ;
			}
		}

		return array_values( $copy );
	}
}
