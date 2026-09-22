# Event Workspace and Module Activation

## Purpose

The Event detail page is the operational center for the core Client-to-Event flow. Event summary, Client, lifecycle actions, planning notes, status history, and timeline remain available independently of every optional module. Optional workspace cards are rendered only when the module is enabled for that Event and the current user passes server-side module authorization.

This baseline does not implement Venue, finance, ticketing, or another optional module. Each card currently reaches a protected readiness page so the access contract can be verified before later module screens are introduced.

## Route protection contract

Every Event-scoped module route must use the `event.module.enabled` middleware in addition to normal authentication, active-account checks, controller authorization, Form Requests, and policies. Use a static middleware parameter on future module routes:

```php
Route::get('/events/{event}/documents', EventDocumentController::class)
    ->middleware('event.module.enabled:documents');
```

The shared workspace route reads its module key from the route parameter. Unknown keys return 404. An authorized module manager requesting a disabled module is redirected to Manage Modules with a warning. Other users receive 404 so preserved module data is not disclosed. Enabling a module never grants access by itself; `EventPolicy::viewModule` also checks Event visibility and the configured module-view permission.

## Data detector contract

Later optional modules that persist Event-owned data must implement `App\Contracts\EventModuleDataDetector` and register the detector class in `config/event-modules.php`. The detector answers only whether data exists for one Event; it must use a bounded indexed existence query and must not mutate records.

The protected Document link is the first registered detector. A Document linked to an Event counts as module data even when the Document is archived, because disabling must preserve historical context.

## Disable and re-enable behavior

- Any disablement requires the general confirmation checkbox.
- If a registered detector finds data, a second explicit confirmation and a reason are required.
- The setting row is updated inside an Event-locked database transaction; module records are never deleted or detached.
- Each actual state change appends `event_module_change_histories` with module key, prior/new state, data-presence flag, actor, reason, and microsecond timestamp.
- The existing audit log and Event timeline also receive the change and reason.
- Re-enabling restores the workspace path to the unchanged records.
- Disabled modules never participate in core Event validation, status transitions, or completion.

## Origin and dependency presentation

`event_module_settings.origin` preserves whether the initial state came from a template or manual selection. The existing `source` field records the latest state-change source. Manage Modules presents the immutable origin, current data-presence indicator, and warning-only dependency guidance. Dependency warnings never select modules, reject a save, or block Event completion.

## Verification

`tests/Feature/Events/EventWorkspaceTest.php` covers permission-aware workspace navigation, disabled and unknown direct routes, populated-module confirmation and reason rules, record preservation, re-enable restoration, manager-only administration, origin indicators, and filterable change history. `EventLifecycleTest` continues to prove that a single administrator can complete an Event with all optional modules disabled.
