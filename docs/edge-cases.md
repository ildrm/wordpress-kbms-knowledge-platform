# Edge-case and failure-mode catalog

Status: **Expected behavior design; no handlers or tests exist.** Each case must gain implementation and test links before its state changes from Not started.

| ID | Trigger | Required behavior | Recovery / observability | Planned test | Status |
|---|---|---|---|---|---|
| EC-HIER-001 | Same slug under two different spaces | Permit because URLs are space-scoped; resolve by space ID, never slug alone | No operator action | Routing/repository test | Not started |
| EC-HIER-002 | Duplicate slug under the same parent | Reject atomically with field-level conflict; suggest unused slug | Keep editor input | Unique/concurrent insert test | Not started |
| EC-HIER-003 | Move a node below its descendant | Reject before write; leave tree and URLs unchanged | Audit rejected action | Deep/concurrent cycle test | Not started |
| EC-HIER-004 | Concurrent hierarchy moves | One succeeds by row version; loser receives current tree and retry option | Repair job detects path inconsistency | Transaction race test | Not started |
| EC-HIER-005 | Category/collection deleted while populated | Block or require explicit reparent/archive plan | Preview affected items and redirects | Delete policy test | Not started |
| EC-HIER-006 | Hierarchy move changes URL | Commit move and redirect together; preserve canonical URL and detect redirect loops | Broken-redirect health check | Permalink mode tests | Not started |
| EC-REL-001 | Circular acyclic relation (`depends_on`, `part_of`) | Reject transaction; directed cycles permitted only for types declared cyclic | Explain path to authorized editor | Graph cycle test | Not started |
| EC-REL-002 | Relation target deleted or becomes inaccessible | Hide edge/target immediately; retain authorized tombstone or flag source for repair | Health issue and owner task | Delete/revoke test | Not started |
| EC-REL-003 | Inverse relation duplicated | Unique canonical relation prevents duplicates; inverse view is deterministic | Repair existing duplicates in migration | Parallel create test | Not started |
| EC-REUSE-001 | Component references itself or indirect recursion occurs | Stop expansion at detection/depth limit; show safe editor error and non-sensitive frontend fallback | Usage graph identifies cycle | Direct/indirect recursion test | Not started |
| EC-REUSE-002 | Shared component update affects many published items | Version component; preview impact; define immediate versus pinned propagation by component policy | Batched reindex and audit | Propagation/rollback test | Not started |
| EC-OWN-001 | Owner is deleted | Prevent silent orphaning; run configured reassignment or mark orphaned with manager task | Preserve pseudonymous authorship/audit | User deletion integration test | Not started |
| EC-OWN-002 | Owner loses access to the space | Remove effective edit rights immediately; preserve ownership marker until reassigned | Notify space manager; orphan dashboard | Permission-change test | Not started |
| EC-WF-001 | Workflow definition removed while in use | Used version is immutable and cannot be deleted; deactivate for new items | Migration wizard for instances | Referential integrity test | Not started |
| EC-WF-002 | Two reviewers approve simultaneously | Atomic row-version/quorum update; no duplicate transition/event | Loser sees already-completed state | Concurrency test | Not started |
| EC-WF-003 | Content changes during review | Submitted revision remains pinned; material edit returns item to configured review state | Notify reviewers | Revision/workflow test | Not started |
| EC-WF-004 | Restore old revision | Create new revision from old content; retain all history; restored revision is not automatically verified | Re-review based on policy | Restore test | Not started |
| EC-WF-005 | High-confidentiality author tries self-approval | Enforce separation of duties/quorum even if actor has generic approve capability | Explain policy to authorized actor | Capability-confusion test | Not started |
| EC-VER-001 | New edit follows verification | New revision is Unverified/Needs Review; prior verification remains attached only to old revision | Dashboard due item | Revision verification test | Not started |
| EC-VER-002 | Review scheduled over DST/timezone change | Store instants in UTC and policy timezone; next recurrence uses calendar semantics | Display both local time and zone | DST boundary test | Not started |
| EC-I18N-001 | Source changes after translation published | Mark translation Source Updated against exact source revision; do not silently unpublish unless policy says so | Notify translator/reviewer | Translation state test | Not started |
| EC-I18N-002 | Translation relation is broken/orphaned | Exclude broken switcher link, retain content, create repair issue | Health scan | Referential repair test | Not started |
| EC-PERM-001 | Child grant conflicts with parent deny | Explicit deny wins regardless of specificity; UI explains conflict to manager | Audit evaluation reason | Precedence table test | Not started |
| EC-PERM-002 | Document restricts access after user loaded a list | Detail and subsequent API calls deny; cached list invalidates by policy version | Remove row without exposing reason/title | Revocation/cache race test | Not started |
| EC-PERM-003 | Permission changes during AI request | Recheck source policy versions before returning; discard answer if any source revoked | Generic retry message; audit aborted response | In-flight revocation test | Not started |
| EC-PERM-004 | Permission changes during export | Recheck at job generation and download; omit or fail according to atomic-export policy | Explain changed scope without naming denied objects | Async export race test | Not started |
| EC-PERM-005 | Admin lacks explicit resource access | No implicit content bypass; only documented break-glass can grant temporary audited access | Alert/audit break-glass | Administrator fixture test | Not started |
| EC-PERM-006 | Resource count could reveal a secret | Compute counts/facets only after authorization; suppress small sensitive aggregates | No different error for hidden/absent resource | Side-channel test | Not started |
| EC-SEARCH-001 | Search provider unavailable | Open circuit; use permitted lexical fallback; label reduced features without leaking query | Health warning and retry | Outage test | Not started |
| EC-SEARCH-002 | Search index corrupted or stale | Exclude records with content/policy hash mismatch; retain direct authorized access; enqueue rebuild | Index health and progress | Corruption/rebuild test | Not started |
| EC-SEARCH-003 | Zero results | Offer safe spelling/related topics and request action derived only from authorized corpus | Record privacy-minimized zero-result event | Restricted-term test | Not started |
| EC-SEARCH-004 | Huge query or hostile Boolean expression | Enforce length/complexity limits and bounded execution | Validation error and rate signal | Complexity/fuzz test | Not started |
| EC-AI-001 | AI disabled after content was embedded | Stop AI routes/jobs and provider calls; ordinary search remains; retain or purge vectors per policy | Visible cleanup state | Feature-toggle test | Not started |
| EC-AI-002 | Embedding model/dimension changes | Create a new generation; never mix vectors; dual-index then atomic cutover | Progress, rollback, cleanup | Model migration test | Not started |
| EC-AI-003 | LLM timeout or rate limit | Cancel/bound request; return retryable error; never fabricate an answer | Retry-after/circuit metrics; lexical search link | Provider-failure test | Not started |
| EC-AI-004 | Evidence below confidence threshold | Return controlled insufficient-evidence outcome with safe source suggestions | Record gap candidate if configured | Deterministic fixture test | Not started |
| EC-AI-005 | Stored text says to ignore instructions or reveal secrets | Treat it as quoted evidence only; no tool/policy effect | Flag suspicious content where configured | Prompt-injection corpus | Not started |
| EC-AI-006 | Citation target revised/deleted before answer opens | Citation pins revision/section; access rechecked; show unavailable without leaking content | Offer current version if authorized | Citation lifecycle test | Not started |
| EC-AI-007 | Provider returns malformed structured output | Reject/repair only within bounded parser; never execute unvalidated tools or render unsafe HTML | Redacted provider error | Fuzz/schema test | Not started |
| EC-FILE-001 | Huge article or attachment | Reject beyond configured bound or stream/chunk async; never exhaust request worker | Report limit and resumable job status | Boundary/load test | Not started |
| EC-FILE-002 | Extension and detected MIME disagree | Quarantine/reject; do not trust filename or browser MIME | Security event; safe deletion | Polyglot/MIME test | Not started |
| EC-FILE-003 | Malicious SVG/HTML/office document | Disallow or sanitize/scan in isolation; never inline unsafe active content | Quarantine with manager-visible reason | Malicious fixture test | Not started |
| EC-FILE-004 | Restricted attachment URL is shared | Opaque URL alone grants nothing; authorize at request or validate short-lived scoped signature | Audit denied download | Direct URL/replay test | Not started |
| EC-IMPORT-001 | Malformed import file | Fail bounded parse with line/item errors; commit nothing for atomic mode or only valid batches for explicit partial mode | Downloadable error report without secrets | Parser/fuzz test | Not started |
| EC-IMPORT-002 | Same source imported twice | Connector/external ID and content hash produce no-op/update, never duplicate | Record idempotent result | Duplicate/parallel import test | Not started |
| EC-IMPORT-003 | Partial import or worker crash | Resume from committed checkpoint; idempotency prevents duplicate rows | Job status shows counts and retry | Fault injection test | Not started |
| EC-IMPORT-004 | Imported content names unauthorized owner/category | Reject or map to configured safe fallback as previewed; never elevate access | Per-item mapping error | Mapping authorization test | Not started |
| EC-GIT-001 | Git file renamed | Detect stable frontmatter ID or rename similarity; update mapping and redirect, not delete/create blindly | Require confirmation when ambiguous | Rename test | Not started |
| EC-GIT-002 | Force-push rewrites history | Compare stored source revision and working tree state; pause destructive sync | Conflict dashboard | Force-push test | Not started |
| EC-GIT-003 | Local and remote both changed | Create explicit conflict with base/local/remote comparison; overwrite neither | Resolve and re-run idempotently | Three-way conflict test | Not started |
| EC-CONN-001 | Connector disconnected or credentials expire | Stop calls, retain local knowledge, mark sync stale, notify authorized manager | Reauthenticate and resume cursor | Expiry/recovery test | Not started |
| EC-HTTP-001 | URL resolves to private IP or redirects there | Block before request and after every redirect/DNS resolution unless explicit safe allowlist | Security log without credentials | SSRF/DNS rebinding test | Not started |
| EC-HOOK-001 | Webhook replay/duplicate delivery | Validate HMAC/timestamp/event ID; process once and return stable acknowledgement | Replay metrics | Replay test | Not started |
| EC-HOOK-002 | Webhook destination repeatedly fails | Exponential backoff with cap; disable/quarantine endpoint after policy threshold | Delivery log and manager alert | Retry/dead-letter test | Not started |
| EC-JOB-001 | Cron disabled | Health check detects overdue queue; admin sees actionable warning; synchronous core reads still work | Configure real cron/worker and resume | Clock advancement test | Not started |
| EC-JOB-002 | Worker dies after side effect before acknowledgement | Same idempotency key makes retry safe; handler reconciles provider state | Attempt history | Kill-point test | Not started |
| EC-JOB-003 | Feature disabled with queued jobs | Dispatcher cancels/no-ops optional work after re-reading feature flag | Show canceled count | Toggle/queue test | Not started |
| EC-DB-001 | Database error during multi-entity write | Roll back transaction; do not emit outbox/audit success; return correlation ID | Retry safe if transient | Fault injection test | Not started |
| EC-DB-002 | Migration interrupted | Record cursor/state, preserve compatible schema, retry safely under lock | Health page and CLI resume | Migration kill/retry test | Not started |
| EC-UI-001 | Browser refresh mid-operation | Idempotency prevents duplicate mutation; refreshed view retrieves authoritative status | Resume/poll async task | E2E refresh test | Not started |
| EC-UI-002 | Two editors save same revision | Expected-version conflict; preserve both inputs; offer compare/reapply | No last-write-wins silence | Two-browser E2E test | Not started |
| EC-UI-003 | Graph JavaScript fails or graph is huge | Accessible relationship list remains; lazy-expand bounded neighborhoods | Error state and retry | JS-disabled/load test | Not started |
| EC-UI-004 | Narrow mobile/RTL/high zoom | Sidebars collapse; tables/code scroll; focus/order/icons remain logical | Visual/manual accessibility report | Viewport/RTL/zoom tests | Not started |
| EC-AN-001 | Analytics dimension has one restricted item/user | Suppress/coarsen small cells and reauthorize drilldown | Explain suppression to authorized analyst | Inference test | Not started |
| EC-RET-001 | Erasure request conflicts with audit/legal hold | Apply documented field-level anonymization/hold rule; never silently delete required evidence | Audited disposition | Privacy workflow test | Not started |
| EC-DEL-001 | Knowledge is deleted while indexed/cached/embedded | Tombstone immediately denies delivery; asynchronous purge covers every derived store | Purge progress and retry | Cross-store deletion test | Not started |
| EC-UNINST-001 | Plugin deactivated/uninstalled | Deactivation preserves data; uninstall defaults to preserve and requires explicit confirmed purge | Preflight count/export recommendation | Lifecycle test | Not started |
| EC-EXP-001 | Huge export | Snapshot permitted IDs, stream/batch, expire artifact, and recheck download permission | Progress/cancel/retry | Scale/revocation test | Not started |
| EC-CSV-001 | Exported cell starts with spreadsheet formula marker | Escape/neutralize according to CSV policy while retaining raw data in safer formats | Security test report | CSV injection test | Not started |
| EC-API-001 | Unknown/mass-assigned REST field | Reject unknown fields or ignore only where schema explicitly permits; never bind request wholesale | Field error | Mass-assignment test | Not started |
| EC-API-002 | Requested page is beyond range or page size huge | Bound page size; return stable empty/out-of-range response without expensive count leak | Rate/latency metrics | Pagination boundary test | Not started |
| EC-CACHE-001 | Shared cache key omits principal/policy version | Design forbids it; security tests verify user A result cannot be served to user B | Flush and security incident procedure | Cache poisoning/isolation test | Not started |

## Catalog maintenance

New failure modes receive stable IDs. A case is complete only when its required behavior is implemented across all applicable channels, its recovery is observable, and the linked deterministic test passes. Generic “error handled” claims are insufficient.
