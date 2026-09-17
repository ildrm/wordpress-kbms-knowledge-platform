<?php

declare(strict_types=1);

namespace KBMS\REST;

use KBMS\AI\GroundedRagPipeline;

final class AssistantController {

	private $pipeline;
	private $permissions;

	public function __construct( GroundedRagPipeline $pipeline, RoutePermissionInterface $permissions ) {
		$this->pipeline    = $pipeline;
		$this->permissions = $permissions;
	}

	public function register(): void {
		register_rest_route(
			'kbms/v1',
			'/ai/ask',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
				'args'                => array(
					'question' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && '' !== trim( $value ) && strlen( $value ) <= 1000; },
					),
				),
				'schema'              => array( $this, 'schema' ),
			)
		);
	}

	/** @param mixed $request @return bool|\WP_Error */
	public function authorize( $request ) {
		$userId = (int) get_current_user_id();
		if ( 0 === $userId || ! $this->permissions->canUseAssistant( $userId ) ) {
			return new \WP_Error( 'kbms_ai_forbidden', __( 'You are not allowed to use the knowledge assistant.', 'wp-kbms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/** @param mixed $request @return mixed */
	public function handle( $request ) {
		try {
			return rest_ensure_response(
				$this->pipeline->ask(
					(string) $request->get_param( 'question' ),
					(int) get_current_user_id()
				)->toArray()
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'kbms_ai_unavailable', __( 'The knowledge assistant is temporarily unavailable.', 'wp-kbms' ), array( 'status' => 503 ) );
		}
	}

	/** @return array<string, mixed> */
	public function schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'kbms_grounded_answer',
			'type'       => 'object',
			'properties' => array(
				'answer'     => array( 'type' => 'string' ),
				'citations'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'confidence' => array(
					'type'    => 'number',
					'minimum' => 0,
					'maximum' => 1,
				),
				'refused'    => array( 'type' => 'boolean' ),
				'reason'     => array( 'type' => 'string' ),
			),
			'required'   => array( 'answer', 'citations', 'confidence', 'refused', 'reason' ),
		);
	}
}
