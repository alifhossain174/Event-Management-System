<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CancelPaymentScheduleRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\StorePaymentScheduleRequest;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\InvoiceBalanceService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventPaymentController extends Controller
{
    public function index(Request $request, Event $event, PaymentService $payments, SettingsService $settings, InvoiceBalanceService $invoiceBalances): View
    {
        Gate::authorize('viewModule', [$event, 'payments']);
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'payment_type' => ['nullable', Rule::in(Payment::TYPES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $paymentRows = $event->payments()->with(['receivedBy', 'allocations.schedule', 'refunds'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(
                fn (Builder $query) => $query->where('receipt_number', 'like', "%{$q}%")
                    ->orWhere('external_reference', 'like', "%{$q}%")
                    ->orWhere('channel', 'like', "%{$q}%")
            ))
            ->when($filters['payment_type'] ?? null, fn (Builder $query, string $type) => $query->where('payment_type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('received_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('received_at', '<=', $date))
            ->paginate(15, ['*'], 'payments_page')->withQueryString();
        $schedules = $event->paymentSchedules()->with('allocations.refunds')
            ->paginate(15, ['*'], 'schedules_page')->withQueryString();
        $payableInvoices = $request->user()->hasPermission('invoices.view')
            && $event->moduleSettings()->where('module_key', 'invoices')->where('is_enabled', true)->exists()
            ? Invoice::query()->where('event_id', $event->getKey())->whereIn('status', ['issued', 'partially_paid'])
                ->orderBy('due_date')->limit(100)->get()->map(fn (Invoice $invoice) => [
                    'invoice' => $invoice, 'balance' => $invoiceBalances->balance($invoice),
                ])
            : collect();

        return view('events.payments.index', [
            'event' => $event->load('client'), 'payments' => $paymentRows, 'schedules' => $schedules,
            'filters' => $filters, 'summary' => $payments->eventSummary($event),
            'paymentService' => $payments,
            'activeUsers' => User::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get(),
            'organizationTimezone' => $settings->string('general.timezone'),
            'payableInvoices' => $payableInvoices,
        ]);
    }

    public function store(StorePaymentRequest $request, Event $event, PaymentService $payments): RedirectResponse
    {
        $payment = $payments->postPayment($event, $request->paymentAttributes(), $request->allocations(), $request->user());

        return redirect()->route('events.payments.receipt', [$event, $payment])->with('status', 'Manual payment posted and receipt created.');
    }

    public function storeSchedule(StorePaymentScheduleRequest $request, Event $event, PaymentService $payments): RedirectResponse
    {
        $payments->createSchedule($event, $request->validated(), $request->user());

        return back()->with('status', 'Payment due schedule created.');
    }

    public function cancelSchedule(CancelPaymentScheduleRequest $request, Event $event, PaymentSchedule $schedule, PaymentService $payments): RedirectResponse
    {
        abort_unless($schedule->event_id === $event->getKey(), 404);
        $payments->cancelSchedule($schedule, $request->validated('reason'), $request->user());

        return back()->with('status', 'Payment schedule cancelled without deleting history.');
    }
}
