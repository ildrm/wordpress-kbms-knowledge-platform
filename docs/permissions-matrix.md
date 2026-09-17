# Permissions and authorization matrix

Status: **0.1.0 RBAC and space policy implemented; expanded ABAC model below is target-state.** Activation installs `kbms_contributor`, `kbms_reviewer`, and `kbms_manager`; administrators receive every KBMS capability. `AuthorizationService` combines capabilities with space visibility, membership role, ownership, publication status, and fail-closed defaults. Search constraints are applied before retrieval, and REST/template/graph/export/AI paths reuse the same service.

| Role | Author/edit | Review/verify | Export | AI when enabled | Manage spaces/workflows/settings |
|---|---:|---:|---:|---:|---:|
| Contributor | Assigned/owned | No | No | Yes | No |
| Reviewer | Assigned + others in scope | Yes | Yes | Yes | No |
| Manager | Yes | Yes | Yes | Yes | Yes |
| Administrator | Yes | Yes | Yes | Yes | Yes |

The concrete capabilities are defined once in `CapabilityRegistrar::ALL_CAPABILITIES`. A role capability alone never grants a private resource: space membership/ownership remains required. The detailed precedence model below is the planned extension for document/team/department/customer rules not present in 0.1.0.

## Principles

WordPress roles are convenient bundles, never the final authorization decision. Each request requires both a base capability and a resource-policy decision. Guest/public access is an explicit read grant. Every channel calls the same policy service, then independently enforces channel-specific controls such as nonce, authentication scope, schema validation, and rate limits.

Precedence is deterministic:

1. invalid, expired, disabled, or unauthenticated principal where authentication is required → deny;
2. applicable explicit deny at any specificity → deny;
3. applicable document grant;
4. category/collection grant;
5. space grant;
6. site default/public grant;
7. no matching grant → deny.

Conditions (team, department, customer organization, language, ownership, confidentiality, content status, and validity period) narrow grants; they never turn a deny into an allow. The effective-decision UI must show the winning rule and blocked rules to authorized managers without revealing membership or resource data to others.

## Capability catalog

| Capability | Permitted action | Security note |
|---|---|---|
| `kbms_read` | Read an allowed published knowledge resource | Resource grant still required. Draft/restricted read is separate. |
| `kbms_read_drafts` | Read allowed non-published revisions | Must not imply edit. |
| `kbms_create` | Create draft knowledge in allowed scopes | Requires destination/type permission. |
| `kbms_edit_own` | Edit own allowed drafts | Ownership alone never grants access. |
| `kbms_edit_all` | Edit allowed knowledge regardless of author | Does not imply publish/permission management. |
| `kbms_delete` | Trash/archive allowed knowledge | Permanent purge is a separate administrative operation. |
| `kbms_publish` | Publish an approved revision | Workflow and separation-of-duty rules still apply. |
| `kbms_review` | Submit review decisions | Only assigned/eligible transitions. |
| `kbms_verify` | Verify a specific revision | Cannot silently verify later revisions. |
| `kbms_approve` | Approve transitions requiring approver | Quorum and conflict-of-interest rules apply. |
| `kbms_manage_spaces` | Create/configure assigned spaces | Cannot grant capabilities the actor lacks. |
| `kbms_manage_workflows` | Version workflow definitions | Changes are audited; used versions immutable. |
| `kbms_manage_integrations` | Configure connectors/webhooks/Git | Secret readback prohibited. |
| `kbms_manage_ai` | Configure AI/providers/policies | External-processing consent and sensitive-space exclusions apply. |
| `kbms_use_ai` | Invoke allowed AI operations | Source read and operation policies both required. |
| `kbms_view_analytics` | View privacy-filtered metrics | Dimension and small-cell disclosure controls apply. |
| `kbms_manage_permissions` | Manage resource ACLs | Cannot self-elevate or exceed delegated scope. |
| `kbms_export` | Export allowed resources | Each exported object is independently authorized. |
| `kbms_audit` | Read appropriately scoped audit events | Audit may contain sensitive metadata; never allows modification. |
| `kbms_manage_settings` | Configure site-level non-secret settings | Security-critical subareas need dedicated capabilities. |
| `kbms_manage_privacy` | Configure retention/privacy and process requests | Legal hold and audit constraints apply. |

Names may be refined during implementation, but splitting read, write, governance, permission, integration, AI, analytics, export, and audit authority is mandatory.

## Role preset matrix

Legend: **A** allowed by preset subject to resource/workflow policy; **O** own/assigned scope only; **R** read only; **—** not granted. WordPress administrators receive no magical bypass inside the matrix; installation may map needed capabilities to Administrator, and resource denies remain enforceable unless a documented break-glass policy is implemented.

| Action | Guest | Reader | Author | Knowledge Owner | Reviewer | Approver | Space Manager | Knowledge Manager | Knowledge Admin |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Read published allowed knowledge | public | R | R | R | R | R | R | R | R |
| Read allowed drafts | — | — | O | O | assigned | assigned | space | A | A |
| Create draft | — | — | A | A | optional | optional | space | A | A |
| Edit own draft | — | — | O | O | — | — | space | A | A |
| Edit others | — | — | — | owned items | — | — | space | A | A |
| Submit for review | — | — | O | O | — | — | space | A | A |
| Review | — | — | — | optional, not own if policy | assigned | — | space | A | A |
| Approve | — | — | — | — | — | assigned | space if eligible | A | A |
| Publish approved revision | — | — | — | optional | — | optional | space | A | A |
| Verify revision | — | — | — | owned if eligible | assigned | optional | space | A | A |
| Deprecate/archive | — | — | — | owned | — | — | space | A | A |
| Delete/trash | — | — | — | — | — | — | space | A | A |
| Manage space configuration | — | — | — | — | — | — | assigned space | delegated | A |
| Manage workflow/type/template | — | — | — | — | — | — | assigned space | A | A |
| Manage permissions | — | — | — | — | — | — | delegated space, no elevation | delegated | A |
| Use AI | —/public policy | configured | configured | configured | configured | configured | configured | configured | configured |
| Configure AI/integrations | — | — | — | — | — | — | delegated space | A | A |
| View analytics | — | — | own contribution | owned scope | review scope | approval scope | space | A | A |
| Export | public print only | allowed items if enabled | allowed items if enabled | owned scope | assigned scope | assigned scope | space | A | A |
| View audit | — | — | own action receipts | owned-object subset | review events | approval events | space subset | delegated | A |

Role presets are starting points. Site administrators may map granular capabilities to WordPress roles, but dangerous combinations and changes must be warned, validated, audited, and tested.

## Resource action matrix

| Resource | Read | Create/update | Publish/execute | Administer |
|---|---|---|---|---|
| Space | explicit/public space read | space create/edit capability and parent scope | n/a | manage-spaces plus delegated scope |
| Collection/category/hierarchy | inherited read plus overrides | edit permission and cycle-safe destination authorization | reordering/move requires both source and destination rights | space manager |
| Knowledge/revision | status-aware resource read | create/edit plus workflow-state permission and expected version | valid transition plus review/approve/publish capability | permission manager cannot bypass workflow |
| Relation/graph edge | read both endpoints and relation | edit source and permission to disclose target | n/a | relation-type management separate |
| Comment/discussion | read knowledge and discussion policy | authenticated comment permission; edit own within policy | resolve requires assignee/moderator | space moderation |
| Attachment/extracted text | read source revision and attachment policy | upload capability, edit source, file validation | protected download rechecks policy | storage settings require admin capability |
| Search/autocomplete/facets | only authorized corpus | query only; no mutation | n/a | search config cannot broaden access |
| AI answer/citation | `use_ai`, operation allowed, and source read | draft creation also needs create/edit | AI never publishes/approves without separate explicit action | manage-ai does not imply source read |
| Knowledge request/feedback | requester/assignee/manager policy | submitter can create; assigned staff update | close via valid workflow | space/request manager |
| Export | read each object plus export capability | create async job with fixed authorized scope | download rechecks actor and expiry | bulk export is audited/rate limited |
| Analytics | aggregate policy and capability | event ingestion validates source authorization | reports use suppression/redaction | privacy manager sets retention |
| Audit | scoped audit capability | append by trusted service only | n/a | no application role can rewrite history |
| Connector/Git/webhook | status visible to scoped managers | manage-integration capability | sync/delivery handler uses service scope and current policy | secret rotation cannot reveal old secret |
| MCP/API credential | credential owner/admin metadata only | scoped credential issuance | every tool checks credential scope + user/resource policy | write tools separately grantable/revocable |

## Channel enforcement matrix

| Channel | Authentication | Authorization and anti-disclosure requirements | State-change protection |
|---|---|---|---|
| Frontend portal | WP session or anonymous | resource read; navigation/counts/related items permission-filtered | nonce plus capability for actions |
| Admin UI | WP session | menu hiding is cosmetic; server checks every request | REST cookie nonce or form nonce plus capability |
| REST | cookie+nonce, application password/OAuth adapter, or scoped credential | route permission callback, object policy, argument/response schema; generic 404/403 policy | schema validation, capability, idempotency where needed |
| AJAX | WP session | same policy service; no unauthenticated sensitive actions | nonce and capability |
| Search/autocomplete | caller context | constrain candidate retrieval; authorized counts/snippets/cache only | rate limit and bounded input |
| Semantic/vector | caller context/service identity | pre-filter namespace/ACL and authoritative post-filter; embeddings never exposed | policy-version invalidation |
| Graph | caller context | both nodes and edge must be visible; degree/count suppression | mutations require endpoint rights |
| AI/RAG | caller + `kbms_use_ai` | authorized retrieval before provider; citation recheck before response | operation allowlist, tool scopes, rate/token limits |
| Attachment | caller or short-lived signed grant | source-resource read at download time; non-enumerating IDs | upload nonce/capability/MIME/size/scan checks |
| Export | caller/job owner | snapshot authorized IDs; recheck on generation and download | idempotent job; signed expiry; audit |
| Webhook | server service identity | events contain minimum data; endpoint subscription policy | HMAC, timestamp, event ID, replay cache, retry limits |
| MCP | scoped credential mapped to principal | independent tool schema, read/write scope split, resource policy | explicit confirmation policy for risky writes, idempotency |
| WP-CLI | local authenticated WP user context | require explicit `--user` or documented system operation scope | destructive actions require confirmation flags and audit |
| Cron/queue | signed/internal job identity | re-read objects and policy versions; least-privilege handler | atomic claim, idempotency, bounded retry |

## Mandatory authorization fixtures

The test suite must include anonymous, reader/subscriber, contributor/author, reviewer, approver, space manager, administrator, explicitly denied user, and explicitly granted user across every channel above. The enterprise fixture contains an Executive space and General space; a normal employee must not learn Executive titles, snippets, filenames, counts, terms, edges, embeddings, citations, or timing-correlated existence.

Required race fixture: revoke access after retrieval but before AI/export response; the response/download must fail or be rebuilt without revoked content. No authorization tests have been implemented or executed yet.
