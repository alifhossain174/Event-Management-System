<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BranchRequest;
use App\Models\Branch;
use App\Services\BranchScope;
use App\Services\BranchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BranchController extends Controller
{
    public function index(Request $request, BranchScope $scope): View
    {
        Gate::authorize('viewAny', Branch::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,archived'],
        ]);
        $query = ($filters['status'] ?? null) === 'archived' ? Branch::onlyTrashed() : Branch::query();
        $branches = $query
            ->search($filters['q'] ?? null)
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('settings.branches.index', compact('branches', 'filters', 'scope'));
    }

    public function create(): View
    {
        Gate::authorize('create', Branch::class);

        return view('settings.branches.create');
    }

    public function store(BranchRequest $request, BranchService $branches): RedirectResponse
    {
        Gate::authorize('create', Branch::class);
        $branch = $branches->create($request->validated(), $request->user());

        return redirect()->route('settings.branches.edit', $branch)->with('status', 'Branch created.');
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return view('settings.branches.edit', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch, BranchService $branches): RedirectResponse
    {
        Gate::authorize('update', $branch);
        $branches->update($branch, $request->validated(), $request->user());

        return back()->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch, BranchService $branches, Request $request): RedirectResponse
    {
        Gate::authorize('delete', $branch);
        $branches->archive($branch, $request->user());

        return redirect()->route('settings.branches.index')->with('status', 'Branch archived.');
    }
}
