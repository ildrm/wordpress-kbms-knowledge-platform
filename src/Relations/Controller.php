<?php

declare(strict_types=1);

namespace KBMS\Relations;

use KBMS\Core\Hookable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Controller implements Hookable {

	public function __construct( private readonly RelationshipService $service ) {
	}

	public function registerHooks(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		register_rest_route(
			'kbms/v1',
			'/items/(?P<id>\d+)/relationships',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => static fn ( WP_REST_Request $request ): bool => current_user_can( 'read_post', (int) $request['id'] ),
					'args'                => array(
						'id'    => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'required'          => true,
						),
						'limit' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 100,
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => static fn ( WP_REST_Request $request ): bool => current_user_can( 'edit_kbms_items' ) && current_user_can( 'edit_post', (int) $request['id'] ),
					'args'                => array(
						'id'        => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'required'          => true,
						),
						'target_id' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'required'          => true,
						),
						'type'      => array(
							'type'              => 'string',
							'enum'              => RelationshipTypes::all(),
							'sanitize_callback' => 'sanitize_key',
							'required'          => true,
						),
					),
				),
			)
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'items' => $this->service->visibleNeighbors( (int) $request['id'], get_current_user_id(), (int) $request->get_param( 'limit' ) ),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$this->service->add( (int) $request['id'], (int) $request['target_id'], (string) $request['type'], get_current_user_id() );
			return new WP_REST_Response( array( 'created' => true ), 201 );
		} catch ( \DomainException $error ) {
			return new WP_Error( 'kbms_invalid_relationship', $error->getMessage(), array( 'status' => 400 ) );
		}
	}
}
