# Audit, Status History, and Protected Documents

## Scope

Prompt 07 implements the reusable cross-cutting baseline for SRS M24, M29, FR-X03, FR-X04, and FR-X05. It does not implement Event, Client, Vendor, Booking, Task, finance, expiry-notification, malware-scanner-provider, or retention-purge business workflows.

## Audit and login history

`AuditService` is the only normal application entry point for action-audit creation. It records actor, action, subject type/id, timestamp, IP address, bounded user agent, and sanitized before/after metadata. Sanitization recursively replaces password, token, secret, API key, authorization/cookie/CSRF, payment-card, bank-account, and sensitive dietary values with `[REDACTED]`; arbitrary objects are represented by class only and long strings are bounded.

`AuditLog` and `LoginHistory` use the `AppendOnly` concern. Eloquent updates and deletes raise an exception. No web update/delete routes exist. Database administrators may later implement a separately authorized retention command, but it must create its own immutable evidence before removal. Authentication history records success/failure, known user when available, normalized identifier, internal reason, IP, user agent, and attempt time; it never records the submitted password.

The `/audit` and `/audit/logins` screens require `audit.view`, are filterable and paginated, and expose read-only details. UI visibility is not the security boundary; policies authorize every route.

## Reusable status history

Models with workflow state implement `TracksStatusHistory` and use `HasStatusHistory`. `StatusTransitionService` locks the subject, verifies the model-owned transition map, updates the current status, appends `status_histories` with actor/type/time/reason/sanitized metadata, and writes the action audit in one database transaction. User-initiated transitions pass a User; a null actor is recorded explicitly as `system`.

The initial consumer is Document archive state. Existing Prompt 04 `user_status_histories` remains intact to preserve completed work; later domain modules should use the generic convention unless a domain-specific history requires additional constrained columns.

## Protected document storage

The `local` Laravel disk resolves to `storage/app/private`. `DocumentService` accepts only the allowlisted disk, MIME/extension pairs, and configured maximum size. The service rechecks validity even after Form Request validation, computes SHA-256, uses a UUID storage name under a year/month partition, and rejects unsafe storage results. Original names are metadata and never become storage paths.

`documents` owns title/category/optional branch/expiry/current status and current-version pointer. `document_versions` is append-only and retains disk, randomized path, original name, MIME, extension, size, checksum, version notes, uploader, and time. Replacement inserts a new immutable version and atomically changes only the current-version pointer. `document_links` is an explicit allowlisted polymorphic association. Company, Branch, and User are supported now; Event, Client, Vendor, and Booking classes can be added to the allowlist only when their owning prompts implement them.

Downloads always pass through an authenticated controller, `DocumentPolicy`, context-aware `DocumentAccessService`, disk allowlist, relationship check for historical versions, and storage existence check. Responses use private/no-store caching and `nosniff`. Storage paths are never rendered or returned as public URLs. `/storage` maps only to the public disk and cannot expose these private objects.

Administrators can operate every document workflow. Non-administrators require both document permissions and access through their own upload, a direct User link, or an assigned Branch/link. Global Company documents therefore remain administrator-only until a later sharing requirement is explicitly defined.

## Configuration

`config/documents.php` defines the protected disk, allowed disks, maximum kilobytes, MIME/extension pairs, and linkable-class allowlist. `DOCUMENT_DISK` and `DOCUMENT_MAX_KILOBYTES` may be set per environment, but the configured disk must still appear in the code-reviewed allowed-disk list. No package, object-storage provider, queue, worker, or public signed-link dependency was added.

Expiry dates are stored and filterable. Alert generation remains with the later Notifications prompt. File scanning can later be introduced behind a synchronous scanner contract; the current baseline uses strict type/size checks and safe private storage, and does not claim malware detection.
