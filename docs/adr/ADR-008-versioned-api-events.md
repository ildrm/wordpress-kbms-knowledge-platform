# ADR-008: Version public APIs, workflows, schemas, and events

- Status: Proposed
- Date: 2026-09-17

## Context

REST clients, MCP clients, hooks, connector mappings, workflow instances, knowledge types, and webhooks outlive individual deployments. In-place mutation creates ambiguous behavior and unsafe upgrades.

## Decision

REST uses an explicit major namespace. Webhook/domain event payloads carry schema version and stable event ID. Published workflow and knowledge-type schemas are immutable versions; active objects pin a version and use explicit migrations. PHP hooks/interfaces follow documented deprecation windows and breaking changes require migration notes.

## Consequences

Compatibility is deliberate and old instances remain explainable. Multiple versions may coexist and require adapters/migrations. Version numbers do not replace consumer contract tests.

## Alternatives rejected

- Mutable definitions and unversioned payloads: makes historical state and external consumers unreliable.
