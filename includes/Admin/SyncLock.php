<?php

namespace CP_Sync\Admin;

use CP_Sync\Integrations\Integration;

/**
 * Per-post "prevent sync" lock control.
 *
 * Any post carrying a `_chms_id` meta ( the marker every CP Sync import gets,
 * regardless of ChMS or integration ) shows a checkbox on its edit screen that
 * lets an admin detach that single post from future syncs after customizing it.
 *
 * The lock is stored in the `Integration::LOCK_META_KEY` post meta and honored
 * on the sync side in Integration::task() ( no update ) and Integration::process()
 * ( no queue, no removal ). This class only manages the admin UI + meta write.
 *
 * @since 1.0.0
 */
class SyncLock {

	/**
	 * @var SyncLock
	 */
	protected static $_instance;

	/**
	 * Only make one instance of SyncLock
	 *
	 * @return SyncLock
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof SyncLock ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		$this->actions();
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	/**
	 * Add the lock meta box to any post that CP Sync imported.
	 *
	 * @param string   $post_type The current post type.
	 * @param \WP_Post $post      The current post.
	 * @return void
	 */
	public function register_meta_box( $post_type, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Only imported posts carry a `_chms_id`. Nothing to lock otherwise.
		if ( '' === (string) get_post_meta( $post->ID, '_chms_id', true ) ) {
			return;
		}

		add_meta_box(
			'cp_sync_lock',
			__( 'CP Sync', 'cp-sync' ),
			[ $this, 'render' ],
			$post_type,
			'side',
			'high'
		);
	}

	/**
	 * Render the lock checkbox.
	 *
	 * @param \WP_Post $post The current post.
	 * @return void
	 */
	public function render( $post ) {
		$locked = (bool) get_post_meta( $post->ID, Integration::LOCK_META_KEY, true );

		wp_nonce_field( 'cp_sync_lock', 'cp_sync_lock_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="cp_sync_lock" value="1" <?php checked( $locked ); ?> />
				<?php esc_html_e( 'Prevent sync from updating this post', 'cp-sync' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'When checked, CP Sync will not overwrite or remove this post, preserving any manual changes you make. Uncheck to resume syncing.', 'cp-sync' ); ?>
		</p>
		<?php
	}

	/**
	 * Persist the lock setting.
	 *
	 * Bails silently on autosave, on a missing/invalid nonce ( which is the case for
	 * every non-edit-screen write, including the sync's own wp_insert_post calls, so
	 * the sync can never clear a user's lock ), and without edit capability.
	 *
	 * @param int      $post_id The post id.
	 * @param \WP_Post $post    The post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (
			! isset( $_POST['cp_sync_lock_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['cp_sync_lock_nonce'] ), 'cp_sync_lock' )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only meaningful for imported posts.
		if ( '' === (string) get_post_meta( $post_id, '_chms_id', true ) ) {
			return;
		}

		if ( ! empty( $_POST['cp_sync_lock'] ) ) {
			update_post_meta( $post_id, Integration::LOCK_META_KEY, 1 );
		} else {
			delete_post_meta( $post_id, Integration::LOCK_META_KEY );
		}
	}
}
