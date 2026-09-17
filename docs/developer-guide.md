# Developer, REST, hooks, and integration guide

## Architecture

`KBMS\Core\Plugin` is the composition root. Runtime modules implement `KBMS\Core\Hookable`; domain ports are constructor-injected. The release uses a local PSR-4 loader and has no production Composer dependency. Storage is hybrid: WordPress posts/revisions/taxonomies for portable content and indexed custom tables for governed queries and append-only operational records.

## REST API

All custom routes use `kbms/v1`, argument schemas, validation/sanitization, explicit permission callbacks, stable JSON responses, and controlled errors.

| Method | Route | Purpose | Authorization |
|---|---|---|---|
| GET | `/search?q=…` | Permission-scoped native search | Anonymous public or authenticated scope |
| POST | `/ai/ask` | Grounded answer with citations | Authenticated + `use_kbms_ai` + AI enabled |
| GET | `/health` | Component status | `manage_kbms` |
| GET/POST | `/items/{id}/relationships` | Visible edges / create edge | Read item / edit item |
| GET | `/graph/{id}?limit=30` | Lazy neighbor expansion | Read root; each neighbor filtered |
| POST | `/items/{id}/feedback` | Helpfulness feedback | Read item; rate limited |

The native `wp/v2/kbms_item` endpoints remain available, with KBMS authorization applied to singular and collection queries. Attachment routes whose parent is knowledge receive the same resource check.

## Provider SDK

Implement these ports without leaking vendor SDK objects into domain code:

- `SearchProviderInterface::search(SearchQuery): SearchResult`
- `LLMProviderInterface::complete(CompletionRequest): CompletionResponse`
- `EmbeddingProviderInterface`
- `VectorStoreInterface`
- `RerankerInterface`

Register the active LLM through `kbms_llm_provider`. Providers must enforce TLS, keep credentials server-side, set timeouts, redact logs, and return only data described by the contracts. Search adapters must constrain authorization before retrieval and counts; post-filtering alone is invalid.

## Hooks

Important actions include `kbms_space_created`, `kbms_space_updated`, `kbms_space_membership_changed`, `kbms_space_membership_removed`, `kbms_knowledge_indexed`, `kbms_knowledge_verified`, `kbms_search_completed`, `kbms/run_review_notifications`, `kbms/prune_retained_data`, and `kbms/check_broken_links_batch`.

Filters include `kbms_llm_provider`, `kbms_search_provider`, `kbms_rag_pipeline`, and the fail-closed extension point `kbms_authorization_decision`. A custom authorization decision must return true only for an explicitly recognized action and resource context.

## Database and migration

`Migrator` runs ordered idempotent migrations during activation, admin initialization, and new-site initialization. The current schema version is `1`. Table definitions, indexes, retention, and relationships are documented in [data-model.md](data-model.md). Add future schema changes as a new `Migration`; never edit the meaning of an already deployed migration without a compatibility path.

## Test and build

Run `composer install`, `composer lint`, and `composer test`. The unit suite covers search scoping order, AI authorization revocation, prompt-injection separation, evidence refusal, reranker injection, workflow validation, relationship inverses, CSV injection, and health scoring. Run `tests/CoreBackend` inside the WordPress PHPUnit environment for database/concurrency authorization coverage.

Build a release by copying only `wp-kbms.php`, `uninstall.php`, `LICENSE`, `README.md`, `CHANGELOG.md`, `src/`, `assets/`, and `docs/` into a top-level `wp-kbms/` directory and zipping it. Never include `vendor`, tests, Git metadata, local configuration, logs, caches, or source maps.

