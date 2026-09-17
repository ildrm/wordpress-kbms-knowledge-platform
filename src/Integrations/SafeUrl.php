<?php

declare(strict_types=1);

namespace KBMS\Integrations;

use InvalidArgumentException;

final class SafeUrl {

	/** @param list<string> $allowedHosts */
	public function assertAllowed( string $url, array $allowedHosts ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			throw new InvalidArgumentException( __( 'A valid absolute URL is required.', 'wp-kbms' ) );
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( ! in_array( $scheme, array( 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new InvalidArgumentException( __( 'Only HTTPS URLs without embedded credentials are allowed.', 'wp-kbms' ) );
		}
		if ( isset( $parts['port'] ) && (int) $parts['port'] !== 443 ) {
			throw new InvalidArgumentException( __( 'Non-standard destination ports are blocked.', 'wp-kbms' ) );
		}
		if ( ! in_array( $host, array_map( 'strtolower', $allowedHosts ), true ) ) {
			throw new InvalidArgumentException( __( 'The destination host is not allowlisted.', 'wp-kbms' ) );
		}

		$addresses = $this->resolve( $host );
		if ( $addresses === array() ) {
			throw new InvalidArgumentException( __( 'The destination host could not be resolved.', 'wp-kbms' ) );
		}
		foreach ( $addresses as $address ) {
			if ( filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
				throw new InvalidArgumentException( __( 'Private, loopback, link-local, and reserved destinations are blocked.', 'wp-kbms' ) );
			}
		}

		return esc_url_raw( $url, array( 'https' ) );
	}

	/** @return list<string> */
	private function resolve( string $host ): array {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}
		$records = dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( ! is_array( $records ) ) {
			return array();
		}
		$addresses = array();
		foreach ( $records as $record ) {
			if ( isset( $record['ip'] ) ) {
				$addresses[] = (string) $record['ip'];
			}
			if ( isset( $record['ipv6'] ) ) {
				$addresses[] = (string) $record['ipv6'];
			}
		}
		return array_values( array_unique( $addresses ) );
	}
}
