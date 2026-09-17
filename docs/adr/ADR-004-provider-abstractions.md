# ADR-004: Vendor-neutral provider abstractions

- Status: Accepted
- Date: 2026-09-17

## Context

Sites range from shared hosting with no external services to enterprise infrastructure. Search, embeddings, vectors, reranking, LLMs, queues, notifications, and connectors evolve independently and carry vendor/privacy constraints.

## Decision

Define narrow capability ports such as `SearchProvider`, `EmbeddingProvider`, `VectorStore`, `Reranker`, `LLMProvider`, `JobQueue`, `NotificationChannel`, and `Connector`. Application DTOs are vendor-neutral. Adapters declare capabilities and health; configuration validates required combinations. WordPress-native lexical search is the minimum functional fallback.

## Consequences

Knowledge remains portable and optional vendors can be replaced. Lowest-common-denominator interfaces are avoided by capability discovery and optional subinterfaces. Contract tests are mandatory for each adapter. An interface alone does not mean an adapter exists.

## Alternatives rejected

- Bind directly to one AI/search SDK: vendor lock-in and weak graceful degradation.
- Promise identical behavior across all adapters: technically misleading and suppresses useful provider capabilities.
