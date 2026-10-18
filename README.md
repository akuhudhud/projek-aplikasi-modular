# Super Runner — Direct Runner Marketplace

Repository source for the Super Runner backend and business modules.

## Active release

**v0.4.0 — Direct Runner Marketplace**

**Status:** RELEASED / VERIFIED

**Release tag:** `v0.4.0`

**Verified release SHA:**

`68c0c499f552a04513ceb7c0950b0a1e6be5efef`

- [GitHub Release v0.4.0](https://github.com/akuhudhud/projek-aplikasi-modular/releases/tag/v0.4.0)
- [Backend Tests — CI #215](https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/39108684929)
- [Verified release commit](https://github.com/akuhudhud/projek-aplikasi-modular/commit/68c0c499f552a04513ceb7c0950b0a1e6be5efef)

The release establishes the Direct Runner Marketplace workflow:

1. Customers create service requests.
2. Eligible Runners submit quotations.
3. Customers select one quotation.
4. Marketplace records the winning quotation and registers and assigns the corresponding Core TASK.

Award processing preserves transactional consistency and retry protection.

Automated pricing, automatic dispatch and automatic quotation selection are outside the active scope.

## v0.4.1 — Active work

**Theme:** Marketplace Stabilization & API Contract Hardening.

The work begins with a post-release audit, documentation reconciliation and verification of API contracts, authorization, quotation privacy, lifecycle behavior and regression coverage.

No implementation change should be made without a documented finding and approved acceptance criteria.

See the project documentation in GitBook:

- [Product scope and requirements](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-skop-produk-dan-keperluan)
- [Architecture contracts](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-kontrak-seni-bina-dan-batas-tanggungjawab)
- [Repository audit and regression matrix](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-audit-repositori-dan-matriks-regresi)
- [Batch plan and verification gates](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-pelan-batch-dan-gerbang-pengesahan)

## Repository contents

- `backend/` — Laravel API, Core services, migrations and automated tests.
- `modules/` — business modules.
- `.github/workflows/backend-tests.yml` — automated backend CI.

## Technical baseline

- PHP `^8.3`
- Laravel `^13.17`
- MySQL 8.x
- PHPUnit and Larastan/PHPStan

CI currently tests against PHP 8.4 and MySQL 8.0. Never commit secrets or real environment files.

## Engineering rules

- Preserve release tags and commit history.
- Never edit existing migration history; use new migrations for approved schema changes.
- Do not change Core or delete existing components without approved contracts and dependency/regression evidence.
- CI results apply only to the exact SHA tested.
- Do not duplicate existing tests without identifying a real coverage gap.
- GitHub is the source of truth for code, tests and CI configuration.
- GitBook is the source of truth for product decisions, contracts, audits and batch plans.

## Release scope boundaries

The following remain outside the active v0.4.1 scope unless separately approved:

- Flutter User App or new web interfaces.
- Activation of the Pick & Drop business workflow.
- Production OTP provider implementation.
- Automatic pricing, dispatch or quotation selection.
- SIDCORE/SUPERID integration without an approved contract.

Future v0.4.x releases require their own dependency audit, design decisions and acceptance criteria.
