<?php

declare(strict_types=1);

namespace KBMS\Notifications;

use KBMS\Core\Hookable;
use WP_Query;

final class ReviewNotifier implements Hookable {

	public function registerHooks(): void {
		add_action( 'kbms/run_review_notifications', array( $this, 'notify' ) );
	}

	public function notify(): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'kbms_item',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_kbms_review_at',
						'value'   => gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ),
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);
		foreach ( $query->posts as $itemId ) {
			$itemId  = (int) $itemId;
			$ownerId = absint( get_post_meta( $itemId, '_kbms_owner_id', true ) );
			$owner   = $ownerId > 0 ? get_userdata( $ownerId ) : false;
			if ( ! $owner || ! is_email( $owner->user_email ) ) {
				continue;
			}
			$dedupe = 'kbms_review_notice_' . $itemId . '_' . gmdate( 'Ymd' );
			if ( get_transient( $dedupe ) !== false ) {
				continue;
			}
			wp_mail(
				$owner->user_email,
				// translators: %s is the knowledge item title.
				sprintf( __( 'Knowledge review due: %s', 'wp-kbms' ), get_the_title( $itemId ) ),
				// translators: %s is the edit URL for the knowledge item.
				sprintf( __( 'Review and verify this knowledge item: %s', 'wp-kbms' ), get_edit_post_link( $itemId, 'raw' ) )
			);
			set_transient( $dedupe, 1, DAY_IN_SECONDS );
		}
	}
}
