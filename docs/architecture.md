# Architecture overview

Status: **Target architecture with a shipped 0.1.0 foundation.** The system context and invariants remain the long-term design. The as-built release currently implements the modular composition root, hybrid storage, central authorization, native search provider, AI/provider ports and grounded pipeline, workflows, audit, relationships/graph API, portal/admin surfaces, maintenance, health, and privacy modules. Target-only components such as Git, MCP, full connectors, semantic adapters, and the interactive graph client are explicitly deferred in the RTM.

## Architectural goals

The platform is a modular monolith inside WordPress, with provider ports for capabilities that may move out of process. Native WordPress storage and lexical discovery form the always-available baseline. AI, semantic search, graph visualization, Git synchronization, MCP, advanced analytics, and external search are feature-flagged adapters. Authorization is a domain service invoked at every delivery channel and before retrieval, never delegated to an external provider or an LLM.

The design priorities are security and privacy, data integrity, correct and explainable authorization, knowledge reliability, usability/accessibility, maintainability, then scale and extension.

## System context

```mermaid
flowchart LR
  Guest[Guest / customer]
  Member[Employee / author / reviewer]
  Admin[Space manager / KM administrator]
  Agent[Authorized AI or MCP client]
  WP[WordPress + KBMS plugin]
  DB[(MySQL / MariaDB)]
  Obj[(Protected media storage)]
  Search[Optional search / vector providers]
  AI[Optional LLM / embedding providers]
  Sources[Git and import sources]
  Notify[Email / webhook / notification adapters]

  Guest -->|public portal, search| WP
  Member -->|editor, portal, REST| WP
  Admin -->|governance and configuration| WP
  Agent -->|scoped MCP credentials| WP
  WP --> DB
  WP --> Obj
  WP -->|minimum authorized scope| Search
  WP -->|consented, delimited context| AI
  Sources -->|validated import/sync| WP
  WP -->|signed, redacted events| Notify
```

Trust boundaries exist at every browser request, REST/MCP endpoint, uploaded/imported artifact, outbound HTTP call, provider adapter, webhook, queue payload, cache, and protected attachment delivery. Stored knowledge is untrusted content for rendering and for AI prompt construction.

## Major components

```mermaid
flowchart TB
  subgraph Delivery[Delivery adapters]
    AdminUI[Admin React UI]
    Editor[Gutenberg]
    Portal[Frontend portal]
    REST[REST v1]
    MCP[MCP]
    CLI[WP-CLI]
    Hooks[Hooks / SDK]
  end

  subgraph App[Application services]
    Knowledge[Knowledge / spaces / taxonomy]
    Governance[Workflow / review / verification]
    Discovery[Search / graph / health]
    Assistant[AI / RAG]
    Exchange[Import / export / Git / webhooks]
    Collaboration[Comments / feedback / notifications]
    Reporting[Analytics / audit / privacy]
  end

  subgraph Policy[Cross-cutting policy]
    Authz[Authorization policy engine]
    Validation[Validation / sanitization]
    Flags[Feature flags]
    Events[Domain events]
    Observability[Structured logs / health]
  end

  subgraph Infra[Infrastructure adapters]
    Repos[Repositories]
    Queue[Background queue]
    Cache[Cache and invalidation]
    SearchPort[Search provider]
    VectorPort[Vector store]
    LLMPort[LLM / embedding / reranker]
    ConnectorPort[Connector adapters]
  end

  Delivery --> App
  App --> Policy
  App --> Infra
  Infra --> WPAPI[WordPress APIs]
  Infra --> SQL[(Custom tables + WP tables)]
```

### Module dependency rules

```mermaid
flowchart LR
  Domain[Pure domain: knowledge, hierarchy, relation, workflow, policy]
  Application[Application use cases]
  Ports[Repository and provider interfaces]
  WPAdapters[WordPress delivery/storage adapters]
  External[External provider adapters]

  Application --> Domain
  Application --> Ports
  WPAdapters --> Application
  WPAdapters --> Ports
  External --> Ports
```

- Domain code must not call WordPress globals, SDKs, HTTP, or SQL.
- Delivery adapters translate requests into application commands/queries and must not contain business rules.
- Provider adapters implement stable ports; domain/application code does not import vendor SDK types.
- Modules communicate through application interfaces and domain events. Direct cross-module table writes and circular dependencies are forbidden.
- Security-sensitive events use an outbox so committed state and subsequent jobs cannot silently diverge.

## Request and data flow

```mermaid
sequenceDiagram
  actor U as User/client
  participant D as Delivery adapter
  participant A as Authentication
  participant Z as Authorization
  participant V as Schema validation
  participant S as Application service
  participant R as Repository
  participant E as Audit/outbox
  U->>D: Request
  D->>A: Establish principal and channel
  A-->>D: Principal or generic failure
  D->>V: Parse, sanitize, validate
  V-->>D: Typed input or field errors
  D->>Z: action + resource + context
  Z-->>D: allow/deny + reason code
  alt allowed
    D->>S: Typed command/query
    S->>R: Transactional read/write
    R-->>S: Domain result
    S->>E: Audit and outbox in transaction
    S-->>D: DTO
    D-->>U: Context-escaped response
  else denied
    D->>E: Security audit where policy requires
    D-->>U: Non-enumerating error
  end
```

## Authorization model and flow

Effective access combines an action capability with resource policy. Explicit deny wins; otherwise the most specific applicable rule wins; otherwise inheritance proceeds document → category/collection → space → site default. Public access is an explicit grant, not absence of a restriction. See [permissions matrix](permissions-matrix.md).

```mermaid
flowchart TD
  Req[Principal + action + resource + channel]
  Auth[Authenticated or anonymous principal]
  Cap{Has base action capability?}
  Rules[Load applicable user, team, role, org and resource rules]
  Deny{Any applicable explicit deny?}
  Specific[Resolve most-specific explicit grant]
  Inherit[Resolve parent and site policy]
  Constraints[Apply status, confidentiality, ownership and purpose constraints]
  Permit[Permit with decision ID]
  Reject[Deny without resource disclosure]

  Req --> Auth --> Cap
  Cap -- no --> Reject
  Cap -- yes / public-read exception --> Rules --> Deny
  Deny -- yes --> Reject
  Deny -- no --> Specific --> Inherit --> Constraints
  Constraints -- satisfied --> Permit
  Constraints -- not satisfied --> Reject
```

Authorization decisions are reused as immutable scopes by repositories/search adapters, but caches are keyed by principal policy version and resource policy version. Permission changes increment versions, invalidate caches, remove or re-scope index/vector records, and update protected file grants before emitting success.

## Search flow

```mermaid
flowchart LR
  Q[Query + principal] --> Parse[Normalize; parse phrase/filter intent]
  Parse --> Scope[Build authorized resource scope]
  Scope --> Lex[Lexical provider]
  Scope --> Sem{Semantic enabled and allowed?}
  Sem -- yes --> Vec[Authorized vector namespace/filter]
  Sem -- no --> Merge
  Lex --> Merge[Merge candidates]
  Vec --> Merge
  Merge --> Recheck[Batch authoritative permission recheck]
  Recheck --> Rank[Configurable hybrid rank]
  Rank --> Safe[Facets/counts/snippets from authorized set only]
  Safe --> Result[Results + explanations]
```

The default adapter may offer a reduced feature set but must support correct permission filtering. Counts, spell suggestions, autocomplete, related items, caches, headings, attachment names, and timing behavior must not disclose unauthorized knowledge. Verification/freshness are ranking guardrails so popularity cannot elevate stale, unverified content above policy thresholds.

## AI/RAG flow

```mermaid
sequenceDiagram
  actor U as User
  participant A as AI endpoint
  participant Z as Authorization
  participant R as Retriever
  participant K as Knowledge repository
  participant L as LLM provider
  participant G as Evidence gate
  participant Audit as Audit service

  U->>A: Question
  A->>Z: Check AI operation and spaces
  Z-->>A: Authorized retrieval scope
  A->>R: Query within scope
  R->>K: Fetch current permitted chunks/versions
  K-->>R: Evidence + stable citation anchors
  R->>Z: Recheck permission and policy versions
  Z-->>R: Allowed evidence only
  R-->>A: Ranked evidence
  A->>L: System instructions + delimited untrusted evidence
  L-->>A: Structured answer with citation claims
  A->>G: Validate citations, thresholds, output policy
  alt sufficient grounded evidence
    G-->>U: Answer + resolvable citations + uncertainty
  else insufficient or changed permission
    G-->>U: Controlled insufficient-evidence response
  end
  A->>Audit: Redacted policy/result metadata
```

No unauthorized candidates may be sent to an LLM and then “filtered” from its answer. A permission change during generation invalidates the response if any selected source policy version changed. Prompt content cannot grant tools or alter system instructions. External processing requires configured consent and excludes sensitive spaces by policy.

## Workflow flow

```mermaid
stateDiagram-v2
  [*] --> Draft
  Draft --> TechnicalReview: submit [author]
  TechnicalReview --> Draft: request changes [reviewer]
  TechnicalReview --> SecurityReview: approve [reviewer, if required]
  TechnicalReview --> Approved: approve [no further gate]
  SecurityReview --> Draft: request changes [security reviewer]
  SecurityReview --> Approved: approve
  Approved --> Published: publish [publisher]
  Published --> NeedsReview: due/dependency changed
  NeedsReview --> Published: verify current revision [verifier]
  Published --> Deprecated: deprecate [owner/manager]
  Deprecated --> Archived: retention/archive policy
```

Actual state machines are configurable per space/type/category/sensitivity. Every transition has an allowed-source set, required capabilities, optional separation-of-duties and approval quorum, optimistic concurrency token, invariant checks, transactional revision/event/audit writes, and idempotency key.

## Import and Git synchronization flow

```mermaid
flowchart TD
  Trigger[Manual, schedule, or signed webhook]
  Fetch[Connector fetch with SSRF and credential controls]
  Stage[Immutable staging artifact + source identity]
  Parse[Bounded parse and malware/content checks]
  Map[Map type, hierarchy, metadata, language]
  Diff[Compare source hash and local revision]
  Conflict{Both source and local changed?}
  Resolve[Explicit conflict resolution]
  Validate[Schema, hierarchy, relation and permission validation]
  Commit[Transactional batch commit]
  Jobs[Index, embed, links, notifications]
  Report[Audit and per-item result]

  Trigger --> Fetch --> Stage --> Parse --> Map --> Diff --> Conflict
  Conflict -- yes --> Resolve --> Validate
  Conflict -- no --> Validate
  Validate --> Commit --> Jobs --> Report
```

Source identity plus revision/hash prevents duplicate imports. Partial failures are reported per item and resumable. Imports never publish by default unless a trusted connector policy explicitly permits it. Git force-pushes and renames are detected; conflicts never silently overwrite either side.

## Background-job flow

```mermaid
flowchart LR
  TX[Domain transaction] --> Outbox[(Outbox record)]
  Outbox --> Dispatch[Dispatcher]
  Dispatch --> Queue[(WordPress-compatible queue)]
  Queue --> Claim[Atomic claim + lease]
  Claim --> Execute[Idempotent handler]
  Execute --> Success[Checkpoint/result]
  Execute --> Retry[Bounded exponential backoff]
  Retry --> Queue
  Retry --> Dead[Dead letter / operator action]
  Success --> Health[Health and metrics]
  Dead --> Health
```

Jobs carry opaque object IDs, expected versions, policy versions, feature flags, attempt count, idempotency keys, and correlation IDs—not secrets or full private AI context. Handlers re-authorize system actions and re-read current state. Disabling a feature prevents new work and safely cancels/no-ops pending optional work.

## Deployment and scalability

The initial deployment is one WordPress plugin and database. Progressive scale points are:

1. Small: WordPress-native lexical search, Action Scheduler-compatible queue adapter, object cache optional.
2. Medium: dedicated tables and aggregates, persistent object cache, external search/vector adapters, worker-backed cron.
3. Large: external search/vector services and connector workers while WordPress remains the policy/control plane and source of portable core knowledge.

Default WordPress search is not represented as suitable for 500,000 items. Load testing at 500, 25,000, and architecture-validation at 500,000 items is required before scale claims.

## Security and privacy invariants

- Deny by default and authorize every channel independently: admin, portal, REST, AJAX, search, graph, AI, attachment, export, webhook, MCP, CLI.
- Use prepared queries, strict schemas, context-specific output escaping, nonces for browser CSRF protection, and capabilities for authorization.
- Validate MIME by content; private files use authorized streaming or provider-signed short-lived URLs, never guessable public media URLs.
- Outbound URLs permit only approved schemes/hosts, resolve and re-check redirects, and block local/link-local/private network ranges unless explicitly allowlisted.
- Secrets stay server-side, are masked after save, never logged/exported, and use encryption-at-rest where key management is available.
- Audit history is append-only to ordinary users, tamper-evident where practical, retained by policy, and privacy-aware.
- Deactivation preserves data; uninstall deletion requires explicit opt-in and confirmation.
- Logs are structured and redacted; client errors never expose stack traces, SQL, provider secrets, or private context.

## UX information architecture

Admin navigation uses one top-level Knowledge application with Dashboard, Spaces, Knowledge, Requests, Reviews, Workflows, Graph, Search, AI, Analytics, Integrations, Users & Access, Templates, Glossary, Imports / Exports, Audit, and Settings. Health and operational tools live in the relevant dashboard/settings views rather than adding excessive WordPress top-level items. Visibility depends on capability. The frontend provides portal home, space/collection/category trees, knowledge pages, search, glossary, recents/popular pages, and optional AI assistant.

Every asynchronous or data view requires loading, empty, partial, error, stale, retry, and permission-revoked states. Relationship lists are the accessible fallback for graph visualization. Primary workflows require keyboard operation, meaningful focus order/errors, WCAG 2.2 AA contrast, reduced motion, localization, and purpose-built RTL layouts.

## Testing boundaries

Unit tests cover pure policy, hierarchy, workflow, ranking, and value objects. WordPress integration tests cover schema/repositories/hooks/capabilities/privacy. Contract tests cover provider/connector adapters. REST and MCP tests enumerate authentication, schema, capability, BOLA/IDOR, pagination, and non-disclosure. Browser/E2E tests cover primary workflows, keyboard accessibility, responsive/RTL behavior, and conflict handling. Security fixtures must prove a user cannot infer a secret via any delivery path. Performance tests use the brief’s small/medium/large data profiles.

No such tests currently exist or have been run.
