<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;
use App\Models\Refund;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Builder;

final class InvoiceBalanceService
{
    public function applied(Invoice $invoice): string
    {
        $allocated = DecimalMath::sum($invoice->allocations()->pluck('amount'));
        $refunded = DecimalMath::sum(Refund::query()
            ->whereHas('allocation', fn (Builder $query) => $query->where('invoice_id', $invoice->getKey()))
            ->where('status', 'posted')->pluck('amount'));

        return DecimalMath::subtract($allocated, $refunded);
    }

    public function balance(Invoice $invoice): string
    {
        $balance = DecimalMath::subtract($invoice->total, $this->applied($invoice));

        return DecimalMath::compare($balance, '0') > 0 ? $balance : '0.0000';
    }

    public function reconcile(Invoice $invoice, ?User $actor = null, string $reason = 'Payment balance reconciled'): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
        if ($invoice->document_type !== 'invoice' || ! in_array($invoice->status, ['issued', 'partially_paid', 'paid'], true)) {
            return $invoice;
        }

        $applied = $this->applied($invoice);
        $balance = $this->balance($invoice);
        $next = DecimalMath::compare($balance, '0') <= 0
            ? 'paid'
            : (DecimalMath::compare($applied, '0') > 0 ? 'partially_paid' : 'issued');
        if ($next !== $invoice->status) {
            $from = $invoice->status;
            $invoice->update(['status' => $next]);
            InvoiceStatusHistory::query()->create([
                'invoice_id' => $invoice->getKey(), 'from_status' => $from, 'to_status' => $next,
                'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
                'metadata' => ['applied' => $applied, 'balance' => $balance], 'changed_at' => now(),
            ]);
        }

        return $invoice->refresh();
    }
}
