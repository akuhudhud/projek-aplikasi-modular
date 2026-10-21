Super Runner — Direct Runner Marketplace

Repository source for the Super Runner backend and business modules.

Active release

v0.4.1 — Marketplace Stabilization & API Contract Hardening

Status: RELEASED / VERIFIED

Release tag: "v0.4.1"

Verified release SHA:

"8fba329b7f5af54e97c8b3bb8b35c3364ec7f837"

- "GitHub Release v0.4.1" (https://github.com/akuhudhud/projek-aplikasi-modular/releases/tag/v0.4.1)
- "Backend Tests — CI #221" (https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/39139960747)
- "Verified release commit" (https://github.com/akuhudhud/projek-aplikasi-modular/commit/8fba329b7f5af54e97c8b3bb8b35c3364ec7f837)

The release strengthens Marketplace API validation, lifecycle guards, terminal-request access, quotation privacy, expiry handling, award idempotency, transaction rollback and MySQL concurrency regression coverage.

The documented release verification reports that CI #221 passed on the verified SHA. The audit did not identify a confirmed new production defect requiring additional code changes.

Next planned work

v0.4.2 — Identity Verification & Role Eligibility

This is a planned workstream, not an authorized implementation change. Repository and dependency audits, design decisions and acceptance criteria must be completed and approved before implementation begins.

The proposed roadmap remains subject to separate approval for each release.

Product workflow

1. Customers create service requests.
2. Eligible Runners submit quotations.
3. Customers compare and select one quotation.
4. Marketplace records the winning quotation and registers and assigns the corresponding Core TASK.
5. Core manages the generic TASK lifecycle, while Marketplace manages requests, quotations and award selection.

Award processing must preserve transactional consistency and retry protection.

Scope boundaries

The following remain outside the active release scope unless separately approved:

- Automatic pricing or fare calculation.
- Automatic dispatch or quotation selection.
- Activation of the Pick & Drop business workflow.
- New Flutter or complete web interfaces.
- Production OTP provider implementation.
- SIDCORE/SUPERID integration without an approved contract.

Repository contents

- "backend/" — Laravel API, Core services, migrations and automated tests.
- "modules/" — business modules.
- ".github/workflows/" — automated backend CI workflows.

Technical baseline

- PHP "^8.3"
- Laravel "^13.17"
- MySQL 8.x
- PHPUnit and Larastan/PHPStan

CI compatibility and results must be verified against the exact commit SHA being evaluated.

Engineering rules

- Preserve existing release tags and commit history.
- Never edit existing migration history; use new migrations for approved schema changes.
- Do not change Core or remove existing components without a documented finding, approved contract and dependency/regression evidence.
- Do not duplicate tests without identifying a genuine coverage gap.
- GitHub is the source of truth for code, tests, CI configuration and releases.
- GitBook is the source of truth for product decisions, architecture contracts, audits and batch plans.
- Do not implement future roadmap items without their own audit, design lock and acceptance criteria.

Working method

Audit → Design Lock → Baseline Approval → Batch Plan → Implement → Test → Commit → Verify → Document → Close.

The result of each verification must be tied to the exact SHA tested.
