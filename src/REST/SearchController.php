<?php

declare(strict_types=1);

namespace KBMS\REST;

use KBMS\Search\SearchProviderInterface;
use KBMS\Search\SearchQuery;

final class SearchController {

	private $search;
	private $permissions;

	public function __construct( SearchProviderInterface $search, RoutePermissionInterface $permissions ) {
		$this->search      = $search;
		$this->permissions = $permissions;
	}

	public function register(): void {
		register_rest_route(
			'kbms/v1',
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
				'args'                => array(
					'q'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && '' !== trim( $value ) && strlen( $value ) <= 500; },
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
					'space'    => array(
						'type'  => 'array',
						'items' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'type'     => array(
						'type'  => 'array',
						'items' => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
					),
					'tag'      => array(
						'type'  => 'array',
						'items' => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
					),
					'verified' => array( 'type' => 'boolean' ),
				),
				'schema'              => array( $this, 'schema' ),
			)
		);
	}

	/** @param mixed $request @return bool|\WP_Error */
	public function authorize( $request ) {
		$userId = (int) get_current_user_id();
		if ( ! $this->permissions->canSearch( $userId ) ) {
			return new \WP_Error( 'kbms_rest_forbidden', __( 'You are not allowed to search this knowledge base.', 'wp-kbms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/** @param mixed $request @return mixed */
	public function handle( $request ) {
		$filters = array();
		foreach ( array( 'space', 'type', 'tag', 'verified' ) as $name ) {
			if ( $request->has_param( $name ) ) {
				$filters[ $name ] = $request->get_param( $name );
			}
		}

		try {
			$result = $this->search->search(
				new SearchQuery(
					(string) $request->get_param( 'q' ),
					(int) get_current_user_id(),
					(int) $request->get_param( 'page' ),
					(int) $request->get_param( 'per_page' ),
					$filters
				)
			);
			return rest_ensure_response( $result->toArray() );
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'kbms_invalid_search', __( 'The search request is invalid.', 'wp-kbms' ), array( 'status' => 400 ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'kbms_search_unavailable', __( 'Search is temporarily unavailable.', 'wp-kbms' ), array( 'status' => 503 ) );
		}
	}

	/** @return array<string, mixed> */
	public function schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'kbms_search_result',
			'type'       => 'object',
			'properties' => array(
				'items'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'total'    => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'page'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 50,
				),
			),
			'required'   => array( 'items', 'total', 'page', 'per_page' ),
		);
	}
}
