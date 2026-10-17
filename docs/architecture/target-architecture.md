
# Target Architecture — GIGAVERSE Direct Runner Marketplace

Status: APPROVED DIRECTION / DETAILED DESIGN PENDING  
Implementation Baseline: v0.4.0 — Direct Runner Marketplace  
Architecture Style: Modular Monolith  
Backend: Laravel / PHP  
Database: MySQL  
Source of Truth: GitHub (code), GitBook (project decisions and knowledge)

## 1. Purpose and Status

This document defines the target architecture for evolving
GIGAVERSE into a universal task platform with a Direct Runner
Marketplace.

The approved product direction is a direct request-and-quotation
model: customers submit service requests, eligible runners provide
quotations, and customers choose the quotation they accept.

Customers must be able to initiate requests through:
- The general marketplace.
- An approved runner's public profile and unique shareable URL.

Both entry points must use the same Marketplace Engine,
quotation-selection rules, eligibility checks and Core assignment
contracts.

This document records the approved product direction and target
architecture. Detailed implementation contracts, API specifications,
state machines, database relationships and acceptance tests still
require design completion and approval before feature implementation.

## 2. Version and Scope Control

The current development baseline is v0.4.0 — Direct Runner
Marketplace.

Decisions:
- v0.3.1 remains RELEASED / VERIFIED.
- The former v0.3.2 development scope is cancelled.
- The former v0.4.0 Pick & Drop Business Workflow MVP scope is
  cancelled and replaced by the Direct Runner Marketplace direction.
- Existing Core and Business Module code is not automatically
  cancelled or deleted.
- Historical commits, tags and release records must be preserved.
- No release may be marked complete without implementation and
  verification evidence.

The cancellation of an old scope does not mean that its code,
migrations, tests or technical capabilities must be removed.
Each existing component must be audited and classified as:
KEEP, REUSE, REPLACE or DELETE.

No component may be deleted before its dependencies, regression
impact and replacement strategy are understood.

## 3. Architecture Principles

1. Core owns universal task identity and generic lifecycle.
2. Marketplace Engine owns marketplace requests, quotations and
   quotation selection.
3. Business Modules own service-specific records and business rules.
4. Identity integration must follow an explicit approved contract.
5. Core must not read private Business Module tables directly.
6. Marketplace operations must be transaction-safe and idempotent
   where retries may occur.
7. Existing authentication, session, authorization, audit and
   account-lifecycle guarantees must remain intact.
8. Database changes must preserve migration history. Use new
   migrations for schema changes.
9. The initial implementation remains a modular monolith.
   Microservices are not required.
10. Architecture, implementation scope and release status must be
    documented separately.
11. Public profile links must not bypass authorization, runner
    eligibility or task validation.
12. Marketplace-origin and profile-origin requests must use the
    same quotation and selection rules.
13. Progress must be tracked against explicit acceptance criteria.
14. Existing code must be reused where it satisfies the approved
    contracts; do not modify Core without an evidenced need.
15. Documentation alone is not proof of implementation.

## 4. Target Components and Ownership

### 4.1 Core

Core is responsible for:
- Universal task identity and registration.
- Generic task status and lifecycle transitions.
- Task listing and availability contracts.
- Runner assignment.
- Authorization of task actions.
- Common operational timestamps.
- Common task audit and traceability.
- Contracts exposed to Marketplace Engine and Business Modules.

Core must not implement service-specific pricing rules or read
module-private business records directly.

Marketplace Engine must use approved Core contracts for task
registration, assignment and lifecycle operations.

The existing Core TASK registry, assignment and lifecycle services
must be audited before any changes are proposed. Their current
behavior must not be assumed to satisfy all Marketplace requirements
without contract and regression-test evidence.

### 4.2 Marketplace Engine

Marketplace Engine is a shared internal component responsible for:
- Creating and managing service requests.
- Managing request status and eligible recipients.
- Recording runner quotations.
- Validating quotation eligibility and status.
- Handling quotation acceptance, rejection and expiry as approved.
- Selecting the successful runner.
- Assigning the selected runner through Core contracts.
- Closing competing quotations after successful acceptance.
- Preventing multiple successful assignments.
- Recording quotation and selection history.
- Recording request acquisition source and profile attribution.
- Enforcing the same selection rules for all request entry points.
- Maintaining traceability between service request, quotation
  and resulting Core TASK.

Marketplace Engine must not create a second independent task
lifecycle that competes with Core.

### 4.3 Business Modules

Business Modules provide service-specific capabilities.

Pick & Drop is a potential initial Business Module, but the
universal marketplace must not depend exclusively on Pick & Drop.

A Business Module may own:
- Service-specific request fields.
- Pickup, destination and service details.
- Service-specific validation.
- Service-specific operational rules.
- Service-specific completion evidence.

Modules must not independently override Core task ownership,
generic lifecycle rules or Marketplace selection guarantees.

The existing Pick & Drop request and internal task models must be
audited to determine whether and how they map to Marketplace
requests and Core TASK records. Do not assume the mapping already
exists.

### 4.4 Identity and Accounts

SIDCORE is the intended identity and account layer.

Before implementation, audit the SIDCORE integration contract.
The existing backend contains Account and Session models.

Do not remove or replace these models until the following are
explicitly resolved:
- Identity ownership.
- Authentication delegation.
- Session validation and revocation.
- Authorization and role mapping.
- Account status and runner eligibility.
- Existing account references in modules.
- Migration compatibility and regression impact.

Identity integration must not weaken current authentication,
session, account-lifecycle or security guarantees.

### 4.5 Public Runner Profile

The Marketplace MVP includes a public-facing profile for a runner
who is approved and eligible to offer delivery services.

The public profile is a marketplace entry point, not a separate
request, quotation or task system.

Requirements:
- Each eligible runner can have a unique, shareable public URL.
- The profile displays only explicitly approved public fields.
- Private account data, credentials, security information and
  non-public contact information remain protected.
- The profile reflects the runner's current eligibility state.
- A customer can initiate a request targeted at that runner.
- Profile-origin requests use the same Marketplace quotation flow.
- The profile URL does not grant protected account permissions.
- An ineligible runner cannot receive new assignments through
  a previously generated profile URL.

The exact public fields, URL format, visibility rules, editing
permissions and analytics schema remain detailed-design decisions.

Public profile viewing may be publicly accessible. Creating a
request, submitting a quotation and accepting an offer must follow
the authentication and authorization requirements approved for
each operation. Public viewing does not imply anonymous permission
to create protected records.

## 5. Direct Runner Marketplace Workflow

### 5.1 Standard Marketplace Request

Target flow:

1. A customer creates a service request through the marketplace.
2. The request becomes available to eligible runners according
   to the approved distribution policy.
3. An eligible runner submits a quotation.
4. The customer reviews available quotations.
5. The customer accepts one quotation.
6. The Marketplace Engine validates the request and quotation.
7. The selected runner is assigned through the Core contract.
8. Competing quotations are closed consistently.
9. The task proceeds through Core's generic lifecycle.
10. The relevant Business Module handles service-specific execution.

The exact request and quotation status transitions must be defined
during detailed design before implementation.

### 5.2 Request Through a Runner Profile Link

Target flow:

1. An approved runner shares their public profile URL.
2. A customer opens the public profile.
3. The customer initiates a request targeted at that runner.
4. The system validates the runner's current eligibility and
   ability to receive new requests.
5. The request enters Marketplace Engine with its source recorded.
6. The targeted runner submits a quotation through the same
   quotation workflow used by the general marketplace.
7. The customer reviews and accepts or rejects the quotation.
8. Acceptance follows the same atomic selection and Core
   assignment guarantees as a standard marketplace request.
9. The resulting task follows Core's generic lifecycle.
10. The request records the source `runner_profile_link` and,
    where appropriate, the originating profile reference.

A profile link must not directly assign a task, automatically
accept a quotation or bypass customer acceptance.

A request targeted at one runner must not silently become a
general marketplace request. Any fallback or redirection behavior
must be explicitly designed and disclosed to the customer.

If the runner is suspended, no longer approved, ineligible or
unable to receive new requests, the system must reject or block
the targeted request safely and communicate the outcome.

### 5.3 Runner Eligibility

Only approved and eligible delivery runners may offer services.

Eligibility must be checked at the relevant operation rather than
assumed from a previously generated profile URL.

Detailed design must define how approval, suspension, availability
and changes in eligibility affect:
- Public profile visibility.
- New targeted requests.
- Quotation submission.
- Quotation acceptance.
- Existing assigned tasks.

The rules for existing tasks must avoid accidental cancellation
or invalid transitions when a runner's eligibility changes.

### 5.4 Request Source and Attribution

At minimum, the system must distinguish:
- `marketplace`
- `runner_profile_link`

The physical schema may use another representation if the
distinction remains explicit and testable.

Where appropriate, attribution should identify the originating
runner profile without treating user-supplied identifiers as proof
of authorization.

Potential analytics include:
- Public profile views.
- Requests initiated from a profile.
- Quotations submitted.
- Accepted quotations.
- Completed tasks.

Analytics must respect privacy, data minimization, retention and
access-control requirements. The minimum event definitions and
collection policy remain detailed-design decisions.

## 6. Transaction and Concurrency Guarantees

Accepting a quotation must be atomic.

A successful operation must ensure that:
- The quotation remains eligible for acceptance.
- The associated request remains open for selection.
- The quotation belongs to the intended request.
- The task has not already been assigned incompatibly.
- Only one quotation wins for the request.
- The selected runner is assigned exactly once.
- Competing quotations are closed consistently.
- A failed transaction leaves no partial selection state.
- Repeated requests do not create duplicate assignments.

Implementation must use database transactions, appropriate row
locking or equivalent concurrency controls, database constraints
where appropriate, and regression tests.

The same guarantees apply to profile-origin requests. A different
entry point must not create a weaker assignment path.

The exact locking strategy, unique constraints, idempotency keys,
retry behavior and failure responses must be specified before
implementation.

## 7. Component Boundaries and Traceability

Ownership is defined as follows:

- Core owns generic task state and assignment invariants.
- Marketplace Engine owns request, quotation and selection policy.
- Business Modules own service-specific records and rules.
- Identity owns account identity according to the approved contract.
- Public Runner Profile exposes approved public information and
  initiates requests through Marketplace Engine.

Cross-component communication must use explicit contracts.
Private module tables must not become implicit public APIs.

The mapping between a business request, marketplace request,
quotation and Core TASK must be explicit and traceable.

The architecture must not allow multiple components to claim
ownership of the same generic task lifecycle.

Before implementation, define:
- The owner of each persistent record.
- The stable identifier used across component boundaries.
- The relationship between Marketplace requests and Core TASKs.
- The behavior when task registration or assignment fails.
- The rollback or retry behavior after partial external operations.
- The audit events required to reconstruct selection history.

## 8. Target Data Concepts

The following are conceptual entities, not an approved physical
database schema:

- Core TASK.
- Marketplace Service Request.
- Runner Service Profile.
- Stable Public Profile Identifier or URL.
- Runner Quotation.
- Quotation Selection History.
- Request Acquisition Source or Attribution.
- Module-specific service record.
- Marketplace audit records.

These concepts do not mandate one table per entity.

Physical schema decisions must follow review of relationships,
ownership, uniqueness, privacy, retention, transaction boundaries
and concurrency requirements.

Do not create migrations solely from this conceptual list.
Existing migration history must not be rewritten.

## 9. Implementation Plan and Batches

The following batches define the proposed implementation sequence.
They do not authorize implementation beyond the approved baseline.
Dependencies and acceptance gates must be resolved before each
batch starts.

### Batch 0 — Repository and Scope Baseline

Tasks:
- Confirm actual GitHub HEAD and release status.
- Audit existing Core TASK contracts and services.
- Audit runner approval, suspension and eligibility behavior.
- Audit existing request and internal task models.
- Audit migrations, tests and CI evidence.
- Audit SIDCORE integration boundaries.
- Identify existing behavior that must remain unchanged.
- Classify relevant components as KEEP, REUSE, REPLACE or DELETE.
- Record verified gaps with file paths and test evidence.

Exit criteria:
- Findings are tied to the actual repository state.
- Cancelled scopes are distinguished from reusable code.
- Dependencies and regression risks are recorded.
- No implementation is based on assumed functionality.

### Batch 1 — Architecture and Contract Design

Tasks:
- Finalize Core, Marketplace Engine, Business Module and Identity
  responsibilities.
- Define request and quotation relationships.
- Define task registration, mapping and assignment contracts.
- Define runner eligibility and public profile boundaries.
- Define request-source attribution.
- Define authorization and privacy requirements.
- Define state machines, error behavior and idempotency.
- Define API contracts and acceptance tests.
- Define migration and rollback strategy.

Exit criteria:
- Component ownership is unambiguous.
- Profile-origin requests use the common quotation workflow.
- Security and concurrency requirements are documented.
- API contracts and acceptance tests are reviewed.
- Detailed design is explicitly approved.

### Batch 2 — Runner Profile and Entry Points

Tasks:
- Define public profile fields and visibility.
- Define stable, shareable profile URLs.
- Define eligibility and unavailable-runner behavior.
- Define targeted request initiation from a profile.
- Define acquisition-source recording and minimum analytics.
- Define authentication and authorization for each action.
- Define privacy and profile enumeration protections.

Exit criteria:
- Approved runners expose only permitted public information.
- Profile-origin requests target the intended runner.
- Profile links cannot bypass eligibility or authorization.
- Request source is recorded and testable.

### Batch 3 — Marketplace Request and Quotation Engine

Tasks:
- Implement the shared service-request workflow.
- Implement quotation submission and status transitions.
- Implement customer review and selection.
- Implement approved rejection, expiry and cancellation rules.
- Implement atomic acceptance and competing-offer closure.
- Add audit records, authorization and idempotency controls.

Exit criteria:
- Valid requests can receive eligible quotations.
- Customers can select only one valid quotation.
- Concurrent acceptance cannot produce multiple winners.
- Failed or repeated operations cannot leave inconsistent state.

### Batch 4 — Core and Business Module Integration

Tasks:
- Integrate quotation selection with Core assignment contracts.
- Map accepted requests to exactly one corresponding Core TASK.
- Integrate the initial Business Module through explicit contracts.
- Preserve Core generic lifecycle ownership.
- Verify service-specific data remains module-owned.
- Define behavior for failed registration or assignment.

Exit criteria:
- Accepted quotations result in exactly one valid assignment.
- Core remains the authority for generic task lifecycle.
- Business Module execution does not bypass Marketplace rules.
- Existing Core behavior and security remain intact.

### Batch 5 — End-to-End Verification

Tasks:
- Test the standard marketplace request flow.
- Test the runner-profile-link request flow.
- Test approval, suspension and eligibility changes.
- Test unauthorized and invalid operations.
- Test concurrent acceptance and duplicate submissions.
- Test retries and idempotency.
- Test migrations and constraints on MySQL.
- Run automated tests, static analysis and CI.
- Review the complete diff and documentation consistency.

Exit criteria:
- All approved acceptance gates pass with recorded evidence.
- No unresolved critical security or consistency defects remain.
- The tested commit and release candidate are identified.

### Batch 6 — Release and Operational Readiness

Tasks:
- Confirm release scope and version.
- Update README and release records.
- Align GitBook with approved architecture and verified state.
- Document operational monitoring and support requirements.
- Release only after authorized release gates pass.

Exit criteria:
- Documentation matches verified implementation.
- Release notes and version status are accurate.
- Deferred features are not represented as delivered.

## 10. Acceptance Gates

The architecture is not considered implemented merely because
documentation, endpoints or database tables exist.

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
- Customers can initiate requests targeted at a runner profile.
- Profile-origin requests use the common quotation workflow.
- Profile links cannot bypass authorization or eligibility checks.
- Request acquisition source is recorded accurately.
- Suspension and loss of eligibility are handled safely.
- Automated tests, static analysis and CI pass.
- Documentation matches the verified implementation.

Each gate must be linked to applicable automated tests, manual
verification evidence where necessary, and the tested commit SHA.

## 11. Change Control and Implementation Authorization

Required workflow:

AUDIT → DESIGN LOCK → BASELINE APPROVAL → BATCH PLAN
→ IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE

The Direct Runner Marketplace direction is approved. Detailed
design and implementation remain subject to their own approval
gates.

No implementation batch may be marked complete without evidence
from the actual repository state and applicable tests.

Do not modify Core, replace identity models, delete modules or
rewrite migrations unless a documented requirement and dependency
analysis justify the change.

A documentation update does not by itself constitute implementation
approval, a release or proof that a feature is complete.

Historical release tags and commits must be preserved.
