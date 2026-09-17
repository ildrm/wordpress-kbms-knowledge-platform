<?php

declare(strict_types=1);

namespace KBMS\AI;

final class CompletionResponse {

	private $text;
	private $provider;
	private $model;

	public function __construct( string $text, string $provider = '', string $model = '' ) {
		$this->text     = trim( $text );
		$this->provider = $provider;
		$this->model    = $model;
	}

	public function text(): string {
		return $this->text; }
	public function provider(): string {
		return $this->provider; }
	public function model(): string {
		return $this->model; }
}
