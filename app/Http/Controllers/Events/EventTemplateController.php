<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventTemplateRequest;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use App\Services\EventModuleService;
use App\Services\EventTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventTemplateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EventTemplate::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,archived'], 'category' => ['nullable', 'integer', 'exists:event_categories,id']]);
        $templates = EventTemplate::query()->with(['category', 'modules.definition'])
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status), fn ($query) => $query->where('status', 'active'))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('event_category_id', $category))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('settings.event-templates.index', ['templates' => $templates, 'filters' => $filters, 'categories' => $this->categories()]);
    }

    public function create(): View
    {
        Gate::authorize('create', EventTemplate::class);

        return view('settings.event-templates.create', $this->formData());
    }

    public function store(EventTemplateRequest $request, EventTemplateService $service): RedirectResponse
    {
        $template = $service->create($request->templateData(), $request->user());

        return redirect()->route('settings.event-templates.show', $template)->with('status', 'Event template created.');
    }

    public function show(EventTemplate $eventTemplate, EventModuleService $modules): View
    {
        Gate::authorize('view', $eventTemplate);
        $eventTemplate->load(['category', 'sourceTemplate', 'modules.definition', 'statusHistory.actor']);
        $enabled = $eventTemplate->modules->where('recommendation', 'default')->pluck('module_key')->all();

        return view('settings.event-templates.show', ['eventTemplate' => $eventTemplate, 'warnings' => $modules->dependencyWarnings($enabled)]);
    }

    public function edit(EventTemplate $eventTemplate): View
    {
        Gate::authorize('update', $eventTemplate);
        $eventTemplate->load('modules');

        return view('settings.event-templates.edit', $this->formData() + ['eventTemplate' => $eventTemplate]);
    }

    public function update(EventTemplateRequest $request, EventTemplate $eventTemplate, EventTemplateService $service): RedirectResponse
    {
        $eventTemplate = $service->update($eventTemplate, $request->templateData(), $request->user());

        return redirect()->route('settings.event-templates.show', $eventTemplate)->with('status', 'Event template updated. Existing Event module snapshots are unchanged.');
    }

    private function formData(): array
    {
        return ['categories' => $this->categories(), 'moduleDefinitions' => ModuleDefinition::query()->where('is_event_scoped', true)->where('is_active', true)->orderBy('sort_order')->get()];
    }

    private function categories()
    {
        return EventCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}
