<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\EmailInvoiceRequest;
use App\Http\Requests\Invoices\InvoiceReasonRequest;
use App\Models\Event;
use App\Models\Invoice;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class InvoiceActionController extends Controller
{
    public function issue(Request $request, Event $event, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->nested($event, $invoice);
        Gate::authorize('issue', $invoice);
        $service->issue($invoice, $request->user());

        return back()->with('status', 'Invoice issued. Its number, parties, items, discounts, tax, and totals are now immutable.');
    }

    public function cancel(InvoiceReasonRequest $request, Event $event, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->nested($event, $invoice);
        $service->cancel($invoice, $request->validated('reason'), $request->user());

        return back()->with('status', 'Invoice cancelled without deleting its history.');
    }

    public function credit(InvoiceReasonRequest $request, Event $event, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->nested($event, $invoice);
        $credit = $service->credit($invoice, $request->validated('reason'), $request->user());

        return redirect()->route('events.invoices.show', [$event, $credit])->with('status', 'Credit note issued and linked to the original Invoice.');
    }

    public function email(EmailInvoiceRequest $request, Event $event, Invoice $invoice, InvoiceDeliveryService $delivery): RedirectResponse
    {
        $this->nested($event, $invoice);
        $delivery->email($invoice, $request->validated('recipient'), $request->user());

        return back()->with('status', 'Invoice emailed synchronously and the delivery attempt was logged.');
    }

    private function nested(Event $event, Invoice $invoice): void
    {
        abort_unless($invoice->event_id === $event->getKey(), 404);
    }
}
