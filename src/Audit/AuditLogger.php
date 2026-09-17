<?php

declare(strict_types=1);

namespace KBMS\Audit;

use wpdb;

final class AuditLogger {

	private const REDACTED_KEYS = array(
		'password',
		'pass',
		'secret',
		'token',
		'api_key',
		'apikey',
		'authorization',
		'cookie',
		'session',
		'context',
		'prompt',
		'private_context',
	);

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/** @param array<string,mixed> $context */
	public function record(
		string $eventType,
		string $objectType,
		string $objectId,
		array $context = array(),
		?int $actorId = null,
		?string $correlationId = null
	): bool {
		$eventType  = substr( sanitize_key( $eventType ), 0, 100 );
		$objectType = substr( sanitize_key( $objectType ), 0, 64 );
		$objectId   = substr( sanitize_text_field( $objectId ), 0, 191 );
		if ( $eventType === '' || $objectType === '' || $objectId === '' ) {
			return false;
		}

		$encoded = wp_json_encode( $this->redact( $context ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ipHash  = $ip !== '' ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : null;

		return $this->db->insert(
			$this->db->prefix . 'kbms_audit_log',
			array(
				'occurred_at'    => current_time( 'mysql', true ),
				'actor_id'       => $actorId ?? get_current_user_id(),
				'event_type'     => $eventType,
				'object_type'    => $objectType,
				'object_id'      => $objectId,
				'ip_hash'        => $ipHash,
				'correlation_id' => $correlationId !== null ? substr( sanitize_text_field( $correlationId ), 0, 64 ) : null,
				'context'        => is_string( $encoded ) ? $encoded : '{}',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		) === 1;
	}

	/**
	 * Returns newest audit records. Capability checks belong at the controller boundary.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function recent( int $limit = 100, int $offset = 0 ): array {
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		$table  = $this->db->prefix . 'kbms_audit_log';
		$sql    = $this->db->prepare(
			"SELECT id,occurred_at,actor_id,event_type,object_type,object_id,correlation_id,context
             FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		);
		$rows   = $this->db->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** @param array<mixed> $data @return array<mixed> */
	private function redact( array $data ): array {
		$redacted = array();
		foreach ( $data as $key => $value ) {
			$normalized = strtolower( (string) $key );
			$sensitive  = false;
			foreach ( self::REDACTED_KEYS as $needle ) {
				if ( $normalized === $needle || str_contains( $normalized, $needle ) ) {
					$sensitive = true;
					break;
				}
			}

			if ( $sensitive ) {
				$redacted[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$redacted[ $key ] = $this->redact( $value );
			} elseif ( is_scalar( $value ) || $value === null ) {
				$redacted[ $key ] = $value;
			} else {
				$redacted[ $key ] = sprintf( '[%s]', get_debug_type( $value ) );
			}
		}
		return $redacted;
	}
}
