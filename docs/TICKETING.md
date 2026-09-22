# Ticketing

## Scope

Prompt 25 implements quantity-based general-admission Tickets. VIP, Regular, Early Bird, and custom Ticket Types are editable data. The module does not implement reserved seats, seat maps, online checkout, a payment gateway, webhooks, or card storage.

## Inventory and issuance

`TicketService::issue` runs in a database transaction and locks the Event and Ticket Type before calculating remaining quantity from Tickets in `issued` or `used` state. It then rechecks the sale window, Promo Code, ticket-count usage limit, and optional posted Payment. A unique idempotency key makes a repeated manager submission return the original Ticket Order. The Ticket Type row lock serializes concurrent last-quantity attempts on MySQL/InnoDB.

Ticket Orders contain a single Ticket Type and quantity. Every Ticket copies its unit price, unit discount, final price, and currency. Later edits to a Ticket Type or Promo Code cannot rewrite issued values. Percentage and fixed discounts apply per Ticket. Promo usage limits count issued Ticket quantity and are intentionally not restored after a refund or cancellation; this policy is reversible for future orders only.

Free orders require no Payment. A priced order must reference a posted manual Payment belonging to the same Event and Client with enough unallocated refundable value. This prevents Ticketing from fabricating sales or duplicating the Finance ledger.

## Credentials and validation

Each Ticket receives 32 random bytes encoded as base64url. The database stores an indexed SHA-256 hash for lookup and a Laravel-encrypted copy for authorized QR re-rendering. QR content is the private Ticket URL only and contains no attendee name, email, phone, price, or Event details.

The validation action locks the Ticket, constrains it to the current Event, accepts either the raw token or its public URL, and creates one unique append-only `ticket_validations` row. A repeated scan returns `Already Used` with the original time and operator. Unknown, wrong-Event, refunded, and cancelled credentials fail without revealing other Event data.

## Refunds and cancellations

A free Ticket refund creates a zero-value `ticket_refunds` trace. A priced Ticket refund requires the order's linked posted Payment and invokes `PaymentService::refund`, which creates the existing manual Refund and recognized-Income reversal; `financial_refund_id` links both histories. There is no gateway action. Used Tickets cannot be refunded. A paid order cannot be cancelled while active paid Tickets remain; those Tickets must be refunded first. Refunds/cancellations preserve every order/Ticket/audit/status record and return released quantity to availability.

## Access and publication

- Event Manager: view/configure/issue/validate/email/publish.
- Finance / Accounts: view and refund.
- Front Desk / Check-in: view and validate only.
- Administrator / Business Manager: all actions without another account.

All authenticated Event routes use `event.module.enabled:ticketing` plus Event visibility and TicketPolicy. TicketDataDetector makes populated disablement use the standard explicit confirmation flow and preserves all records. Public catalogue, Ticket, and QR routes require the Event Ticket catalogue to be explicitly published and the Ticketing module to remain enabled. Public pages never offer checkout; issuance remains authenticated.

Ticket email uses CommunicationService synchronously and links the immutable outbound attempt to its Ticket Order. The stored/sent notification contains the non-secret order reference and quantity, not the private credential; secure admission credentials remain available through the authorized print/QR screen and explicitly published opaque-token page. A missing/failed provider is logged as Failed. Printing uses stored snapshots and locally rendered SVG QR assets.

## Verification

`TicketingManagementTest` covers serialized last-ticket behavior, idempotency, Promo validity/limits, credential secrecy, URL/raw-token validation, wrong-Event and double scans, refund invalidation, manual ledger reversal linkage, RBAC, explicit publication, disabled-module preservation, and Registration independence.
