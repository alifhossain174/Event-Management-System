# Client Management

## Scope

Prompt 08 implements the M04 Client master only. It does not implement Events, Bookings, Invoices, Payments, Communications, or the Event Draft transition rule.

Clients may be individuals or organizations and exist independently of application login accounts. `user_id` is nullable; creating a Client never creates a User. Linking or unlinking an existing active Client-role User is a separately authorized and audited administrator action.

## Data and lifecycle

- `clients` stores the master identity, primary contact channels, organization metadata, address, nullable branch/user links, search normalizations, status, archive metadata, and optional merge target.
- `client_contacts` stores additional people. Email and phone values are normalized for search but deliberately not unique because households and organizations may share channels.
- Active Clients may be archived and later reactivated. Both operations append generic status history with actor, time, and reason.
- A privileged merge moves all active/archived contacts to the active target, copies missing protected-document links, marks the source as merged, and preserves the source row and history.
- No destructive Client delete route exists. Later Events, Bookings, Invoices, and Payments should use restrictive Client foreign keys.

## Duplicate handling

The duplicate service compares normalized primary email, phone digits, and display name. Matches are warnings, not validation failures. Users may save legitimate shared-contact records and review them on the detail page. Only users with `clients.merge` may merge a confirmed duplicate.

Audit snapshots record names, identifiers, statuses, and whether a contact channel is present; they do not copy raw email/phone values unnecessarily.

## Authorization and branch scope

- `clients.view`, `clients.create`, and `clients.update` protect normal Client and contact work.
- `clients.delete` means archive/reactivate, never hard deletion.
- `clients.assign` protects portal user linking.
- `clients.merge` protects irreversible-in-practice deduplication.
- Administrator / Business Manager has all permissions. Event Manager can view, create, and update Clients without receiving privileged lifecycle/link/merge permissions.
- `BranchScope` is applied explicitly to lists and lookups. When branch mode is disabled, all authorized records remain visible. When enabled, non-administrators see unscoped records plus assigned branches and cannot assign another branch.

## Future Event form integration

Use the reusable Blade component with a branch-scoped collection of active Clients:

```blade
<x-clients.selector :clients="$clients" :value="old('client_id', $event->client_id ?? null)" />
```

The component posts quick creation to `clients.quick-create`; that endpoint uses `StoreClientRequest` and `ClientService`, exactly like the full create screen. `clients.lookup?q=...` provides a bounded 20-result JSON endpoint for a future searchable selector. Both endpoints enforce authentication, active-account middleware, policies, branch scope, and duplicate warnings.

## Detail-page integration contract

The Client detail screen reports actual protected Documents now. Events, optional Bookings, Invoices, Payments, and Communications display explicit later-module placeholders. `ClientSummaryService` starts counting each relationship only after its owning table and `client_id` column exist, avoiding speculative tables or fake records.

## Verification

`tests/Feature/Clients/ClientManagementTest.php` covers type validation, creation without User, normalization, duplicate warnings and shared channels, filters/pagination, branch scope, contact lifecycle, archive/reactivate history, missing destructive route, authorization, lookup/quick-create validation, portal linkage, merge preservation, and audit entries.
