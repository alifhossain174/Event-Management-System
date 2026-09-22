<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

final class InvoicePdfService
{
    public function __construct(private readonly InvoiceBalanceService $balances) {}

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'event', 'client', 'creditFor']);

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice, 'applied' => $this->balances->applied($invoice),
            'balance' => $this->balances->balance($invoice),
        ])->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();
    }
}
