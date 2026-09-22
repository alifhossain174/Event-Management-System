<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AttendanceRequest;
use App\Http\Requests\Staff\LeaveRequestRequest;
use App\Http\Requests\Staff\PerformanceRecordRequest;
use App\Http\Requests\Staff\SalaryRecordRequest;
use App\Http\Requests\Staff\ShiftRequest;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\LeaveRequest;
use App\Models\PerformanceRecord;
use App\Models\SalaryRecord;
use App\Models\Shift;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Services\BranchScope;
use App\Services\SettingsService;
use App\Services\StaffOperationsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class StaffOperationsController extends Controller
{
    public function index(Request $request, BranchScope $branches, SettingsService $settings): View
    {
        abort_unless($request->user()->hasPermission('staff.view-work') || $request->user()->hasPermission('staff.view-own-work'), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'staff' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'status' => ['nullable', 'string', 'max:30'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $profileQuery = $request->user()->hasPermission('staff.view-work')
            ? $branches->apply(StaffProfile::query(), $request->user())
            : StaffProfile::query()->where('user_id', $request->user()->getKey());
        $profiles = $profileQuery->orderBy('display_name')->get();
        $ids = $profiles->pluck('id');
        if (isset($filters['staff'])) {
            $ids = $ids->intersect([(int) $filters['staff']]);
        }

        $assignments = StaffAssignment::query()->whereIn('staff_profile_id', $ids)->with(['staff', 'event'])
            ->search($filters['q'] ?? null)->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('scheduled_ends_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('scheduled_starts_at', '<=', $v))
            ->orderByDesc('scheduled_starts_at')->paginate(15, ['*'], 'assignments_page')->withQueryString();
        $shifts = Shift::query()->whereIn('staff_profile_id', $ids)->with(['staff', 'event'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('ends_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('starts_at', '<=', $v))
            ->orderByDesc('starts_at')->paginate(15, ['*'], 'shifts_page')->withQueryString();
        $leave = LeaveRequest::query()->whereIn('staff_profile_id', $ids)->with('staff')
            ->orderByDesc('starts_on')->paginate(15, ['*'], 'leave_page')->withQueryString();
        $attendance = Attendance::query()->whereIn('staff_profile_id', $ids)->with(['staff', 'event'])
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('attendance_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('attendance_date', '<=', $v))
            ->orderByDesc('attendance_date')->paginate(15, ['*'], 'attendance_page')->withQueryString();
        $salary = $request->user()->hasPermission('staff.view-salary')
            ? SalaryRecord::query()->whereIn('staff_profile_id', $ids)->with('staff')->orderByDesc('period_ends_on')->paginate(15, ['*'], 'salary_page')->withQueryString()
            : null;
        $performance = $request->user()->hasPermission('staff.manage-performance')
            ? PerformanceRecord::query()->whereIn('staff_profile_id', $ids)->with(['staff', 'event'])->orderByDesc('reviewed_at')->paginate(15, ['*'], 'performance_page')->withQueryString()
            : null;

        return view('staff.operations', compact('profiles', 'assignments', 'shifts', 'leave', 'attendance', 'salary', 'performance', 'filters') + [
            'events' => $request->user()->hasPermission('staff.schedule') ? $branches->apply(Event::query()->whereNull('archived_at')->whereNotIn('status', ['completed', 'cancelled']), $request->user())->orderBy('starts_at')->limit(100)->get() : collect(),
            'currency' => $settings->string('general.currency'), 'timezone' => $settings->string('general.timezone'),
        ]);
    }

    public function storeShift(ShiftRequest $request, StaffProfile $staff, StaffOperationsService $service): RedirectResponse
    {
        $shift = $service->createShift($staff, $request->shiftAttributes(), $request->user());

        return back()->with($shift->conflict_overridden ? 'warning' : 'status', $shift->conflict_overridden ? 'Shift scheduled with an authorized conflict override.' : 'Shift scheduled.');
    }

    public function transitionShift(Request $request, StaffProfile $staff, Shift $shift, StaffOperationsService $service): RedirectResponse
    {
        abort_unless($shift->staff_profile_id === $staff->getKey(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['completed', 'cancelled'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        $service->transitionShift($shift, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Shift status updated.');
    }

    public function storeLeave(LeaveRequestRequest $request, StaffProfile $staff, StaffOperationsService $service): RedirectResponse
    {
        $service->requestLeave($staff, $request->validated(), $request->user());

        return back()->with('status', 'Leave request recorded.');
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest, StaffOperationsService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])], 'review_notes' => ['nullable', 'string', 'max:5000']]);
        $service->reviewLeave($leaveRequest, $data['status'], $request->user(), $data['review_notes'] ?? null);

        return back()->with('status', 'Leave status updated.');
    }

    public function storeAttendance(AttendanceRequest $request, StaffProfile $staff, StaffOperationsService $service): RedirectResponse
    {
        $service->recordAttendance($staff, $request->validated(), $request->user());

        return back()->with('status', 'Attendance recorded.');
    }

    public function storeSalary(SalaryRecordRequest $request, StaffProfile $staff, StaffOperationsService $service): RedirectResponse
    {
        $service->recordSalary($staff, $request->validated(), $request->user());

        return back()->with('status', 'Operational salary/payment tracking record saved.');
    }

    public function transitionSalary(Request $request, StaffProfile $staff, SalaryRecord $salaryRecord, StaffOperationsService $service): RedirectResponse
    {
        abort_unless($salaryRecord->staff_profile_id === $staff->getKey(), 404);
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(['paid', 'void'])],
            'paid_on' => ['nullable', 'date'], 'payment_reference' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->transitionSalary($salaryRecord, $data['payment_status'], $request->user(), $data['paid_on'] ?? null, $data['payment_reference'] ?? null, $data['reason'] ?? null);

        return back()->with('status', 'Salary/payment tracking status updated.');
    }

    public function storePerformance(PerformanceRecordRequest $request, StaffProfile $staff, StaffOperationsService $service): RedirectResponse
    {
        $service->recordPerformance($staff, $request->validated(), $request->user());

        return back()->with('status', 'Performance record saved.');
    }
}
