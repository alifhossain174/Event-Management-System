# Guest Management, RSVP, Seating, Invitations, and Check-In

## Scope

Prompt 23 implements the optional Event-scoped `guests` module. A Guest is an Event-owned business record and never requires or creates a User account. The module provides filterable Guest records, groups/families, VIP flags, invitations, manager-recorded RSVP responses, seating labels, operational/private notes, and one-time front-desk check-in. Registration and ticketing remain separate optional modules.

Every route is authenticated, authorized, and protected by `event.module.enabled:guests`. Disabling the module hides the workspace and blocks direct access while `GuestDataDetector` preserves all Guest records and history for re-enable.

## Records and history

- `guests` contains import-ready `external_reference` and `source` fields plus normalized name/email/phone indexes. It is archived/reactivated rather than deleted.
- `guest_groups` and `guest_group_members` model one current family/household/company/party membership per Guest without merging Guest identity.
- `invitations` keeps every issued/revoked invitation. Issuing a replacement revokes the prior active token and appends generic status history.
- `rsvps` keeps the current response and uses append-only status history for response changes. The confirmed party size is an Event capacity input, not a venue reservation.
- `seat_assignments` enforces one seat per Guest and one table/seat label per Event. Labels are deliberately data, not hard-coded seat-map geometry.
- `guest_notes` distinguishes private manager notes from operations notes; policies and constrained eager loading prevent private notes from reaching Staff/Front Desk views.
- `guest_check_ins` is append-only and unique per Guest. Repeated scans return the original time/operator and never create another row.

## Invitation token and QR design

The check-in credential is 32 cryptographically random bytes encoded as a 43-character base64url token. The searchable database value is only its SHA-256 hash; the recoverable copy is encrypted with Laravel Crypt because an authorized manager must render the QR again. Audit metadata redacts token-like fields. The QR contains only the Event check-in URL plus the opaque token—never a Guest name, email, phone, seating label, or other personal data.

`endroid/qr-code` 6.0.9 is the single Prompt 23 package addition under the `^6.0` constraint. Composer resolution verified PHP `^8.2`; the package is framework-independent, maintained, MIT-licensed, and uses the locally installed SVG writer, so it needs neither a Laravel adapter nor GD. Production uses the Composer-built PHP dependency and does not require Node.js, an external QR service, or network access.

Lookup hashes the submitted token and constrains it by `event_id`. Expired, revoked, malformed, archived-Guest, and wrong-Event credentials return the same safe validation response. Check-in locks the Invitation row in a database transaction, validates the allowed party size, and relies on a unique Guest constraint as the final concurrency guard.

## Authorization

- Managers with granular Guest permissions create/update/archive Guests, groups, Invitations, RSVP, seating, and notes.
- Only `guests.invite` holders may retrieve an Invitation QR.
- Front Desk/Check-in receives `events.view`, `guests.view`, and `guests.check-in`; it can look up/check in a valid Guest but cannot issue Invitations, edit Guests, or see private notes.
- Staff may view operations-safe Guest details but cannot check in or view private notes unless permissions are deliberately expanded.
- Administrator/Business Manager retains wildcard authority and can run the entire workflow alone.

## Capacity and operational behavior

`GuestCapacityService` sums active confirmed party sizes and compares the result with the current active Venue allocation snapshot/space/Venue capacity when Venue data exists. Exceeding capacity creates a visible warning only; it does not silently alter RSVP, seating, Venue allocations, or Event lifecycle. There is no hard dependency on Venue.

All Guest lists are Event-scoped, paginated, and filterable by search, RSVP, Invitation state, group, VIP, and archive state. Output uses escaped Blade expressions. Check-in remains synchronous and works on shared hosting without Redis, queues, WebSockets, a scanning daemon, or a third-party QR API.

## Reversible defaults

- One active Invitation token per Guest; reissuing revokes the earlier token but retains it and its history.
- One successful check-in per Guest record, with `party_size` representing the admitted party.
- Seating uses free-text table and seat labels rather than a graphical seat-map inventory.
- Capacity exceedance is advisory.

These defaults are reversible through future schema/service/UI changes that preserve existing Invitation, RSVP, seat, and check-in history.
