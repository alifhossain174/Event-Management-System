# Requirements Traceability

## Purpose and baseline

This matrix maps the full scope in `Event_Management_System_SRS_Laravel_MySQL_v1.2.docx` to an implementation phase and planned verification. It covers all 29 functional modules, FR-X01 through FR-X13, and NFR-01 through NFR-13. The source SRS remains authoritative for detailed behavior; this file controls implementation sequencing and test coverage.

No row is considered implemented merely because a migration, screen, or class exists. Completion requires the listed automated tests and any stated manual verification to pass.

## Planned phases

| Phase | Scope |
| --- | --- |
| P0 Environment and project bootstrap | Laravel 12 scaffold, Git, local database and application configuration, test harness, and build verification. |
| P1 Foundation and core Client to Event flow | Authentication, RBAC, settings, master records, Event categories/templates, per-Event modules, direct Event creation, basic dashboard/search, protected documents, and audit. |
| P2 Common operations and finance | Optional Booking, Venue, vendor/staff assignments, Tasks, Budget, manual Payments, Invoices, Calendar, static Notifications, and Communication Center. |
| P3 Optional event services | Guest, Registration, Ticketing, Inventory, Catering, Decoration, Transportation, Accommodation, and Marketing. |
| P4 Reporting and business completion | Reports, Analytics, reconciliation, performance reporting, and module/template usability refinement. |
| P5 Hardening and deployment | Security, privacy, accessibility, performance, browser coverage, backup/restore, cPanel deployment, and user acceptance testing. |

## Implemented evidence

Prompt 04 provides partial P1 evidence for M02, M29, FR-X02, FR-X03, FR-X04, NFR-01, NFR-04, and NFR-07:

- `AuthenticationTest` covers active login/logout, inactive rejection, session termination, login history, and disabled public registration.
- `PasswordResetTest` covers active-user reset and suppresses reset delivery for inactive users.
- `ProfileTest` covers authenticated profile/password changes and their audit records.
- `UserManagementTest` covers seven-role seeding, role assignment, policy denial on direct routes, full Administrator / Business Manager permissions, status history, audit hooks, filters, and pagination.

This is not completion evidence for M29 or the cross-cutting requirements beyond identity/security; later domain prompts must extend the audit and authorization matrix for their own sensitive actions.

## Functional module traceability

| ID | SRS module | Planned phase | Planned test evidence |
| --- | --- | --- | --- |
| M01 | Dashboard | P1 baseline; P5 completion | `DashboardMetricsTest`: status counts and filtered drill-downs reconcile with Event records. `DashboardFinancialVisibilityTest`: financial totals reconcile and are hidden without permission. Browser smoke test covers date and manager filters. |
| M02 | User Management | P1; P5 security hardening | `UserAdministrationTest`: create, deactivate, search, reset password, and reject disabled login. `AuthorizationMatrixTest`: protected actions are denied server-side for every role. |
| M03 | Event Management | P1 | `DirectEventLifecycleTest`: create an Event without Booking, change status with history, duplicate planning configuration only, cancel with reason, and complete. `EventModuleConfigurationTest`: enable/disable modules and preserve existing module data. |
| M04 | Client Management | P1 | `ClientManagementTest`: create an individual or organization without a User, link Events/payments/communications, archive records with history, and flag duplicate contacts. `EventClientGateTest`: block leaving Draft without a Client. |
| M05 | Booking Management | P2 | `OptionalBookingWorkflowTest`: create, approve in one manager step, confirm, reschedule, cancel, waitlist, and convert/link to an Event. `DirectEventWithoutBookingTest`: the complete core workflow succeeds with a null Booking reference. |
| M06 | Venue Management | P2 | `VenueAvailabilityTest`: profile, capacity, quoted pricing, facilities, rooms, and availability. `VenueConflictTest`: conflicting exclusive allocations are blocked or explicitly overridden and capacity warnings appear. |
| M07 | Vendor Management | P1 master data; P2 assignments and finance | `VendorManagementTest`: create/archive a Vendor without login and maintain configurable categories. `VendorAssignmentTest`: availability, work orders, contracts, invoices, payments, delivery status, and completed-assignment ratings are scoped correctly. |
| M08 | Staff Management | P1 master data; P2 scheduling and salary tracking | `StaffManagementTest`: create Staff without login and maintain department/status. `StaffSchedulingTest`: shifts, leave, attendance, tasks, and conflicts. `SalaryLedgerTest`: simple salary/payment records work without statutory payroll calculations. |
| M09 | Guest Management | P3 | `GuestManagementTest`: guest list, RSVP, invitations, groups, VIP status, seating, and notes. `GuestCheckInTest`: unique QR token, one successful check-in, and duplicate-scan detection. |
| M10 | Ticket Management | P3 | `GeneralAdmissionTicketTest`: quantity-based ticket types, availability, issuance, promo rules, refund, and sold-out behavior. `TicketValidationTest`: non-guessable QR, event/status validation, duplicate scan detection, and rejection of refunded tickets. |
| M11 | Registration System | P3 | `RegistrationFormTest`: configure common field types and required validation for online/offline submissions. `RegistrationApprovalTest`: approval, confirmation, export, and optional ticket/guest creation follow Event configuration. |
| M12 | Task Management | P2 | `TaskWorkflowTest`: assign, comment, attach, prioritize, complete, and retain status history. `TaskDeadlineTest`: overdue derivation and event/assignee/status filters. |
| M13 | Budget Management | P2; P4 reconciliation | `EventBudgetTest`: separate budget from actuals and calculate category variance. `ProfitReconciliationTest`: posted income less posted expenses matches Event and report totals. |
| M14 | Payment Management | P2; P4 reconciliation | `ManualPaymentLedgerTest`: advance, partial, installment, final payment, allocation, due, receipt reference, correction, and refund preserve history. `NoGatewayDependencyTest`: payment workflows run with every provider disabled. |
| M15 | Invoice System | P2; P5 output verification | `InvoiceCalculationTest`: line items, discount, configurable tax/rounding, payments, refunds, and balance reconcile. `InvoiceSequenceTest`: prefix/year/sequence is unique. PDF/print visual test compares rendered totals with stored totals. |
| M16 | Inventory Management | P3; P4 reporting | `InventoryLedgerTest`: quantity-based stock movements reconcile and cannot be silently overwritten. `InventoryReservationTest`: active Event reservations affect availability, damage is traceable, and low stock creates a notification. |
| M17 | Catering Management | P3 | `CateringPlanTest`: menu, package, quantity, schedule, dietary data, vendor link, and cost. Authorization test restricts dietary data; finance integration test posts approved costs once. |
| M18 | Decoration Management | P3 | `DecorationPlanTest`: Event-specific theme/elements, reference files, revisions/notes, Vendor and Inventory links. Finance integration test prevents duplicate cost totals. |
| M19 | Transportation Management | P3 | `TransportationPlanTest`: vehicle, driver, route, pickup, guest/group, time, and status. `TransportConflictTest`: overlapping vehicle/driver assignments are flagged and costs reach Event expenses. |
| M20 | Accommodation Management | P3 | `AccommodationAllocationTest`: booking, room allocation, stay dates, check-in/out, and charges. Capacity test blocks over-allocation unless an authorized override exists. |
| M21 | Marketing Module | P3 | `MarketingPlanningTest`: campaigns, audience, content/link tracking, coupons, referrals, consent, opt-out, and metrics. `NoSocialPublishingTest`: no direct social API operation is exposed. |
| M22 | Communication Center | P2; P5 failure handling | `CommunicationLogTest`: templates, recipients, Event/Client context, status, and immutable attempt history. `DisabledProviderTest`: channels stay unavailable without credentials; synchronous provider failures are logged without losing history. |
| M23 | Calendar | P2 | `CalendarProjectionTest`: daily/weekly/monthly entries derive from Events and enabled schedules without duplicating source data. Permission, timezone, branch-disabled, and click-through tests cover visibility. |
| M24 | Document Management | P1; P5 security hardening | `ProtectedDocumentTest`: allowed upload types/sizes, private storage, contextual links, authorized download, and denial of direct unauthorized access. Expiry test creates the configured alert. |
| M25 | Reports | P4; P5 export/browser verification | `ReportReconciliationTest`: Event, finance, Client, Vendor, Staff, and Inventory totals match source records. Filter/export tests verify PDF/CSV/Excel output and permission controls. |
| M26 | Analytics Dashboard | P4; P5 performance verification | `AnalyticsMetricTest`: documented definitions, period comparison, growth, popular types, performance metrics, and financial reconciliation. Query-count and response-time tests cover agreed data volume. |
| M27 | Notifications | P2; P5 scheduler verification | `InAppNotificationTest`: correct recipient, read/unread persistence, source link, and permission scope. `NoPushOrSocketTest`: core behavior works by request/refresh and scheduled checks with no browser push, WebSocket, or queue worker. |
| M28 | Settings | P1; P5 deployment verification | `SettingsTest`: company, disabled-by-default branches, timezone, currency, language, tax, invoice prefix, integrations, security, and backup metadata. Secret encryption/masking and audit tests cover sensitive changes. |
| M29 | Audit and Security | P1; P5 hardening | `AuditTrailTest`: sensitive actions append actor/action/entity/time/before-after metadata and normal users cannot alter it. `LoginHistoryTest`: success/failure, IP, time, and user agent are filterable. Backup/restore evidence is covered in NFR-06. |

## Cross-module functional requirement traceability

| Requirement | Planned phase | Planned test evidence |
| --- | --- | --- |
| FR-X01 Global search | P1 framework; P2-P4 adapters | `GlobalSearchAuthorizationTest` covers Events, Bookings, Clients, Vendors, and Invoices and proves unauthorized records never appear. |
| FR-X02 Lists and export | P1 shared components; all later phases | Contract tests require server-side pagination, stable sorting, filters, bounded page size, and filter-consistent export on every applicable list. |
| FR-X03 Non-destructive history | P1 pattern; all phases | `ArchivalIntegrityTest` verifies archive/inactive/soft-delete behavior and retained financial, relationship, and audit references. |
| FR-X04 Status history | P1 pattern; all phases | Shared status-transition tests require actor and timestamp and reject invalid transitions. |
| FR-X05 Secured file service | P1; P5 | `DocumentAuthorizationTest` covers reusable contextual attachments, private paths, signed/controller-mediated access, and direct-link denial. |
| FR-X06 Organization formatting | P1; P5 | `OrganizationFormattingTest` covers configured timezone, currency, language, invoice rendering, and date/money consistency. |
| FR-X07 Optional branch scope | P1 schema; P5 enablement test | `BranchDisabledDefaultTest` proves single-company records work without branch selection. `BranchScopeTest` is prepared for later enablement and checks assigned-branch isolation. |
| FR-X08 Configurable master data | P1; P2-P3 consumers | CRUD and authorization tests cover Event, Vendor, Inventory, and Finance categories without code changes. |
| FR-X09 Event without Booking | P1 | `DirectEventWithoutBookingTest` runs Client to Event to payment/invoice to completion with a null Booking reference. |
| FR-X10 Editable Event modules | P1 | `EventTemplateSuggestionTest` verifies suggested defaults, manager overrides, later changes, and no hard-coded template lock. |
| FR-X11 Domain records without login | P1 | `NullablePortalIdentityTest` creates and uses Client, Vendor, and Staff records with null `user_id`; later account linking is separately authorized. |
| FR-X12 Preserve disabled-module data | P1 pattern; P2-P3 modules | `ModuleDisablePreservesDataTest` requires confirmation, hides normal navigation, retains records, and restores access after re-enable. |
| FR-X13 Disabled modules impose no fields | P1 pattern; P2-P3 modules | Parameterized lifecycle tests complete a small Event while every non-core module is disabled and assert no disabled-module validation runs. |

## Non-functional requirement traceability

| Requirement | Planned phase | Planned test evidence |
| --- | --- | --- |
| NFR-01 Security | P1 controls; P5 hardening | Feature tests for authentication, policies, CSRF, validation/escaping, mass assignment, rate limits, secure hashing, HTTPS production configuration, and common authorization bypasses. |
| NFR-02 Privacy | P1 data classification; P5 review | Permission tests for personal, payment, and dietary data; retention/export/deletion checks after policy values are confirmed; log and error-message inspection for sensitive data. |
| NFR-03 Performance | P5 | Repeatable baseline dataset and timed tests for typical authenticated list/detail actions, targeting under 2 seconds excluding third-party latency; record query counts and host profile. |
| NFR-04 Scalability | P1 query conventions; P5 load tests | Pagination limits, index review using query plans, bounded eager loading, synchronous workload tests, and an architecture assertion that Redis/Horizon/persistent workers are not required. |
| NFR-05 Availability | P5 | Health endpoint, production error handling, Laravel log checks, cPanel/Apache error-log runbook, and maintenance/recovery exercise after targets are agreed. |
| NFR-06 Backup | P5 | Scheduled command smoke test, protected database/file backup artifact, retention test after policy confirmation, restore into an isolated database, checksum/record validation, and documented recovery result. |
| NFR-07 Auditability | P1; P5 | Coverage matrix for refunds, cancellations/deletes, settings, permissions, restore, login/security events, and privileged corrections; immutability and retention tests. |
| NFR-08 Accessibility | P1 component rules; P5 audit | Keyboard-only walkthrough of core Client to Event flow, semantic label checks, focus visibility, contrast scan, error association, and responsive zoom/manual review. |
| NFR-09 Compatibility | P5 | Browser matrix for latest two major Chrome, Edge, Firefox, and Safari versions at desktop, tablet, and mobile breakpoints; PDF/print verification on agreed targets. |
| NFR-10 Localization | P1 architecture; P5 verification | Locale/timezone/currency configuration tests, translation-key coverage, UTF-8 data round-trip, and no hard-coded currency/date output in business screens. |
| NFR-11 Observability | P2 integrations; P5 operations | Tests and manual log review for application exceptions, provider failures, scheduled command failures, and payment-recording errors; secrets and personal data must be redacted. |
| NFR-12 Data integrity | P1 patterns; P2-P4 modules | Database constraints and transaction rollback tests for finance, Booking conversion, ticket issuance/validation/refund, stock movement, invoice sequencing, and status transitions. |
| NFR-13 Shared hosting compatibility | P0; P5 | Production-like deployment test with prebuilt assets, writable `storage` and `bootstrap/cache`, MySQL/MariaDB, HTTPS, scheduler via cron when available, and no Node runtime, Redis, WebSocket server, or long-running worker. |

## Traceability maintenance rules

- A test name is a planned identifier until the test file exists; implementation may refine the class name without weakening coverage.
- Each change to an SRS requirement must update this matrix, `DECISIONS.md`, affected tests, and `ARCHITECTURE.md` or `DATABASE_ROADMAP.md` when it changes a boundary or schema contract.
- Optional Event modules require tests in both enabled and disabled states.
- A passing UI test never substitutes for server-side authorization, transaction, or constraint tests.
- Phase completion requires all mapped Must requirements for that phase, no open critical/high-severity defects, and recorded user acceptance evidence.
