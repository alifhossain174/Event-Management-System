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

## Database-wide conventions

### Engine, character set, and identifiers

- All business tables use InnoDB and utf8mb4 with the project collation.
- Internal primary keys use unsigned BIGINT auto-increment values unless a later measured requirement justifies another type.
- Public QR, invitation, ticket, download, or validation tokens use a separate unique non-guessable UUID, ULID, or cryptographic token. Internal numeric IDs are not exposed as security tokens.
- Foreign-key columns use the same unsigned BIGINT type as their parent.
- Stable module keys use VARCHAR(64) and are never renamed after production data exists.

### Required nullable links

- events.booking_id is always nullable. It is added only when the optional Booking tables are introduced.
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

module_definitions contains the 18 stable event-scoped keys. event_module_settings contains event_id, module_key, is_enabled, source, enabled_by_user_id, enabled_at, disabled_by_user_id, disabled_at, and timestamps. It has a unique key on event_id plus module_key. Disabling a module updates state and appends audit history; it never cascades into module data.

## Module table roadmap

Status values distinguish implemented framework/identity foundations from planned business tables. An implemented identity baseline does not imply that an Event Management business module is complete.

| SRS module and key | Scope | Status | Proposed tables |
| --- | --- | --- | --- |
| M01 dashboard | Global projection | Planned | No source table. Optional saved_filters after a demonstrated need; metrics derive from source tables. |
| M02 users | Global | Identity/RBAC implemented; organization links planned | users account controls, roles, permissions, role_user, permission_role, login_histories, and user_status_histories are implemented. user_branches and optional user_profiles remain planned. |
| M03 events | Core | Planned | event_categories, event_templates, module_definitions, event_template_modules, events, event_module_settings, event_status_histories, event_timeline_items, event_notes, event_media. |
| M04 clients | Global master | Planned | clients, client_contacts; use a client_type column for individual or organization and nullable unique user_id. |
| M05 booking | Optional upstream | Planned | bookings, booking_status_histories, booking_changes, waitlist_entries; later add nullable events.booking_id. |
| M06 venue | Event-scoped | Planned | venues, venue_spaces, venue_facilities, venue_space_facility, venue_rates, venue_unavailability, venue_allocations, venue_media. Allocations require event_id. |
| M07 vendors | Global master plus Event work | Planned | vendors, vendor_categories, vendor_category_vendor, vendor_availabilities, vendor_assignments, vendor_contracts, vendor_ratings, vendor_invoices, vendor_payments. Assignment and finance rows require event_id where applicable. |
| M08 staff | Global master plus Event work | Planned | staff_profiles, departments, shifts, attendance_records, leave_requests, staff_assignments, performance_records, salary_records. Event assignment/attendance rows carry event_id when Event-specific. |
| M09 guests | Event-scoped | Planned | guests, guest_groups, guest_group_members, invitations, rsvps, guest_check_ins, seat_assignments, guest_notes. All require event_id directly or through an Event-owned parent with an enforced unique Event relationship. |
| M10 ticketing | Event-scoped | Planned | ticket_types, tickets, promo_codes, promo_code_redemptions, ticket_orders, ticket_order_lines, ticket_validations, ticket_refunds. Ticket type/order/ticket records require event_id. |
| M11 registration | Event-scoped | Planned | registration_forms, registration_fields, registrations, registration_responses, registration_status_histories. All forms and registrations require event_id. |
| M12 tasks | Event-scoped | Planned | event_tasks, task_assignments, task_comments, task_attachments, task_status_histories. event_tasks require event_id. |
| M13 budget | Event-scoped | Planned | finance_categories, event_budgets, budget_lines, event_income_entries, event_expense_entries. Every budget and actual entry requires event_id. |
| M14 payments | Event-scoped | Planned | payments, payment_schedules, payment_allocations, payment_adjustments, payment_refunds. Payment requires event_id and client_id; invoice allocation is optional. |
| M15 invoices | Event-scoped | Planned | invoice_sequences, invoices, invoice_items, invoice_status_histories. Invoice requires event_id and client_id; booking_id is nullable. |
| M16 inventory | Global master plus Event work | Planned | inventory_categories, inventory_items, stock_movements, inventory_reservations, inventory_issues, inventory_returns, damage_reports. Event reservations/issues/damage carry event_id. |
| M17 catering | Event-scoped | Planned | menus, meal_packages, catering_plans, catering_plan_items, dietary_preferences, kitchen_schedules. Plans and sensitive dietary summaries require event_id. |
| M18 decoration | Event-scoped | Planned | decoration_plans, decoration_elements, decoration_media, decoration_resources. Plans and resource links require event_id. |
| M19 transportation | Global master plus Event work | Planned | vehicles, drivers, transport_routes, transport_assignments, pickups, fuel_costs. Assignment, pickup, and Event cost rows require event_id. |
| M20 accommodation | Global master plus Event work | Planned | hotels, hotel_rooms, accommodation_bookings, room_allocations, guest_stays, room_charges. Booking/allocation/charge rows require event_id. |
| M21 marketing | Event-scoped | Planned | marketing_campaigns, campaign_recipients, consent_records, coupons, coupon_redemptions, referrals, campaign_metrics. Campaigns and attribution rows require event_id where applicable. |
| M22 communications | Event-scoped capability | Planned | message_templates, outbound_messages, message_recipients, delivery_logs, reminder_schedules. Event communication rows carry event_id; templates may be global. |
| M23 calendar | Global projection | Planned | No duplicated schedule source table. Optional saved_calendar_filters; views query Events and enabled scheduling sources. |
| M24 documents | Event-scoped shared service | Planned | document_categories, documents, document_links, document_versions. An Event link uses event_id; the polymorphic-like link design must use an allowlist and application authorization. |
| M25 reports | Global projection | Planned | No reporting ledger. Optional report_definitions and saved_report_filters; synchronous exports are generated from source queries and are not queue jobs. |
| M26 analytics | Global projection | Planned | metric_definitions and metric_snapshots only after measured need. Snapshots are rebuildable and never replace transactions. |
| M27 notifications | Global | Planned | notifications, notification_recipients. Optional source type/id and event_id link; read state belongs to each recipient. |
| M28 settings | Global | Planned | companies, branches, system_settings, integration_credentials, backup_records, api_keys, two_factor_methods. branch_id remains nullable outside branch records until branch mode is enabled. |
| M29 audit | Global cross-cutting | Prompt 04 baseline implemented | audit_logs and login_histories are implemented for identity/security actions and are append-only through application services. Broader security_events and module-specific audit context remain planned. |

## Key and index roadmap

Laravel automatically indexes most foreign keys, but common filtered access needs deliberate composite indexes.

| Area | Planned indexes and uniqueness |
| --- | --- |
| Event | index status/starts_at, client_id/status, branch_id/starts_at, manager_user_id/status; unique nullable booking_id when conversion is one-to-one |
| Module state | unique event_id/module_key; index module_key/is_enabled |
| Client/Vendor/Staff | unique nullable user_id; normalized email/phone indexes according to confirmed duplicate rules; branch_id/status indexes |
| Booking | client_id/status/requested_start_at; branch_id/status; waitlist Event/date ordering; optional unique converted Event link |
| Scheduling | event_id/start/end and resource_id/start/end composites for venue, staff, vendor, vehicle, and room conflict queries |
| Status history | parent_id/changed_at; actor_user_id/changed_at |
| Invoice | unique invoice_number; unique sequence scope plus next value; event_id/status, client_id/status, due_date/status |
| Payment | event_id/paid_at, client_id/paid_at, reference_number where uniqueness is agreed; allocation unique by payment/target/type rules |
| Ticket/QR | unique public token/code; event_id/status; ticket_type_id/status; validation ticket_id/validated_at |
| Inventory | item_id/occurred_at, event_id/item_id/status; unique idempotency/source reference for generated movements |
| Communication | event_id/created_at, recipient/status, provider/status/attempted_at |
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
| G10 Organization, identity, and security | Partially implemented in P1 | Prompt 04 implements user account controls, roles, permissions, pivots, login histories, user status histories, and audit logs. Companies, branches, optional profiles, broader security events, and system settings remain planned. |
| G20 Independent master data | P1 | clients and contacts; vendor categories/vendors; departments/staff profiles; configurable Event categories |
| G30 Event configuration and core | P1 | module definitions seed, Event templates, template module suggestions, events, Event module settings, Event status histories, notes, timeline, media |
| G40 Protected documents | P1 | document categories, documents, document links, versions after core owners exist |
| G50 Optional Booking | P2 | bookings and histories, changes, waitlist; then add nullable events.booking_id and its foreign key/index |
| G60 Common Event operations | P2 | venues/spaces/allocations, vendor assignments/contracts/invoices, staff shifts/assignments/attendance/leave/salary, Event tasks and histories |
| G70 Finance | P2 | finance categories, Event budgets/lines, income/expense entries, invoice sequences/invoices/items/history, payments/schedules/allocations/adjustments/refunds |
| G80 Attendee services | P3 | guests/groups/invitations/RSVP/check-in/seating, registrations/forms/responses/history, ticket types/orders/tickets/promos/validation/refunds |
| G90 Inventory and Event services | P3 | inventory/movements/reservations/damage, catering, decoration, transportation, accommodation, marketing |
| G100 Communications and notifications | P2-P3 | templates, outbound messages/recipients/delivery logs/reminders, notifications/recipients; Calendar remains derived |
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
| Payment/refund | Append ledger record and allocations; recalculate/assert balance; append audit atomically |
| Ticket issue/validation/refund | Enforce quantity and unique token under transaction; prevent duplicate validation; invalidate refunded ticket |
| Inventory movement/reservation | Lock item or use guarded atomic quantity update; append movement; prevent negative availability |
| Venue/staff/vendor/vehicle/room allocation | Recheck overlap within transaction immediately before insert; record authorized override |

Application validation improves messages, but database constraints and unique indexes remain the final concurrency guard.

## Seed and factory policy

- Seed only stable system data such as the 18 module definitions, baseline permissions, and explicitly approved configuration.
- Event Template examples remain development/demo seed data until the business confirms launch templates.
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
