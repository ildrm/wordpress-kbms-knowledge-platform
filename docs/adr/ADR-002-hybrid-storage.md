# ADR-002: Hybrid WordPress and dedicated-table storage

- Status: Accepted
- Date: 2026-09-17

## Context

Gutenberg, revisions, media, and ecosystem compatibility favor WordPress posts. High-volume ACLs, graph edges, workflows, jobs, audit, analytics, indexing, and connector mappings require explicit constraints and efficient indexed queries.

## Decision

Store authored knowledge and revisions as a custom post type. Store structured, relational, high-volume, security-sensitive, and operational data in versioned dedicated tables. Use typed metadata definitions/values for queryable attributes rather than arbitrary unindexed postmeta. Treat WordPress revision IDs as immutable content-version identities.

## Consequences

Native editing remains available while performance-critical access paths are controllable. Repositories must coordinate WordPress APIs and database transactions carefully, and migrations are a first-class subsystem. Duplicate sources of truth are forbidden: projections identify an authoritative entity and carry version hashes.

## Alternatives rejected

- Posts/postmeta only: poor constraint, join, analytics, ACL, and large-scale behavior.
- All custom tables: loses substantial Gutenberg/revision/media compatibility and ecosystem value.
