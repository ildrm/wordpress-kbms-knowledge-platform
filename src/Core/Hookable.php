<?php

declare(strict_types=1);

namespace KBMS\Core;

interface Hookable {

	public function registerHooks(): void;
}
