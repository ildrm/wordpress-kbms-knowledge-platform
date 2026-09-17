# ADR-001: WordPress modular monolith with ports and adapters

- Status: Accepted
- Date: 2026-09-17

## Context

The brief spans tightly related WordPress editing, governance, search, AI, integrations, and delivery channels. Independent services would add deployment and consistency cost for small sites, while direct WordPress-global coupling would make policy and tests brittle.

## Decision

Ship one plugin as a modular monolith. Keep domain and application services independent of WordPress globals. Delivery, persistence, and vendor integrations are adapters behind explicit interfaces. Cross-module writes occur through application services; domain events decouple secondary work.

## Consequences

Small sites retain one deployable unit and transactions can protect core invariants. External search/vector/worker infrastructure can scale independently through ports. Module boundaries require dependency tests and disciplined registration; this does not promise future microservices.

## Alternatives rejected

- Microservices-first: excessive operational burden and poor fit for ordinary WordPress hosting.
- Conventional plugin with global functions and direct table access: insufficient isolation, testability, and authorization consistency.
