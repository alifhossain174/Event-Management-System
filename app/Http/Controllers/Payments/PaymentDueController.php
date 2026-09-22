<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PaymentSchedule;
use App\Services\BranchScope;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PaymentDueController extends Controller
{
    public function __invoke(Request $request, BranchScope $branches, PaymentService $payments): View
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PaymentSchedule::STATUSES)],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'upcoming'])],
            'q' => ['nullable', 'string', 'max:191'],
        ]);
        $eventIds = $branches->apply(Event::query(), $request->user())
            ->whereHas('moduleSettings', fn (Builder $query) => $query->where('module_key', 'payments')->where('is_enabled', true))
            ->select('events.id');
        $query = PaymentSchedule::query()->whereIn('event_id', $eventIds)->with(['event', 'client'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(
                fn (Builder $query) => $query->where('label', 'like', "%{$q}%")
                    ->orWhereHas('event', fn (Builder $events) => $events->where('name', 'like', "%{$q}%")->orWhere('reference_number', 'like', "%{$q}%"))
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('display_name', 'like', "%{$q}%"))
            ))
            ->when(($filters['due'] ?? null) === 'overdue', fn (Builder $query) => $query->whereDate('due_date', '<', today())->whereNotIn('status', ['paid', 'cancelled']))
            ->when(($filters['due'] ?? null) === 'today', fn (Builder $query) => $query->whereDate('due_date', today()))
            ->when(($filters['due'] ?? null) === 'upcoming', fn (Builder $query) => $query->whereDate('due_date', '>', today())->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('due_date')->orderBy('id');

        return view('payments.due', [
            'schedules' => $query->paginate(25)->withQueryString(), 'filters' => $filters, 'paymentService' => $payments,
        ]);
    }
}
