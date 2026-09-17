<?php

declare(strict_types=1);

namespace KBMS\ImportExport;

use KBMS\Permissions\AuthorizationInterface;
use WP_Post;

final class Exporter {

	public function __construct( private readonly AuthorizationInterface $authorization ) {
	}

	/** @param list<int> $ids */
	public function json( array $ids, int $userId ): string {
		$items = array();
		foreach ( array_values( array_unique( array_map( 'absint', $ids ) ) ) as $id ) {
			if ( ! $this->authorization->can( 'export', $id, $userId ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || $post->post_type !== 'kbms_item' ) {
				continue;
			}
			$items[] = array(
				'id'           => $id,
				'slug'         => $post->post_name,
				'title'        => $post->post_title,
				'content'      => $post->post_content,
				'status'       => $post->post_status,
				'modified_gmt' => $post->post_modified_gmt,
				'metadata'     => $this->safeMetadata( $id ),
			);
		}
		return (string) wp_json_encode(
			array(
				'schema'       => 'kbms-export/v1',
				'generated_at' => gmdate( DATE_ATOM ),
				'items'        => $items,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}

	/** @return array<string, scalar|array<array-key, scalar>|null> */
	private function safeMetadata( int $id ): array {
		$allowed = array( '_kbms_space_id', '_kbms_owner_id', '_kbms_reviewer_id', '_kbms_workflow_id', '_kbms_workflow_state', '_kbms_verification_status', '_kbms_review_at', '_kbms_verified_at', '_kbms_confidentiality', '_kbms_language', '_kbms_attributes' );
		$result  = array();
		foreach ( $allowed as $key ) {
			$result[ ltrim( $key, '_' ) ] = get_post_meta( $id, $key, true );
		}
		return $result;
	}

	public static function csvSafe( string $value ): string {
		return preg_match( '/^[=+\-@\t\r]/', $value ) === 1 ? "'" . $value : $value;
	}
}
