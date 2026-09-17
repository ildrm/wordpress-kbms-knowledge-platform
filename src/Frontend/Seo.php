<?php

declare(strict_types=1);

namespace KBMS\Frontend;

use KBMS\Core\Hookable;

final class Seo implements Hookable {

	public function registerHooks(): void {
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'structuredData' ), 20 );
	}

	/** @param array<string, bool|string> $robots @return array<string, bool|string> */
	public function robots( array $robots ): array {
		if ( is_singular( 'kbms_item' ) ) {
			$visibility = (string) get_post_meta( get_queried_object_id(), '_kbms_confidentiality', true );
			if ( $visibility !== '' && $visibility !== 'public' ) {
				$robots['noindex']  = true;
				$robots['nofollow'] = true;
			}
		}
		return $robots;
	}

	public function structuredData(): void {
		if ( ! is_singular( 'kbms_item' ) || (string) get_post_meta( get_queried_object_id(), '_kbms_confidentiality', true ) !== 'public' ) {
			return;
		}
		$id   = get_queried_object_id();
		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'TechArticle',
			'headline'         => get_the_title( $id ),
			'dateModified'     => get_post_modified_time( DATE_ATOM, true, $id ),
			'mainEntityOfPage' => get_permalink( $id ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
