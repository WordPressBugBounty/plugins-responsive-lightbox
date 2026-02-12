<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Gallery Duplication Trait.
 *
 * Handles gallery duplication functionality.
 *
 * @trait Responsive_Lightbox_Gallery_Duplicate
 */
trait Responsive_Lightbox_Gallery_Duplicate {

	/**
	 * Duplicate gallery action in admin.
	 *
	 * @return void
	 */
	public function duplicate_gallery() {
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) ) || ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['rl_gallery_nonce'] ) || ( isset( $_REQUEST['rl_gallery_nonce'] ) && ! wp_verify_nonce( $_REQUEST['rl_gallery_nonce'], 'responsive-lightbox-duplicate-gallery' ) ) )
			wp_die( esc_html__( 'No gallery to duplicate has been supplied!', 'responsive-lightbox' ) );

		// get the original post
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_POST['post'] ) ? (int) $_POST['post'] : 0 );

		if ( empty( $post_id ) )
			wp_die( esc_html__( 'No gallery to duplicate has been supplied!', 'responsive-lightbox' ) );

		if ( ! current_user_can( 'edit_post', $post_id ) )
			wp_die( esc_html__( 'You do not have permission to copy this gallery.', 'responsive-lightbox' ) );

		$post = get_post( $post_id );

		// copy the post and insert it
		if ( isset( $post ) && $post !== null ) {
			$this->create_gallery_duplicate( $post );

			// redirect to the post list screen
			wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
			exit;
		} else
			wp_die( esc_html__( 'Copy creation failed, could not find original gallery:', 'responsive-lightbox' ) . ' ' . (int) $post_id );
	}

	/**
	 * Add duplicate link to gallery listing.
	 *
	 * @global string $pagenow
	 *
	 * @param array $actions Link actions
	 * @param object $post Post object
	 * @return array
	 */
	public function post_row_actions_duplicate( $actions, $post ) {
		global $pagenow;

		if ( $post->post_type !== 'rl_gallery' )
			return $actions;

		if ( ! current_user_can( 'edit_post', $post->ID ) )
			return $actions;

		// duplicate link
		$actions['duplicate_gallery'] = '<a class="duplicate-gallery" title="' . esc_attr__( 'Duplicate this item', 'responsive-lightbox' ) . '" href="' . esc_url( wp_nonce_url( admin_url( $pagenow . '?post=' . $post->ID . '&action=duplicate_gallery' ), 'responsive-lightbox-duplicate-gallery', 'rl_gallery_nonce' ) ) . '">' . esc_html__( 'Duplicate', 'responsive-lightbox' ) . '</a>';

		return $actions;
	}

	/**
	 * Create a gallery duplicate.
	 *
	 * @param $post object Post object
	 * @return void|int
	 */
	public function create_gallery_duplicate( $post ) {
		// skip revisions
		if ( $post->post_type === 'revision' )
			return;

		$new_post = apply_filters(
			'rl_duplicate_gallery_args',
			[
				'menu_order'	 => $post->menu_order,
				'comment_status' => $post->comment_status,
				'ping_status'	 => $post->ping_status,
				'post_author'	 => $post->post_author,
				'post_content'	 => $post->post_content,
				'post_excerpt'	 => $post->post_excerpt,
				'post_mime_type' => $post->post_mime_type,
				'post_parent'	 => $post->post_parent,
				'post_password'	 => $post->post_password,
				'post_status'	 => $post->post_status,
				'post_title'	 => $post->post_title,
				'post_type'		 => $post->post_type,
				'post_date'		 => current_time( 'mysql' ),
				'post_date_gmt'	 => get_gmt_from_date( current_time( 'mysql' ) )
			],
			$post
		);

		$new_post_id = wp_insert_post( $new_post );

		// if the copy is published or scheduled, we have to set a proper slug
		if ( $new_post['post_status'] === 'publish' || $new_post['post_status'] === 'future' ) {
			$post_name = wp_unique_post_slug( $post->post_name, $new_post_id, $new_post['post_status'], $post->post_type, $new_post['post_parent'] );

			$new_post = [];
			$new_post['ID'] = $new_post_id;
			$new_post['post_name'] = $post_name;

			// update the post into the database
			wp_update_post( $new_post );
		}

		// create metadata for the duplicated gallery
		$this->create_gallery_duplicate_metadata( $new_post_id, $post );

		// copy taxonomies
		$this->duplicate_gallery_taxonomies( $new_post_id, $post );

		// action hook for developers
		do_action( 'rl_after_duplicate_gallery', $new_post_id, $post );

		return $new_post_id;
	}

	/**
	 * Create a gallery duplicate metadata.
	 *
	 * @param int $new_post_id Post ID
	 * @param object $post Post object
	 * @return void
	 */
	public function create_gallery_duplicate_metadata( $new_post_id, $post ) {
		if ( empty( $post ) || $post == null )
			return;

		// meta keys to be copied
		$meta_keys = apply_filters( 'rl_duplicate_gallery_meta_keys', get_post_custom_keys( $post->ID ) );

		if ( empty( $meta_keys ) )
			return;

		foreach ( $meta_keys as $meta_key ) {
			// meta values to be copied
			$meta_values = apply_filters( 'rl_duplicate_gallery_meta_values', get_post_custom_values( $meta_key, $post->ID ) );

			foreach ( $meta_values as $meta_value ) {
				$meta_value = maybe_unserialize( $meta_value );

				// add metadata to duplicated post
				add_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Copy the taxonomies of a gallery to another gallery.
	 *
	 * @global object $wpdb
	 *
	 * @param int $new_post_id Post ID
	 * @param object $post Post object
	 * @return void
	 */
	function duplicate_gallery_taxonomies( $new_post_id, $post ) {
		global $wpdb;

		if ( isset( $wpdb->terms ) ) {
			// clear default category
			wp_set_object_terms( $new_post_id, null, 'category' );

			// get gallery taxonomies
			$gallery_taxonomies = get_object_taxonomies( $post->post_type );

			if ( ! empty( $gallery_taxonomies ) ) {
				foreach ( $gallery_taxonomies as $taxonomy ) {
					$terms = [];

					// get taxonomy terms
					$post_terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'orderby' => 'term_order' ) );

					if ( ! empty( $post_terms ) ) {
						foreach ( $post_terms as $term ) {
							$terms[] = $term->slug;
						}
					}

					// copy taxonomy terms
					wp_set_object_terms( $new_post_id, $terms, $taxonomy );
				}
			}
		}
	}
}
