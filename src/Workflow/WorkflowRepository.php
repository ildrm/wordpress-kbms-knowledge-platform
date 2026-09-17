<?php

declare(strict_types=1);

namespace KBMS\Workflow;

use DomainException;
use wpdb;

final class WorkflowRepository {

	private wpdb $db;

	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	public function find( int $id ): ?WorkflowDefinition {
		$table = $this->db->prefix . 'kbms_workflows';
		$row   = $this->db->get_row(
			$this->db->prepare(
				"SELECT id,initial_state,states,transitions FROM {$table} WHERE id = %d AND enabled = 1",
				$id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$states      = json_decode( (string) $row['states'], true );
		$transitions = json_decode( (string) $row['transitions'], true );
		if ( ! is_array( $states ) || ! is_array( $transitions ) ) {
			throw new DomainException( __( 'Stored workflow definition is invalid.', 'wp-kbms' ) );
		}
		return new WorkflowDefinition( (int) $row['id'], (string) $row['initial_state'], $states, $transitions );
	}

	/**
	 * @param array<string,array<string,mixed>> $states
	 * @param list<array<string,mixed>>         $transitions
	 */
	public function create( string $name, string $initialState, array $states, array $transitions, ?int $spaceId = null ): WorkflowDefinition {
		$name         = sanitize_text_field( $name );
		$initialState = sanitize_key( $initialState );
		if ( $name === '' ) {
			throw new DomainException( __( 'Workflow name is required.', 'wp-kbms' ) );
		}
		$cleanStates = array();
		foreach ( $states as $key => $configuration ) {
			$cleanStates[ sanitize_key( (string) $key ) ] = is_array( $configuration ) ? $configuration : array();
		}
		$cleanTransitions = array();
		foreach ( $transitions as $transition ) {
			if ( ! is_array( $transition ) ) {
				continue;
			}
			$cleanTransitions[] = array(
				'from'       => sanitize_key( (string) ( $transition['from'] ?? '' ) ),
				'to'         => sanitize_key( (string) ( $transition['to'] ?? '' ) ),
				'action'     => sanitize_key( (string) ( $transition['action'] ?? 'edit' ) ),
				'capability' => sanitize_key( (string) ( $transition['capability'] ?? 'edit_kbms_items' ) ),
			);
		}

		// Validate before persistence.
		new WorkflowDefinition( 1, $initialState, $cleanStates, $cleanTransitions );
		$now      = current_time( 'mysql', true );
		$inserted = $this->db->insert(
			$this->db->prefix . 'kbms_workflows',
			array(
				'space_id'      => $spaceId,
				'name'          => $name,
				'initial_state' => $initialState,
				'states'        => wp_json_encode( $cleanStates ),
				'transitions'   => wp_json_encode( $cleanTransitions ),
				'enabled'       => 1,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( $inserted !== 1 ) {
			throw new DomainException( __( 'Unable to create workflow.', 'wp-kbms' ) );
		}
		return new WorkflowDefinition( (int) $this->db->insert_id, $initialState, $cleanStates, $cleanTransitions );
	}

	public function currentState( int $postId ): ?string {
		$table = $this->db->prefix . 'kbms_knowledge_meta';
		$state = $this->db->get_var(
			$this->db->prepare(
				"SELECT workflow_state FROM {$table} WHERE post_id = %d",
				$postId
			)
		);
		return is_string( $state ) ? $state : null;
	}

	public function compareAndSwapState(
		int $postId,
		int $workflowId,
		string $from,
		string $to,
		int $actorId,
		string $comment
	): bool {
		$metaTable   = $this->db->prefix . 'kbms_knowledge_meta';
		$eventsTable = $this->db->prefix . 'kbms_workflow_events';
		$this->db->query( 'START TRANSACTION' );
		try {
			$updated = $this->db->query(
				$this->db->prepare(
					"UPDATE {$metaTable} SET workflow_state = %s, version = version + 1, updated_at = %s
                 WHERE post_id = %d AND workflow_id = %d AND workflow_state = %s",
					$to,
					current_time( 'mysql', true ),
					$postId,
					$workflowId,
					$from
				)
			);
			if ( $updated !== 1 ) {
				$this->db->query( 'ROLLBACK' );
				return false;
			}
			$inserted = $this->db->insert(
				$eventsTable,
				array(
					'post_id'     => $postId,
					'workflow_id' => $workflowId,
					'from_state'  => $from,
					'to_state'    => $to,
					'actor_id'    => $actorId,
					'comment'     => sanitize_textarea_field( $comment ),
					'created_at'  => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
			);
			if ( $inserted !== 1 ) {
				$this->db->query( 'ROLLBACK' );
				throw new DomainException( __( 'Unable to record workflow transition.', 'wp-kbms' ) );
			}
			$this->db->query( 'COMMIT' );
			update_post_meta( $postId, '_kbms_workflow_state', $to );
			return true;
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
