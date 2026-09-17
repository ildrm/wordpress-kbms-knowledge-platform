<?php

declare(strict_types=1);

namespace KBMS\Content;

final class TemplateCatalog {

	/** @return array<string, array{name: string, sections: list<string>}> */
	public function all(): array {
		return array(
			'sop'             => array(
				'name'     => __( 'SOP', 'wp-kbms' ),
				'sections' => array( 'Purpose', 'Scope', 'Prerequisites', 'Responsibilities', 'Procedure', 'Validation', 'Rollback', 'Escalation', 'Owner', 'Revision history' ),
			),
			'troubleshooting' => array(
				'name'     => __( 'Troubleshooting', 'wp-kbms' ),
				'sections' => array( 'Symptoms', 'Environment', 'Likely cause', 'Diagnosis', 'Solution', 'Verification', 'Prevention', 'Related incidents' ),
			),
			'runbook'         => array(
				'name'     => __( 'Runbook', 'wp-kbms' ),
				'sections' => array( 'Trigger', 'Preconditions', 'Procedure', 'Verification', 'Recovery', 'Escalation' ),
			),
			'adr'             => array(
				'name'     => __( 'Architecture decision record', 'wp-kbms' ),
				'sections' => array( 'Context', 'Decision', 'Alternatives', 'Consequences', 'Date', 'Status', 'Owners' ),
			),
			'policy'          => array(
				'name'     => __( 'Policy', 'wp-kbms' ),
				'sections' => array( 'Purpose', 'Scope', 'Policy', 'Responsibilities', 'Exceptions', 'Enforcement', 'Review' ),
			),
			'incident'        => array(
				'name'     => __( 'Incident knowledge', 'wp-kbms' ),
				'sections' => array( 'Incident summary', 'Impact', 'Timeline', 'Root cause', 'Resolution', 'Prevention', 'Lessons learned' ),
			),
		);
	}

	public function blockMarkup( string $type ): string {
		$template = $this->all()[ sanitize_key( $type ) ] ?? null;
		if ( $template === null ) {
			return '';
		}

		return implode(
			"\n\n",
			array_map(
				static fn ( string $heading ): string => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' . "\n\n" . '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->',
				$template['sections']
			)
		);
	}
}
