# Budget and Financial Ledger Foundation

## Scope

Prompt 19 implements the Event-scoped Budget workspace and the first actual financial source ledger. It does not implement client Payments, Payment allocations/refunds, Invoices, tax calculation, accounts payable, bank reconciliation, or a general accounting system.

The Budget module remains optional. An Event can be created, progressed, and completed without enabling it. Disabling a populated Budget module hides and protects its routes while retaining every budget and ledger row for later re-enable and reporting.

## Planned versus actual values

`event_budgets` and `budget_lines` contain planning values. There is at most one current Event budget and each line is explicitly income or expense, uses a configurable FinanceCategory, stores `DECIMAL(19,4)`, and snapshots the Event currency. Template budget suggestions are copied only when the Event budget is created and remain editable while the budget is Draft.

`event_income_entries` and `event_expense_entries` contain actual financial entries. These records never derive from or overwrite budget lines. Every entry requires an Event and FinanceCategory and may reference the relevant Client or Vendor, a protected Event Document as evidence, and an explicit source type/ID.

## Recognition and profit definition

Recognized income is the algebraic sum of posted income entries. Recognized expense is the algebraic sum of posted expense entries. A posted reversal contributes the negative of its stored positive amount. Draft and Void entries contribute zero.

Event profit is:

`recognized posted income - recognized posted expense`

Budget variances compare those recognized totals with active planned lines in the same direction and category. These definitions are used by `FinanceService`; Dashboard, Reports, and Analytics must call the same service or reproduce these exact rules rather than store manual totals.

## Posting and reversal rules

- Draft entries may be edited or voided.
- Posting requires `finance.approve`, records approver/poster and timestamps, appends status history, and makes the entry immutable through normal application services.
- A posted entry is never changed into a different amount, date, category, party, or source.
- Voiding a posted entry creates an equal posted reversal linked through `reversal_of_id`. The original remains posted, records who reversed it and why, and the two posted rows net to zero.
- A reversal cannot itself be edited, posted again, or reversed through the normal service.
- The unique Event/source-type/source-ID key makes an operational source idempotent inside each income or expense ledger.

## Authorization

`budget.view`, `budget.manage`, and `budget.approve` protect planning. `finance.view`, `finance.create`, `finance.approve`, and `finance.void` independently protect actual entries. Event visibility, BranchScope through the Event, and `event.module.enabled:budget` are also required.

The Administrator / Business Manager retains every permission. Event Managers may manage and approve planning and create draft actual entries, but Finance / Accounts or the Administrator posts and reverses actuals by default. This separation does not prevent the single Administrator from completing the workflow alone.

## Integration contract

Operational modules integrate through `source_type` and `source_id`; they do not write duplicate totals. `FinanceService::createExpense` and `createIncome` return the existing Event/source record when the same identity is submitted again. Vendor approved costs remain a read-only provider until a user or later approved workflow deliberately creates and posts an Expense. Catering, Transportation, Accommodation, Inventory damage, Ticketing, Payments, and Invoices must follow the same explicit-source rule when implemented.

## Approved budget baseline

An approved budget is a locked planning baseline. Current corrections must be made while the budget is still Draft. A future approved requirement may introduce versioned budget revisions; it must preserve the prior approved baseline rather than editing it in place.
