<?php

declare(strict_types=1);

namespace KBMS\AI;

final class CompletionRequest {

	private $systemInstruction;
	private $question;
	/** @var array<int, array<string, mixed>> */
	private $sources;
	private $maxTokens;

	/** @param array<int, array<string, mixed>> $sources */
	public function __construct( string $systemInstruction, string $question, array $sources, int $maxTokens ) {
		$this->systemInstruction = $systemInstruction;
		$this->question          = $question;
		$this->sources           = $sources;
		$this->maxTokens         = min( 4096, max( 64, $maxTokens ) );
	}

	public function systemInstruction(): string {
		return $this->systemInstruction; }
	public function question(): string {
		return $this->question; }
	/** @return array<int, array<string, mixed>> */
	public function sources(): array {
		return $this->sources; }
	public function maxTokens(): int {
		return $this->maxTokens; }
}
