<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\UpdateEventModulesRequest;
use App\Models\Event;
use App\Models\EventModuleChangeHistory;
use App\Models\ModuleDefinition;
use App\Services\EventModuleDataRegistry;
use App\Services\EventModuleService;
use App\Services\EventModuleSettingService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventModuleSettingController extends Controller
{
    public function edit(
        Request $request,
        Event $event,
        EventModuleService $modules,
        EventModuleDataRegistry $dataDetectors,
        SettingsService $settings,
    ): View {
        Gate::authorize('manageModules', $event);
        $event->load('moduleSettings');
        $enabled = $event->moduleSettings->where('is_enabled', true)->pluck('module_key')->all();
        $filters = $request->validate([
            'history_module' => ['nullable', Rule::in($modules->eventScopedKeys())],
            'history_action' => ['nullable', Rule::in(['enabled', 'disabled'])],
        ]);
        $history = EventModuleChangeHistory::query()
            ->where('event_id', $event->getKey())
            ->with(['actor', 'definition'])
            ->when($filters['history_module'] ?? null, fn ($query, $key) => $query->where('module_key', $key))
            ->when(($filters['history_action'] ?? null) === 'enabled', fn ($query) => $query->where('to_enabled', true))
            ->when(($filters['history_action'] ?? null) === 'disabled', fn ($query) => $query->where('to_enabled', false))
            ->orderByDesc('changed_at')
            ->paginate(20, ['*'], 'history')
            ->withQueryString();

        return view('events.modules', [
            'event' => $event,
            'moduleDefinitions' => ModuleDefinition::query()->where('is_event_scoped', true)->orderBy('sort_order')->get(),
            'enabledModules' => $enabled,
            'warnings' => $modules->dependencyWarnings($enabled),
            'dataPresence' => $dataDetectors->presenceFor($event, $modules->eventScopedKeys()),
            'history' => $history,
            'historyFilters' => $filters,
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function update(UpdateEventModulesRequest $request, Event $event, EventModuleSettingService $modules): RedirectResponse
    {
        $modules->update(
            $event,
            $request->enabledModules(),
            $request->user(),
            $request->boolean('confirm_disable'),
            $request->boolean('confirm_disable_with_data'),
            $request->reason(),
        );

        return redirect()->route('events.show', $event)->with('status', 'Event modules updated. Disabled-module data remains preserved.');
    }
}
