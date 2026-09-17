<?php

declare(strict_types=1);

namespace KBMS\Content;

use KBMS\Core\Hookable;

final class ReusableComponents implements Hookable {

	private const OPTION    = 'kbms_variables';
	private const MAX_DEPTH = 5;

	public function registerHooks(): void {
		add_filter( 'the_content', array( $this, 'render' ), 12 );
	}

	public function render( string $content ): string {
		if ( get_post_type() !== 'kbms_item' || strpos( $content, '{{' ) === false ) {
			return $content;
		}
		return $this->expand( $content, (array) get_option( self::OPTION, array() ), 0, array() );
	}

	/** @param array<string, mixed> $variables @param list<string> $stack */
	public function expand( string $content, array $variables, int $depth = 0, array $stack = array() ): string {
		if ( $depth >= self::MAX_DEPTH ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/\{\{([a-z][a-z0-9_]*)\}\}/i',
			function ( array $match ) use ( $variables, $depth, $stack ): string {
				$key = strtolower( $match[1] );
				if ( in_array( $key, $stack, true ) || ! array_key_exists( $key, $variables ) ) {
					return $match[0];
				}
				$value = wp_kses_post( (string) $variables[ $key ] );
				return $this->expand( $value, $variables, $depth + 1, array( ...$stack, $key ) );
			},
			$content
		);
	}
}
