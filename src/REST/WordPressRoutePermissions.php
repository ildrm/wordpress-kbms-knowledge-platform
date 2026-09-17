<?php

declare(strict_types=1);

namespace KBMS\REST;

final class WordPressRoutePermissions implements RoutePermissionInterface {

	/** @var callable */
	private $aiEnabled;

	/** @param callable|null $aiEnabled Returns whether the AI feature is enabled. */
	public function __construct( ?callable $aiEnabled = null ) {
		$this->aiEnabled = $aiEnabled ?? static function (): bool {
			$settings = (array) get_option( 'kbms_settings', array() );
			return ! empty( $settings['ai_enabled'] );
		};
	}

	public function canSearch( int $userId ): bool {
		// Anonymous search is safe because AuthorizationInterface constrains it to public posts.
		return 0 === $userId || user_can( $userId, 'read' );
	}

	public function canUseAssistant( int $userId ): bool {
		return $userId > 0 && user_can( $userId, 'use_kbms_ai' ) && (bool) call_user_func( $this->aiEnabled );
	}

	public function canViewHealth( int $userId ): bool {
		return $userId > 0 && user_can( $userId, 'manage_kbms' );
	}
}
