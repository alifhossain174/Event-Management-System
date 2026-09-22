# Invoice Operations

## Scope

Prompt 21 implements Event- and Client-owned Invoices without making Booking mandatory. An Invoice may optionally reference the Booking already linked to its Event. The Event `invoices` module must be enabled for normal access, and disabling the module preserves every Invoice, item, sequence, delivery attempt, status row, allocation, and credit note.

## Draft and issue lifecycle

- Drafts are editable and may contain Event-derived or manual line items.
- Issue allocates a unique number and snapshots the seller, Client, currency, descriptions, quantities, unit prices, discounts, tax rates, and totals in one locked transaction.
- Issued snapshots cannot be edited or deleted. Payment reconciliation may change only the operational state between Issued, Partially Paid, and Paid.
- Overdue is derived when an unpaid Issued or Partially Paid Invoice is past its due date; no drifting overdue flag is stored.
- An unpaid Draft or Issued Invoice may be cancelled with a reason. An issued Invoice may be credited only after its allocations are fully refunded; the linked credit note contains negative snapshot values and the original remains intact.

## Numbering and calculations

Numbers use the configurable prefix, current calendar year, and a padded sequence, for example `INV-2026-000001`. Sequence scope is global by default and branch-specific only when branch mode is enabled. The sequence row is created idempotently and locked before increment so concurrent requests cannot reuse a number.

Calculations use decimal strings and `DECIMAL(19,4)`. Each line is calculated in this order:

1. quantity multiplied by unit price;
2. fixed or percentage discount;
3. tax applied to the discounted taxable amount;
4. four-decimal half-up rounding.

This is a reversible default, not jurisdiction-specific tax advice. Configure the prefix/default tax in Organization Settings and define a legal invoice profile before production if local law requires different fields, rounding, numbering, or tax treatment.

## Payment reconciliation

Manual Payments remain the receipt source and posted Income remains the recognized-income source. Payment allocations may target only an issued Invoice for the same Event, Client, currency, and enabled module. An allocation cannot exceed the Invoice balance; excess money stays explicit unallocated Client credit. Allocation-linked Refunds reduce the applied amount and may reopen the Invoice. Invoice creation or issue does not duplicate an Income entry.

## PDF, print, and email

PDF output uses `barryvdh/laravel-dompdf` 3.1.x, which resolves on PHP 8.2 and Laravel 12. Remote resource loading and embedded PHP execution are disabled. The PDF renders the stored snapshot values, so its totals match the database rather than recalculating mutable source prices.

Print output is server-rendered Blade. Email sends synchronously with the configured Laravel mail transport, attaches the generated PDF, and records an immutable succeeded/failed delivery attempt. A send failure is logged and returned as validation feedback; no queue worker is required.

## Authorization and operational checks

Invoice view, create, update, issue, cancel, credit, email, and export permissions are independent. Every Event route also enforces Event visibility and module enablement. Client history and global search include only authorized Invoices whose Events have the module enabled. Before production, verify company/contact/tax configuration, mail transport, PDF appearance, sequence policy, and the applicable statutory invoice rules.
