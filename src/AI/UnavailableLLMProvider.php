<?php

declare(strict_types=1);

namespace KBMS\AI;

final class UnavailableLLMProvider implements LLMProviderInterface {

	public function complete( CompletionRequest $request ): CompletionResponse {
		throw new \RuntimeException( 'No language model provider is configured.' );
	}
}
