# Event Management System Architecture Baseline

## Purpose and status

This document fixes the application boundaries for feature development. It defines how the Laravel 12 application is organized, how the Event-centered optional-module model works, and which infrastructure choices are allowed on the cPanel target.

Prompt 03 established the architecture baseline. Prompts 04 through 06 implement identity/RBAC, the reusable administration UI, and organization/settings/master-data foundations. No Event operational module described below is implemented merely because its boundary, navigation concept, or future class location appears here.

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

Company, Branch, and User implement the reusable attachment relationship. Event, Client, Vendor, Booking, and other future owners are intentionally absent from the link allowlist until their modules exist. Document expiry alerts and malware-scanner integration remain later responsibilities. Detailed usage is in `docs/AUDIT_AND_PROTECTED_DOCUMENTS.md`.

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

Prompt 03 added no business behavior. Prompt 04 added identity/security, Prompt 05 added the shared presentation foundation, Prompt 06 added cross-cutting organization configuration plus simple category master data, Prompt 07 added reusable audit/status/document services, and Prompt 08 adds the independent Client master. Event operational routes and tables remain deferred. Architecture contract tests continue to validate the baseline and stable module keys.

## Architecture decision records

- ADR 0001 records Laravel 12 on PHP 8.2.
- ADR 0002 records the synchronous shared-hosting runtime with no queues, WebSockets, or payment gateway.

See docs/adr/0001-laravel-12-php-82.md and docs/adr/0002-synchronous-shared-hosting-runtime.md.
