# Event Management System Database Roadmap

## Purpose and implementation status

This roadmap defines proposed MySQL tables and migration order before domain migrations begin. It is a design contract, not evidence that the listed business tables exist.

Implemented now:

- Laravel migration repository table.
- users, password_reset_tokens, and sessions from the Laravel user baseline migration.
- cache and cache_locks from the Laravel cache baseline migration.
- User model, UserFactory, and DatabaseSeeder from the Laravel scaffold.

Prompt 03 created no business migration, model, factory, or seeder. Prompt 04 implements only the identity/security tables identified below; all Event Management business tables remain planned.

Prompt 05 is presentation-only and introduces no migration, table, model, factory, or seeder.

Prompt 06 implements `companies`, `branches`, `branch_user`, `system_settings`, `event_categories`, `vendor_categories`, `inventory_categories`, `finance_categories`, and `document_categories`. It does not implement Events, Vendors, Inventory Items, finance ledgers, Documents, provider credentials, or any later module behavior.

Prompt 07 implements `status_histories`, `documents`, `document_versions`, and `document_links`, and hardens the existing `audit_logs` and `login_histories` as append-only Eloquent records. Prompt 08 enables Client links, and Prompt 09 enables Vendor and StaffProfile links. Event/Booking contexts remain unavailable until those owner tables and policies exist.

Prompt 08 implements `clients` and `client_contacts`, enables Client document links, and reuses generic status/audit history. Events, Bookings, Invoices, Payments, and Communications are now implemented by their owning prompts; the Client detail summary activates only when each table exists and the viewer has its permission.

Prompt 09 implements `vendors`, `vendor_category_vendor`, `vendor_contacts`, `vendor_service_areas`, `departments`, and `staff_profiles`. The global master records, nullable account links, search indexes, archive metadata, audit/status history, and protected document contexts are implemented. Event assignments and every work, schedule, payroll, contract, invoice, payment, rating, and performance table remain planned.

Prompt 10 implements `module_definitions`, `event_templates`, and `event_template_modules`. All 29 stable keys are materialized, while only the 18 event-scoped keys are valid template/Event toggles. Template configuration, duplicate ancestry, archive/status history, JSON starter suggestions, and warning-only dependencies are implemented. `events` and `event_module_settings` remain planned for Prompt 11.

Prompt 11 implements `events`, `event_module_settings`, `event_status_histories`, `event_notes`, and `event_timeline_items`. The Event is central, direct creation keeps `booking_id` nullable with no Booking dependency, all 18 module-state rows are created transactionally, and lifecycle/correction/cancellation history is append-only. `event_media` and every Booking, assignment, attendance, ticket, finance, and specialized module table remain planned.

Prompt 12 adds `event_module_change_histories` and the `event_module_settings.origin` column. Origin preserves template/manual initialization provenance while `source` may record later manual changes. Each actual enable/disable change retains prior/new state, actor, reason, data-presence result, and microsecond timestamp. No specialized module table is added.

Prompt 13 adds no data-owning table and stores no KPI/search projection. It adds measured lookup indexes on `events.name` and `users.name`; existing unique reference/email indexes and normalized Client/Vendor indexes support the other provider paths. Dashboard values and global-search results remain live, bounded, authorization-scoped queries.

Prompt 14 implements `bookings`, `booking_status_histories`, `waitlist_entries`, and `booking_changes`. It adds the restrictive foreign key and unique constraint for nullable `events.booking_id`, while `bookings.event_id` remains nullable and unique. Booking requests use UTC `DATETIME(6)`, `DECIMAL(19,4)`, branch/category/client indexes, append-only history/change rows, and non-destructive cancellation flags.

Prompt 15 implements `venues`, `venue_spaces`, `venue_facilities`, `venue_rates`, `seating_plans`, `venue_media`, `event_venue_allocations`, and `event_venue_allocation_status_histories`. Availability is derived from Event start/end plus active allocations; no duplicate `venue_unavailability` projection is stored. Venue and child capacities are nullable, rate types remain open-ended strings, per-Event quoted prices use `DECIMAL(19,4)`, branch links are nullable, and allocation overrides/status changes retain actor/time/reason. Venue protected-image Documents are linked through the existing polymorphic allowlist. Payment, Invoice, Guest, and Notification tables remain unimplemented.

Prompt 16 adds vendor_availabilities, vendor_assignments, vendor_work_orders, vendor_contracts, vendor_ratings, and vendor_invoices, plus a configurable Vendor overlap policy. Event ownership is mandatory for assignments; assignment schedules use UTC instants and indexed half-open overlap queries; costs/invoice amounts use DECIMAL(19,4). Contracts and vendor invoices reference protected Documents and never create finance ledger rows. Generic status histories capture assignment/work-order/contract/invoice changes. Actual Expenses and Payments remain unimplemented.

Prompt 17 adds shifts, staff_assignments, attendances, leave_requests, salary_records, and performance_records. Event ownership is mandatory for Staff assignments and optional for reusable shifts/attendance/performance context. Schedule windows use UTC DATETIME(6) and indexed half-open conflict queries; approved DATE-based leave affects availability. Salary amount uses DECIMAL(19,4), has separate permissions, and is an operational tracking record rather than statutory payroll. Generic status histories cover shifts, assignments, leave, and salary payment state.

Prompt 18 adds `event_tasks`, `task_assignments`, `task_comments`, `task_attachments`, and `task_status_histories`. Every Task requires `event_id`; assignees may be a User or StaffProfile; deadlines use UTC DATETIME(6); completion and archive retain actor/time; overdue is derived. The Calendar remains a bounded read projection and adds no table.

Prompt 19 adds `event_budgets`, `budget_lines`, `event_income_entries`, and `event_expense_entries` while reusing configurable `finance_categories`. Planning and actual values are separate. Actual rows use Draft/posted/Void state, actor/timestamps, optional protected evidence, optional Client/Vendor, and Event-scoped source identity. Posted corrections append an equal posted reversal and never mutate the original amount. Payment and Invoice tables remain planned.

Prompt 20 adds `payments`, `payment_schedules`, `payment_allocations`, and `refunds`. Payments and Refunds are immutable positive records; Payment posting creates one source-linked posted Income and Refund posting creates one source-linked Income reversal. Schedules own optional planned due obligations, allocations are append-only, and status/audit history retains actor/time. Prompt 21 now connects the nullable indexed `invoice_id` columns with restrictive foreign keys and enables validated Invoice allocations.

Prompt 21 adds `invoice_sequences`, `invoices`, `invoice_items`, `invoice_status_histories`, and `invoice_deliveries`. Invoices require Event and Client, keep Booking/Branch nullable, use `DECIMAL(19,4)` snapshots, derive Overdue, and retain cancellation/credit/email evidence. Restrictive foreign keys now connect the Prompt 20 nullable Invoice references after a preflight rejects unmatched legacy IDs. Sequence scope/prefix/year is unique and locked for issue. Issued snapshots and items have no destructive application path.

## Database-wide conventions

### Engine, character set, and identifiers

- All business tables use InnoDB and utf8mb4 with the project collation.
- Internal primary keys use unsigned BIGINT auto-increment values unless a later measured requirement justifies another type.
- Public QR, invitation, ticket, download, or validation tokens use a separate unique non-guessable UUID, ULID, or cryptographic token. Internal numeric IDs are not exposed as security tokens.
- Foreign-key columns use the same unsigned BIGINT type as their parent.
- Stable module keys use VARCHAR(64) and are never renamed after production data exists.

### Required nullable links

- events.booking_id is always nullable. Prompt 14 adds a restrictive foreign key and unique index so direct Events remain unlinked while one converted Booking can own at most one Event.
- events.client_id may be nullable only while the Event is Draft; the transition service requires a Client before leaving Draft.
- clients.user_id, vendors.user_id, and staff_profiles.user_id are nullable and unique when populated.
- branch_id is nullable unless branch mode is enabled. Branch-scoped validation becomes mandatory only after that mode is explicitly enabled.
- Optional template, venue, vendor, staff, guest, registration, ticket, document, and provider references are nullable when their related workflow is not used.

### Event ownership

Every record containing Event-specific operational or financial data has a non-null event_id foreign key. Global master records such as vendors, staff_profiles, venues, inventory_items, menus, vehicles, hotels, and message_templates do not require event_id; their assignment, allocation, plan, reservation, or attempt tables do.

Event module tables must not use a nullable event_id as a way to store unrelated global data. If a concept has both a master and Event use, it is split into a global master table and an Event-scoped linking table.

### Money and quantities

- Monetary amounts use DECIMAL(19,4); never FLOAT or DOUBLE.
- Currency codes use CHAR(3) and are snapshotted on issued financial records.
- Percent rates such as tax and discount rates use DECIMAL(9,6).
- General quantities use DECIMAL(19,4) when fractional units are possible and unsigned integer columns for indivisible counts such as tickets or seats.
- Authoritative totals are calculated with decimal arithmetic in services, rounded by configured rules, and stored as immutable invoice or posting snapshots where required.
- Negative values are not overloaded to mean refund unless the specific ledger design defines that convention. Prefer explicit adjustment/refund type and relationship columns.

### Dates, timestamps, and time zones

- Store absolute instants as UTC DATETIME(6) columns with names ending in _at.
- Use DATE for calendar-only values and TIME(6) only when an independent time-of-day is meaningful.
- Events store an IANA timezone name and UTC start/end instants so daylight-saving conversions are reproducible.
- Display and input convert through the organization or Event timezone; database session timezone remains UTC.
- created_at and updated_at use microsecond precision where practical. Historical rows also record the business-effective timestamp instead of relying only on created_at.

### Status and audit history

Every status that drives workflow has a current status column plus an append-only history table containing:

- parent identifier;
- from_status and to_status;
- actor_type;
- actor_user_id, nullable only for a documented system action;
- changed_at in UTC;
- reason or comment where the transition needs one;
- metadata JSON for non-sensitive structured context.

The service updates the parent and inserts history in one transaction. User actions require an actor. Audit logs redact secrets, credentials, full payment details, and sensitive personal/dietary data.

### Foreign-key deletion behavior

| Relationship type | Default behavior |
| --- | --- |
| Optional login identity | nullOnDelete for Client, Vendor, or Staff user_id after account deactivation/deletion policy permits |
| Historical business parent | restrictOnDelete; archive or soft-delete the parent |
| Pure owned draft child | cascadeOnDelete only when the parent is physically purged under an approved retention operation |
| Optional descriptive master | nullOnDelete only if the historical row stores an adequate snapshot |
| Finance, status, audit, validation, or stock ledger | Never cascade during normal operation; void/reverse/archive instead |

## Central relationship plan

The planned events table is the center of operational data. Its core columns include:

- id;
- client_id nullable only for Draft;
- booking_id nullable and added in the Booking migration group;
- event_category_id;
- event_template_id nullable;
- branch_id nullable;
- name, status, description, expected_guest_count, theme, dress_code;
- starts_at, ends_at, and timezone;
- manager_user_id nullable where the single administrator implicitly manages the Event;
- currency_code;
- completed_at, cancelled_at, cancellation_reason;
- created_by_user_id and updated_by_user_id;
- timestamps and soft deletion/archive metadata.

Core indexes include status plus starts_at, client_id plus status, branch_id plus starts_at, manager_user_id plus status, event_category_id plus starts_at, and booking_id unique when populated if one Booking converts to at most one Event.

module_definitions contains all 29 stable module keys plus scope, event-scoped eligibility, active availability, display label, and order. event_module_settings contains event_id, module_key, is_enabled, immutable initialization origin, latest-change source, enabled_by_user_id, enabled_at, disabled_by_user_id, disabled_at, and timestamps. It has a unique key on event_id plus module_key. event_module_change_histories is append-only and indexed by Event/time, module/time, and actor/time. Disabling updates state and appends module/audit/timeline history; it never cascades into module data.

## Module table roadmap

Status values distinguish implemented framework/identity foundations from planned business tables. An implemented identity baseline does not imply that an Event Management business module is complete.

| SRS module and key | Scope | Status | Proposed tables |
| --- | --- | --- | --- |
| M01 dashboard | Global projection | Planned | No source table. Optional saved_filters after a demonstrated need; metrics derive from source tables. |
| M02 users | Global | Identity/RBAC and branch assignment foundation implemented | users account controls, roles, permissions, role_user, permission_role, login_histories, user_status_histories, and branch_user are implemented. Optional user_profiles remain planned. |
| M03 events | Core | Core lifecycle implemented in Prompt 11 | event_categories, module_definitions, event_templates, event_template_modules, events, event_module_settings, event_status_histories, event_timeline_items, and event_notes are implemented. Event media and all optional operational/financial tables remain planned. |
| M04 clients | Global master | Implemented in Prompt 08 | clients and client_contacts are implemented. `type` distinguishes individual/organization; user_id and branch_id are nullable; normalized search columns are indexed; archive/reactivate/merge preserve history and restrictive downstream references. |
| M05 booking | Optional upstream | Implemented | bookings, booking_status_histories, booking_changes, waitlist_entries; nullable unique Event links preserve direct creation. |
| M06 venue | Event-scoped | Implemented in Prompt 15 | venues, venue_spaces, venue_facilities, venue_rates, seating_plans, venue_media, event_venue_allocations, and allocation status history. Allocations require event_id; availability is derived. |
| M07 vendors | Global master plus Event work | Operational scope implemented in Prompt 16 | vendors, categories/pivot, contacts, service areas, availabilities, assignments, work orders, protected contract links, ratings, and vendor invoice metadata are implemented. Assignments require event_id; actual Expenses and Payments remain in Finance. |
| M08 staff | Global master and practical operations implemented in Prompts 09/17 | Implemented operational baseline | staff_profiles, departments, shifts, staff_assignments, attendances, leave_requests, salary_records, and performance_records are implemented. Event assignments require event_id; salary remains permission-isolated operational tracking, not statutory payroll. Staff contracts/invoices are not part of the approved Prompt 17 scope. |
| M09 guests | Event-scoped | Implemented in Prompt 23 | guests, guest_groups, guest_group_members, invitations, rsvps, guest_check_ins, seat_assignments, guest_notes. Direct event_id ownership, restrictive foreign keys, normalized search indexes, encrypted/hashed Invitation credentials, one current RSVP/seat/check-in per Guest, and append-only status/audit evidence enforce Event isolation and history. |
| M10 ticketing | Event-scoped | Implemented in Prompt 25 | ticket_types, promo_codes, ticket_orders, tickets, ticket_validations, ticket_refunds. Each record has direct event_id; one Ticket Order owns one snapshotted Ticket Type/quantity, so speculative order-line/redemption tables were not added. Promo snapshots and issued Ticket rows retain historical price/redemption evidence. |
| M11 registration | Event-scoped | Implemented in Prompt 24 | registration_forms, registration_fields, registrations, registration_responses, registration_status_histories. Forms and submissions require event_id; public slugs and submission idempotency keys are unique; responses snapshot key/label/type/value; status history is append-only; optional Guest and outbound-message links are restrictive. Ticketing is not a dependency. |
| M12 tasks | Event-scoped | Implemented in Prompt 18 | event_tasks, task_assignments, task_comments, task_attachments, and dedicated append-only task_status_histories. Every Task requires event_id; User/Staff assignees are explicit; overdue is derived. |
| M13 budget | Event-scoped | Implemented in Prompt 19 | finance_categories, event_budgets, budget_lines, event_income_entries, and event_expense_entries. Every budget and actual entry requires event_id; posted reversals preserve immutable history. |
| M14 payments | Event-scoped | Implemented in Prompt 20 and integrated in Prompt 21 | payments, payment_schedules, payment_allocations, refunds. Payment requires event_id/client_id, posted rows are immutable, schedules derive due, overpayment remains unallocated credit, Refunds preserve originals, Income source links provide recognized financial truth, and nullable Invoice links now have restrictive foreign keys. |
| M15 invoices | Event-scoped | Implemented in Prompt 21 | invoice_sequences, invoices, invoice_items, invoice_status_histories, invoice_deliveries. Invoice requires event_id/client_id, booking_id and branch_id remain nullable, issued financial/party snapshots are immutable, Overdue is derived, allocations reconcile balance/status, and credit notes preserve corrections. |
| M16 inventory | Global master plus Event work | Planned | inventory_categories, inventory_items, stock_movements, inventory_reservations, inventory_issues, inventory_returns, damage_reports. Event reservations/issues/damage carry event_id. |
| M17 catering | Event-scoped | Planned | menus, meal_packages, catering_plans, catering_plan_items, dietary_preferences, kitchen_schedules. Plans and sensitive dietary summaries require event_id. |
| M18 decoration | Event-scoped | Planned | decoration_plans, decoration_elements, decoration_media, decoration_resources. Plans and resource links require event_id. |
| M19 transportation | Global master plus Event work | Planned | vehicles, drivers, transport_routes, transport_assignments, pickups, fuel_costs. Assignment, pickup, and Event cost rows require event_id. |
| M20 accommodation | Global master plus Event work | Planned | hotels, hotel_rooms, accommodation_bookings, room_allocations, guest_stays, room_charges. Booking/allocation/charge rows require event_id. |
| M21 marketing | Event-scoped | Planned | marketing_campaigns, campaign_recipients, consent_records, coupons, coupon_redemptions, referrals, campaign_metrics. Campaigns and attribution rows require event_id where applicable. |
| M22 communications | Event-scoped capability | Implemented in Prompt 22 | message_templates, outbound_messages, message_recipients, delivery_logs, reminder_schedules. Event communication rows carry event_id with nullable Client/Booking context; templates are global; attempts are append-only and provider errors are sanitized. |
| M23 calendar | Global projection | Implemented in Prompt 18 | No data-owning table. Daily/weekly/monthly views query authorized Events and enabled Venue/Staff/Vendor/Task sources with UTC windows. |
| M24 documents | Event-scoped shared service | Shared service and Client/Vendor/Staff/Event/Booking ownership implemented | document_categories, documents, document_links, and document_versions use private storage and explicit allowlisted owner types. |
| M25 reports | Global projection | Planned | No reporting ledger. Optional report_definitions and saved_report_filters; synchronous exports are generated from source queries and are not queue jobs. |
| M26 analytics | Global projection | Planned | metric_definitions and metric_snapshots only after measured need. Snapshots are rebuildable and never replace transactions. |
| M27 notifications | Global | Implemented in Prompt 22 | notifications and notification_recipients. Optional source type/id and event_id links are retained; role targeting is materialized to users so read_at belongs to one recipient; idempotency keys prevent duplicate scheduled alerts. |
| M28 settings | Global | Company, branches, and typed settings implemented; integrations/security/backup planned | companies, branches, branch_user, and system_settings are implemented. integration_credentials, backup_records, api_keys, and two_factor_methods remain planned. branch_id remains nullable outside branch records until branch mode is enabled. |
| M29 audit | Global cross-cutting | Prompt 07 baseline implemented | audit_logs and login_histories are append-only through model guards, have protected filter/detail screens, and central audit metadata redaction. Generic status_histories are implemented. Broader security_events and later module-specific audit context remain planned. |

## Key and index roadmap

Laravel automatically indexes most foreign keys, but common filtered access needs deliberate composite indexes.

| Area | Planned indexes and uniqueness |
| --- | --- |
| Event | implemented indexes include name, status/starts_at, client_id/status, branch_id/starts_at, manager_user_id/status, category/starts_at, and archived_at/status; retain indexed nullable booking_id and add a unique constraint only if future conversion is one-to-one |
| Module state | unique event_id/module_key; index module_key/is_enabled |
| Client/Vendor/Staff/User | unique nullable profile user_id; normalized master-data name/email/phone indexes, branch_id/status indexes, and the implemented users.name plus unique users.email indexes |
| Booking | client_id/status/requested_start_at; branch_id/status; waitlist Event/date ordering; optional unique converted Event link |
| Scheduling | event_id/start/end and resource_id/start/end composites for venue, staff, vendor, vehicle, and room conflict queries |
| Status history | parent_id/changed_at; actor_user_id/changed_at |
| Invoice | unique invoice_number; unique sequence scope plus next value; event_id/status, client_id/status, due_date/status |
| Payment | unique receipt_number and nullable unique idempotency_key; event_id/status/received_at, client_id/status/received_at, invoice_id/status; schedule event_id/status/due_date and client_id/status/due_date; allocation payment/schedule and invoice/time; refund payment/event/client plus status/refunded_at |
| Ticket/QR | unique public token/code; event_id/status; ticket_type_id/status; validation ticket_id/validated_at |
| Inventory | item_id/occurred_at, event_id/item_id/status; unique idempotency/source reference for generated movements |
| Communication | event_id/created_at, recipient/status, provider/status/attempted_at |
| Registration | unique form public_slug, unique registration reference/idempotency key, form/state/published, form/field-key and field order, event/status/submitted_at, form/normalized-email, registration/history changed_at |
| Ticketing | unique Event/type code and Event/promo code; type Event/status/public and sale window; unique order reference/idempotency and Event/status/time; unique Ticket number/token hash plus Event/type/status; one successful validation and one refund per Ticket; Event publication slug/time |
| Documents | checksum where deduplication is allowed; document links context_type/context_id; expiry_date/status |
| Audit/Login | subject_type/subject_id/created_at, actor_user_id/created_at, event_id/created_at, action/created_at, login user_id/attempted_at |

Index names must stay within MySQL limits. Migrations use explicit names for long composite indexes. Full-text indexes are added only after search behavior and language needs are measured.

## Archive and immutability rules

| Record family | Rule |
| --- | --- |
| Users, Clients, Vendors, Staff, Venues, Hotels, Vehicles, Inventory items | Deactivate/archive and optionally soft-delete; keep historical relationships |
| Events and Bookings | Soft-delete/archive only after policy checks; cancellation/completion remains a status, not deletion |
| Templates and categories | Archive; do not break past Event snapshots or keys |
| Event module settings | Retain enable/disable actor/time; module data survives disablement |
| Tasks and operational plans | Soft-delete or archive if they have history; status history remains |
| Payments, allocations, refunds, invoices, invoice lines, posted income/expense | Append, void, credit, refund, or reverse; never hard-delete through normal UI |
| Stock movements, ticket validations, check-ins | Append-only corrections with linked reversal where required |
| Documents | Archive metadata and retain versions until retention policy allows controlled file removal |
| Audit, login, and security history | Append-only to normal users; retention purge is a privileged scheduled/manual operation with its own audit evidence |

## Migration groups and order

Migration filenames use increasing timestamps and each group must pass migrate, rollback where safe, and clean-database tests before the next group begins.

| Group | Phase | Contents and dependency order |
| --- | --- | --- |
| G00 Framework baseline | Implemented | users/password resets/sessions, cache/cache locks, migrations repository |
| G10 Organization, identity, and security | Partially implemented in P1 | Prompts 04, 06, and 07 implement user account controls, roles, permissions, append-only audit/login/status history, protected audit views, companies, branches, assignments, and typed settings. Optional profiles, broader security events, provider credentials, and backup/security records remain planned. |
| G20 Independent master data | Partially implemented in P1 | Prompt 06 implements Event, Vendor, Inventory, Finance, and Document category tables. Prompt 08 implements Clients/contacts. Prompt 09 implements Vendors/contacts/service areas and Departments/Staff profiles. Other domain masters remain planned. |
| G30 Event configuration and core | Partially implemented in P1 | Prompt 10 implements module definitions, Event templates, and template module suggestions. Prompt 11 implements Events, all per-Event module settings, dedicated status history, notes, and timeline. Prompt 12 adds immutable module-change history, origin provenance, middleware enforcement, and the data-detector contract. Prompt 13 adds Event/User search indexes but no projection table. Event media remains planned. |
| G40 Protected documents | Partially implemented in P1-P2 | Private documents, immutable versions, allowlisted links, expiry metadata, archive status, and protected downloads are implemented for Company/Branch/User/Client/Vendor/VendorAssignment/StaffProfile/Venue/Event/Booking. Add other owner mappings only as later domain models are implemented. |
| G50 Optional Booking | Implemented in Prompt 14 | bookings and histories, changes, waitlist; nullable unique Event links and restrictive foreign keys |
| G60 Common Event operations | Partially implemented in P2 | Prompt 15 implements Venue operations; Prompt 16 implements Vendor work; Prompt 17 implements Staff operations; Prompt 18 implements Event Tasks and the table-free Calendar projection. Actual Finance posting remains planned. |
| G70 Finance | Implemented P2 baseline; later modules integrate sources | Prompt 19 implements finance categories, Event budgets/lines, and income/expense entries with posted reversals. Prompt 20 implements manual payments/schedules/allocations/refunds and links them to posted Income/reversals. Prompt 21 implements locked Invoice sequences, immutable Invoice/item/tax snapshots, status/delivery history, credit notes, and Payment allocation reconciliation. |
| G80 Attendee services | Partially implemented in P3 | Prompt 23 implements Guests/groups/invitations/RSVP/check-in/seating/notes. Prompt 24 implements Registration forms/fields/submissions/response snapshots/status history plus nullable Guest and communication context. Ticket types/orders/tickets/promos/validation/refunds remain planned. |
| G90 Inventory and Event services | P3 | inventory/movements/reservations/damage, catering, decoration, transportation, accommodation, marketing |
| G100 Communications and notifications | Implemented P2 baseline; later sources register alerts | Prompt 22 implements templates, outbound messages/recipients, immutable delivery logs, reminder schedules, and notifications/recipients. Later Inventory/Marketing modules add only their own sources/consent records; Calendar remains derived. |
| G110 Reporting support | P4 only if justified | saved report filters, metric definitions, rebuildable snapshots; no duplicate financial source table |
| G120 Operations hardening | P5 | backup records, retention support indexes, measured performance indexes, and compatibility migrations only after production constraints are known |

When two modules refer to each other, create the independent parent tables first, then add the optional foreign key in a later migration. Do not disable foreign-key checks to hide a circular design.

## Transaction and concurrency roadmap

| Operation | Required controls |
| --- | --- |
| Event status transition | Lock or optimistic state check; update current status and append actor/timestamp history atomically |
| Booking conversion | Lock Booking; create/link one Event; copy approved snapshot; append status history; nullable Event booking link |
| Module enable/disable | Lock Event setting; confirm data warning; record actor/time and audit without deleting data |
| Invoice issue | Lock sequence row; allocate number; snapshot lines/tax/discount/currency; append status atomically |
| Payment/refund | Lock Event/Client and sorted targets; enforce idempotency and positive DECIMAL values; append Payment/allocations or Refund; post linked Income/reversal; recalculate schedule status/due; append status/audit atomically |
| Ticket issue/validation/refund | Enforce quantity and unique token under transaction; prevent duplicate validation; invalidate refunded ticket |
| Guest invitation/check-in | Lock Invitation for check-in; constrain opaque token hash by Event; enforce expiry/revocation/active Guest; keep one append-only Guest check-in and return its original actor/time on repeat |
| Registration submit/review/Guest conversion | Enforce request idempotency and configured duplicate policy; snapshot every active field response atomically; lock pending Registration for review; append actor/time history; lock Registration for one optional Guest link |
| Inventory movement/reservation | Lock item or use guarded atomic quantity update; append movement; prevent negative availability |
| Venue/staff/vendor/vehicle/room allocation | Recheck overlap within transaction immediately before insert; record authorized override |

Application validation improves messages, but database constraints and unique indexes remain the final concurrency guard.

## Seed and factory policy

- Seed only stable system data such as the 18 module definitions, baseline permissions, and explicitly approved configuration.
- Event Template examples are editable starting configuration from the SRS; seeder reruns create missing baselines without overwriting administrator edits. Business review may change or archive them before launch.
- Factories create coherent test graphs with optional modules disabled by default unless a test enables them.
- Factories must support direct Event creation with booking_id null and Client/Vendor/Staff with user_id null.
- Never seed real credentials, provider keys, personal data, or production invoice sequences.

## Verification required for each migration group

- composer validate --strict;
- php artisan migrate on the local MySQL database;
- migrate:fresh --seed on a disposable test database when safe and configured;
- rollback and re-migrate for reversible migrations;
- schema assertions for foreign keys, nullability, decimal precision, indexes, and engine/collation;
- feature tests for transactions, authorization, history, enabled/disabled module behavior, and direct Event creation without Booking;
- full php artisan test and Laravel Pint before phase handoff.

Production migrations require a verified backup and maintenance plan. Destructive column/table removal uses an expand-migrate-contract sequence and is never combined with the first deployment of replacement code.
