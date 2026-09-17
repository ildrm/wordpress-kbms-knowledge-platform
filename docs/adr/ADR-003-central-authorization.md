# ADR-003: Central deny-by-default resource authorization

- Status: Accepted
- Date: 2026-09-17

## Context

The same knowledge is exposed through UI, REST, search, graph, AI, files, exports, webhooks, and MCP. WordPress role checks alone cannot represent resource, team, confidentiality, ownership, or inheritance rules.

## Decision

Implement one resource-policy service using base action capabilities plus contextual ACL rules. Explicit deny wins, otherwise the most-specific grant wins, then inheritance, with default deny. All channels must call the service. Candidate retrieval is authorization-scoped and every result is authoritatively rechecked. Policy versions key caches and derived indexes.

## Consequences

Authorization semantics become explainable and testable. Query planning and cache invalidation are complex and release-blocking. Administrators do not receive an undocumented content bypass; any break-glass mechanism must be separately designed, temporary, and audited.

## Alternatives rejected

- Per-controller checks: guaranteed drift and bypass risk.
- Retrieve all then hide in presentation/LLM output: leaks data and side channels.
- Most-specific rule always wins: a child grant could defeat a security deny.
