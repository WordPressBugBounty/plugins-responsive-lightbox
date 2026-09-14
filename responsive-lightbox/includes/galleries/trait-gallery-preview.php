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
	 * Return all bounded preview snapshots stored for this site.
	 *
	 * Snapshot records deliberately contain only parent, revision, user, token
	 * digest, and expiry data; unsaved gallery data remains revision metadata.
	 *
	 * @return array
	 */
	private function get_preview_snapshots() {
		$snapshots = get_option( self::PREVIEW_SNAPSHOTS_OPTION, [] );
		return is_array( $snapshots ) ? $snapshots : [];
	}

	/**
	 * Persist preview snapshots without autoloading request-scoped state.
	 *
	 * @param array $snapshots
	 * @return void
	 */
	private function update_preview_snapshots( $snapshots ) {
		if ( empty( $snapshots ) ) {
			delete_option( self::PREVIEW_SNAPSHOTS_OPTION );
			return;
		}

		update_option( self::PREVIEW_SNAPSHOTS_OPTION, $snapshots, false );
	}

	/**
	 * Derive a non-reversible binding for the signed preview URL.
	 *
	 * @param int $gallery_id
	 * @param int $revision_id
	 * @param int $user_id
	 * @param string $nonce
	 * @return string
	 */
	private function preview_snapshot_token( $gallery_id, $revision_id, $user_id, $nonce ) {
		return wp_hash( implode( '|', [ (int) $gallery_id, (int) $revision_id, (int) $user_id, $nonce ] ), 'responsive_lightbox_preview_snapshot' );
	}

	/**
	 * Start or refresh the bounded preview snapshot for an authorized request.
	 *
	 * @param int $gallery_id
	 * @param int $revision_id
	 * @param int $user_id
	 * @param string $nonce
	 * @return bool Whether the snapshot persisted and can be cleaned later.
	 */
	private function remember_preview_snapshot( $gallery_id, $revision_id, $user_id, $nonce ) {
		$token = $this->preview_snapshot_token( $gallery_id, $revision_id, $user_id, $nonce );
		$snapshots = $this->get_preview_snapshots();
		foreach ( $snapshots as $key => $snapshot ) {
			if ( is_array( $snapshot ) && isset( $snapshot['parent_gallery_id'], $snapshot['user_id'] ) && (int) $snapshot['parent_gallery_id'] === (int) $gallery_id && (int) $snapshot['user_id'] === (int) $user_id && $key !== $token ) {
				unset( $snapshots[$key] );
			}
		}

		$snapshots[$token] = [
			'parent_gallery_id' => (int) $gallery_id,
			'revision_id' => (int) $revision_id,
			'user_id' => (int) $user_id,
			'token' => $token,
			'created_at' => time(),
			'expires_at' => time() + self::PREVIEW_SNAPSHOT_TTL,
		];
		$this->update_preview_snapshots( $snapshots );

		$stored = $this->get_preview_snapshots();
		return isset( $stored[$token] ) && is_array( $stored[$token] ) && (int) $stored[$token]['revision_id'] === (int) $revision_id;
	}

	/**
	 * Confirm a pagination request still owns a live signed preview snapshot.
	 *
	 * @param int $gallery_id
	 * @param int $revision_id
	 * @param int $user_id
	 * @param string $nonce
	 * @return bool
	 */
	private function preview_snapshot_is_valid( $gallery_id, $revision_id, $user_id, $nonce ) {
		$key = $this->preview_snapshot_token( $gallery_id, $revision_id, $user_id, $nonce );
		$snapshots = $this->get_preview_snapshots();
		if ( ! isset( $snapshots[$key] ) || ! is_array( $snapshots[$key] ) )
			return false;

		$snapshot = $snapshots[$key];
		$expired = empty( $snapshot['expires_at'] ) || (int) $snapshot['expires_at'] <= time();
		$valid = ! $expired
			&& isset( $snapshot['parent_gallery_id'], $snapshot['revision_id'], $snapshot['user_id'], $snapshot['token'] )
			&& (int) $snapshot['parent_gallery_id'] === (int) $gallery_id
			&& (int) $snapshot['revision_id'] === (int) $revision_id
			&& (int) $snapshot['user_id'] === (int) $user_id
			&& wp_is_post_revision( $revision_id ) === (int) $gallery_id
			&& hash_equals( $snapshot['token'], $this->preview_snapshot_token( $gallery_id, $revision_id, $user_id, $nonce ) );

		if ( ! $valid && $expired ) {
			unset( $snapshots[$key] );
			$this->update_preview_snapshots( $snapshots );
		}

		return $valid;
	}

	/**
	 * Remove all authorization snapshots belonging to a gallery.
	 *
	 * @param int $gallery_id
	 * @return void
	 */
	public function clear_preview_snapshots_for_gallery( $gallery_id ) {
		$gallery_id = (int) $gallery_id;
		if ( $gallery_id <= 0 )
			return;

		$snapshots = $this->get_preview_snapshots();
		foreach ( $snapshots as $key => $snapshot ) {
			if ( is_array( $snapshot ) && isset( $snapshot['parent_gallery_id'] ) && (int) $snapshot['parent_gallery_id'] === $gallery_id )
				unset( $snapshots[$key] );
		}
		$this->update_preview_snapshots( $snapshots );
	}

	/**
	 * Invalidate snapshots when their parent gallery has been saved.
	 *
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update
	 * @return void
	 */
	public function clear_preview_snapshots_for_saved_gallery( $post_id, $post, $update ) {
		if ( is_object( $post ) && $post->post_type === 'rl_gallery' )
			$this->clear_preview_snapshots_for_gallery( $post_id );
	}

	/**
	 * Invalidate snapshots before a parent gallery is deleted.
	 *
	 * @param int $post_id
	 * @param object|null $post
	 * @return void
	 */
	public function clear_preview_snapshots_for_deleted_gallery( $post_id, $post = null ) {
		if ( is_object( $post ) && $post->post_type === 'rl_gallery' )
			$this->clear_preview_snapshots_for_gallery( $post_id );
	}

	/**
	 * Remove expired or orphaned authorization snapshots on normal request initialization.
	 *
	 * @return void
	 */
	public function sweep_expired_preview_snapshots() {
		$snapshots = $this->get_preview_snapshots();
		foreach ( $snapshots as $key => $snapshot ) {
			$parent_id = is_array( $snapshot ) && isset( $snapshot['parent_gallery_id'] ) ? (int) $snapshot['parent_gallery_id'] : 0;
			$revision_id = is_array( $snapshot ) && isset( $snapshot['revision_id'] ) ? (int) $snapshot['revision_id'] : 0;
			$expired = ! is_array( $snapshot ) || empty( $snapshot['expires_at'] ) || (int) $snapshot['expires_at'] <= time();
			if ( $expired || $parent_id <= 0 || $revision_id <= 0 || get_post_type( $parent_id ) !== 'rl_gallery' || wp_is_post_revision( $revision_id ) !== $parent_id ) {
				unset( $snapshots[$key] );
			}
		}
		$this->update_preview_snapshots( $snapshots );
	}

	/**
	 * Save gallery revision metadata.
	 *
	 * @param int $revision_id
	 * @return void
	 */
	public function save_revision( $revision_id ) {
		$revision = get_post( $revision_id );
		if ( ! is_object( $revision ) || ! wp_is_post_revision( $revision_id ) )
			return;

		$post_id = (int) wp_is_post_revision( $revision_id );
		if ( $post_id <= 0 || $post_id !== (int) $revision->post_parent || get_post_type( $post_id ) !== 'rl_gallery' )
			return;

		$this->revision_id = $revision_id;

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $this->gallery_save_request_is_valid( $post_id, get_post( $post_id ) ) )
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
	 * End a gallery preview request without acting on request-supplied revisions.
	 *
	 * @return void
	 */
	public function shutdown_preview() {
		// Snapshots are authorization records only. WordPress owns revision retention.
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

		// prepare keys
		$keys = array( '_rl_featured_image_type', '_rl_featured_image', '_rl_images_count', '_thumbnail_id' );

		// add other metakeys
		foreach ( array_keys( $this->tabs ) as $key ) {
			$keys[] = '_rl_' . $key;
		}

		$revision_id = isset( $this->preview_revision_id ) ? (int) $this->preview_revision_id : 0;

		// Restrict preview metadata to the already authorized signed revision.
		if ( ! in_array( $meta_key, $keys, true ) || $revision_id <= 0 || wp_is_post_revision( $revision_id ) !== (int) $object_id )
			return $value;

		// Revisions that predate gallery metadata must retain their parent value.
		if ( ! metadata_exists( 'post', $revision_id, $meta_key ) )
			return $value;

		// finally replace metadata
		return array( get_post_meta( $revision_id, $meta_key, $single ) );
	}
}
