<?php

declare(strict_types=1);

namespace KBMS\REST;

use KBMS\Health\HealthStatus;

final class HealthController {

	private $health;
	private $permissions;

	public function __construct( HealthStatus $health, RoutePermissionInterface $permissions ) {
		$this->health      = $health;
		$this->permissions = $permissions;
	}

	public function register(): void {
		register_rest_route(
			'kbms/v1',
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
				'args'                => array(),
				'schema'              => array( $this, 'schema' ),
			)
		);
	}

	/** @param mixed $request @return bool|\WP_Error */
	public function authorize( $request ) {
		if ( ! $this->permissions->canViewHealth( (int) get_current_user_id() ) ) {
			return new \WP_Error( 'kbms_health_forbidden', __( 'You are not allowed to view system health.', 'wp-kbms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/** @param mixed $request @return mixed */
	public function handle( $request ) {
		return rest_ensure_response( $this->health->summary() );
	}

	/** @return array<string, mixed> */
	public function schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'kbms_health',
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type' => 'string',
					'enum' => array( 'good', 'recommended', 'critical' ),
				),
				'checks' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'status', 'checks' ),
		);
	}
}
