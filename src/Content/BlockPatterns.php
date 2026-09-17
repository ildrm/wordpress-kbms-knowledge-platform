<?php

declare(strict_types=1);

namespace KBMS\Content;

use KBMS\Core\Hookable;

final class BlockPatterns implements Hookable {

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'registerPatterns' ) );
	}

	public function registerPatterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		register_block_pattern_category( 'kbms', array( 'label' => __( 'Knowledge components', 'wp-kbms' ) ) );
		register_block_pattern(
			'kbms/warning',
			array(
				'title'      => __( 'Knowledge warning', 'wp-kbms' ),
				'categories' => array( 'kbms' ),
				'content'    => '<!-- wp:group {"className":"kbms-callout kbms-callout--warning","layout":{"type":"constrained"}} --><div class="wp-block-group kbms-callout kbms-callout--warning"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'Warning', 'wp-kbms' ) . '</h3><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Describe the risk and the safe action.', 'wp-kbms' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
			)
		);
		register_block_pattern(
			'kbms/procedure',
			array(
				'title'      => __( 'Step-by-step procedure', 'wp-kbms' ),
				'categories' => array( 'kbms' ),
				'content'    => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Procedure', 'wp-kbms' ) . '</h2><!-- /wp:heading --><!-- wp:list {"ordered":true} --><ol><!-- wp:list-item --><li>' . esc_html__( 'First action', 'wp-kbms' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Verification', 'wp-kbms' ) . '</li><!-- /wp:list-item --></ol><!-- /wp:list -->',
			)
		);
	}
}
