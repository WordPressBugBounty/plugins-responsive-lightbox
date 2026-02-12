<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Gallery Preview & Revision Trait.
 *
 * Handles gallery preview and revision functionality.
 *
 * @trait Responsive_Lightbox_Gallery_Preview
 */
trait Responsive_Lightbox_Gallery_Preview {

	/**
	 * Save gallery revision metadata.
	 *
	 * @param int $revision_id
	 * @return void
	 */
	public function save_revision( $revision_id ) {
		// get revision
		$revision = get_post( $revision_id );

		// get gallery ID
		$post_id = $revision->post_parent;

		// is it rl gallery?
		if ( get_post_type( $post_id ) !== 'rl_gallery' )
			return;

		$this->revision_id = $revision_id;

		if ( ! wp_is_post_revision( $revision_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || empty( $_POST['rl_gallery'] ) )
			return;

		// save revisioned meta data
		$this->save_gallery( wp_unslash( $_POST ), $revision_id, true );
	}

	/**
	 * Update preview link.
	 *
	 * @param string $link Preview link
	 * @return string
	 */
	public function preview_post_link( $link ) {
		// add gallery revision id
		if ( property_exists( $this, 'revision_id' ) && ! is_null( $this->revision_id ) ) {
			$post_id = wp_get_post_parent_id( $this->revision_id );

			// is it valid rl_gallery post?
			if ( $post_id && get_post_type( $post_id ) === 'rl_gallery' )
				return add_query_arg( 'rl_gallery_revision_id', $this->revision_id, $link );
		}

		return $link;
	}

	/**
	 * Delete gallery revision at shutdown.
	 *
	 * @global object $post
	 *
	 * @return void
	 */
	public function shutdown_preview() {
		// is it a frontend preview?
		if ( is_preview() && isset( $_GET['rl_gallery_revision_id'] ) ) {
			global $post;

			// cast revision ID
			$revision_id = (int) $_GET['rl_gallery_revision_id'];

			// is it a valid revision?
			if ( get_post_type( $post->ID ) === 'rl_gallery' && wp_is_post_revision( $revision_id ) === (int) $post->ID )
				wp_delete_post_revision( $revision_id );
		}
	}

	/**
	 * Filter gallery meta data needed for frontend gallery preview.
	 *
	 * @param mixed $value Meta value to filter
	 * @param int $object_id
	 * @param string $meta_key Meta key to filter a value for
	 * @param bool $single Whether to return a single value
	 * @return mixed
	 */
	public function filter_preview_metadata( $value, $object_id, $meta_key, $single ) {
		// ignore other post types
		if ( get_post_type( $object_id ) !== 'rl_gallery' )
			return $value;

		// get current post
		$post = get_post();

		// prepare keys
		$keys = array( '_rl_featured_image_type', '_rl_featured_image', '_rl_images_count', '_thumbnail_id' );

		// add other metakeys
		foreach ( array_keys( $this->tabs ) as $key ) {
			$keys[] = '_rl_' . $key;
		}

		// restrict only to specified data
		if ( empty( $post ) || (int) $post->ID !== (int) $object_id || ! in_array( $meta_key, $keys, true ) || $post->post_type === 'revision' )
			return $value;

		// grab the last autosave
		$preview = wp_get_post_autosave( $post->ID );

		// invalid revision?
		if ( ! is_object( $preview ) )
			return $value;

		// finally replace metadata
		return array( get_post_meta( $preview->ID, $meta_key, $single ) );
	}
}
