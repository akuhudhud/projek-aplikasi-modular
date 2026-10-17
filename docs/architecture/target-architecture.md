
# Target Architecture — Universal Task & Direct Runner Marketplace

Status: PROPOSED FOR DESIGN LOCK
Architecture Style: Modular Monolith
Backend: Laravel / PHP
Database: MySQL
Source of Truth: GitHub (code), GitBook (project decisions and knowledge)

## 1. Purpose

This document defines the target architecture for evolving the
existing modular application into a universal task platform with
a Direct Runner Marketplace.

This is a target architecture decision document. It does not
authorize changes to an existing release scope by itself.

Implementation must follow the approved batch plan, preserve
existing Core security guarantees, and pass regression tests.

## 2. Architecture Principles

1. Core owns universal task identity and lifecycle.
2. Marketplace Engine owns quotation and offer-selection workflows.
3. Business Modules own service-specific data and business rules.
4. Identity integration must use an explicit contract.
5. Core must not read private Business Module tables directly.
6. Marketplace operations must be transaction-safe and idempotent
   where retries may occur.
7. Existing authentication, session, authorization, audit and
   account-lifecycle guarantees must remain intact.
8. Database migrations must be additive where practical.
   Existing migration history must not be rewritten.
9. The initial implementation remains a modular monolith.
   Microservices are not required for this architecture.
10. Architecture decisions and implementation scope must be
    documented separately.

## 3. Target Components

### 3.1 Core

Core is responsible for:

- Universal task identity and registration.
- Generic task status and lifecycle transitions.
- Task distribution and availability.
- Runner assignment.
- Authorization of task actions.
- Common operational timestamps.
- Common task audit and traceability.
- Contracts exposed to Business Modules and Marketplace Engine.

Core must not implement service-specific pricing rules or read
module-private business records directly.

### 3.2 Marketplace Engine

Marketplace Engine is a shared internal component responsible for:

- Receiving or coordinating quotation requests.
- Recording runner quotations.
- Validating quotation eligibility and status.
- Accepting or rejecting quotations.
- Selecting the successful runner.
- Assigning the selected runner to the corresponding task.
- Closing competing quotations after successful acceptance.
- Preventing multiple successful assignments.
- Recording quotation and selection history.

Marketplace Engine must use Core contracts for task assignment
and lifecycle operations rather than bypassing Core invariants.

### 3.3 Business Modules

Business Modules provide service-specific capabilities.

The initial business module is PickAndDrop.

A module may own:

- Service-specific request fields.
- Pickup and destination details.
- Service-specific validation.
- Service-specific operational rules.
- Service-specific completion evidence.

Modules must not independently override Core task ownership,
generic lifecycle rules or Marketplace selection guarantees.

### 3.4 Identity and Accounts

SIDCORE is the intended identity and account layer.

Before implementation, its integration contract must be audited.
The current backend still contains Account and Session models.

Do not remove or replace these models until identity ownership,
authentication delegation, session validation, authorization,
account status and migration compatibility are explicitly resolved.

## 4. Direct Runner Marketplace

The marketplace follows a direct request-and-quotation model.

1. A customer creates a service request.
2. Eligible runners can discover or receive the request.
3. A runner submits a quotation.
4. The customer reviews available quotations.
5. The customer accepts one quotation.
6. The accepted runner is assigned to the corresponding task.
7. Competing quotations are closed after successful acceptance.
8. The task proceeds through Core's generic lifecycle.
9. The relevant Business Module handles service-specific execution.

Only eligible delivery runners may offer delivery services.

Quotation status and task status are separate state machines.
Accepting a quotation must not be treated as equivalent to
completing the task.

## 5. Transaction and Concurrency Guarantees

Accepting a quotation must be atomic.

The successful operation must ensure that:

- The quotation is still eligible for acceptance.
- The associated request is still open for selection.
- The task has not already been assigned incompatibly.
- Only one quotation wins for the request.
- The selected runner is assigned exactly once.
- Competing quotations are closed consistently.
- A failed transaction does not leave partial selection state.

The implementation must use database transactions, suitable
locking or equivalent concurrency controls, database constraints
where appropriate, and regression tests.

Retries must not create duplicate successful assignments.

## 6. Component Boundaries

Core owns generic task state and assignment invariants.

Marketplace Engine owns quotation state and selection policy.

Business Modules own service-specific request records and rules.

Identity owns account identity according to the approved
identity integration contract.

Cross-component communication must use explicit contracts.
Private module tables must not become implicit public APIs.

The mapping between a business request, marketplace request,
quotation and Core task must be explicit and traceable.

## 7. Target Data Concepts

The final schema is subject to detailed design and migration review.

Expected conceptual entities include:

- Core Task
- Service Request
- Runner Service Profile or Service Listing
- Quotation
- Quotation Selection History
- Module-specific service record

These concepts do not mandate one table per entity.
The physical schema must be determined after relationship,
ownership, uniqueness, retention and concurrency requirements
are documented.

Do not create migrations solely from this conceptual list.

## 8. Implementation Strategy

Phase 1 — Architecture and Gap Audit
- Confirm the actual GitHub HEAD.
- Inventory existing Core TASK contracts and services.
- Inspect module boundaries and module discovery.
- Audit current migrations and test coverage.
- Confirm the SIDCORE integration boundary.
- Record the gap between current implementation and target design.

Phase 2 — Design Lock
- Finalize component responsibilities.
- Define request, quotation and task relationships.
- Define state machines and allowed transitions.
- Define concurrency and idempotency guarantees.
- Define API contracts and authorization requirements.
- Approve the batch implementation plan.

Phase 3 — Core Compatibility
- Preserve existing Core behavior.
- Extend universal task contracts only where necessary.
- Maintain backward compatibility where practical.
- Add migrations instead of rewriting migration history.

Phase 4 — Marketplace Engine
- Implement quotation lifecycle.
- Implement customer selection.
- Implement atomic runner assignment and competing-offer closure.
- Add authorization, audit and concurrency regression tests.

Phase 5 — Business Module Integration
- Integrate PickAndDrop through explicit contracts.
- Keep service-specific rules inside the module.
- Verify end-to-end task and quotation consistency.

Phase 6 — Verification and Release
- Run the relevant automated test suite.
- Verify MySQL behavior and concurrency cases.
- Run static analysis and CI.
- Audit the final diff and migration safety.
- Update README, GitBook and release records.
- Release only after the approved acceptance gates pass.

## 9. Scope and Release Protection

The existing v0.3.2 scope remains governed by its current
acceptance criteria until formally closed or revised.

The deferred v0.4.0 scope must not be silently redefined.

The new marketplace architecture requires an explicit approved
implementation baseline and version plan before feature work
begins.

Historical release tags must not be moved or rewritten.

## 10. Acceptance Gates

The architecture is not considered implemented merely because
the document or database tables exist.

Acceptance requires evidence that:

- Core and module boundaries are respected.
- Existing authentication and account security remain intact.
- Customers can create valid service requests.
- Eligible runners can submit quotations.
- Customers can accept only one valid quotation.
- Concurrent acceptance cannot produce multiple winners.
- Runner assignment and competing-offer closure are atomic.
- Task lifecycle transitions remain valid.
- Service-specific data remains owned by the appropriate module.
- Automated tests, static analysis and CI pass.
- Documentation matches the verified implementation.

## 11. Change Control

Required workflow:

AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST
→ COMMIT → VERIFY → DOCUMENT → CLOSE

No implementation batch may be marked complete without
evidence from the actual repository state and applicable tests.
