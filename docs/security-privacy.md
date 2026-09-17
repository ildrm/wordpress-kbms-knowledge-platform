# Security, AI, and privacy guide

## Trust boundaries and assets

Protected assets are private knowledge, attachment metadata, memberships, workflow decisions, audit events, integration credentials, AI context, and provider responses. Trust boundaries exist at wp-admin forms, WordPress REST, public templates, uploads, scheduled jobs, outbound HTTP, webhooks, search providers, vector stores, and LLM providers.

Threat actors include anonymous visitors, authenticated non-members, malicious contributors, compromised administrators/integrations, and hostile content imported into the knowledge corpus. The principal threats are BOLA/IDOR, capability confusion, SQL injection, XSS, CSRF, SSRF/DNS rebinding, unsafe uploads, webhook replay, query/title side channels, cache poisoning, CSV formula injection, prompt injection, and AI data exfiltration.

## Controls in this release

- central `AuthorizationInterface` used by templates, native/mixed queries, REST, graph, relationships, export, and RAG;
- authorization-constrained candidate queries before result counts or snippets exist;
- final per-resource recheck immediately before AI context construction;
- WP capabilities plus space membership/ownership, with unindexed content fail-closed;
- nonce + capability checks for state-changing admin forms;
- parameterized SQL and fixed table-name allowlists;
- contextual output escaping and rich-content allowlisting;
- HTTPS/public-address/allowlisted-host outbound URL policy;
- timestamped HMAC webhook verification with nonce replay cache;
- bounded inputs, graph expansion, AI context, and feedback rate limits;
- raw AI context, credentials, authorization headers, IP addresses, and raw search text excluded from logs/analytics;
- CSV formula neutralization and explicit export authorization;
- structured audit events for schema, content, spaces, membership, workflow, and verification changes.

Direct media-file protection remains environment-dependent because a web server can serve uploads without executing WordPress. Sensitive deployments must use private object storage or a deny-by-default uploads rule plus an authenticated download controller; this is a documented release limitation, not implied protection.

## AI safety

Stored/imported text is placed only in delimited source fields and described to the provider as untrusted data. It never becomes a system instruction. Rerankers cannot introduce resource IDs absent from the authorized candidate set. Insufficient evidence, provider errors, disabled AI, or access revocation produce a controlled refusal. Provider adapters must obtain explicit external-processing consent and enforce excluded-space policy before they are registered.

## Privacy

Feedback is connected to a user only when logged in; anonymous rate keys are salted transient hashes, not stored addresses. Search analytics store only an HMAC of a normalized term and aggregate daily counts. WordPress personal-data export/erasure callbacks cover feedback. Retention deletes bounded feedback batches; audit/legal retention must be configured according to organizational policy.

## Release security status

No known critical/high defect was found in the implemented slice during code review and automated unit/static checks. Full penetration testing, browser E2E, malware scanning, protected-download infrastructure, external-provider certification, and enterprise-scale load testing require a deployed WordPress environment and remain open quality gates.

