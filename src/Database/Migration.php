<?php

declare(strict_types=1);

namespace KBMS\Database;

interface Migration {

	public function version(): int;

	public function up(): void;
}
