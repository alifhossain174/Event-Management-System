# Manual Payments Allocations Due Tracking and Refunds

## Scope

Prompt 20 implements the optional Event-scoped manual client Payment ledger. It records advances, installments, partial payments, final payments, schedules, allocations, printable receipts, and refunds. It does not provide checkout, a payment-method catalog, gateway APIs, webhooks, card storage, automatic bKash collection, or bank integrations.

Payments may record a free-text channel and external reference for historical traceability. These values do not configure or invoke a provider.

## Posting and recognition

Every successful Payment post is one immutable positive receipt entry with a unique generated receipt number and a client-supplied idempotency UUID. The service locks the Event, Client, and allocation targets, validates the allocation total, appends status and audit history, and creates one linked posted Income through FinanceService in the same database transaction.

The Payment ledger explains client cash movement. The Prompt 19 Income ledger remains the recognized-income source used for Event profit and reporting. The explicit source pair payment plus Payment ID prevents the same receipt from being recognized twice.

Posted Payments and allocations have no edit or delete route. A correction is a Refund followed, when necessary, by a replacement Payment. The original receipt remains unchanged.

## Schedules allocations and due

PaymentSchedule is the implemented due obligation until the Invoice module exists. Event and Client due is the sum of active schedule amounts less net allocations. Paid and partially paid status is recalculated from allocation records and linked refunds; overdue is derived from due date and status.

A Payment may be split across schedules. Allocations cannot exceed either the Payment amount or a schedule's current outstanding amount. Database row locks make the service recheck current outstanding due immediately before inserting allocations.

Any Payment amount not allocated is retained as an unallocated advance or client credit. It does not make due negative and is not silently assigned to another schedule.

Invoice IDs remain nullable indexed extension columns and Prompt 21 adds restrictive foreign keys to the Invoice parent. PaymentService accepts an allocation only for an issued Invoice belonging to the same enabled Event, Client, and currency, locks the Invoice target, rejects allocations above its balance, and reconciles status after allocations or Refunds. Invoice totals are never copied into the Payment ledger.

## Refund rules

A Refund is a separate immutable positive record that reduces recognized cash through a linked posted Income reversal. Total posted Refunds cannot exceed the original Payment.

An allocated Refund names one PaymentAllocation and cannot exceed that allocation's remaining refundable amount. It reduces the allocation and therefore reopens the related schedule due. An unallocated Refund can consume only the Payment's remaining unallocated advance. This explicit choice prevents an ambiguous refund from silently changing the wrong obligation.

## Authorization and optional-module behavior

payments.view protects histories, due lists, and receipts. payments.create protects schedules and Payment posting. payments.refund is separately required for Refunds. Event visibility and the payments module guard are enforced for Event routes; Client history and the global due list include only authorized Events whose Payments module is enabled.

Disabling a populated Payments module requires the existing data-presence confirmation flow. It hides normal Payment routes while preserving schedules, Payments, allocations, Refunds, linked Income entries, statuses, and audit records for re-enable and later reporting.
