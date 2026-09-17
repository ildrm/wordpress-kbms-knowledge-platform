<?php

declare(strict_types=1);

namespace KBMS\Search;

use KBMS\Permissions\AuthorizationInterface;

final class WordPressSearchProvider implements SearchProviderInterface {

	private $authorization;
	private $postType;

	public function __construct( AuthorizationInterface $authorization, string $postType = 'kbms_item' ) {
		$this->authorization = $authorization;
		$this->postType      = $postType;
	}

	public function search( SearchQuery $query ): SearchResult {
		if ( ! class_exists( 'WP_Query' ) ) {
			throw new \RuntimeException( 'WordPress must be loaded before searching.' );
		}

		$args = array(
			'post_type'           => $this->postType,
			'post_status'         => 'publish',
			's'                   => $query->term(),
			'paged'               => $query->page(),
			'posts_per_page'      => $query->perPage(),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		$args                  = $this->applySupportedFilters( $args, $query->filters() );
		$args                  = $this->authorization->constrainQueryArgs( $args, $query->userId() );
		$fingerprint           = hash( 'sha256', serialize( array( $query->userId(), $args ) ) );
		$wpQuery               = new \WP_Query( $args );
		$hits                  = array();
		$rank                  = 0;
		$authorizationMismatch = false;

		foreach ( (array) $wpQuery->posts as $post ) {
			$postId = (int) $post->ID;
			if ( ! $this->authorization->can( 'read', $postId, $query->userId() ) ) {
				$authorizationMismatch = true;
				continue;
			}

			++$rank;
			$content = wp_strip_all_tags( (string) $post->post_content, true );
			$excerpt = has_excerpt( $postId ) ? (string) get_the_excerpt( $postId ) : wp_trim_words( $content, 55, '…' );
			$title   = wp_strip_all_tags( (string) get_the_title( $postId ), true );
			$excerpt = wp_strip_all_tags( $excerpt, true );
			$hits[]  = new SearchHit(
				$postId,
				$title,
				$excerpt,
				(string) get_permalink( $postId ),
				$this->relevance( $query->term(), $title, $excerpt, $rank ),
				array(
					'modified_gmt' => (string) $post->post_modified_gmt,
					'version'      => '',
					'verified'     => 'verified' === (string) get_post_meta( $postId, '_kbms_verification_status', true ),
				)
			);
		}

		// found_posts is safe to expose because constrain() was applied before WP_Query.
		$total = $authorizationMismatch ? count( $hits ) : (int) $wpQuery->found_posts;
		$result = new SearchResult( $hits, $total, $query->page(), $query->perPage(), $fingerprint );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'kbms_search_completed', $query->term(), $result );
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $filters
	 * @return array<string, mixed>
	 */
	private function applySupportedFilters( array $args, array $filters ): array {
		$taxQuery = array();
		foreach ( array(
			'type' => 'kbms_type',
			'tag'  => 'kbms_topic',
		) as $filter => $taxonomy ) {
			if ( ! isset( $filters[ $filter ] ) ) {
				continue;
			}
			$terms = array_filter( array_map( 'sanitize_title', (array) $filters[ $filter ] ) );
			if ( $terms ) {
				$taxQuery[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array_values( $terms ),
				);
			}
		}
		if ( $taxQuery ) {
			$args['tax_query'] = $taxQuery;
		}

		$metaQuery = array();
		if ( isset( $filters['space'] ) ) {
			$spaceIds = array_values( array_filter( array_map( 'absint', (array) $filters['space'] ) ) );
			if ( $spaceIds ) {
				$metaQuery[] = array(
					'key'     => '_kbms_space_id',
					'value'   => $spaceIds,
					'compare' => 'IN',
					'type'    => 'UNSIGNED',
				);
			}
		}
		if ( isset( $filters['verified'] ) ) {
			$metaQuery[] = array(
				'key'     => '_kbms_verification_status',
				'value'   => 'verified',
				'compare' => $filters['verified'] ? '=' : '!=',
			);
		}
		if ( $metaQuery ) {
			$args['meta_query'] = $metaQuery;
		}

		return $args;
	}

	private function relevance( string $term, string $title, string $excerpt, int $rank ): float {
		$lower  = static function ( string $value ): string {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		};
		$needle = $lower( $term );
		if ( str_contains( $lower( $title ), $needle ) ) {
			return 1.0;
		}
		if ( str_contains( $lower( $excerpt ), $needle ) ) {
			return 0.8;
		}
		// WordPress may match individual terms rather than the complete phrase.
		return max( 0.5, 0.7 - ( 0.02 * max( 0, $rank - 1 ) ) );
	}
}
