<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\EventLifecycleService;
use App\Services\EventModuleService;
use App\Services\EventQueryService;
use App\Services\EventService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventController extends Controller
{
    public function index(
        Request $request,
        BranchScope $branches,
        SettingsService $settings,
        EventQueryService $eventQueries,
    ): View {
        Gate::authorize('viewAny', Event::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,confirmed,planning,in_progress,completed,cancelled'],
            'category' => ['nullable', 'integer', 'exists:event_categories,id'],
            'client' => ['nullable', 'integer', 'exists:clients,id'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'manager' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'view' => ['nullable', 'in:upcoming'],
            'archive' => ['nullable', 'in:active,archived'],
        ]);

        $events = $eventQueries->applyListScope(
            $eventQueries->applyDashboardFilters(
                $branches->apply(Event::query(), $request->user()),
                $filters,
            ),
            $filters['view'] ?? null,
        )
            ->with(['client', 'category', 'manager', 'branch'])
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client'] ?? null, fn ($query, $client) => $query->where('client_id', $client))
            ->when(($filters['archive'] ?? 'active') === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'filters' => $filters,
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'clients' => $branches->apply(Client::query()->where('status', 'active'), $request->user())->orderBy('display_name')->limit(200)->get(),
            'branches' => $branches->optionsFor($request->user()),
            'managers' => $eventQueries->managerOptions($request->user()),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function create(Request $request, EventModuleService $modules, SettingsService $settings): View
    {
        Gate::authorize('create', Event::class);
        $template = EventTemplate::query()->where('status', 'active')->find($request->integer('template'));
        $plan = $modules->initializationPlan($template);

        return view('events.create', $this->formData($request, $settings) + [
            'selectedTemplate' => $template,
            'initialEnabledModules' => $plan->enabledKeys(),
            'moduleWarnings' => $plan->warnings,
        ]);
    }

    public function store(StoreEventRequest $request, EventService $events): RedirectResponse
    {
        $event = $events->create($request->eventAttributes(), $request->enabledModules(), $request->user());

        return redirect()->route('events.show', $event)->with('status', 'Event created directly in Draft without requiring a Booking.');
    }

    public function show(Event $event, EventLifecycleService $lifecycle, EventModuleService $modules, SettingsService $settings): View
    {
        Gate::authorize('view', $event);
        $event->load([
            'client', 'booking', 'category', 'template', 'sourceEvent', 'manager', 'branch',
            'moduleSettings.definition', 'statusHistory.actor', 'notes.author',
            'timelineItems.actor', 'documentLinks.document.currentVersion',
        ]);
        $workspaceModules = $event->moduleSettings
            ->where('is_enabled', true)
            ->filter(fn ($setting) => Gate::allows('viewModule', [$event, $setting->module_key]))
            ->values();

        return view('events.show', [
            'event' => $event,
            'availableTransitions' => $lifecycle->availableTransitions($event),
            'moduleWarnings' => $modules->dependencyWarnings($workspaceModules->pluck('module_key')->all()),
            'workspaceModules' => $workspaceModules,
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function edit(Request $request, Event $event, SettingsService $settings): View
    {
        Gate::authorize('update', $event);

        return view('events.edit', $this->formData($request, $settings) + ['event' => $event]);
    }

    public function update(UpdateEventRequest $request, Event $event, EventService $events): RedirectResponse
    {
        $events->update($event, $request->eventAttributes(), $request->user());

        return redirect()->route('events.show', $event)->with('status', 'Event details updated.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, SettingsService $settings): array
    {
        $branches = app(BranchScope::class);

        return [
            'clients' => $branches->apply(Client::query()->where('status', 'active'), $request->user())->orderBy('display_name')->limit(500)->get(),
            'categories' => EventCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'templates' => EventTemplate::query()->where('status', 'active')->with('modules')->orderBy('name')->get(),
            'moduleDefinitions' => ModuleDefinition::query()->where('is_event_scoped', true)->where('is_active', true)->orderBy('sort_order')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'managers' => $this->managers(),
            'timezones' => collect(config('system-settings.timezones', ['UTC']))->mapWithKeys(fn (string $timezone) => [$timezone => $timezone]),
            'organizationTimezone' => $settings->string('general.timezone'),
        ];
    }

    /** @return Collection<int, User> */
    private function managers(): Collection
    {
        return User::query()->where('is_active', true)->whereNull('deleted_at')
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['administrator', 'event-manager']))
            ->orderBy('name')->get();
    }
}
