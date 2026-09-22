<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffAssignmentRequest;
use App\Models\Event;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\StaffOperationsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventStaffAssignmentController extends Controller
{
    public function index(Request $request, Event $event, BranchScope $branches): View
    {
        Gate::authorize('viewModule', [$event, 'staff']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['planned', 'confirmed', 'in_progress', 'completed', 'cancelled'])],
            'staff' => ['nullable', 'integer', 'exists:staff_profiles,id'],
        ]);
        $query = $event->staffAssignments()->with(['staff.department', 'responsibleManager']);
        if (! $request->user()->hasPermission('staff.view-work')) {
            $query->whereHas('staff', fn ($staff) => $staff->where('user_id', $request->user()->getKey()));
        }
        $assignments = $query->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['staff'] ?? null, fn ($query, $value) => $query->where('staff_profile_id', $value))
            ->orderByDesc('scheduled_starts_at')->paginate(20)->withQueryString();

        $staffOptions = $request->user()->hasPermission('staff.view-work')
            ? $branches->apply(StaffProfile::query()->where('record_status', 'active')->whereNotIn('employment_status', ['separated']), $request->user())->orderBy('display_name')->get()
            : StaffProfile::query()->where('user_id', $request->user()->getKey())->orderBy('display_name')->get();

        return view('events.staff.index', [
            'event' => $event, 'assignments' => $assignments, 'filters' => $filters,
            'staffOptions' => $staffOptions,
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StaffAssignmentRequest $request, Event $event, StaffOperationsService $service): RedirectResponse
    {
        $data = $request->assignmentAttributes();
        $staff = StaffProfile::query()->findOrFail($data['staff_profile_id']);
        unset($data['staff_profile_id']);
        $assignment = $service->createAssignment($event, $staff, $data, $request->user());

        return back()->with($assignment->conflict_overridden ? 'warning' : 'status', $assignment->conflict_overridden ? 'Staff assigned with an authorized conflict override.' : 'Staff assigned to the Event.');
    }

    public function transition(Request $request, Event $event, StaffAssignment $assignment, StaffOperationsService $service): RedirectResponse
    {
        abort_unless($assignment->event_id === $event->getKey(), 404);
        Gate::authorize('update', $assignment);
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'in_progress', 'completed', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:2000'], 'completion_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $service->transitionAssignment($assignment, $data['status'], $request->user(), $data['reason'] ?? null, $data['completion_notes'] ?? null);

        return back()->with('status', 'Staff assignment status updated.');
    }
}
