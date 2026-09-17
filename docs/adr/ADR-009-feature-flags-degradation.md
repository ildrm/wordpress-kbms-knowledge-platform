# ADR-009: Feature flags and graceful degradation for optional modules

- Status: Accepted
- Date: 2026-09-17

## Context

AI, graph visualization, Git sync, MCP, external search, analytics, and advanced collaboration are costly or environment-dependent. Core knowledge must remain usable without them.

## Decision

Register optional routes, assets, jobs, hooks, and provider calls only when their feature flag and prerequisites are enabled. Core editing, governance, protected delivery, export, and lexical discovery remain vendor-independent. Provider failure uses bounded fallback: semantic→lexical, graph canvas→relationship list, connector failure→local content.

## Consequences

Sites can adopt progressively and failures have smaller blast radius. Every flag requires enable/disable/queued-work/migration tests. Disabling a feature must not erase source knowledge, and retained derived data follows explicit privacy/cleanup policy.

## Alternatives rejected

- Always initialize every subsystem: needless workload, attack surface, and dependency failures.
- Treat failure as transparent success: hides degraded confidence/capability from users.
