<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffProfileRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\StaffService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StaffProfileController extends Controller
{
    public function index(Request $request, BranchScope $branches): View
    {
        Gate::authorize('viewAny', StaffProfile::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'record_status' => ['nullable', 'in:active,archived'], 'employment_status' => ['nullable', 'in:active,probation,on_leave,separated'], 'department' => ['nullable', 'integer', 'exists:departments,id'], 'branch' => ['nullable', 'integer', 'exists:branches,id']]);
        $query = $request->user()->hasPermission('staff.view') ? $branches->apply(StaffProfile::query(), $request->user()) : StaffProfile::query()->where('user_id', $request->user()->id);
        $staff = $query->with(['department', 'branch', 'user'])->search($filters['q'] ?? null)->when($filters['record_status'] ?? null, fn ($q, $v) => $q->where('record_status', $v), fn ($q) => $q->where('record_status', 'active'))->when($filters['employment_status'] ?? null, fn ($q, $v) => $q->where('employment_status', $v))->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))->when($filters['branch'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))->orderBy('display_name')->paginate(20)->withQueryString();

        return view('staff.index', ['staff' => $staff, 'filters' => $filters, 'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(), 'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', StaffProfile::class);

        return view('staff.create', $this->options());
    }

    public function store(StaffProfileRequest $request, StaffService $service): RedirectResponse
    {
        $profile = $service->create($request->validated(), $request->user());

        return redirect()->route('staff.show', $profile)->with('status', 'Staff profile created without creating a login.');
    }

    public function show(StaffProfile $staff): View
    {
        Gate::authorize('view', $staff);
        $staff->load(['department', 'branch', 'user', 'statusHistory.actor', 'documentLinks.document.currentVersion']);
        $users = User::query()->where('is_active', true)->whereNull('deleted_at')->whereHas('roles', fn ($q) => $q->where('slug', 'staff'))->where(fn ($q) => $q->whereDoesntHave('staffProfile')->when($staff->user_id, fn ($q, $id) => $q->orWhere('users.id', $id)))->orderBy('name')->get();

        return view('staff.show', compact('staff', 'users'));
    }

    public function edit(StaffProfile $staff): View
    {
        Gate::authorize('update', $staff);

        return view('staff.edit', $this->options() + ['staff' => $staff]);
    }

    public function update(StaffProfileRequest $request, StaffProfile $staff, StaffService $service): RedirectResponse
    {
        $staff = $service->update($staff, $request->validated(), $request->user());

        return redirect()->route('staff.show', $staff)->with('status', 'Staff profile updated.');
    }

    private function options(): array
    {
        return ['departments' => Department::query()->where('is_active', true)->orderBy('name')->get(), 'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get()];
    }
}
