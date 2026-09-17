<?php

declare(strict_types=1);

namespace KBMS\Integrations;

final class WebhookVerifier {

	private const MAX_CLOCK_SKEW = 300;

	public function verify( string $body, string $timestamp, string $nonce, string $signature, string $secret ): bool {
		if ( $secret === '' || ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::MAX_CLOCK_SKEW ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{16,128}$/', $nonce ) ) {
			return false;
		}

		$replayKey = 'kbms_hook_' . hash( 'sha256', $nonce );
		if ( get_transient( $replayKey ) !== false ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $nonce . '.' . $body, $secret );
		if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
			return false;
		}
		set_transient( $replayKey, 1, self::MAX_CLOCK_SKEW * 2 );
		return true;
	}
}
