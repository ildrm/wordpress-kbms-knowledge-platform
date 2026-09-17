<?php

declare(strict_types=1);

namespace KBMS\Admin;

use KBMS\Core\Hookable;
use KBMS\Knowledge\SpaceRepository;
use WP_Post;

final class KnowledgeMetaBox implements Hookable {

	public function __construct( private readonly SpaceRepository $spaces ) {
	}

	public function registerHooks(): void {
		add_action( 'add_meta_boxes_kbms_item', array( $this, 'add' ) );
		add_action( 'save_post_kbms_item', array( $this, 'save' ), 10, 2 );
	}

	public function add(): void {
		add_meta_box( 'kbms-governance', __( 'Knowledge governance', 'wp-kbms' ), array( $this, 'render' ), 'kbms_item', 'side', 'high' );
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( 'kbms_save_governance_' . $post->ID, 'kbms_governance_nonce' );
		$currentSpace = absint( get_post_meta( $post->ID, '_kbms_space_id', true ) );
		$currentOwner = absint( get_post_meta( $post->ID, '_kbms_owner_id', true ) );
		$currentOwner = $currentOwner > 0 ? $currentOwner : (int) $post->post_author;
		?>
		<p><label for="kbms-space-id"><strong><?php echo esc_html__( 'Space', 'wp-kbms' ); ?></strong></label><br><select id="kbms-space-id" name="kbms_space_id" required><option value=""><?php echo esc_html__( 'Select a space', 'wp-kbms' ); ?></option>
		<?php
		foreach ( $this->spaces->all() as $space ) :
			?>
			<option value="<?php echo esc_attr( (string) $space->id ); ?>" <?php selected( $space->id, $currentSpace ); ?>><?php echo esc_html( $space->name ); ?></option><?php endforeach; ?></select></p>
		<p><label for="kbms-owner-id"><strong><?php echo esc_html__( 'Owner user ID', 'wp-kbms' ); ?></strong></label><br><input id="kbms-owner-id" name="kbms_owner_id" type="number" min="1" value="<?php echo esc_attr( (string) $currentOwner ); ?>"></p>
		<p><label for="kbms-confidentiality"><strong><?php echo esc_html__( 'Confidentiality', 'wp-kbms' ); ?></strong></label><br><select id="kbms-confidentiality" name="kbms_confidentiality">
		<?php
		$current = (string) get_post_meta( $post->ID, '_kbms_confidentiality', true ); foreach ( array( 'public', 'internal', 'confidential', 'restricted' ) as $level ) :
			?>
			<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $level, $current ); ?>><?php echo esc_html( ucfirst( $level ) ); ?></option><?php endforeach; ?></select></p>
		<p><label for="kbms-review-at"><strong><?php echo esc_html__( 'Review date', 'wp-kbms' ); ?></strong></label><br><input id="kbms-review-at" name="kbms_review_at" type="date" value="<?php echo esc_attr( substr( (string) get_post_meta( $post->ID, '_kbms_review_at', true ), 0, 10 ) ); ?>"></p>
		<?php
	}

	public function save( int $postId, WP_Post $post ): void {
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) || ! isset( $_POST['kbms_governance_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['kbms_governance_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'kbms_save_governance_' . $postId ) || ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}
		$spaceId = isset( $_POST['kbms_space_id'] ) ? absint( $_POST['kbms_space_id'] ) : 0;
		$ownerId = isset( $_POST['kbms_owner_id'] ) ? absint( $_POST['kbms_owner_id'] ) : (int) $post->post_author;
		if ( $spaceId > 0 && $this->spaces->find( $spaceId ) !== null ) {
			update_post_meta( $postId, '_kbms_space_id', $spaceId );
		}
		if ( $ownerId > 0 && get_userdata( $ownerId ) !== false ) {
			update_post_meta( $postId, '_kbms_owner_id', $ownerId );
		}
		$confidentiality = isset( $_POST['kbms_confidentiality'] ) ? sanitize_key( wp_unslash( $_POST['kbms_confidentiality'] ) ) : 'internal';
		if ( in_array( $confidentiality, array( 'public', 'internal', 'confidential', 'restricted' ), true ) ) {
			update_post_meta( $postId, '_kbms_confidentiality', $confidentiality );
		}
		$reviewAt = isset( $_POST['kbms_review_at'] ) ? sanitize_text_field( wp_unslash( $_POST['kbms_review_at'] ) ) : '';
		update_post_meta( $postId, '_kbms_review_at', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewAt ) === 1 ? $reviewAt . ' 00:00:00' : '' );
	}
}
