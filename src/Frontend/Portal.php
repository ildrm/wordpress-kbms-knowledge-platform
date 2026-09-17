<?php

declare(strict_types=1);

namespace KBMS\Frontend;

use KBMS\Core\Hookable;
use KBMS\Permissions\AuthorizationInterface;
use WP_Query;

final class Portal implements Hookable {

	public function __construct( private readonly AuthorizationInterface $authorization ) {
	}

	public function registerHooks(): void {
		add_shortcode( 'kbms_portal', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'appendKnowledgeDetails' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** @param array<string, mixed> $attributes */
	public function shortcode( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'limit' => 20,
				'space' => '',
			),
			$attributes,
			'kbms_portal'
		);
		$queryText  = isset( $_GET['kbms_q'] ) ? sanitize_text_field( wp_unslash( $_GET['kbms_q'] ) ) : '';
		$args       = array(
			'post_type'      => 'kbms_item',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 100, absint( $attributes['limit'] ) ) ),
			's'              => $queryText,
			'orderby'        => $queryText !== '' ? 'relevance' : 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => false,
		);
		if ( (string) $attributes['space'] !== '' ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'kbms_space',
					'field'    => 'slug',
					'terms'    => sanitize_title( (string) $attributes['space'] ),
				),
			);
		}
		$query = new WP_Query( $this->authorization->constrainQueryArgs( $args, get_current_user_id() ) );

		ob_start();
		?>
		<section class="kbms-portal" aria-labelledby="kbms-portal-title">
			<header class="kbms-portal__header">
				<h2 id="kbms-portal-title"><?php echo esc_html__( 'Knowledge center', 'wp-kbms' ); ?></h2>
				<form class="kbms-search" role="search" method="get">
					<label for="kbms-query"><?php echo esc_html__( 'Search knowledge', 'wp-kbms' ); ?></label>
					<div class="kbms-search__controls"><input id="kbms-query" type="search" name="kbms_q" value="<?php echo esc_attr( $queryText ); ?>"><button type="submit"><?php echo esc_html__( 'Search', 'wp-kbms' ); ?></button></div>
				</form>
			</header>
			<?php // translators: %d is the number of visible search results. ?>
			<p class="kbms-result-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d result', '%d results', (int) $query->found_posts, 'wp-kbms' ), (int) $query->found_posts ) ); ?></p>
			<?php if ( $query->have_posts() ) : ?>
				<ul class="kbms-results">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>
					<li class="kbms-result">
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p>
						<div class="kbms-result__meta">
							<?php
							$types = get_the_terms( get_the_ID(), 'kbms_type' ); if ( is_array( $types ) && $types !== array() ) :
								?>
								<span><?php echo esc_html( $types[0]->name ); ?></span><?php endif; ?>
							<?php
							if ( get_post_meta( get_the_ID(), '_kbms_verified_at', true ) ) :
								?>
								<span class="kbms-badge kbms-badge--verified"><?php echo esc_html__( 'Verified', 'wp-kbms' ); ?></span><?php endif; ?>
						</div>
					</li>
				<?php endwhile; ?>
				</ul>
			<?php else : ?>
				<div class="kbms-empty"><h3><?php echo esc_html__( 'No knowledge found', 'wp-kbms' ); ?></h3><p><?php echo esc_html__( 'Try a broader phrase or ask a knowledge owner to fill the gap.', 'wp-kbms' ); ?></p></div>
				<?php
			endif;
			wp_reset_postdata();
			?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public function appendKnowledgeDetails( string $content ): string {
		if ( ! is_singular( 'kbms_item' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id         = get_the_ID();
		$owner      = absint( get_post_meta( $id, '_kbms_owner_id', true ) );
		$reviewDate = (string) get_post_meta( $id, '_kbms_review_at', true );
		$verifiedAt = (string) get_post_meta( $id, '_kbms_verified_at', true );
		ob_start();
		?>
		<aside class="kbms-document-status" aria-label="<?php echo esc_attr__( 'Knowledge status', 'wp-kbms' ); ?>">
			<?php
			if ( $owner > 0 ) :
				?>
				<?php // translators: %s is the knowledge owner's display name. ?>
				<span><?php echo esc_html( sprintf( __( 'Owner: %s', 'wp-kbms' ), get_the_author_meta( 'display_name', $owner ) ) ); ?></span><?php endif; ?>
			<?php
			if ( $verifiedAt !== '' ) :
				?>
				<?php // translators: %s is the formatted verification date. ?>
				<span class="kbms-badge kbms-badge--verified"><?php echo esc_html( sprintf( __( 'Verified %s', 'wp-kbms' ), wp_date( get_option( 'date_format' ), strtotime( $verifiedAt ) ) ) ); ?></span><?php endif; ?>
			<?php
			if ( $reviewDate !== '' ) :
				?>
				<?php // translators: %s is the formatted review due date. ?>
				<span><?php echo esc_html( sprintf( __( 'Review due: %s', 'wp-kbms' ), wp_date( get_option( 'date_format' ), strtotime( $reviewDate ) ) ) ); ?></span><?php endif; ?>
		</aside>
		<section class="kbms-feedback" data-kbms-feedback data-item-id="<?php echo esc_attr( (string) $id ); ?>">
			<h2><?php echo esc_html__( 'Was this knowledge helpful?', 'wp-kbms' ); ?></h2>
			<button type="button" data-helpful="true"><?php echo esc_html__( 'Yes', 'wp-kbms' ); ?></button>
			<button type="button" data-helpful="false"><?php echo esc_html__( 'No', 'wp-kbms' ); ?></button>
			<p role="status" aria-live="polite"></p>
		</section>
		<?php
		return $content . (string) ob_get_clean();
	}

	public function enqueue(): void {
		if ( ! is_singular( 'kbms_item' ) && ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'kbms_portal' ) ) {
			return;
		}
		wp_enqueue_style( 'kbms-frontend', KBMS_URL . 'assets/css/frontend.css', array(), KBMS_VERSION );
		if ( is_singular( 'kbms_item' ) ) {
			wp_enqueue_script( 'kbms-feedback', KBMS_URL . 'assets/js/feedback.js', array(), KBMS_VERSION, true );
			wp_localize_script(
				'kbms-feedback',
				'kbmsFeedback',
				array(
					'root'    => esc_url_raw( rest_url( 'kbms/v1/items/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'success' => __( 'Thank you for improving this knowledge.', 'wp-kbms' ),
					'failure' => __( 'Feedback could not be sent. Please try again.', 'wp-kbms' ),
				)
			);
		}
	}
}
