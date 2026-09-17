<?php

declare(strict_types=1);

namespace KBMS\Graph;

use KBMS\Admin\Settings;
use KBMS\Core\Hookable;
use KBMS\Relations\RelationshipService;
use WP_REST_Request;
use WP_REST_Response;

final class Controller implements Hookable {

	public function __construct( private readonly RelationshipService $relationships ) {
	}

	public function registerHooks(): void {
		if ( Settings::get( 'graph_enabled' ) ) {
			add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		}
	}

	public function registerRoutes(): void {
		register_rest_route(
			'kbms/v1',
			'/graph/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'show' ),
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
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function show( WP_REST_Request $request ): WP_REST_Response {
		$rootId = (int) $request['id'];
		$edges  = $this->relationships->visibleNeighbors( $rootId, get_current_user_id(), (int) $request->get_param( 'limit' ) );
		$ids    = array( $rootId );
		foreach ( $edges as $edge ) {
			$ids[] = (int) $edge['source_id'];
			$ids[] = (int) $edge['target_id'];
		}
		$ids   = array_values( array_unique( $ids ) );
		$nodes = array_map(
			static fn ( int $id ): array => array(
				'id'    => $id,
				'label' => get_the_title( $id ),
				'url'   => get_permalink( $id ),
				'type'  => (string) get_post_meta( $id, '_kbms_type', true ),
			),
			$ids
		);

		return new WP_REST_Response(
			array(
				'root'      => $rootId,
				'nodes'     => $nodes,
				'edges'     => $edges,
				'truncated' => count( $edges ) >= (int) $request->get_param( 'limit' ),
			)
		);
	}
}
