# ADR-007: Authorized delivery for restricted attachments

- Status: Proposed
- Date: 2026-09-17

## Context

Standard WordPress uploads commonly have public, guessable URLs. Restricting the parent page does not restrict the file, extracted text, thumbnails, or derivatives.

## Decision

Public attachments may use normal media delivery. Restricted attachments use a storage adapter and opaque key outside direct public delivery, or an equivalently protected origin. Download requests authorize against the source revision and current policy, then stream safely or issue a short-lived scoped signed URL. All derivatives inherit source policy and retention.

## Consequences

Private files remain protected across direct links and caches. Range requests, CDN integration, malware scanning, cleanup, and large-file performance require dedicated design and tests. Moving an item between visibility classes requires a reconciled storage transition.

## Alternatives rejected

- Hide the link only: trivial direct URL bypass.
- `.htaccess`-only rules: not portable across supported servers/storage systems.
