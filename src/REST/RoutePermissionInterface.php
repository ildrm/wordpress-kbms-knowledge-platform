<?php

declare(strict_types=1);

namespace KBMS\REST;

interface RoutePermissionInterface {

	public function canSearch( int $userId ): bool;
	public function canUseAssistant( int $userId ): bool;
	public function canViewHealth( int $userId ): bool;
}
