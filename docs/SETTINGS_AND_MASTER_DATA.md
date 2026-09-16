# Organization Settings Branches and Master Data

## Purpose

Prompt 06 provides the configuration services later modules consume. It implements organization identity, typed settings, optional branch preparation, and five configurable category domains without implementing Events, providers, invoices, documents, or other later workflows.

## Typed settings contract

`App\Services\SettingsService` is the only application-facing interface for `system_settings`. Allowed keys and defaults live in `config/system-settings.php`.

- `string`, `boolean`, `integer`, and `decimal` methods return stable PHP types. Decimal values remain strings so financial services do not inherit binary floating-point errors.
- Values are cached with the configured Laravel cache store and invalidated after every write. This works with the approved file cache and a future database cache.
- Unknown keys fail explicitly instead of creating speculative configuration.
- Secret keys require the separate `secret()` method. They are encrypted with the current application key.
- Forms submit an empty secret to retain the existing value. Views receive only a configured/not-configured flag, and audit payloads store only `[configured]` or `[not configured]`.

Changing `APP_KEY` without decrypting and re-encrypting saved secrets makes them unreadable. Production key rotation therefore requires an explicit maintenance procedure.

## Organization profile and branding

The one-company default uses the first `companies` row. Contact/address fields and logo metadata are audited. Uploaded logos are intentionally public branding media stored on the public disk; contracts and later business documents remain protected private files.

## Branch strategy

Branch mode defaults to disabled through `features.branches_enabled`. Branch records can be prepared without requiring branch selection on existing or future records.

Later branch-aware queries must call `BranchScope::apply()` explicitly. No global Eloquent scope is installed. The service follows these rules:

- disabled mode does not restrict any query;
- administrators remain unrestricted;
- enabled mode includes the user's assigned branches;
- null `branch_id` records remain visible so pre-enable data is not silently lost;
- later launch of strict branch isolation requires assignment/backfill review and its prepared authorization tests.

## Category master data

Event, Vendor, Inventory, Finance, and Document categories use separate tables and models. They share stable slug, description, active/archive, ordering, policy, service, filters, pagination, and audit conventions. Finance categories additionally require an `income`, `expense`, or `both` direction.

The shared `MasterDataRegistry` contains only supported category domains. Adding a domain requires a dedicated model/table when its constraints differ, a registry entry, permissions, validation, and tests. Do not place unrelated fields in a metadata JSON column to avoid a migration.
