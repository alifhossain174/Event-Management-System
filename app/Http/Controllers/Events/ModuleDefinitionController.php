<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ModuleDefinitionRequest;
use App\Models\ModuleDefinition;
use App\Services\ModuleDefinitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ModuleDefinitionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ModuleDefinition::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'scope' => ['nullable', 'string', 'max:30'], 'status' => ['nullable', 'in:active,inactive']]);
        $definitions = ModuleDefinition::query()->search($filters['q'] ?? null)
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')->paginate(20)->withQueryString();

        return view('settings.module-definitions.index', ['definitions' => $definitions, 'filters' => $filters]);
    }

    public function edit(ModuleDefinition $moduleDefinition): View
    {
        Gate::authorize('update', $moduleDefinition);

        return view('settings.module-definitions.edit', compact('moduleDefinition'));
    }

    public function update(ModuleDefinitionRequest $request, ModuleDefinition $moduleDefinition, ModuleDefinitionService $service): RedirectResponse
    {
        $service->update($moduleDefinition, $request->validated(), $request->user());

        return redirect()->route('settings.module-definitions.index')->with('status', 'Module display settings updated; the stable key and scope were not changed.');
    }
}
