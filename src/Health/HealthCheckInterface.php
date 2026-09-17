<?php

declare(strict_types=1);

namespace KBMS\Health;

interface HealthCheckInterface {

	public function id(): string;
	public function run(): HealthCheckResult;
}
