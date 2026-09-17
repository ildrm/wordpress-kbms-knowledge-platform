# Operations and user guide

## Administrator setup

Activate the plugin, then create at least one space at **Knowledge → Spaces**. A space owner is automatically an administrator member. Choose `public` for anonymous published documentation, `internal` for every authenticated user, or `private` for explicit members and owners only.

At **Knowledge → Settings**, enable only the modules the organization has approved. AI is off by default. Enabling it exposes the assistant route only to roles with `use_kbms_ai`; a separate provider adapter is still required. Configure the review interval, retention period, and outbound integration host allowlist. The host allowlist accepts DNS names only; HTTPS, public addresses, and port 443 are enforced independently.

Roles installed by activation:

- Knowledge Contributor: authors and edits assigned knowledge.
- Knowledge Reviewer: reviews, verifies, exports, and may use enabled AI.
- Knowledge Manager: manages spaces/workflows/settings and all knowledge.
- Administrator: receives every KBMS capability.

## Author workflow

Create a **Knowledge Item**, choose a space, owner, confidentiality level, and review date in **Knowledge governance**, then add a knowledge type and topics. Gutenberg revisions and hierarchy remain native WordPress features. The warning and procedure patterns are available in the block inserter. Built-in template markup is exposed by `TemplateCatalog` for integrations and future editor tooling.

An item without a valid space is deliberately absent from retrieval. Publishing does not bypass this fail-closed rule. Parent/child items provide optional deep hierarchy.

## Reviewer workflow

Workflow transitions are explicit, authorized, and compare the expected state before updating, which prevents two simultaneous reviewers from silently overwriting one another. Verification records reviewer/time and schedules the next review. Daily maintenance marks due verified knowledge stale and sends bounded, deduplicated owner reminders.

## Portal

Place `[kbms_portal]` on any page. Optional attributes are `limit` (1–100) and `space` (space slug). Results are scoped before the database query, and the count therefore excludes inaccessible items. Single knowledge pages show owner, verification, review date, and a rate-limited helpfulness control.

## Recovery and troubleshooting

- Check **Tools → Site Health → Status** for schema, cron, component, and AI-provider state.
- Run `wp kbms status` for version, schema, and published counts.
- Run `wp kbms review-due` to execute the reminder batch manually.
- If an external AI provider fails, search remains operational and answers fail closed.
- If the graph client is unavailable, use the relationship endpoint/list data; the API is bounded to 100 edges.
- If migration fails, inspect structured `kbms_audit_log` entries for `schema.failed`; no version is advanced on failure.

Deletion is intentionally conservative. Deactivation retains all data. For a destructive uninstall, an administrator must explicitly set `kbms_delete_data_on_uninstall` to true before deleting the plugin and should take a database backup first.

