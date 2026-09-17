# ADR-010: Default to WordPress site isolation

- Status: Accepted
- Date: 2026-09-17

## Context

WordPress multisite can use per-site tables or network-global data. A network knowledge graph introduces tenant-boundary, lifecycle, role, URL, and export complexity not specified sufficiently by the brief.

## Decision

The first implementation must isolate each site's knowledge, policies, caches, indexes, credentials, and jobs using per-site tables or an unavoidable `site_id` repository constraint. Network activation may install per-site schemas, but cross-site search/sharing is not implied. A future network knowledge feature requires a separate ADR and threat model.

## Consequences

Multisite compatibility is achievable without accidental cross-blog disclosure. Network-wide administration and sharing are deferred honestly. Tests must switch blogs and attempt ID/cache/index collisions.

## Alternatives rejected

- Network-global tables without mandatory site scope: high-severity data isolation risk.
- Declare multisite unsupported without evaluation: conflicts with the compatibility objective.
