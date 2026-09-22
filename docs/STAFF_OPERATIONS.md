# Staff Operations

## Scope

Prompt 17 implements practical workforce operations around the existing independent `StaffProfile` master. A Staff profile remains usable without a User account. A linked Staff-role User may view their own schedule and update permitted work statuses, while the Administrator / Business Manager can operate the same workflow on behalf of every Staff member.

Implemented records are `Shift`, `StaffAssignment`, `Attendance`, `LeaveRequest`, `SalaryRecord`, and `PerformanceRecord`. Event assignments require an Event. Shifts, attendance, salary, leave, and performance remain Staff-owned and may have an optional Event relationship where it is meaningful.

## Availability and conflict rules

`StaffAvailabilityService` uses half-open intervals: an existing item overlaps when its start is before the requested end and its end is after the requested start. Adjacent windows are therefore allowed. Active shifts, active Event assignments, and approved leave are checked while the parent Staff row is locked in the write transaction.

Conflicts block by default. Only a User with `staff.override-conflicts` may continue, and the request must include a reason. The saved row retains the override actor, reason, and conflict details; the sensitive action is also audited. Archived or separated Staff cannot receive new work, but all historical records remain available.

## Authorization

- `staff.view-work` sees branch-permitted Staff operations; `staff.view-own-work` sees only the linked profile.
- `staff.schedule` manages shifts and Event assignments.
- `staff.update-own-work` permits a linked Staff User to advance only their own work status.
- leave, attendance, performance, and conflict override use separate permissions.
- salary access is independently protected by `staff.view-salary` and `staff.manage-salary`; Event Managers do not receive those permissions.
- every Event Staff route also requires Event visibility and `event.module.enabled:staff`.

## Salary boundary

`SalaryRecord` is operational salary/payment tracking only. It records a period, amount, currency, due/paid state, payment date/reference, notes, actor, and status history. It is not a statutory payroll system and does not calculate taxes, deductions, benefits, payslips, filings, or jurisdictional compliance. Replacing or integrating this ledger with a payroll provider remains the reversible path recorded in `DECISIONS.md`.

## Reporting inputs and history

Attendance rows are filterable by Staff, date, Event, assignment, and status. They retain the recording User and optional clock times. Workflow records use the common append-only status-history convention; schedule, leave, attendance, salary, and performance writes also create sanitized audit evidence where sensitive or operationally significant.

The Staff module data detector treats any Event Staff assignment as module data. Disabling the Event module therefore uses the existing explicit confirmation/reason flow and preserves assignments for re-enable and later reporting.
