# Event Management System Agent Guide

## Before feature work

Read the complete SRS at docs/requirements/Event_Management_System_SRS_Laravel_MySQL_v1.2.docx, then review docs/REQUIREMENTS_TRACEABILITY.md, docs/DECISIONS.md, docs/IMPLEMENTATION_STATUS.md, docs/ARCHITECTURE.md, and docs/DATABASE_ROADMAP.md. Preserve existing work and inspect git status before editing. Implement only the approved prompt or phase.

## Architecture constraints

- Laravel 12 on PHP 8.2 with MySQL-compatible InnoDB and utf8mb4.
- Modular monolith using Blade, Bootstrap 5, and vanilla JavaScript.
- Target cPanel shared hosting and synchronous request processing.
- Production uses prebuilt assets included in the deployment package and must not require Node.js.
- Do not introduce Redis, Horizon, persistent queue workers, WebSockets, an SPA framework, or an online payment gateway.
- Prefer Laravel framework features. Any new package requires a documented need and verified PHP 8.2 and Laravel 12 compatibility.

## Coding standards

- Keep controllers thin; put validation in Form Requests and business rules in focused services.
- Enforce protected actions server-side with Policies or Gates.
- Use Eloquent relationships, database constraints, useful indexes, and transactions for critical multi-record writes.
- Preserve history with soft deletion or explicit archiving; do not hard-delete historical business records.
- Record sensitive actions in the audit log and record status transitions when status drives workflow.
- Keep list screens paginated, filterable, and authorization-aware.
- Use strict, descriptive tests. Never weaken a test to conceal a regression.
- Run Pint on changed PHP files and avoid speculative abstractions.

## Optional-module invariant

The core Client-to-Event flow must work without Booking or any optional Event module. Every Event module is optional unless a recorded decision explicitly changes that rule. Disabling a populated module must preserve its records.

## Git conventions

Keep the repository's configured default branch unless the maintainer explicitly chooses a rename. Keep commits small and phase-scoped, write imperative commit subjects, and do not mix unrelated cleanup with feature work. Never commit `.env`, credentials, `vendor`, `node_modules`, generated hot-reload files, or local storage links. Build artifacts must be generated and included in the deployment package even when they are not committed.

## Required verification

Run the smallest relevant tests while developing, then before handoff run:

~~~powershell
composer validate --strict
php artisan migrate:status
php artisan test
npm run build
vendor\bin\pint
~~~

For schema changes, also migrate a clean disposable test database and verify rollback behavior where the change is reversible.
