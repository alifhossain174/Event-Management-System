<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'role' => ['nullable', 'string', 'exists:roles,slug'],
        ]);

        $query = ($filters['status'] ?? null) === 'archived'
            ? User::onlyTrashed()
            : User::query()->withAccountStatus($filters['status'] ?? null);

        $users = $query->with('roles')
            ->search($filters['q'] ?? null)
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas(
                'roles', fn ($query) => $query->where('slug', $role),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['roles' => Role::query()->orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request, UserManagementService $users): RedirectResponse
    {
        $user = $users->create($request->validated(), $request->user());

        return redirect()->route('users.show', $user)->with('status', 'User created.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('users.show', [
            'managedUser' => $user->load(['roles', 'statusHistory.actor']),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        Gate::authorize('assignRoles', $user);

        return view('users.edit', [
            'managedUser' => $user->load('roles'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserManagementService $users): RedirectResponse
    {
        $users->update($user, $request->validated(), $request->user());

        return redirect()->route('users.show', $user)->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $users->archive($user, $request->user(), $validated['reason'] ?? null);

        return redirect()->route('users.index')->with('status', 'User archived.');
    }
}
