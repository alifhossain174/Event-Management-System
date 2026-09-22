# Vendor and Staff Master Records

Prompt 09 implements the independent global master records for SRS modules M07 and M08. It intentionally does not implement Event assignments or later operational/financial workflows.

## Vendor boundary

`Vendor` stores the supplier identity, optional branch, contact channels, notes, archive state, and nullable one-to-one portal `user_id`. Many-to-many `VendorCategory` links classify suppliers. `VendorContact` and `VendorServiceArea` are owned supporting records. Creating any of these records never creates a User.

Rating, availability, Event assignment, work order, delivery, contract, invoice, payment, and performance data remain later-module extensions. The detail page names those extensions without creating speculative tables.

## Staff boundary

`StaffProfile` stores the worker identity, optional branch and Department, contact channels, title, employment status, availability notes, archive state, and nullable one-to-one portal `user_id`. Department is a separate configurable master-data table. Creating a profile never creates a User.

Shift, attendance, leave, Event assignment, performance, contract, invoice, and simple salary/payment tracking remain later-module extensions.

## Authorization and lifecycle

- Administrator / Business Manager can operate both master-record workflows alone.
- Event Manager can view, create, and update masters but cannot archive or link accounts.
- A linked Vendor or Staff portal role can view only its own profile and its authorized private documents; it cannot edit the master record.
- Link/unlink requires a matching active role and a uniqueness check. Account deactivation does not remove the business link or history.
- Archive/reactivate is an audited status transition. There is no destructive master-record delete route.
- Branch filtering is explicit through `BranchScope`; no global scope hides records.

## Documents and audit

Vendor and StaffProfile are allowlisted protected-document contexts. Files stay on the private disk and remain downloadable only through authorized controllers. Create, update, archive/reactivate, account-link, contact, and service-area actions append sanitized audit records. Status transitions append actor and timestamp history.
