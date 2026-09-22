<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Invoice;
use App\Services\InvoiceBalanceService;
use App\Services\InvoicePdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceOutputController extends Controller
{
    public function pdf(Request $request, Event $event, Invoice $invoice, InvoicePdfService $pdf): Response
    {
        $this->authorizeOutput($request, $event, $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.($invoice->invoice_number ?? 'draft-invoice').'.pdf"',
        ]);
    }

    public function print(Request $request, Event $event, Invoice $invoice, InvoiceBalanceService $balances): View
    {
        $this->authorizeOutput($request, $event, $invoice);
        $invoice->load(['items', 'event', 'client', 'creditFor']);

        return view('invoices.print', ['invoice' => $invoice, 'applied' => $balances->applied($invoice), 'balance' => $balances->balance($invoice)]);
    }

    private function authorizeOutput(Request $request, Event $event, Invoice $invoice): void
    {
        abort_unless($invoice->event_id === $event->getKey(), 404);
        Gate::authorize('view', $invoice);
        abort_unless($request->user()->hasPermission('invoices.export'), 403);
    }
}
