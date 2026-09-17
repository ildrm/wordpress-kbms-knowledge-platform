# WordPress Knowledge Base & Knowledge Management System (KBMS)

KBMS 0.1.0 is a security-first WordPress knowledge-management foundation. It adds governed knowledge spaces, hierarchical knowledge items, workflow and verification services, permission-scoped search, typed relationships, bounded graph expansion, feedback, privacy controls, operational health checks, and an optional grounded AI pipeline.

This release is a production-oriented foundation, not a claim that every enterprise feature in the product backlog is complete. The exact implemented and deferred scope is recorded in [requirements traceability](docs/requirements-traceability.md).

## Requirements

- WordPress 6.5 or newer
- PHP 8.1 or newer
- MySQL 5.7+/MariaDB 10.4+ as supported by WordPress
- Pretty permalinks recommended, but not required

## Install

1. Upload `wp-kbms-0.1.0.zip` through **Plugins → Add New → Upload Plugin**, or copy this directory to `wp-content/plugins/wp-kbms`.
2. Activate **KBMS Knowledge Platform**. Activation creates versioned per-site tables and role capabilities; it never deletes existing content.
3. Open **Knowledge → Spaces** and create the first space.
4. Create a Knowledge Item, assign its space and owner in **Knowledge governance**, then publish it.
5. Add `[kbms_portal]` to a page for the theme-independent knowledge portal.

Network activation runs the migration independently on every existing site. New multisite sites are migrated automatically. Deactivation retains data. Uninstall deletes data only when `kbms_delete_data_on_uninstall` was explicitly set to true before uninstalling.

## Included in 0.1.0

- PSR-4 modular composition with no mandatory runtime dependencies
- custom capabilities and contributor/reviewer/manager roles
- public, internal, and private spaces with membership roles
- WordPress-native hierarchical knowledge, revisions, topics, and knowledge types
- queryable governance index for owner, reviewer, workflow, verification, sensitivity, language, review, expiry, and attributes
- optimistic workflow transitions and append-only audit records
- review expiry and notification scheduling
- permission constraints applied before native search retrieval and counts
- vendor-neutral search, LLM, embedding, vector-store, and reranker contracts
- grounded RAG with a permission recheck, bounded context, evidence threshold, controlled refusal, and citations
- typed relationships with duplicate and cycle protection; permission-filtered lazy graph API
- secured REST endpoints under `kbms/v1` for search, AI, graph, relationships, feedback, and health
- Gutenberg patterns, six knowledge templates, and recursion-bounded reusable variables
- responsive, RTL-safe portal and admin styling
- privacy exporter/eraser, retention batch, anonymous feedback throttling, and aggregate analytics without raw query storage
- SSRF URL policy, signed webhook verification, CSV formula neutralization, site health checks, and WP-CLI status commands

## Secure defaults

Content without a valid space is absent from public/search indexes. Public drafts are private. Private-space metadata is excluded before search, graph, REST, AI context, and export delivery. AI routes are disabled unless the feature flag is enabled and require `use_kbms_ai`. With no provider, the assistant returns a controlled refusal and ordinary search continues.

Provider plugins can register an LLM adapter:

```php
add_filter('kbms_llm_provider', function ($provider) {
    return new Acme\Knowledge\CompanyLlmProvider();
});
```

The adapter must implement `KBMS\AI\LLMProviderInterface`; secrets remain server-side.

## Development

```text
composer install
composer lint
composer test
```

The release runtime uses its own small autoloader, so `vendor/` is development-only and excluded from the ZIP. WordPress integration tests in `tests/CoreBackend` require the standard WordPress PHPUnit test environment and `WP_TESTS_DIR`; the default deterministic unit suite does not.

## Documentation

- [Architecture](docs/architecture.md)
- [Operations and user guide](docs/user-guide.md)
- [Developer, REST, hooks, and integration guide](docs/developer-guide.md)
- [Security, AI, and privacy](docs/security-privacy.md)
- [Database model](docs/data-model.md)
- [Permissions](docs/permissions-matrix.md)
- [Requirements traceability](docs/requirements-traceability.md)
- [Quality reviews and test report](docs/reviews.md)
- [Risks](docs/risk-register.md) and [edge cases](docs/edge-cases.md)
- [Changelog](CHANGELOG.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
