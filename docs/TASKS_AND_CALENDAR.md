# Task Management and Consolidated Calendar

## Scope

Prompt 18 implements Event-scoped Task Management and the global derived Calendar. Task records are optional per Event. The Calendar owns no schedule records; it projects authorized source rows at request time.

## Task aggregate

`event_tasks` stores the Event, title, description, optional UTC deadline, priority, workflow status, progress, completion actor/time, and non-destructive archive metadata. Related tables are:

- `task_assignments`: exactly one nullable User or StaffProfile per row, assigned actor/time, and unique Task/assignee pairs;
- `task_comments`: append-oriented discussion with author/time;
- `task_attachments`: protected Document references with uploader/time and optional note;
- `task_status_histories`: append-only from/to status, actor/time, reason, and safe metadata.

Task status values are Pending, In Progress, Blocked, Completed, and Cancelled. Completion always sets progress to 100 and records `completed_by_user_id` plus `completed_at`. A Blocked transition requires a reason. Completed and Cancelled tasks are terminal in this baseline. Overdue is never stored: `Task::isOverdue()` derives it from `due_at`, current status, archive state, and the current time.

Managers may assign active Users or active StaffProfiles. A StaffProfile does not need a User account. If a Staff account is linked later, `tasks.view-assigned` exposes only tasks assigned directly to that User or to the linked StaffProfile. Every Event Task route uses `event.module.enabled:tasks`; disabled access is blocked while records remain intact. `TaskDataDetector` makes populated disablement use the existing confirmation/reason workflow.

## Protected attachments

Task attachments reuse the Prompt 07 Document service. The uploaded file is MIME/extension/size validated, stored under a randomized private path, versioned, and linked to the Task. Downloads remain controller-mediated. `DocumentAccessService` includes only Task records visible through `TaskAccessService`, so an unassigned Staff user cannot infer or download another Task attachment.

## Calendar projection

`CalendarService` accepts a daily, weekly, or monthly local date window, converts the exclusive bounds to UTC, and returns bounded `CalendarEntry` read models. It can project:

- core Event start/end dates;
- active Venue allocations using the owning Event schedule;
- active Staff shifts and Event assignments;
- active Vendor assignments;
- Task deadlines.

Each entry carries a stable source type/id and an authorized source route. Source collections are de-duplicated by that pair. Event-scoped sources require their owning module setting to be enabled. BranchScope, source permission, portal ownership, and Task assignment rules are applied before a row becomes a Calendar entry. Cancelled/archived or out-of-window rows are omitted according to their source rules.

There is intentionally no `calendar_events` or `schedule_entries` table. Changes to Events, assignments, shifts, allocations, or deadlines appear without synchronization jobs. This remains compatible with synchronous cPanel hosting and does not require queues, Redis, WebSockets, or a production Node runtime.

## Verification

- `php artisan test tests/Feature/Tasks/TaskWorkflowTest.php tests/Feature/Calendar/CalendarProjectionTest.php`
- `php artisan test`
- `php artisan migrate`
- `composer validate --strict`
- `npm run build`
- `php artisan view:cache`
- `vendor/bin/pint --test`
