# Event Management System Architecture Baseline

## Purpose and status

This document fixes the application boundaries for feature development. It defines how the Laravel 12 application is organized, how the Event-centered optional-module model works, and which infrastructure choices are allowed on the cPanel target.

Prompt 03 established the architecture baseline. Prompts 04 through 10 implement identity/RBAC, the reusable administration UI, organization/settings/master-data foundations, audit/documents, Client/Vendor/Staff masters, and Event template/module configuration. No Event operational module described below is implemented merely because its boundary, navigation concept, or future class location appears here.

## System shape

The application is a Laravel modular monolith deployed as one web application and one MySQL schema. Modules are logical boundaries inside the normal Laravel project, not separately deployed services or third-party module packages.

The system has one source of truth for each business fact:

- Event is the central operational aggregate.
- Client is the commercial owner of a normal Event and becomes mandatory before an Event leaves Draft.
- Booking is an optional upstream enquiry and approval workflow.
- Event Template suggests module selections but never locks them.
- Event module records remain owned by an Event even when their module is later disabled.
- Financial ledgers, status histories, documents, and audit records are append-oriented history rather than replaceable summaries.

## Approved technology boundary

| Concern | Decision |
| --- | --- |
| Runtime | PHP 8.2 with Laravel 12 |
| Database | MySQL-compatible InnoDB, utf8mb4, foreign keys, indexes, and transactions |
| Server rendering | Blade components and views |
| Frontend | Bootstrap 5 and vanilla JavaScript through Vite |
| Deployment | One cPanel-compatible Laravel application with document root at public |
| Request model | Synchronous HTTP requests and synchronous scheduled commands |
| Storage | Laravel local protected storage; public disk only for explicitly public media |
| Excluded infrastructure | Redis, Horizon, persistent queue workers, WebSockets, SPA frameworks, and online payment gateways |

Laravel framework features are preferred. A package may be introduced only after its need, maintenance status, PHP 8.2 compatibility, Laravel 12 compatibility, deployment effect, and simpler framework alternative are documented.

## Module catalog and stable keys

Module keys are stable identifiers used by Event Templates, Event module settings, policies, navigation, tests, and analytics. A key must not be renamed after data exists. Display labels are translated or configured separately.

Only event-scoped keys are stored in per-Event enablement records. Core and global modules are available according to authorization and do not appear as optional Event toggles.

| SRS | Stable key | Scope | Event toggle | Boundary |
| --- | --- | --- | --- | --- |
| M01 Dashboard | dashboard | Global | No | Derived operational summary and authorized shortcuts |
| M02 User Management | users | Global | No | Login identities, roles, permissions, and account lifecycle |
| M03 Event Management | events | Core | No | Event aggregate, lifecycle, templates, notes, and module configuration |
| M04 Client Management | clients | Global master | No | Client identity and relationship history |
| M05 Booking Management | booking | Optional upstream | No | Enquiry, one-step approval, waitlist, and optional Event conversion |
| M06 Venue Management | venue | Event-scoped | Yes | Venue selection, allocation, availability, and conflict warnings |
| M07 Vendor Management | vendors | Global master plus Event-scoped work | Yes | Vendor master data and Event assignments, contracts, invoices, and ratings |
| M08 Staff Management | staff | Global master plus Event-scoped work | Yes | Staff master data and Event shifts, attendance, assignments, and salary ledger |
| M09 Guest Management | guests | Event-scoped | Yes | Guest list, RSVP, seating, invitations, and check-in |
| M10 Ticket Management | ticketing | Event-scoped | Yes | General-admission ticket types, issue, refund, and validation |
| M11 Registration System | registration | Event-scoped | Yes | Event forms, submissions, approval, and confirmation |
| M12 Task Management | tasks | Event-scoped | Yes | Event work, assignments, comments, attachments, and status history |
| M13 Budget Management | budget | Event-scoped | Yes | Planned Event income and expense lines with variance |
| M14 Payment Management | payments | Event-scoped | Yes | Manual client payment, allocation, adjustment, and refund ledger |
| M15 Invoice System | invoices | Event-scoped | Yes | Issued financial snapshots, sequence, lines, tax, and balance |
| M16 Inventory Management | inventory | Event-scoped | Yes | Global stock master with Event reservations and movements |
| M17 Catering Management | catering | Event-scoped | Yes | Event menus, quantities, dietary details, schedules, and approved cost |
| M18 Decoration Management | decoration | Event-scoped | Yes | Event design plan, elements, references, resources, and approved cost |
| M19 Transportation Management | transportation | Event-scoped | Yes | Event vehicles, drivers, routes, pickups, conflicts, and cost |
| M20 Accommodation Management | accommodation | Event-scoped | Yes | Event hotel bookings, room allocation, stays, and charges |
| M21 Marketing Module | marketing | Event-scoped | Yes | Campaign planning, consent, audiences, coupons, referrals, links, and metrics |
| M22 Communication Center | communications | Event-scoped | Yes | Templates and synchronous delivery attempts with contextual history |
| M23 Calendar | calendar | Global projection | No | Authorized projection of existing Event and resource schedules |
| M24 Document Management | documents | Event-scoped | Yes | Protected files and contextual links; shared service may also link global records |
| M25 Reports | reports | Global | No | Authorized filtered reporting derived from transactional records |
| M26 Analytics Dashboard | analytics | Global | No | Defined metrics and trends derived from transactional records |
| M27 Notifications | notifications | Global | No | Static stored in-application notifications and read state |
| M28 Settings | settings | Global | No | Company, branch, locale, finance, integration, security, and backup settings |
| M29 Audit and Security | audit | Global cross-cutting | No | Append-only audit, login history, security events, and recovery evidence |

The event-scoped key set is: venue, vendors, staff, tasks, guests, registration, ticketing, budget, payments, invoices, inventory, catering, decoration, transportation, accommodation, marketing, documents, and communications.

## Dependency guidance

Dependencies are suggestions shown as warnings. They do not become hidden required modules or database prerequisites.

| Module | Suggested companion | Warning behavior |
| --- | --- | --- |
| guests | venue | Warn when seating or check-in planning lacks a venue; allow save |
| registration | guests | Offer approved-registration-to-guest creation; do not require Guest Management |
| ticketing | registration or guests | Explain attendee/check-in integrations; allow ticket-only operation |
| catering | guests | Offer guest count as a starting quantity; allow an independent catering quantity |
| decoration | vendors or inventory | Offer resource links; allow notes-only decoration planning |
| transportation | guests | Offer guest/group pickup links; allow operational routes without guest records |
| accommodation | guests | Offer guest allocation; allow block booking before named guests exist |
| budget | payments and invoices | Explain reconciliation benefits; never require Payment or Invoice modules |
| communications | clients, guests, or registration | Require a recipient context for a send, not another Event module toggle |
| documents | any operational module | Allow contextual attachments without making Documents mandatory for that module |

Validation for an enabled module applies only to actions inside that module. Disabled modules cannot add required fields to Event creation, lifecycle transitions, or completion.

## Laravel code organization

Use standard Laravel folders with domain-oriented subfolders. Do not introduce a Modules directory that hides Laravel conventions and do not install a third-party modular framework.

~~~text
app/
  Http/
    Controllers/{Domain}/
    Requests/{Domain}/
  Models/{Domain}/
  Policies/
  Services/{Domain}/
  Contracts/{Capability}/
  Support/
database/
  factories/{Domain}/
  migrations/
  seeders/
resources/
  views/{domain}/
  views/components/
routes/
  web.php
tests/
  Feature/{Domain}/
  Unit/{Domain}/
~~~

Small cross-cutting models may remain directly under app/Models when a domain subfolder would reduce clarity. Namespaces follow the folder structure. Controllers and requests are grouped by user-facing domain, while shared services are named by capability rather than by a vague Helpers namespace.

## Layers and request flow

A protected write follows this order:

1. A web route selects middleware and a resource-oriented controller action.
2. Authentication and a Policy or Gate authorize the action on the server.
3. A Form Request normalizes and validates request input.
4. The controller calls one application service and coordinates the response.
5. The service enforces business rules and owns the database transaction when several records change.
6. Eloquent models express relationships, casts, scopes, and small entity invariants; they do not become request orchestrators.
7. The controller redirects or renders a Blade view with explicit view data.

Controllers must not contain multi-step business rules, provider code, permission shortcuts, raw SQL for normal persistence, or financial calculations. Form Requests do not authorize by UI visibility alone. Blade templates render authorized state but never replace server-side authorization.

## Module boundary rules

- A module may query another module through an explicit Eloquent relationship, a narrow service method, or a read projection. It must not duplicate another module's source data.
- A write that changes another module uses that module's service rather than editing its tables ad hoc.
- Shared master data such as Client, Vendor, Staff, categories, and settings has one owner.
- Calendar, Dashboard, Reports, and Analytics are read-oriented projections. They do not own copied Event, schedule, or financial truth.
- Notifications and audit records may be written synchronously after a successful business action. Critical audit writes participate in the same transaction when losing the audit would make the action unacceptable.
- Laravel Events and Listeners are allowed only when execution remains synchronous and failure behavior is explicit.
- Scheduled reminders, expiry checks, and backups use Laravel Scheduler invoked by cPanel Cron when available. They are not queued jobs.

## Event aggregate and optional modules

Event stores its Client, optional Booking link, Category, optional Template, branch scope, lifecycle status, schedule, timezone, and core descriptive fields. Event creation never requires Booking.

Per-Event module state is stored separately from templates. Applying a template copies suggested states into Event module settings with a source marker. A later template edit does not silently mutate existing Events.

The enablement invariant is:

- One row per Event and event-scoped module key.
- A missing row follows the module definition's disabled default; implementations should normally materialize all 18 states at Event creation for predictable auditing.
- Enable and disable operations capture actor and time.
- Disabling a populated module requires confirmation, hides normal navigation, and preserves all module data.
- Re-enabling restores access to preserved data.
- Deleting an Event uses archive or soft deletion and never cascades through historical finance, status, audit, or issued-document records.

Event workspace navigation is a server-rendered Blade projection of authorized enabled modules. Vanilla JavaScript may improve confirmations or tabs but the same rules must hold without client-side trust.

## Authorization, audit, and status history

Every protected controller action calls a Policy or Gate through middleware, controller authorization, or Form Request authorization. Query scopes prevent lists, exports, search, calendars, reports, and lookup endpoints from exposing unauthorized records.

Sensitive actions include login/security events, permissions, settings, restore, cancellations, refunds, destructive archive requests, privileged corrections, and module disablement with data. They append an audit entry containing actor, action, subject type/id, event/branch context where relevant, timestamp, request metadata, and redacted before/after data.

Each workflow status change uses a transition service. The service validates the transition, updates the current status, and appends history with from status, to status, actor, timestamp, reason, and metadata in one transaction. System-initiated transitions identify the system actor type; user-initiated transitions require a user actor.

### Implemented identity and RBAC baseline

Prompt 04 implements identity/security with Laravel's native session guard, password broker, gates, policies, route middleware, and Eloquent relationships. No RBAC or authentication starter package is installed.

- `users` stores activation, deactivation actor/time, last login, and soft-delete state.
- `roles` and `permissions` are stable records connected through `role_user` and `permission_role`.
- Seven system roles are seeded. Administrator / Business Manager owns every configured permission through the native permission check after active-account validation; record-level safety rules still apply.
- Permission slugs follow `module.action`, such as `users.view`, `events.approve`, `payments.refund`, and `settings.configure`.
- Policies protect record-aware user actions; permission middleware is available for coarse route protection; Blade navigation is derived from the same server-side permission checks.
- Inactive and archived users cannot authenticate, reset passwords, or retain a protected session.
- User deactivation/archive and role changes run through a transactional service. The final active administrator is protected from lockout.
- `audit_logs`, `login_histories`, and `user_status_histories` record Prompt 04 security history. Password values and reset tokens are never stored in audit payloads.
- Public self-registration has no route. Administrators create accounts, and initial local setup uses environment-only credentials or the interactive administrator command.

### Implemented administration UI baseline

Prompt 05 provides one responsive Blade/Bootstrap administration shell for protected screens:

- fixed desktop sidebar and Bootstrap offcanvas mobile navigation;
- sticky top navigation with a static notification placeholder and account menu;
- permission-filtered navigation groups, breadcrumbs, flash feedback, validation summary, and a shared confirmation modal;
- reusable components for page headers, empty states, filters, bounded paginated tables, status badges, form controls, tabs, pagination, and a fixed whitelist of inline SVG icons;
- production-oriented 403, 404, 419, and 500 views;
- a protected style guide route registered only in local/testing environments.

Presentation components escape ordinary output and improve accessibility, but do not authorize. Routes, controllers, Policies/Gates, Form Requests, and query scopes remain the security boundary. The component contract and examples are documented in docs/UI_COMPONENTS.md.

### Implemented organization settings and master data baseline

Prompt 06 implements the configuration foundation used by later modules:

- `companies` stores the one-company profile and public branding-logo metadata;
- `system_settings` stores allowlisted typed values accessed only through `SettingsService`, with cache invalidation on write;
- secret provider placeholders are encrypted through Laravel Crypt and are never returned to the browser or written to audit payloads;
- `branches` and `branch_user` prepare optional branch assignments while `features.branches_enabled` remains false by default;
- `BranchScope` is an explicit query strategy rather than a global Eloquent scope, so administrators and null-branch records cannot be silently hidden;
- Event, Vendor, Inventory, Finance, and Document categories use separate models/tables with shared administration conventions; Finance retains its own direction constraint.

The organization-settings middleware applies the configured timezone and locale to web requests. Currency, date format, tax, and invoice sequence values are typed inputs for later services; Prompt 06 does not issue invoices or implement any provider adapter. Communication providers remain disabled.

### Implemented audit, status-history, and protected-document baseline

Prompt 07 implements the M24/M29 cross-cutting foundation without introducing an Event or other business module:

- `AuditService` recursively redacts credential, authorization, payment, bank, and sensitive dietary keys before append-only `audit_logs` storage; bounded request metadata retains IP and user agent without request bodies;
- `login_histories` records authentication success/failure safely, and the permission-protected audit screens are read-only, paginated, and filterable;
- `TracksStatusHistory`, `HasStatusHistory`, and `StatusTransitionService` provide explicit, transactional transitions with row locking, model-defined transition maps, actor/type/time/reason/metadata history, and an audit entry;
- `documents`, append-only `document_versions`, and allowlisted `document_links` store protected metadata, current-version selection, contextual ownership, optional nullable branch, expiry, and archive state;
- `DocumentService` validates both MIME and extension plus size/disk, computes a checksum, creates a UUID path on the private local disk, cleans up failed writes, and preserves every replacement version;
- `DocumentPolicy` and `DocumentAccessService` protect lists, details, current/historical downloads, replacement, and archive. Storage paths are never exposed as URLs.

Company, Branch, User, Client, Vendor, StaffProfile, Event, and Booking implement the reusable attachment relationship. Other future owners remain absent from the link allowlist until their modules exist. Document expiry alerts and malware-scanner integration remain later responsibilities. Detailed usage is in `docs/AUDIT_AND_PROTECTED_DOCUMENTS.md`.

### Implemented Client master baseline

Prompt 08 implements M04 as an independent global master without introducing Events or any later business module:

- `Client` represents either an individual or organization, permits a nullable one-to-one portal `user_id`, and stores nullable `branch_id` for explicit `BranchScope` use;
- `ClientContact` retains additional people and shared contact channels. Normalized names, emails, and phone digits support search and advisory duplicate detection without false uniqueness rules;
- thin controllers and Form Requests delegate create/update/archive/reactivate/link/merge/contact rules to explicit transactional services;
- policy checks and scoped queries protect every route; Administrator / Business Manager can complete all Client actions, while Event Manager receives only normal view/create/update/contact access;
- no destructive Client delete route exists. Status transitions append actor/time/reason history, and merge preserves the source row while moving contacts and adding document context to the target;
- `DocumentLink` now allowlists Client ownership, and portal users may access explicitly Client-linked documents only when the document permission boundary also allows the action;
- the detail screen exposes explicit placeholders for Events, optional Bookings, Invoices, Payments, Communications, and actual protected Documents so later modules can populate without changing Client ownership;
- the reusable `<x-clients.selector>` and JSON lookup/quick-create routes let the future Event form select or create a Client through the same request validation and business service.

Client behavior and integration contracts are documented in `docs/CLIENT_MANAGEMENT.md`. Event creation, the Draft-to-active Client requirement, and all financial/communication source tables remain later prompts.

## Financial boundaries and transactions

Financial amounts use decimal arithmetic and stored snapshots. Controllers and Blade templates never calculate authoritative totals.

The following operations are transaction boundaries:

- Issue invoice: lock the sequence, reserve the next number, store invoice header and immutable lines/tax/discount snapshots, and append status history.
- Record payment: store the manual ledger entry, allocations, affected invoice balances or derived reconciliation state, receipt reference, and audit entry.
- Correct or refund payment: append adjustment/refund records and allocations; never overwrite or delete the posted original.
- Post an operational cost: create one expense source reference with an idempotent uniqueness rule so Vendor, Catering, Decoration, Transportation, Accommodation, Inventory, or Staff costs are not counted twice.
- Change an Event or Booking status when the transition also creates financial or operational records.
- Reserve or issue inventory and tickets where quantity and uniqueness must remain consistent.

Reporting derives totals from posted source records and documented recognition rules. Cached summaries are rebuildable projections, never the source ledger.

## Synchronous service adapters

External capabilities are represented by narrow contracts and provider implementations under app/Contracts and app/Services. Examples include MailSender, SmsSender, WhatsAppSender, MapProvider, PdfRenderer, and FileScanner.

An adapter call runs in the initiating request or scheduled command. It must have a bounded timeout, log a redacted failure, preserve an attempt record where required, and return actionable user feedback. Provider credentials remain disabled until configured. A disabled provider must report unavailable status; it must not pretend delivery succeeded.

The application has no online payment adapter in the initial release. Manual Payment records may store free-text historical channel/reference data but do not represent a gateway transaction.

## Protected file delivery

Private files are stored on the local private disk with generated non-public paths. MySQL stores metadata and contextual links, not file contents.

Upload handling must validate size, MIME type, extension, and authorization; generate the storage name; and record checksum and uploader. A protected download route resolves the Document, authorizes the user against every relevant context, then streams the file with safe headers. Direct public paths are not issued for private content.

Public storage is limited to intentionally public media. Signed URLs, if used, are short-lived and still resolve through an authorized application route. Replacements create DocumentVersion history rather than erasing the prior file until retention policy permits removal.

## cPanel operating limits

- Build Vite assets before deployment; production does not run Node.js.
- Use file cache and file sessions unless the hosting plan provides an explicitly approved alternative.
- Use the sync queue connection; do not run queue:work, Horizon, Supervisor, Redis, or a WebSocket server.
- Keep web requests bounded. Large exports must be constrained by filters/row limits or implemented as explicit scheduled commands only when cPanel Cron is confirmed.
- Use cPanel Cron for schedule:run when available. Provide manual administrative actions when cron is unavailable.
- Store writable data only under storage and bootstrap/cache; the web document root is public.
- Respect PHP upload, memory, and execution limits. Stream downloads and exports where possible.
- Provider timeouts and failures must not lose the local attempt or business transaction history.
- Deployment includes HTTPS, production APP_KEY and secrets, storage permissions, storage link where needed, configuration caching, database migration backup, and post-deploy health checks.

## Testing boundary

Each implemented module requires feature tests for server-side authorization, Form Request validation, normal and failure paths, filters/pagination where lists exist, archive/history behavior, and enabled/disabled Event states. Unit tests cover pure rules and calculations. Database tests cover constraints, indexes, transactions, and rollback behavior.

### Implemented Vendor and Staff master baseline

Prompt 09 adds global `Vendor` and `StaffProfile` aggregates without introducing Event operations. Both permit nullable unique portal `user_id` and nullable `branch_id`; explicit policies and scoped queries support manager-wide access and linked-role own-record access. Vendor owns contacts, service areas, and category links. StaffProfile references a configurable Department. Both reuse protected Documents and audited status transitions. Assignment, scheduling, attendance, work-order, contract, rating, performance, invoice, payment, and salary workflows remain in their later owning modules.

### Implemented Event template and module-registry baseline

Prompt 10 materializes the 29-key catalog in `module_definitions`, with the 18 event-scoped keys eligible for template and future Event selection. The stable key, scope, and event-scoped classification are not browser-editable; label, order, and active availability are configuration. `event_templates` and `event_template_modules` store editable SRS starting points, starter-task/budget/service suggestions, duplication source, archive/status history, and default/optional recommendations.

`EventModuleService` validates keys, returns enabled keys, calculates warning-only dependencies, and creates an immutable initialization plan. Applying a template is explicitly copy-on-create: optional recommendations initialize disabled, manager overrides win, all 18 modules may remain disabled, and later template edits do not change earlier plans or Events. Detailed usage is in `docs/EVENT_TEMPLATES_AND_MODULE_REGISTRY.md`.

### Implemented core Event aggregate and lifecycle

Prompt 11 introduces the central Event aggregate without introducing Booking or any specialized business module. EventService owns direct transactional creation, updates, archive/reactivate, template snapshots, and initialization of all 18 EventModuleSetting rows. EventLifecycleService owns the explicit status graph and client-before-leaving-Draft rule. EventDuplicationService creates an independent planning copy, while EventNoteService, EventTimelineService, and the dedicated append-only EventStatusHistory model keep event-level planning and traceability explicit.

Controllers remain thin: Form Requests validate input and authorize actions, Policies enforce permissions and branch visibility, and services lock the Event for critical writes. Completed, cancelled, and archived Events are operationally read-only. Only the separate events.correct ability can reopen a completed Event to Planning, and it requires a recorded reason. Cancellation likewise records reason, actor, and timestamp.

All 18 optional module settings exist for every Event. Disabled settings remain rows and no module-owned data is deleted. Templates seed an editable checklist in the same creation transaction; dependencies remain warnings and a zero-module Event can complete. Event time input is interpreted in the selected IANA time zone, converted to UTC, and persisted as DATETIME(6); organization-time-zone display is applied at the presentation boundary.

### Implemented Event workspace and activation boundary

Prompt 12 makes the Event detail page the server-rendered operational center without implementing an optional business module. Core summary, Client, lifecycle actions, notes, status history, and timeline are independent of module toggles. Optional workspace cards are projected only from enabled settings that pass `EventPolicy::viewModule`; presentation filtering does not replace policies or middleware.

Every Event-scoped route must use `event.module.enabled`, with a static key for later module routes or the route key for the shared readiness page. The middleware validates the stable key, authorizes Event/module access, and checks the persisted setting before a controller may query module records. Disabled manager requests redirect to Manage Modules; unauthorized/non-manager requests fail without returning module data.

`EventModuleDataDetector` and `EventModuleDataRegistry` provide explicit, bounded presence checks. Each later module that persists Event-owned records registers one detector. A populated disable operation requires general and data-specific confirmations plus a reason, locks the Event/settings transactionally, preserves records, and appends `event_module_change_histories`, audit, and timeline entries. `event_module_settings.origin` preserves template/manual provenance independently of later state changes. Detailed usage is in `docs/EVENT_WORKSPACE.md`.

### Implemented dashboard and global-search baseline

Prompt 13 implements the role-aware Dashboard as a read projection over authorized Event records. `EventQueryService` owns the reusable BranchScope plus date/category/branch/manager and upcoming filters used by both `DashboardService` and `EventController`. The KPI query calculates total, upcoming, ongoing, completed, and cancelled values directly from current Event rows; cards link to the equivalent filtered, paginated list. There is no KPI storage table. Task and finance widgets are deliberately absent until their owning modules provide real records and permissions.

Global search uses `GlobalSearchProvider` implementations resolved from `config/global-search.php`. The coordinator normalizes and bounds input, checks `canSearch` before any provider query, applies per-entity authorization/branch ownership, and limits each result group. Current providers cover Bookings, Events, Clients, Vendors, and Users. Invoice modules can register providers later without changing the controller or Blade view. Search results expose links and short presentation fields only; they do not expose hidden provider counts or issue unauthorized broad queries.

### Implemented optional Booking workflow

Prompt 14 introduces Booking as an optional upstream aggregate; it does not alter the direct Client-to-Event path. BookingWorkflowService owns locked transactional creation, explicit review, one-step approve/confirm, waitlist promotion, rescheduling, cancellation, and idempotent conversion. BookingStatusHistory and BookingChange are append-only records with actor, time, reason, state snapshots, and conflict metadata. The Booking and Event links are nullable and unique when populated.

BookingConflictChecker and BookingFinancialImpactInspector are synchronous service-adapter boundaries. Prompt 15 binds BookingConflictChecker to known Venue allocations: an exact active Venue-name preference is checked against exclusive Event allocations, while unknown free-text preferences remain non-blocking. Only an actor with bookings.override-conflicts can submit an explicit override. Finance impacts still use the no-op adapter until Finance is implemented. Cancellation never deletes finance records; it stores only bounded review flags returned by the finance adapter. No notification action is emitted because no notification service exists yet.

Booking list/detail/action queries combine BookingPolicy, granular permissions, and explicit BranchScope. The Dashboard shows only authorized Booking rows and search registers BookingSearchProvider without exposing unauthorized counts. Conversion presents Event Category, optional Template, editable module suggestions, schedule, manager, and branch before using the established EventService transaction. Details are in `docs/BOOKING_WORKFLOW.md`.

### Implemented Venue management and availability

Prompt 15 adds the global Venue aggregate and Event-owned allocation aggregate without making Venue mandatory. Venue owns spaces, facilities, open-ended optional rate guidance, seating-plan metadata, and protected Document-backed images. Venue type explicitly supports owned and third-party locations. BranchScope is applied in lists, policies, document visibility, and again inside allocation transactions; no global scope can hide administrator data.

`AvailabilityService` is the synchronous allocation boundary. It locks the Event and parent Venue (and selected Space), then checks active allocations against the Event's persisted UTC schedule using half-open intervals. Whole-venue allocations collide with relevant room allocations; different rooms may coexist; non-exclusive allocations coexist only when neither side is exclusive. Cancelled Events and allocations do not reserve availability. An override is a separate permissioned path with mandatory reason, actor, timestamp, audit payload, and append-only allocation status history. The parent lock intentionally serializes the empty-result race where two requests could otherwise both observe availability.

The event-scoped Venue routes use `event.module.enabled:venue`, EventPolicy module authorization, and `venues.allocate`; `VenueAllocationDataDetector` makes populated disablement use the established confirmation/reason flow. Disabling never removes allocations, and re-enabling restores the workspace. Capacity is a visible advisory snapshot using expected guests and the selected Space/Venue capacity. Registered-guest comparison is an extension point for Guest Management rather than a speculative dependency. Availability remains derived, so no duplicate availability calendar table can drift from Event schedules.

Prompt 03 added no business behavior. Prompt 04 added identity/security, Prompt 05 added the shared presentation foundation, Prompt 06 added cross-cutting organization configuration plus simple category master data, Prompt 07 added reusable audit/status/document services, Prompt 08 added the independent Client master, Prompt 09 added Vendor/Staff masters, Prompt 10 added Event template/module configuration, Prompt 11 added the core Event lifecycle, Prompt 12 added the protected workspace/activation boundary, Prompt 13 added derived dashboard/search read models, and Prompt 14 adds only the optional Booking workflow. Specialized operational and financial modules remain deferred. Detailed lifecycle rules are in `docs/CORE_EVENT_LIFECYCLE.md`.

### Implemented Vendor assignments, work orders, and cost boundary

Prompt 16 extends the global Vendor master with Event-owned VendorAssignment records. Assignment scope, category, UTC schedule, quoted/approved DECIMAL(19,4) costs, responsible manager, delivery state, completion evidence, work orders, protected contracts/invoices, and completed-work ratings remain inside the Vendor domain. Generic append-only StatusHistory records and explicit AuditService calls capture operational transitions without observers or hidden side effects.

VendorAvailabilityService is the synchronous scheduling boundary. It locks the Vendor parent before checking unavailable rules and active assignments with half-open intervals. Overlap behavior is explicit data: Vendors default to a reversible warning policy, may be configured to block, and unavailable windows choose warn/block individually. The Event workspace is filterable/paginated and every route uses event.module.enabled:vendors; VendorAssignmentDataDetector ensures disabling a populated module preserves records and requires the established confirmation flow.

Vendor portal linkage remains optional. A linked Vendor-role User may query only assignments owned by that Vendor and may update delivery/work status, manage availability, or upload an invoice. Managers retain assignment, approved-cost, work-order, contract, and rating authority. Contracts and invoices reuse private Documents linked to Event, Vendor, and VendorAssignment, so protected download authorization works for both manager and owner contexts.

ApprovedVendorCostProvider is the only finance-facing boundary introduced here. It exposes approved costs from approved/in-progress/completed assignments as read-only value objects. Vendor invoice rows are source metadata plus protected files; Prompt 16 creates no Expense or Payment. A later Finance module must consume the contract or define an idempotent source-of-truth/posting decision before ledger creation.

### Implemented Staff scheduling and operational records

Prompt 17 extends the independent StaffProfile master with Shifts, Event-owned StaffAssignments, Attendance, LeaveRequests, SalaryRecords, and PerformanceRecords. A nullable Staff user_id remains optional: managers may create every operational row on behalf of an unlinked Staff profile, while linked Staff-role Users receive only their own schedule and narrowly permitted status updates.

StaffAvailabilityService is the synchronous scheduling boundary. It locks the Staff parent and checks active shifts, active Event assignments, and approved leave with half-open intervals. Conflicts block by default. A separate staff.override-conflicts permission plus mandatory reason is required to continue, and the persisted override actor/reason/conflict details are audited. Archived or separated Staff cannot receive new work, but restrictive foreign keys preserve every historical operational row.

Event Staff routes use event.module.enabled:staff, Event/assignment policies, and StaffAssignmentDataDetector. Disabled-module access is denied without deleting assignments; re-enable restores access. Attendance is a reporting input keyed by Staff/date and optional Event/assignment. Salary access has its own finance-grade permissions and the UI states its limited scope: operational salary/payment tracking only, with no tax, deduction, benefits, payslip, filing, or statutory payroll behavior. Detailed rules are in docs/STAFF_OPERATIONS.md.

### Implemented Task management and derived Calendar

Prompt 18 adds the Event-owned Task aggregate with User/StaffProfile assignments, comments, protected attachments, dedicated append-only status history, progress, completion actor/time, and non-destructive archive metadata. Deadlines are UTC instants displayed through organization/Event time zones. Overdue is derived from deadline and terminal state; there is no drifting overdue column. TaskPolicy and TaskAccessService separate manager-wide Event access from linked-Staff assigned-only access.

Every Task route uses event.module.enabled:tasks. TaskDataDetector participates in safe disablement, so disabling hides navigation, direct routes, and normal Calendar projection while retaining all task rows, comments, attachments, and history. Task Documents are linked only to the Task and DocumentAccessService reuses the same Task visibility query before authorizing a download.

CalendarService is a global read projection over Event dates, active Venue allocations, Staff shifts/assignments, Vendor assignments, and Task deadlines. Daily, weekly, and monthly windows are bounded, converted from organization time to UTC, filtered by BranchScope/source permission/portal ownership, and de-duplicated by source type/id. Each entry links to the authorized source route. No calendar_events, schedule_entries, synchronization job, queue, or socket is introduced. Detailed behavior is in docs/TASKS_AND_CALENDAR.md.

### Implemented Budget and financial ledger foundation

Prompt 19 adds one optional EventBudget with Event-owned BudgetLines and separate Event-owned Income and Expense actual ledgers. All monetary values and currency snapshots follow the `DECIMAL(19,4)`/`CHAR(3)` conventions. Template suggestions copy into a newly created Draft budget but do not force the Budget module or change the immutable Event template snapshot. Approval locks the current planning baseline; future revision requirements must add versioned supersession rather than overwrite an approved baseline.

FinanceService is the only authoritative posting/reversal/calculation boundary. Draft actual entries may be edited or voided. Posting records approver/poster and status history and makes the business fields immutable through normal application paths. Reversing a posted entry creates an equal posted reversal linked to the posted original; recognized totals algebraically include both, so the correction nets to zero without changing or deleting history. Recognized Event profit is posted income less posted expense. Draft and Void rows contribute zero.

Operational integrations use the nullable `source_type`/`source_id` pair with an Event-scoped unique constraint in each ledger. Repeated calls with the same source identity are idempotent. Vendor, Catering, Transportation, Accommodation, Inventory, Ticket, Payment, and Invoice modules must create ledger rows only through this explicit boundary; they must not copy approved amounts into independent totals. The protected evidence reference accepts only a Document already linked to the Event.

Planning and actual permissions are separate. Every Event finance route also requires Event visibility and `event.module.enabled:budget`. BudgetDataDetector makes populated disablement use the existing confirmation/reason workflow; records remain available after re-enable. Details and metric definitions are in `docs/BUDGET_AND_FINANCE_LEDGER.md`.

### Implemented manual Payment and Refund ledger

Prompt 20 adds Event- and Client-owned Payments, PaymentSchedules, PaymentAllocations, and Refunds. Each Payment is a manually entered positive receipt for an advance, installment, partial, or final payment. A unique receipt and client-supplied idempotency key protect duplicate submission; channel/reference remains free text and cannot invoke a provider. Payment and allocation rows are immutable after posting and no normal delete route exists.

PaymentService is the locked transactional boundary. It locks Event, Client, and sorted schedule targets, rechecks outstanding amounts, creates allocations, appends status/audit history, and calls FinanceService to create exactly one posted Income linked by source type/ID. Refunds are separate positive records; FinanceService creates their posted Income reversals, so recognized profit remains based on the Prompt 19 ledger rather than copied Payment totals.

Due is currently derived from active PaymentSchedules less allocations net of allocation-linked Refunds. Unallocated Payment value remains a client advance/credit and cannot make due negative. A Refund either identifies one allocation and reopens that obligation, or consumes only unallocated credit. Corrections are Refund plus replacement Payment, preserving the original receipt.

Prompt 21 adds restrictive Invoice foreign keys to Payment, PaymentSchedule, and PaymentAllocation. Allocations accept only an issued Invoice for the same Event, Client, and currency while the Invoice module is enabled; excess receipt value remains unallocated credit. Allocation-linked Refunds reduce the applied total and reconcile the Invoice back to Partially Paid or Issued. Event routes use `event.module.enabled:payments`, while Client history and global due projections include only authorized enabled Events. PaymentDataDetector preserves populated module data across disable/re-enable. Details are in `docs/MANUAL_PAYMENTS.md`.

### Implemented Invoice snapshots and delivery

Prompt 21 adds Event- and Client-owned Invoices, immutable items, prefix/year sequence rows, status history, and email delivery attempts. Booking remains an optional nullable link restricted to the Booking already attached to the Event. InvoiceService is the locked transaction boundary for Draft creation/update, issue, cancellation, and credit notes. Issuing snapshots seller/Client identity, currency, descriptions, quantities, unit prices, discount, tax, and totals. After issue, only reconciled status and traceable cancellation/credit fields may change; no normal delete path exists.

InvoiceCalculator performs decimal-string multiplication and four-decimal half-up rounding. The reversible default is line discount before tax. InvoiceBalanceService derives applied value from PaymentAllocations net of allocation-linked Refunds, floors balance at zero, and reconciles Issued/Partially Paid/Paid without posting duplicate Income. Overdue is a due-date projection rather than stored state.

InvoicePdfService renders only stored snapshot values with remote loading and embedded PHP disabled. InvoiceDeliveryService sends synchronously through Laravel mail, attaches the PDF, retains an immutable success/failure attempt, logs failures, and returns user-visible validation feedback. Invoice routes combine granular permissions, Event visibility, and `event.module.enabled:invoices`; InvoiceDataDetector preserves data across disable/re-enable. Client history and InvoiceSearchProvider use the same visibility boundary. Details are in `docs/INVOICES.md`.

### Implemented notifications and synchronous communication boundary

Prompt 22 implements global stored Notifications and the event-scoped Communications workspace. `NotificationService` accepts trusted user or role targets, materializes active role members into `notification_recipients`, and stores only allowlisted Laravel route names plus parameters. Each recipient owns an independent `read_at`; opening a notification does not bypass the destination policy or module middleware. The top-bar count and inbox query only the authenticated recipient. Notification content is escaped by Blade.

`CommunicationService` snapshots Event, Client, optional Booking, channel, purpose, subject/body, recipient, and consent basis before calling a channel adapter synchronously. Every attempt appends a `delivery_logs` row and preserves Pending/Sent/Failed state; provider exceptions become bounded error codes instead of raw credential/network output. Email uses Laravel mail and is locally testable through array/log transports. SMS and WhatsApp implement the same interface but remain unavailable until separately approved providers and secrets exist. Marketing requires explicit consent confirmation independent of operational-message authority.

`ReminderService` provides idempotent Event, payment-due, manual, and vendor-contract-expiry checks. Artisan commands are safe to run manually and are registered with Laravel Scheduler for cPanel cron. There is no queue, worker, daemon, polling loop, WebSocket, browser push, or duplicate calendar table. The `communications` module uses the standard Event permission + enabled-module middleware + data detector boundary; disablement preserves messages and reminder schedules. Inventory owns low-stock alert creation when that module is implemented.

### Implemented Guest planning and one-time check-in

Prompt 23 implements the Event-owned Guest aggregate without introducing Guest login identities. Guest, GuestGroup membership, Invitation, RSVP, SeatAssignment, GuestNote, and append-only GuestCheckIn records carry direct Event ownership so Event isolation remains explicit. Normalized search fields and an optional external reference make later imports possible without conflating shared family contact details.

`GuestInvitationService` owns token issue/revocation and status/audit history. It creates a 256-bit base64url secret, stores only a SHA-256 lookup hash plus a Laravel-encrypted recoverable value, and revokes the previous active Invitation when reissuing. `GuestQrCodeService` renders a local SVG containing only an authorized Event lookup URL and opaque token. The verified `endroid/qr-code` 6.0.9 dependency under `^6.0` works on PHP 8.2 and avoids an external provider or GD requirement.

`GuestCheckInService` constrains token lookup by Event, rejects expired/revoked/wrong-Event credentials uniformly, locks the Invitation during attendance writes, and creates at most one append-only check-in row per Guest. A repeated scan returns the original check-in time/operator. Front Desk has a narrow permission set; QR retrieval, Guest changes, Invitation issue, private notes, and seating remain separately authorized.

Every Guest route uses `event.module.enabled:guests`, Event visibility, and GuestPolicy/Form Request checks. `GuestDataDetector` preserves populated records through disable/re-enable. `GuestCapacityService` optionally reads the active Venue allocation and emits an advisory when confirmed party counts exceed capacity; it neither requires Venue nor blocks RSVP/Event completion. Detailed rules are in `docs/GUEST_MANAGEMENT.md`.

### Implemented Registration forms and submissions

Prompt 24 implements Event-owned Registration forms independently of Ticketing. `RegistrationForm` owns an ordered, allow-listed definition of `RegistrationField` records; public and manager-entered submissions create one `Registration` plus immutable `RegistrationResponse` snapshots. Later label, option, ordering, or validation changes therefore affect future input only and cannot rewrite prior answers. Dynamic validation is generated by `RegistrationValidationService` from bounded structured constraints; administrators cannot store executable validation rules or HTML.

Public access requires an active published form, a 48-character random slug, an enabled Registration module, an operational Event, CSRF validation, and request throttling. `RegistrationSpamGuard` is a replaceable synchronous extension point whose initial implementation rejects a hidden honeypot. Duplicate handling is configurable per form and defaults to blocking a second non-rejected submission with the same normalized email. Idempotency keys prevent request retries from creating duplicate rows.

`RegistrationWorkflowService` locks each pending submission, records explicit Approved/Rejected status history plus audit evidence, and dispatches configured operational confirmation synchronously through `CommunicationService`. Disabled/missing channels fail or skip visibly without fabricating delivery. Approval-free forms use an explicit system-actor transition. CSV output streams stored snapshots through the same Event/policy/module boundary.

Guest creation is an explicit post-approval action through `RegistrationGuestService`; it is idempotent, requires Guest permissions and the Guest module, and may link an Event-owned existing Guest without overwriting populated contact details. Registration never enables or requires Ticketing. `RegistrationDataDetector` preserves all form/submission/history data through disable/re-enable.

### Implemented general-admission Ticketing boundary

Prompt 25 implements Event-owned TicketType, PromoCode, TicketOrder, Ticket, TicketValidation, and TicketRefund records. TicketService is the synchronous transaction boundary: it locks the Event, Ticket Type, Promo Code, and optional posted Payment as needed; rechecks sale windows, capacity, promo ticket-use limits, and unallocated manual funds; then creates immutable order/Ticket price snapshots with one idempotency key. Availability is derived from Issued/Used Ticket rows, so no duplicate inventory counter can drift. Refunds and cancellations release quantity but never delete history.

Each Ticket uses a 256-bit base64url credential. Only SHA-256 is indexed for lookup; an encrypted copy supports authorized re-rendering. The local QR renderer contains only a private public Ticket URL, never attendee data. Event-constrained locking creates at most one append-only TicketValidation. Used, refunded, cancelled, wrong-Event, and unknown credentials fail safely; a repeat successful scan reports the original validation.

Priced issuance is cash-first: the order references enough unallocated value in an existing posted Payment. Refunds call PaymentService synchronously and link TicketRefund to its manual financial Refund/Income reversal; free refunds remain zero-value operational records. There is no gateway, checkout, webhook, card storage, seat allocation, queue, or duplicated Finance total.

Every manager route uses Event visibility, granular TicketPolicy permissions, and `event.module.enabled:ticketing`; TicketDataDetector preserves populated data through disable/re-enable. Public catalogue, Ticket, and QR routes require both an enabled module and explicit Event ticket publication. Email uses the existing synchronous CommunicationService and records success/failure. Registration remains independent when Ticketing is disabled. Detailed rules are in `docs/TICKETING.md`.

## Architecture decision records

- ADR 0001 records Laravel 12 on PHP 8.2.
- ADR 0002 records the synchronous shared-hosting runtime with no queues, WebSockets, or payment gateway.

See docs/adr/0001-laravel-12-php-82.md and docs/adr/0002-synchronous-shared-hosting-runtime.md.
