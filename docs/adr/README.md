# Architecture decision records

These records capture both accepted 0.1.0 decisions and proposed target-state decisions. ADR status values are Proposed, Accepted, Superseded, or Rejected; implementation must not silently diverge.

| ADR | Decision | Status |
|---|---|---|
| [ADR-001](ADR-001-modular-monolith.md) | WordPress modular monolith with ports/adapters | Proposed |
| [ADR-002](ADR-002-hybrid-storage.md) | CPT/revisions plus dedicated relational tables | Proposed |
| [ADR-003](ADR-003-central-authorization.md) | Central deny-by-default resource authorization | Proposed |
| [ADR-004](ADR-004-provider-abstractions.md) | Vendor-neutral search, AI, queue, and connector ports | Proposed |
| [ADR-005](ADR-005-revision-pinning.md) | Pin governance, citations, and derivatives to revisions | Proposed |
| [ADR-006](ADR-006-outbox-jobs.md) | Transactional outbox and idempotent background jobs | Proposed |
| [ADR-007](ADR-007-protected-attachments.md) | Authorized delivery for restricted attachments | Proposed |
| [ADR-008](ADR-008-versioned-api-events.md) | Version public APIs, workflows, schemas, and events | Proposed |
| [ADR-009](ADR-009-feature-flags-degradation.md) | Optional heavy modules and graceful degradation | Proposed |
| [ADR-010](ADR-010-multisite-isolation.md) | Default to site isolation; defer network knowledge | Proposed |

Every accepted decision must include implementation and test links. Security-relevant changes require threat-model and risk-register updates.
