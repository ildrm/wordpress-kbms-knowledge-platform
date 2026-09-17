# ADR-006: Transactional outbox and idempotent background jobs

- Status: Proposed
- Date: 2026-09-17

## Context

Indexing, embeddings, imports, exports, notifications, analytics, link checks, Git sync, and purges cannot reliably run inside web requests. A database commit followed by an unrecorded queue failure creates silent divergence.

## Decision

Write minimal domain events to an outbox in the same transaction as authoritative state. Dispatch to a WordPress-compatible queue. Jobs use unique idempotency keys, atomic leases, bounded exponential retry, checkpoints, dead-letter state, cancellation where practical, and structured redacted errors. Handlers re-read feature and policy versions.

## Consequences

Work is at-least-once, so every external side effect needs reconciliation/idempotency. Operations gain visible lag and failure state. Cron remains a deployment risk and production guidance should recommend a real scheduler/worker.

## Alternatives rejected

- Fire-and-forget after commit: loses work.
- Run all secondary work synchronously: request timeouts and poor availability.
- Exactly-once claim: unrealistic across WordPress and external providers.
