# Core Event Lifecycle

## Scope

Prompt 11 implements only the central manager-driven Client-to-Event workflow. Booking is optional, booking_id is nullable, and no Venue, assignment, Task, finance, attendee, ticket, inventory, service, marketing, communication, or media business logic is introduced.

## Aggregate

Event owns:

- one immutable public reference and optional source Event;
- nullable Client only while Draft, nullable optional Booking, category, optional template, branch, and manager;
- event-local input time zone with UTC DATETIME(6) start/end instants;
- primary contact, expected guests, theme, dress code, description, and DECIMAL(19,4) core budget estimate;
- completion, cancellation, and archive metadata with actor/time/reason where applicable;
- exactly one EventModuleSetting row for every one of the 18 event-scoped stable module keys;
- append-only EventStatusHistory and EventTimelineItem rows plus manager planning notes.

The template record remains linked for context, while template_snapshot freezes the name, starter suggestions, and application timestamp used at creation. Later template edits do not rewrite the Event.

## Status graph

Normal transitions are:

1. Draft to Confirmed or Cancelled.
2. Confirmed to Planning or Cancelled.
3. Planning to In Progress or Cancelled.
4. In Progress to Completed or Cancelled.
5. Completed to Planning only through privileged correction.
6. Cancelled is terminal.

A Client is required for Draft to Confirmed. Cancellation requires a reason. Completed, Cancelled, and archived Events are operationally read-only. The events.correct permission permits an administrator to reopen Completed to Planning with a required reason; this is a correction mechanism, not a silent status edit.

Every status write locks the Event in a database transaction and appends dedicated status history, timeline activity, and a sanitized audit entry.

## Optional modules

Templates propose initial module selections and the create form allows managers to override them. Event creation stores all 18 states in the same transaction as the Event. Dependencies produce warnings only. Disabled settings are retained, require confirmation when a previously enabled module is turned off, and define a data-preservation contract for later module tables. An Event with every specialized module disabled can complete.

## Duplication policy

Duplication creates a new Draft with a new reference, null booking_id, independent rows, and a source_event_id trace. It copies planning fields, template snapshot, and all module states. Planning notes are copied only when the source note is marked include_in_duplicate and the operator opts in.

It never copies payments, invoices, attendance, ticket scans, prior status history, prior timeline items, audit history, completion/cancellation/archive state, or later transactional module records.

## Routes and authorization

The authenticated Event routes provide list/create/store/show/edit/update, explicit status transition, privileged correction, archive/reactivate, duplicate, note creation, and module configuration. There is no destructive delete route. Policies enforce permissions and explicit BranchScope; navigation visibility is presentation only and does not replace server-side authorization.

The Administrator / Business Manager owns all Event permissions and can operate the workflow without assignment to a second account. Event Managers can run normal Event operations but cannot use privileged correction.

## Verification

tests/Feature/Events/EventLifecycleTest.php covers direct creation with no Booking, Draft without Client, the Client transition gate, the complete forward lifecycle, all modules disabled, invalid dates, time-zone conversion, cancellation traceability, duplication isolation/exclusions, completed read-only behavior, privileged correction, module-disable confirmation/preservation, authorization, filtering, pagination, and the quick-client-enabled form.
