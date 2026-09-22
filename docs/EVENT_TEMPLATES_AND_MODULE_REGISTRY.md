# Event Templates and Module Registry

## Scope

Prompt 10 implements configuration used by future Event creation. It does not create the `events` or `event_module_settings` tables and does not implement Event CRUD.

The registry contains all 29 stable SRS module keys. Eighteen are event-scoped optional toggles; global, core, master, upstream, and projection keys cannot be selected as per-Event modules.

## Stable key contract

`config/event-modules.php` is the source-code contract for known keys, scope classification, initial labels/order, and dependency guidance. `module_definitions` materializes that contract so administrators can change display labels, display order, and active availability. The string `key`, `scope`, and event-scoped classification are immutable through the browser.

The event-scoped keys are:

`venue`, `vendors`, `staff`, `tasks`, `guests`, `registration`, `ticketing`, `budget`, `payments`, `invoices`, `inventory`, `catering`, `decoration`, `transportation`, `accommodation`, `marketing`, `documents`, and `communications`.

An inactive definition remains a valid historical key. It is hidden from future selection and is not enabled from a template, but existing references remain valid.

## Template behavior

`event_templates` stores editable category, descriptive/service notes, starter-task suggestions, budget-line suggestions, status, archive metadata, and optional duplication source. `event_template_modules` stores a unique module-key recommendation per template as either `default` or `optional`.

Applying a template is a copy operation:

- default recommendations initialize enabled states;
- optional recommendations remain visible suggestions but initialize disabled;
- manager overrides win;
- starter tasks, service notes, and budget lines are copied into an immutable `EventModulePlan` value object;
- every one of the 18 specialized modules can remain disabled;
- later template edits never mutate a previously created plan or Event;
- dependency guidance is returned as warnings and never rejects a valid selection.

The five editable starting templates are Birthday Party, Wedding/Marriage Ceremony, Corporate/Seminar/Workshop, Concert/Exhibition/Festival, and Custom Event. Custom Event has no preset modules. Seeder reruns create missing baseline records but do not overwrite administrator edits.

## Service API

`EventModuleService` is the integration boundary for Prompt 11 and later modules:

- `eventScopedKeys()` returns the stable toggle list;
- `validateModuleKeys()` rejects unknown and non-event keys;
- `initializationPlan()` copies an active template plus manager overrides;
- `enabledKeys()` normalizes persisted state rows or keyed state arrays;
- `dependencyWarnings()` returns advisory companion-module messages.

Future Event creation must persist the returned plan into Event-owned tables inside the Event transaction. It must not retain a live relationship that makes template edits retroactive.

`EventTemplateService` owns transactional create/update/duplicate/archive/reactivate behavior and audit records. `ModuleDefinitionService` changes only label, order, and active availability. Archive/reactivate uses the reusable status-history service; no destructive template route exists.

## Authorization and screens

`event-templates.view` protects the paginated/filterable template and registry lists and template details. `event-templates.configure` protects template creation/edit/duplicate/archive/reactivate and registry display settings. Administrator / Business Manager receives all actions; Event Manager receives view access only.

Administration routes are under `/settings/event-templates` and `/settings/module-definitions`. Navigation visibility is permission-aware, while Policies/Gates and Form Requests remain the security boundary.

## Deferred work

Prompt 11 owns Event CRUD, `event_module_settings`, persistence of plan snapshots, enable/disable history, populated-module confirmation, and preservation/re-enable tests against real Event data. No operational module, Booking workflow, Event task, or financial behavior is implemented here.

