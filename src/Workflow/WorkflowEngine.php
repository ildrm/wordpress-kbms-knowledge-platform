<?php

declare(strict_types=1);

namespace KBMS\Workflow;

use KBMS\Audit\AuditLogger;
use KBMS\Core\Hookable;
use KBMS\Permissions\AuthorizationInterface;
use WP_Error;

final class WorkflowEngine implements Hookable {

	private WorkflowRepository $repository;
	private AuthorizationInterface $authorization;
	private AuditLogger $audit;

	public function __construct( WorkflowRepository $repository, AuthorizationInterface $authorization, AuditLogger $audit ) {
		$this->repository    = $repository;
		$this->authorization = $authorization;
		$this->audit         = $audit;
	}

	public function registerHooks(): void {
		// Allows internal modules to request a transition through one guarded path.
		add_action( 'kbms_transition_item', array( $this, 'transition' ), 10, 5 );
	}

	/** @return true|WP_Error */
	public function transition( int $postId, int $workflowId, string $toState, int $actorId, string $comment = '' ) {
		$workflow = $this->repository->find( $workflowId );
		if ( $workflow === null ) {
			return new WP_Error( 'kbms_workflow_missing', __( 'Workflow not found or disabled.', 'wp-kbms' ), array( 'status' => 404 ) );
		}
		$current = $this->repository->currentState( $postId );
		if ( $current === null || ! $workflow->hasState( $toState ) ) {
			return new WP_Error( 'kbms_workflow_state_invalid', __( 'The workflow state is invalid.', 'wp-kbms' ), array( 'status' => 409 ) );
		}
		$transition = $workflow->transition( $current, $toState );
		if ( $transition === null ) {
			return new WP_Error( 'kbms_transition_forbidden', __( 'This workflow transition is not allowed.', 'wp-kbms' ), array( 'status' => 409 ) );
		}

		$action     = (string) ( $transition['action'] ?? 'edit' );
		$capability = (string) ( $transition['capability'] ?? 'edit_kbms_items' );
		if ( ! $this->authorization->can( $action, $postId, $actorId ) || ! user_can( $actorId, $capability ) ) {
			$this->audit->record(
				'workflow.denied',
				'knowledge',
				(string) $postId,
				array(
					'from' => $current,
					'to'   => $toState,
				),
				$actorId
			);
			return new WP_Error( 'kbms_transition_unauthorized', __( 'You are not allowed to perform this transition.', 'wp-kbms' ), array( 'status' => 403 ) );
		}

		if ( ! $this->repository->compareAndSwapState( $postId, $workflowId, $current, $toState, $actorId, $comment ) ) {
			return new WP_Error( 'kbms_transition_conflict', __( 'The item changed. Reload it and try again.', 'wp-kbms' ), array( 'status' => 409 ) );
		}

		$this->audit->record(
			'workflow.transitioned',
			'knowledge',
			(string) $postId,
			array(
				'workflow_id' => $workflowId,
				'from'        => $current,
				'to'          => $toState,
			),
			$actorId
		);
		do_action( 'kbms_workflow_transitioned', $postId, $workflowId, $current, $toState, $actorId );
		return true;
	}
}
