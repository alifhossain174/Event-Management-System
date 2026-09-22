# Notifications and Communication Center

Prompt 22 implements stored alerts and synchronous communication history without real-time infrastructure.

## Boundaries

- Notifications are global and recipient-private. Role targeting is resolved to active users when an alert is created, preserving one read/unread state per user.
- Links store an allowlisted named route and parameters, never an arbitrary URL. The destination controller, policy, branch scope, and optional-module middleware still authorize access.
- Communications are Event-scoped and keep nullable Client and Booking context. The Event `communications` module must be enabled and the user must have the matching permission.
- Operational messages and marketing messages have distinct consent snapshots. Marketing requires explicit confirmation; this is not a substitute for the later Marketing module's consent ledger.
- Outbound delivery is synchronous. Every attempt is append-only; retry never replaces history. Error storage uses safe codes rather than provider response bodies, secrets, tokens, or credentials.
- Email uses Laravel mail. Local/testing array or log transports are allowed. Production requires the communications feature flag and valid sender/transport configuration.
- SMS and WhatsApp are deliberately disabled adapter implementations until a maintained provider and credentials are approved. Missing configuration produces a retained Failed attempt, not data loss.
- No queue, Horizon, Redis, WebSocket, polling daemon, browser push, or direct social publishing is used.

## Implemented sources

- Booking enquiry creation;
- manual Payment receipt;
- Staff and Vendor Event assignment when a linked active User exists;
- Event start within 24 hours;
- PaymentSchedule due date;
- VendorContract expiry within the configured command look-ahead window;
- manually scheduled per-user Event reminders.

Low-stock alerts are an extension point only because Inventory is not implemented. Later source modules call `NotificationService` with a stable idempotency key and an allowlisted destination.

## Operations

`php artisan notifications:send-due-reminders` processes due manual reminders and discovers Event/payment reminders. `php artisan notifications:check-expiries --days=30` discovers expiring Vendor contracts. Both may run repeatedly without creating duplicate alerts. Laravel Scheduler registers them for cPanel `schedule:run`; manual execution remains supported.
