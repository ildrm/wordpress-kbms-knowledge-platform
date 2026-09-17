<?php

declare(strict_types=1);

namespace KBMS\Relations;

final class RelationshipTypes {

	/** @var array<string, string> */
	private const INVERSES = array(
		'related_to'      => 'related_to',
		'depends_on'      => 'required_by',
		'required_by'     => 'depends_on',
		'explains'        => 'explained_by',
		'explained_by'    => 'explains',
		'implements'      => 'implemented_by',
		'implemented_by'  => 'implements',
		'supersedes'      => 'superseded_by',
		'superseded_by'   => 'supersedes',
		'contradicts'     => 'contradicts',
		'resolves'        => 'resolved_by',
		'resolved_by'     => 'resolves',
		'troubleshoots'   => 'troubleshot_by',
		'troubleshot_by'  => 'troubleshoots',
		'prerequisite_of' => 'requires',
		'requires'        => 'prerequisite_of',
		'followed_by'     => 'follows',
		'follows'         => 'followed_by',
		'references'      => 'referenced_by',
		'referenced_by'   => 'references',
		'part_of'         => 'contains',
		'contains'        => 'part_of',
		'alternative_to'  => 'alternative_to',
		'caused_by'       => 'causes',
		'causes'          => 'caused_by',
		'owned_by'        => 'owns',
		'owns'            => 'owned_by',
		'expert_for'      => 'has_expert',
		'has_expert'      => 'expert_for',
	);

	/** @var list<string> */
	private const ACYCLIC = array( 'depends_on', 'required_by', 'supersedes', 'superseded_by', 'prerequisite_of', 'requires', 'part_of', 'contains', 'followed_by', 'follows' );

	/** @return list<string> */
	public static function all(): array {
		return array_keys( self::INVERSES );
	}

	public static function valid( string $type ): bool {
		return isset( self::INVERSES[ $type ] );
	}

	public static function inverse( string $type ): string {
		return self::INVERSES[ $type ] ?? '';
	}

	public static function isAcyclic( string $type ): bool {
		return in_array( $type, self::ACYCLIC, true );
	}
}
