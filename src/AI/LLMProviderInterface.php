<?php

declare(strict_types=1);

namespace KBMS\AI;

interface LLMProviderInterface {

	public function complete( CompletionRequest $request ): CompletionResponse;
}
