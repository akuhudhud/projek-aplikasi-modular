
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

The marketplace follows a direct request-and-quotation model:
customers submit service requests, eligible runners provide
quotations, and customers choose the quotation they accept.

The platform must support a customer requesting a service from
the general marketplace or directly through an approved runner's
public profile link.

This is a target architecture decision document. It does not
authorize changes to an existing release scope by itself.

Implementation must follow an explicitly approved implementation
baseline and batch plan, preserve existing Core security
guarantees, and pass applicable regression tests.

## 2. Architecture Principles

1. Core owns universal task identity and lifecycle.
2. Marketplace Engine owns requests, quotations and offer selection.
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
11. A public profile link must not bypass authentication,
    authorization, runner eligibility or task validation.
12. The same quotation and selection rules must apply whether a
    request originates from the marketplace or a runner profile.
13. Architecture and implementation progress must be tracked
    against explicit acceptance criteria, not assumptions.

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

Marketplace Engine must use Core contracts for task assignment
and lifecycle operations.

### 3.2 Marketplace Engine

Marketplace Engine is a shared internal component responsible for:

- Receiving and managing service requests.
- Managing request availability and eligible recipients.
- Recording runner quotations.
- Validating quotation eligibility and status.
- Accepting or rejecting quotations.
- Selecting the successful runner.
- Assigning the selected runner to the corresponding task
  through Core contracts.
- Closing competing quotations after successful acceptance.
- Preventing multiple successful assignments.
- Recording quotation and selection history.
- Recording request acquisition source and attribution metadata.
- Enforcing the same selection rules for all request entry points.

Marketplace Engine must not create a second, independent task
lifecycle that competes with Core.

### 3.3 Business Modules

Business Modules provide service-specific capabilities.

PickAndDrop is the intended initial business module, but the
universal marketplace architecture must not depend exclusively
on PickAndDrop.

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

### 3.5 Public Runner Profile

The platform must support a public-facing profile for an approved
runner who is eligible to offer delivery services.

The public profile is a marketplace entry point, not a separate
task or quotation system.

The profile capability is part of the intended Marketplace MVP
from the initial launch, not a feature automatically deferred
until after launch.

The public profile must:

- Have a unique, shareable public URL.
- Identify the runner and display approved public profile data.
- Display only fields explicitly approved for public exposure.
- Keep private account data, credentials, security information
  and non-public contact information protected.
- Reflect the runner's current eligibility and availability state.
- Provide a way for a customer to initiate a request targeted
  specifically to that runner.

A public profile or shareable URL must not itself grant permission
to perform protected account or marketplace actions.

The exact public fields, URL format, visibility rules and profile
editing permissions must be finalized during detailed design.

## 4. Direct Runner Marketplace

The marketplace follows a direct request-and-quotation model.

### 4.1 Standard Marketplace Request

1. A customer creates a service request through the marketplace.
2. The request is made available to eligible runners according
   to the approved distribution policy.
3. An eligible runner submits a quotation.
4. The customer reviews the available quotations.
5. The customer accepts one quotation.
6. The selected runner is assigned to the corresponding task
   through Core.
7. Competing quotations are closed after successful acceptance.
8. The task proceeds through Core's generic lifecycle.
9. The relevant Business Module handles service-specific execution.

### 4.2 Request Through a Runner Profile Link

A customer must also be able to enter the same marketplace
workflow through an approved runner's public profile URL.

Expected flow:

1. An approved runner shares their public profile link.
2. A customer opens the public profile.
3. The customer initiates a service request targeted at that
   specific runner.
4. The system validates the runner's current eligibility and
   whether the targeted request can be accepted.
5. The request enters the Marketplace Engine.
6. The targeted runner submits a quotation through the same
   quotation workflow used by the marketplace.
7. The customer reviews and accepts or rejects the quotation.
8. Acceptance follows the same atomic selection and Core
   assignment guarantees as a standard marketplace request.
9. The resulting task follows Core's generic lifecycle.
10. The request records that its acquisition source was the
    runner profile link.

A profile link must not directly assign a task, automatically
accept a quotation or bypass customer acceptance.

A request targeted at one runner must not silently become a
general marketplace request. Any fallback or redirection policy
must be explicitly designed and disclosed to the customer.

If the runner is suspended, no longer approved, ineligible or
unable to receive new requests, the system must reject or block
the targeted request safely and communicate the outcome.

Authentication and authorization requirements still apply.
Public profile viewing does not imply anonymous permission to
create protected records, submit quotations or accept offers.

### 4.3 Runner Eligibility

Only eligible delivery runners may offer delivery services.

Runner eligibility must be checked at the relevant operation,
not assumed from a previously generated profile URL.

The design must define how runner approval, suspension,
availability and changes in eligibility affect:

- Public profile visibility.
- New targeted requests.
- Quotation submission.
- Quotation acceptance.
- Existing assigned tasks.

A runner profile link must not allow an ineligible runner to
receive new assignments.

### 4.4 Acquisition Source and Attribution

The marketplace must distinguish at least these request sources:

- `marketplace`
- `runner_profile_link`

The detailed schema may use a different representation if the
same distinction is preserved and testable.

Where appropriate, the system should support attribution to
the originating runner profile without trusting user-supplied
identifiers as proof of authorization.

The design should identify the minimum useful analytics, such as:

- Public profile views.
- Requests initiated from a profile.
- Quotations submitted.
- Accepted quotations.
- Completed tasks.

Analytics must respect privacy, data minimization and access
control requirements.

The objective is to measure whether runners can attract their
own customers and bring organic demand into the platform.

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
- Repeated requests do not create duplicate assignments.

The implementation must use database transactions, suitable
locking or equivalent concurrency controls, database constraints
where appropriate, and regression tests.

The same guarantees apply to requests originating from a
runner profile link. The entry point must not create a weaker
assignment path.

## 6. Component Boundaries

Core owns generic task state and assignment invariants.

Marketplace Engine owns request, quotation and selection policy.

Business Modules own service-specific request records and rules.

Identity owns account identity according to the approved
identity integration contract.

Public Runner Profile exposes only approved public information
and initiates requests through Marketplace Engine contracts.

Cross-component communication must use explicit contracts.
Private module tables must not become implicit public APIs.

The mapping between a business request, marketplace request,
quotation and Core task must be explicit and traceable.

Request source and runner-profile attribution must be represented
without duplicating task lifecycle ownership.

## 7. Target Data Concepts

The final schema is subject to detailed design and migration review.

Expected conceptual entities include:

- Core Task
- Service Request
- Runner Service Profile
- Public Profile URL or equivalent stable public identifier
- Quotation
- Quotation Selection History
- Request Acquisition Source or Attribution
- Module-specific service record
- Relevant marketplace audit records

These concepts do not mandate one table per entity.

The physical schema must be determined after relationship,
ownership, uniqueness, privacy, retention and concurrency
requirements are documented.

Do not create migrations solely from this conceptual list.

## 8. Implementation Planning and Work Breakdown

Work must be organized into approved batches with clear
dependencies, files or components in scope, and acceptance gates.

The following is the target planning sequence, not authorization
to start implementation or change an existing release.

### Batch 0 — Repository and Scope Baseline

- Confirm the actual GitHub HEAD and current release status.
- Audit existing Core task contracts and services.
- Audit current request and runner approval flows.
- Audit migrations, tests and CI evidence.
- Audit SIDCORE integration boundaries.
- Record existing capabilities and verified gaps.
- Identify files and behaviors that must remain unchanged.

Exit criteria:
- Audit findings are tied to the actual repository state.
- Current release scope is distinguished from target architecture.
- No implementation begins on the basis of assumed functionality.

### Batch 1 — Architecture and Contract Design

- Finalize Core, Marketplace Engine, Business Module and
  Identity responsibilities.
- Define service request and quotation relationships.
- Define task mapping and assignment contracts.
- Define runner eligibility and public profile boundaries.
- Define request source and attribution.
- Define authorization and privacy requirements.
- Define state machines, error behavior and idempotency.
- Define the API contracts and acceptance tests.

Exit criteria:
- Component ownership is unambiguous.
- Public profile requests use the same quotation workflow.
- Security and concurrency requirements are documented.
- The design and implementation baseline are explicitly approved.

### Batch 2 — Runner Profile and Entry Points

- Define public profile fields and visibility.
- Define stable, shareable profile URLs.
- Define profile eligibility and unavailable-runner behavior.
- Define request initiation from a profile.
- Define acquisition-source recording and minimum analytics.
- Define authentication and authorization for each action.

Exit criteria:
- Approved runners can expose only permitted public information.
- Profile-origin requests target the intended runner.
- Profile links do not bypass eligibility or authorization.
- Request source is recorded and can be verified by tests.

### Batch 3 — Marketplace Request and Quotation Engine

- Implement or integrate the shared service-request workflow.
- Implement quotation submission and status transitions.
- Implement customer review and selection.
- Implement rejection, expiry and cancellation rules as approved.
- Implement atomic acceptance and competing-offer closure.
- Add audit records, authorization and idempotency controls.

Exit criteria:
- A valid request can receive eligible quotations.
- The customer can select only one valid quotation.
- Concurrent acceptance cannot produce multiple winners.
- Failed or repeated operations cannot leave inconsistent state.

### Batch 4 — Core and Business Module Integration

- Integrate marketplace selection with Core assignment contracts.
- Map accepted requests to one corresponding Core task.
- Integrate the initial Business Module through explicit contracts.
- Preserve Core's generic lifecycle and module ownership.
- Verify service-specific request data remains module-owned.

Exit criteria:
- Accepted quotations result in exactly one valid assignment.
- Core remains the authority for generic task lifecycle.
- Business Module execution does not bypass Marketplace rules.
- Existing Core behavior and security remain intact.

### Batch 5 — End-to-End Verification

- Test standard marketplace request flow.
- Test runner-profile-link request flow.
- Test runner approval, suspension and eligibility changes.
- Test unauthorized and invalid operations.
- Test concurrent acceptance, retries and duplicate submissions.
- Test database constraints and migration safety on MySQL.
- Run automated tests, static analysis and CI.
- Audit the complete diff and verify documentation consistency.

Exit criteria:
- All approved acceptance gates pass with recorded evidence.
- No unresolved critical security or consistency defects remain.
- The tested commit and release candidate are explicitly identified.

### Batch 6 — Release and Operational Readiness

- Confirm release scope and version.
- Update README and release records.
- Align GitBook with the approved architecture and verified state.
- Document operational monitoring and support requirements.
- Release only after the authorized release gates pass.

Exit criteria:
- Documentation matches the verified implementation.
- Release notes and version status are accurate.
- No deferred feature is represented as delivered.

## 9. Implementation Strategy

The detailed batch plan must be approved before implementation.

Implementation should proceed in this order:

1. Audit actual repository state and current scope.
2. Finalize and approve architecture and contracts.
3. Approve a batch plan with explicit acceptance gates.
4. Implement only the approved batch.
5. Run relevant automated tests and static analysis.
6. Review the complete diff and migration safety.
7. Commit and verify the exact resulting HEAD.
8. Update GitHub and GitBook documentation.
9. Close the batch only when evidence supports completion.

The batch sequence may be adjusted only through an explicit
dependency or design decision. Any adjustment must be documented.

## 10. Scope and Release Protection

The existing v0.3.2 scope remains governed by its current
acceptance criteria until formally closed or revised.

The deferred v0.4.0 scope must not be silently redefined.

The Direct Runner Marketplace, including public runner profiles
and profile-origin requests, describes the intended target MVP.
It does not automatically add these features to an in-progress
release or authorize immediate implementation.

Before feature work begins, approve an explicit implementation
baseline, version plan, dependency map and batch plan.

Historical release tags must not be moved or rewritten.

## 11. Acceptance Gates

The target architecture is not considered implemented merely
because documentation, endpoints or database tables exist.

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
- Approved runners have shareable public profiles.
- Public profiles expose only approved information.
- Customers can initiate requests targeted at a runner's profile.
- Profile-origin requests use the common quotation workflow.
- Profile links cannot bypass authorization or eligibility checks.
- Request acquisition source is recorded accurately.
- Runner suspension or loss of eligibility is handled safely.
- Automated tests, static analysis and CI pass.
- Documentation matches the verified implementation.

## 12. Change Control

Required workflow:

AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST
→ COMMIT → VERIFY → DOCUMENT → CLOSE

No implementation batch may be marked complete without
evidence from the actual repository state and applicable tests.

A documentation update does not by itself constitute design
approval, implementation approval or a release.
