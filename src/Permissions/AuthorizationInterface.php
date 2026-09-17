<?php

declare(strict_types=1);

namespace KBMS\Permissions;

interface AuthorizationInterface {

	public function can( string $action, int $resourceId, int $userId ): bool;

	/**
	 * Constrains WP_Query-compatible arguments before retrieval.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function constrainQueryArgs( array $args, int $userId ): array;
}
