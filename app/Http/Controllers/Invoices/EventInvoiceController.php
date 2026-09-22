<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\Event;
use App\Models\Invoice;
use App\Services\InvoiceBalanceService;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventInvoiceController extends Controller
{
    public function index(Request $request, Event $event, InvoiceBalanceService $balances): View
    {
        Gate::authorize('viewModule', [$event, 'invoices']);
        abort_unless($request->user()->hasPermission('invoices.view'), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in([...Invoice::STATUSES, 'overdue'])],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $invoices = $event->invoices()->with(['client', 'creditNote'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")->orWhere('client_name', 'like', "%{$q}%")))
            ->when(($filters['status'] ?? null) === 'overdue', fn (Builder $query) => $query->whereIn('status', ['issued', 'partially_paid'])->whereDate('due_date', '<', today()))
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'overdue', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '<=', $date))
            ->paginate(15)->withQueryString();

        return view('events.invoices.index', compact('event', 'invoices', 'filters', 'balances'));
    }

    public function create(Request $request, Event $event, SettingsService $settings): View
    {
        Gate::authorize('create', [Invoice::class, $event]);
        $event->load(['client', 'booking']);
        $items = [[
            'description' => $event->name.' event services', 'quantity' => '1.0000',
            'unit_price' => $event->core_budget_estimate ?: '0.0000', 'discount_type' => 'none',
            'discount_value' => '0.000000', 'tax_rate' => $settings->decimal('finance.default_tax_rate'),
        ]];

        return view('events.invoices.create', [
            'event' => $event, 'invoice' => new Invoice(['due_date' => today()->addDays(14), 'tax_label' => 'Tax', 'default_tax_rate' => $settings->decimal('finance.default_tax_rate')]),
            'items' => $items,
        ]);
    }

    public function store(StoreInvoiceRequest $request, Event $event, InvoiceService $service): RedirectResponse
    {
        $invoice = $service->createDraft($event, $request->invoiceAttributes(), $request->items(), $request->user());

        return redirect()->route('events.invoices.show', [$event, $invoice])->with('status', 'Invoice draft created from the Event charge details.');
    }

    public function show(Request $request, Event $event, Invoice $invoice, InvoiceBalanceService $balances): View
    {
        $this->nested($event, $invoice);
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'statusHistory.actor', 'deliveries.attemptedBy', 'creditNote', 'creditFor']);

        return view('events.invoices.show', [
            'event' => $event, 'invoice' => $invoice,
            'applied' => $balances->applied($invoice), 'balance' => $balances->balance($invoice),
        ]);
    }

    public function edit(Request $request, Event $event, Invoice $invoice): View
    {
        $this->nested($event, $invoice);
        Gate::authorize('update', $invoice);

        return view('events.invoices.edit', ['event' => $event->load('booking'), 'invoice' => $invoice->load('items'), 'items' => $invoice->items]);
    }

    public function update(UpdateInvoiceRequest $request, Event $event, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $invoice = $service->updateDraft($invoice, $request->invoiceAttributes(), $request->items(), $request->user());

        return redirect()->route('events.invoices.show', [$event, $invoice])->with('status', 'Invoice draft updated.');
    }

    private function nested(Event $event, Invoice $invoice): void
    {
        abort_unless($invoice->event_id === $event->getKey(), 404);
    }
}
