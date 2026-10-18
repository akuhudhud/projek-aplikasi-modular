
# GIGAVERSE — Modular App Platform

Repository source for the GIGAVERSE backend and business modules.

## Active baseline

**v0.4.0 — Direct Runner Marketplace**

**Status:** Design Lock approved. Batch 1 verification passed; final diff and commit closeout review pending.

The product model is customer requests, runner quotations, and customer selection of one winning quotation. Automated pricing and automatic dispatch are outside the active scope.

## Verification

Latest verified commit:

`233b4de281c20076f5eaf81cebfb0792f14e7a26`

GitHub Actions: [Backend Tests — CI #213](https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/39105581086)

- Full feature suite: 388 tests, 1,585 assertions — passed.
- MySQL concurrency suite: 2 tests, 25 assertions — passed.

Verification applies only to the exact commit tested.

## Project documentation

Product decisions, requirements, architecture, audits and batch plans are maintained in GitBook.

- [Project overview](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/gigaverse-v0.4.0-direct-runner-marketplace)
- [Product scope and requirements](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-skop-produk-dan-keperluan)
- [Architecture contracts](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-kontrak-seni-bina-dan-batas-tanggungjawab)
- [Batch plan and verification gates](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-pelan-batch-dan-gerbang-pengesahan)
- [Repository audit and regression matrix](https://app.gitbook.com/s/BgBcq0Hr7peJWpy6ZF4H/v0.4.0-audit-repositori-dan-matriks-regresi)

## Repository contents

- `backend/` — Laravel API, Core services, migrations and automated tests.
- `modules/` — business modules.
- `.github/workflows/backend-tests.yml` — automated backend CI.

## Technical baseline

- PHP 8.3+
- Laravel
- MySQL 8.x
- PHPUnit and Larastan/PHPStan

CI currently tests against MySQL 8.0 and PHP 8.4. Never commit secrets or real environment files.

## Engineering rules

- Preserve release tags and commit history.
- Never edit existing migration history; use new migrations for schema changes.
- Do not change Core or delete existing components without approved contracts and dependency/regression evidence.
- CI results apply only to the SHA actually tested.
- GitBook is the source of truth for product decisions, contracts, audits and batch plans.
