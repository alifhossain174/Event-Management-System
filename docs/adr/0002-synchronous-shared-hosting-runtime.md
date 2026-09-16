# ADR 0002 Synchronous Shared Hosting Runtime

## Status

Accepted on 16 September 2026.

## Context

The initial production target is standard cPanel shared hosting. It may provide PHP, MySQL, file storage, Apache configuration, and periodic Cron, but it must not be assumed to provide Redis, Supervisor, Horizon, persistent queue workers, or a WebSocket server.

The confirmed first release records client payments manually. External email, SMS, WhatsApp, and map providers are optional and disabled until credentials exist. Static in-application notifications satisfy the core notification requirement.

## Decision

Run core work synchronously in HTTP requests or explicit scheduled commands:

- QUEUE_CONNECTION remains sync.
- Do not require queue:work, Horizon, Supervisor, Redis, or a persistent worker.
- Do not use WebSockets or browser push for core behavior; notifications are stored and read on request/refresh.
- Do not implement an online payment gateway, webhook receiver, card flow, or automatic bKash/bank collection in the initial release.
- Keep provider access behind narrow service contracts so email, SMS, WhatsApp, maps, object storage, or a future payment provider can be introduced without changing Event, Invoice, Payment, or communication history.
- Use cPanel Cron to call Laravel Scheduler only when the hosting plan supports it. Otherwise provide an authorized manual operation for affected reminders or checks.
- Prebuild Vite assets before deployment; production does not require Node.js.

## Consequences

- Provider calls need bounded timeouts, explicit error handling, redacted logs, persisted attempt history, and clear retry guidance.
- Long reports and exports need filters, limits, streaming, or an explicit scheduled-command design rather than hidden background work.
- A failed external send does not roll back an already committed business transaction unless that send is itself the agreed transaction requirement.
- Manual payment records remain provider-independent and may store only descriptive channel/reference text.
- Static notifications and health checks work on shared hosting without additional processes.

## Revisit triggers

- Measured request times cannot meet agreed targets within bounded synchronous processing.
- A production host with supervised workers and operational monitoring is approved.
- A legally or commercially required real-time or payment capability is separately specified.
- The team defines retry, idempotency, reconciliation, security, and deployment requirements for the new infrastructure.

Any revision requires a new ADR and migration/deployment plan. Introducing a queue or gateway in code before that decision is not permitted.
