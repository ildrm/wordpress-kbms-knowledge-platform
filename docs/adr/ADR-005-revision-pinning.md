# ADR-005: Pin governance, citations, and derived data to revisions

- Status: Proposed
- Date: 2026-09-17

## Context

Verification, workflow approval, translations, citations, embeddings, and exports become misleading if they refer only to a mutable knowledge item.

## Decision

Publishing selects an immutable WordPress revision. Review and verification events, translation currency, search documents/chunks, embeddings, AI citations, and export manifests record the exact revision and relevant policy/content hashes. Restoring old content creates a new revision.

## Consequences

Evidence is reproducible and edits cannot inherit approval silently. Storage and cleanup are more involved, and UI must distinguish item state from revision state. A citation may become unavailable after retention or access changes and must fail safely.

## Alternatives rejected

- Reference only current item ID: breaks auditability and can validate content never reviewed.
