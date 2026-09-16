# Prompt 02 Schema Baseline

## Scope

Prompt 02 establishes only the Laravel framework database baseline. It does not define the Event Management domain schema; that work belongs to later approved prompts.

Prompt 03 now defines the planned domain schema, keys, indexes, archive rules, precision, time policy, and migration order in `docs/DATABASE_ROADMAP.md`. That roadmap distinguishes implemented framework tables from planned business tables and does not itself implement a module.

## Database contract

- Connection: Laravel mysql driver over TCP to 127.0.0.1:3306.
- Local database: event_management.
- Tables: InnoDB with utf8mb4 and a utf8mb4 collation.
- Foreign keys, uniqueness, check constraints where supported, and indexes will enforce later domain invariants.
- Critical multi-record writes will use database transactions.
- Historical business records will use soft deletion or explicit archiving.

## Baseline migrations

The Laravel 12 skeleton currently provides the standard users/password-reset/session and cache migrations. No authentication routes, controllers, or domain migrations are installed in Prompt 02. The skeleton job and failed-job migration was removed because the approved runtime is synchronous and does not use persistent queue workers.

File-based session and cache drivers are configured for the shared-hosting baseline, so their framework tables are not active runtime dependencies. A later schema prompt may remove unused framework tables when authentication and persistence choices are finalized.

## Deferred by design

Clients, Events, Bookings, branches, module enablement, audit entries, status histories, payments, and every other SRS entity remain unimplemented. Their relationships, indexes, retention behavior, and authorization rules must follow the traceability matrix and recorded decisions in their assigned phases.
