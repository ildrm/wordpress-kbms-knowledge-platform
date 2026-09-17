<?php

declare(strict_types=1);

namespace KBMS\Feedback;

use KBMS\Core\Hookable;
use KBMS\Permissions\AuthorizationInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use wpdb;

final class Controller implements Hookable {

	public function __construct( private readonly wpdb $db, private readonly AuthorizationInterface $authorization ) {
	}

	public function registerHooks(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		register_rest_route(
			'kbms/v1',
			'/items/(?P<id>\d+)/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => fn ( WP_REST_Request $request ): bool => $this->authorization->can( 'view', (int) $request['id'], get_current_user_id() ),
				'args'                => array(
					'id'      => array(
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'required'          => true,
					),
					'helpful' => array(
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
						'required'          => true,
					),
					'comment' => array(
						'type'              => 'string',
						'maxLength'         => 2000,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$itemId  = (int) $request['id'];
		$rateKey = 'kbms_feedback_' . hash_hmac( 'sha256', $this->clientKey() . '|' . $itemId, wp_salt( 'nonce' ) );
		if ( (int) get_transient( $rateKey ) >= 10 ) {
			return new WP_Error( 'kbms_rate_limited', __( 'Too many feedback submissions. Please try again later.', 'wp-kbms' ), array( 'status' => 429 ) );
		}
		$result = $this->db->insert(
			$this->db->prefix . 'kbms_feedback',
			array(
				'post_id'       => $itemId,
				'user_id'       => get_current_user_id(),
				'rating'        => rest_sanitize_boolean( $request['helpful'] ) ? 1 : 0,
				'feedback_type' => 'helpfulness',
				'comment'       => (string) $request['comment'],
				'status'        => 'open',
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( $result === false ) {
			return new WP_Error( 'kbms_feedback_failed', __( 'Feedback could not be saved.', 'wp-kbms' ), array( 'status' => 500 ) );
		}
		set_transient( $rateKey, ( (int) get_transient( $rateKey ) ) + 1, HOUR_IN_SECONDS );
		return new WP_REST_Response( array( 'created' => true ), 201 );
	}

	private function clientKey(): string {
		if ( get_current_user_id() > 0 ) {
			return 'user:' . get_current_user_id();
		}
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'anonymous:' . $address;
	}
}
