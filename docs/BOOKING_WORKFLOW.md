# Optional Booking Workflow

## Scope

Booking is an optional manager-created enquiry and approval path before an Event. It is never required to create, progress, or complete an Event. Prompt 14 does not implement Venue reservations, Payments, Invoices, or Notifications.

## Lifecycle

| State | Meaning | Available actions |
| --- | --- | --- |
| Enquiry | Initial request captured for a Client. | Start review, approve/confirm, waitlist, reschedule, cancel. |
| Under Review | A manager is reviewing the request. | Approve/confirm, waitlist, reschedule, cancel. |
| Waitlisted | The request is retained in ordered waitlist data. | Approve/confirm, reschedule, cancel. |
| Confirmed | One-step manager approval and confirmation is complete. | Convert once, reschedule, cancel. |
| Converted | A single linked Draft Event exists. | Open the linked Event; Booking is terminal. |
| Cancelled | Cancellation reason, actor, time, and finance-review flags are retained. | No further workflow action. |

Every state change appends `booking_status_histories`. Reschedule and conversion append `booking_changes`. Both records retain actor/time/reason; conflict details are bounded metadata. Normal application routes never edit or delete these history rows.

## Conversion

Conversion requires a Confirmed Booking and `bookings.convert` plus `events.create`. The form reviews Event Category, optional Template, editable module suggestions, schedule, branch, manager, guest estimate, budget estimate, and description. The service locks the Booking, creates the Event through `EventService`, and writes both nullable links in one transaction. Database uniqueness permits at most one Event per Booking. A repeated conversion request returns the existing Event without creating another row.

## Integration adapters

- `BookingConflictChecker` receives the proposed UTC range and venue preference. Its default returns no conflicts until Venue/resource services exist.
- Reported conflicts block rescheduling unless the request explicitly enables override and the actor has `bookings.override-conflicts`.
- `BookingFinancialImpactInspector` returns bounded review flags on cancellation. Its default returns none until Finance exists. Flags never delete or rewrite financial data.
- No notification is dispatched because the repository has no Notification service. A later synchronous domain adapter may be called after transaction success.

## Authorization and visibility

BookingPolicy controls list/detail and every action. BranchScope is applied explicitly; administrators are unrestricted, branch mode remains disabled by default, and no global scope silently hides data. Event Managers receive normal Booking workflow permissions but not conflict override. Booking dashboard/search/document projections repeat the same policy and branch boundaries.
