<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CategoryRequest;
use App\Services\MasterDataService;
use App\Support\MasterDataRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MasterDataController extends Controller
{
    public function index(string $type, Request $request, MasterDataRegistry $registry): View
    {
        $definition = $registry->get($type);
        Gate::authorize('viewAny', $definition['model']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,archived'],
        ]);
        $model = $definition['model'];
        $query = ($filters['status'] ?? null) === 'archived' ? $model::onlyTrashed() : $model::query();
        $categories = $query
            ->search($filters['q'] ?? null)
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $types = $registry->all();

        return view('settings.master-data.index', compact('type', 'definition', 'categories', 'filters', 'types'));
    }

    public function create(string $type, MasterDataRegistry $registry): View
    {
        $definition = $registry->get($type);
        Gate::authorize('create', $definition['model']);

        $types = $registry->all();

        return view('settings.master-data.create', compact('type', 'definition', 'types'));
    }

    public function store(string $type, CategoryRequest $request, MasterDataRegistry $registry, MasterDataService $service): RedirectResponse
    {
        $definition = $registry->get($type);
        Gate::authorize('create', $definition['model']);
        $service->create($type, $definition['model'], $request->validated(), $request->user());

        return redirect()->route('settings.master-data.index', $type)->with('status', "{$definition['singular']} created.");
    }

    public function edit(string $type, int $category, MasterDataRegistry $registry): View
    {
        $definition = $registry->get($type);
        $category = $this->find($definition['model'], $category);
        Gate::authorize('update', $category);

        $types = $registry->all();

        return view('settings.master-data.edit', compact('type', 'definition', 'category', 'types'));
    }

    public function update(string $type, int $category, CategoryRequest $request, MasterDataRegistry $registry, MasterDataService $service): RedirectResponse
    {
        $definition = $registry->get($type);
        $category = $this->find($definition['model'], $category);
        Gate::authorize('update', $category);
        $service->update($type, $category, $request->validated(), $request->user());

        return back()->with('status', "{$definition['singular']} updated.");
    }

    public function destroy(string $type, int $category, Request $request, MasterDataRegistry $registry, MasterDataService $service): RedirectResponse
    {
        $definition = $registry->get($type);
        $category = $this->find($definition['model'], $category);
        Gate::authorize('delete', $category);
        $service->archive($type, $category, $request->user());

        return redirect()->route('settings.master-data.index', $type)->with('status', "{$definition['singular']} archived.");
    }

    private function find(string $modelClass, int $id): Model
    {
        return $modelClass::query()->findOrFail($id);
    }
}
