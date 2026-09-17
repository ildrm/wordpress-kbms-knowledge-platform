# Architecture and requirements baseline

This directory contains the planning baseline and as-built documentation for KBMS 0.1.0.

## Repository state

The repository began as a one-line README and license on 2026-09-17. It now contains an installable plugin foundation, migrations, production modules, deterministic unit tests, and release documentation. Consequently:

- the compact current-release table at the top of the RTM is authoritative for implemented evidence;
- the full 155-section table remains the product backlog and uses **Not started** where the complete enterprise requirement is not satisfied;
- architecture documents distinguish as-built 0.1.0 behavior from target-state decisions;
- automated results and remaining environment-dependent gates are recorded in [reviews.md](reviews.md).

The authoritative implementation status is the [requirements traceability matrix](requirements-traceability.md). Status changes require links to both implementation and verification evidence.

## Documents

- [Requirements traceability matrix](requirements-traceability.md)
- [Architecture overview](architecture.md)
- [Data model](data-model.md)
- [Permissions matrix](permissions-matrix.md)
- [Risk register](risk-register.md)
- [Edge-case catalog](edge-cases.md)
- [Operations and user guide](user-guide.md)
- [Developer, REST, hooks, and integration guide](developer-guide.md)
- [Security, AI, and privacy guide](security-privacy.md)
- [Quality reviews and test report](reviews.md)
- [Architecture decision records](adr/README.md)

## Status vocabulary

| Status | Meaning |
|---|---|
| Not started | No implementation evidence exists. |
| In progress | Work exists but the definition of done is incomplete. |
| Blocked | A recorded dependency prevents progress. |
| Implemented, unverified | Production behavior exists, but required tests/reviews are incomplete. |
| Verified | Implementation, authorization, validation, failure handling, tests, accessibility/i18n considerations, and documentation satisfy the requirement. |
| Deferred | Deliberately excluded from the current release by an approved decision; not represented as complete. |

## Traceability update rule

For a row to become **Verified**, replace proposed paths with exact code locations, add automated test identifiers and command/result evidence, link any manual review artifact, and confirm all cross-channel authorization paths. A parent row cannot be Verified while a child acceptance criterion remains open.
