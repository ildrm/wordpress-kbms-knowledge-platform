<?php

declare(strict_types=1);

namespace KBMS\Knowledge;

use InvalidArgumentException;

final class Space {

	public const VISIBILITIES = array( 'public', 'internal', 'private' );

	public int $id;
	public string $name;
	public string $slug;
	public string $description;
	public string $visibility;
	public int $ownerId;

	/** @var array<string,mixed> */
	public array $settings;

	/** @param array<string,mixed> $settings */
	public function __construct(
		int $id,
		string $name,
		string $slug,
		string $description,
		string $visibility,
		int $ownerId,
		array $settings = array()
	) {
		if ( $name === '' || $slug === '' || ! in_array( $visibility, self::VISIBILITIES, true ) ) {
			throw new InvalidArgumentException( 'Invalid knowledge space.' );
		}
		$this->id          = $id;
		$this->name        = $name;
		$this->slug        = $slug;
		$this->description = $description;
		$this->visibility  = $visibility;
		$this->ownerId     = $ownerId;
		$this->settings    = $settings;
	}
}
