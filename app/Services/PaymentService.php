<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentSchedule;
use App\Models\Refund;
use App\Models\StatusHistory;
use App\Models\User;
use App\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PaymentService
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly AuditService $audit,
        private readonly InvoiceBalanceService $invoiceBalances,
        private readonly NotificationService $notifications,
    ) {}

    public function createSchedule(Event $event, array $data, User $actor): PaymentSchedule
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $this->ensureEventClient($event);
            if ($event->isOperationallyReadOnly()) {
                throw ValidationException::withMessages(['event' => 'Payment schedules cannot be changed for a completed, cancelled, or archived Event.']);
            }
            $this->ensureInvoice($data['invoice_id'] ?? null, $event);
            $schedule = PaymentSchedule::query()->create([
                'event_id' => $event->getKey(), 'client_id' => $event->client_id,
                'invoice_id' => $data['invoice_id'] ?? null, 'label' => $data['label'],
                'amount_due' => $this->positive($data['amount_due']), 'currency_code' => $event->currency_code,
                'due_date' => $data['due_date'], 'status' => 'scheduled', 'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $this->recordStatus($schedule, 'none', 'scheduled', $actor, 'Payment due scheduled');
            $this->audit->record('payment.schedule_created', $schedule, [], $schedule->only(['event_id', 'client_id', 'amount_due', 'currency_code', 'due_date', 'status']), $actor);

            return $schedule;
        }, 3);
    }

    public function cancelSchedule(PaymentSchedule $schedule, string $reason, User $actor): PaymentSchedule
    {
        return DB::transaction(function () use ($schedule, $reason, $actor) {
            $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            if ($schedule->status === 'cancelled') {
                return $schedule;
            }
            if (DecimalMath::compare($this->netScheduleAllocated($schedule), '0') > 0) {
                throw ValidationException::withMessages(['schedule' => 'A schedule with allocated payment history cannot be cancelled. Refund or correct the allocation first.']);
            }
            $from = $schedule->status;
            $schedule->update([
                'status' => 'cancelled', 'cancelled_by_user_id' => $actor->getKey(),
                'cancelled_at' => now(), 'cancellation_reason' => $reason,
            ]);
            $this->recordStatus($schedule, $from, 'cancelled', $actor, $reason);
            $this->audit->record('payment.schedule_cancelled', $schedule, ['status' => $from], ['status' => 'cancelled', 'reason' => $reason], $actor);

            return $schedule->refresh();
        }, 3);
    }

    /** @param list<array{payment_schedule_id?: int|null, invoice_id?: int|null, amount: string}> $allocations */
    public function postPayment(Event $event, array $data, array $allocations, User $actor): Payment
    {
        return DB::transaction(function () use ($event, $data, $allocations, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $this->ensureEventClient($event);
            if ($event->archived_at) {
                throw ValidationException::withMessages(['event' => 'Payments cannot be posted to an archived Event.']);
            }
            if ($existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if ($existing->event_id !== $event->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another Event.']);
                }

                return $existing;
            }
            $amount = $this->positive($data['amount']);
            $this->ensureInvoice($data['invoice_id'] ?? null, $event);
            $receivedBy = User::query()->whereKey($data['received_by_user_id'] ?? $actor->getKey())
                ->where('is_active', true)->whereNull('deleted_at')->first();
            if (! $receivedBy) {
                throw ValidationException::withMessages(['received_by_user_id' => 'The receiving user must be active.']);
            }

            $allocationTotal = DecimalMath::sum(array_column($allocations, 'amount'));
            if (DecimalMath::compare($allocationTotal, $amount) > 0) {
                throw ValidationException::withMessages(['allocations' => 'Allocated amounts cannot exceed the payment amount.']);
            }

            $scheduleIds = collect($allocations)->pluck('payment_schedule_id')->filter()->unique()->sort()->values();
            $schedules = PaymentSchedule::query()->whereIn('id', $scheduleIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $invoiceIds = collect($allocations)->pluck('invoice_id')->filter()->unique()->sort()->values();
            $invoices = Invoice::query()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $payment = Payment::query()->create([
                'event_id' => $event->getKey(), 'client_id' => $event->client_id,
                'invoice_id' => $data['invoice_id'] ?? null, 'receipt_number' => $this->reference('PAY'),
                'idempotency_key' => $data['idempotency_key'], 'payment_type' => $data['payment_type'],
                'amount' => $amount, 'currency_code' => $event->currency_code,
                'received_at' => $data['received_at'], 'received_by_user_id' => $receivedBy->getKey(),
                'status' => 'draft', 'channel' => $data['channel'] ?? null,
                'external_reference' => $data['external_reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'entered_by_user_id' => $actor->getKey(),
            ]);

            foreach ($allocations as $allocationData) {
                $allocationAmount = $this->positive($allocationData['amount']);
                $scheduleId = $allocationData['payment_schedule_id'] ?? null;
                $invoiceId = $allocationData['invoice_id'] ?? null;
                if ((bool) $scheduleId === (bool) $invoiceId) {
                    throw ValidationException::withMessages(['allocations' => 'Each allocation must target one schedule or one invoice.']);
                }
                if ($scheduleId) {
                    $schedule = $schedules->get($scheduleId);
                    if (! $schedule || $schedule->event_id !== $event->getKey() || $schedule->client_id !== $event->client_id || $schedule->status === 'cancelled') {
                        throw ValidationException::withMessages(['allocations' => 'A selected payment schedule is unavailable for this Event and Client.']);
                    }
                    if ($schedule->currency_code !== $event->currency_code || DecimalMath::compare($allocationAmount, $this->scheduleOutstanding($schedule)) > 0) {
                        throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the selected schedule outstanding amount or uses another currency.']);
                    }
                } else {
                    $invoice = $invoices->get($invoiceId);
                    $this->ensurePayableInvoice($invoice, $event);
                    if (DecimalMath::compare($allocationAmount, $this->invoiceBalances->balance($invoice)) > 0) {
                        throw ValidationException::withMessages(['allocations' => 'An allocation cannot exceed the Invoice outstanding balance. Keep any excess as unallocated credit.']);
                    }
                }
                PaymentAllocation::query()->create([
                    'payment_id' => $payment->getKey(), 'payment_schedule_id' => $scheduleId,
                    'invoice_id' => $invoiceId, 'amount' => $allocationAmount,
                    'allocated_at' => now(), 'allocated_by_user_id' => $actor->getKey(),
                ]);
            }

            $income = $this->finance->recordPostedPaymentIncome($event, [
                'source_id' => $payment->getKey(), 'client_id' => $event->client_id, 'amount' => $amount,
                'transaction_date' => CarbonImmutable::parse($data['received_at'])->toDateString(),
                'description' => 'Client payment '.$payment->receipt_number,
            ], $actor);
            $payment->update([
                'status' => 'posted', 'income_entry_id' => $income->getKey(),
                'posted_by_user_id' => $actor->getKey(), 'posted_at' => now(),
            ]);
            $this->recordStatus($payment, 'draft', 'posted', $actor, 'Manual payment posted');
            foreach ($schedules as $schedule) {
                $this->syncScheduleStatus($schedule, $actor, 'Payment allocated');
            }
            foreach ($invoices as $invoice) {
                $this->invoiceBalances->reconcile($invoice, $actor);
            }
            $this->audit->record('payment.posted', $payment, [], [
                'event_id' => $event->getKey(), 'client_id' => $event->client_id,
                'receipt_number' => $payment->receipt_number, 'amount' => $amount,
                'currency_code' => $event->currency_code, 'allocation_total' => $allocationTotal,
                'payment_type' => $payment->payment_type, 'channel' => $payment->channel,
            ], $actor);
            $this->notifications->sendToRoles(['administrator', 'finance-accounts'], [
                'type' => 'payment_received', 'title' => 'Payment received',
                'body' => $payment->receipt_number.' recorded for '.$amount.' '.$event->currency_code.'.',
                'source' => $payment, 'event_id' => $event->getKey(),
                'route_name' => 'events.payments.index', 'route_parameters' => ['event' => $event->getKey()],
                'idempotency_key' => 'payment-received:'.$payment->getKey(),
            ], $actor);

            return $payment->fresh(['allocations.schedule', 'refunds', 'incomeEntry']);
        }, 3);
    }

    public function refund(Payment $payment, array $data, User $actor): Refund
    {
        return DB::transaction(function () use ($payment, $data, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if ($existing->payment_id !== $payment->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another Payment.']);
                }

                return $existing;
            }
            $amount = $this->positive($data['amount']);
            if (DecimalMath::compare($amount, $this->paymentRefundable($payment)) > 0) {
                throw ValidationException::withMessages(['amount' => 'The refund exceeds the remaining refundable payment amount.']);
            }
            $allocation = null;
            if ($allocationId = $data['payment_allocation_id'] ?? null) {
                $allocation = PaymentAllocation::query()->lockForUpdate()->findOrFail($allocationId);
                if ($allocation->payment_id !== $payment->getKey() || DecimalMath::compare($amount, $this->allocationRefundable($allocation)) > 0) {
                    throw ValidationException::withMessages(['amount' => 'The refund exceeds the selected allocation refundable amount.']);
                }
            } elseif (DecimalMath::compare($amount, $this->unallocatedRefundable($payment)) > 0) {
                throw ValidationException::withMessages(['payment_allocation_id' => 'Select an allocation because the unallocated advance is insufficient for this refund.']);
            }

            $refund = Refund::query()->create([
                'payment_id' => $payment->getKey(), 'event_id' => $payment->event_id,
                'client_id' => $payment->client_id, 'payment_allocation_id' => $allocation?->getKey(),
                'refund_number' => $this->reference('RF'), 'idempotency_key' => $data['idempotency_key'],
                'amount' => $amount, 'currency_code' => $payment->currency_code,
                'refunded_at' => $data['refunded_at'], 'refunded_by_user_id' => $actor->getKey(),
                'status' => 'posted', 'channel' => $data['channel'] ?? null,
                'external_reference' => $data['external_reference'] ?? null, 'reason' => $data['reason'],
            ]);
            $this->finance->recordPostedPaymentRefund($payment->incomeEntry()->firstOrFail(), [
                'source_id' => $refund->getKey(), 'amount' => $amount,
                'transaction_date' => CarbonImmutable::parse($data['refunded_at'])->toDateString(),
                'description' => 'Client refund '.$refund->refund_number, 'reason' => $refund->reason,
            ], $actor);
            $this->recordStatus($refund, 'none', 'posted', $actor, $refund->reason);
            if ($allocation?->schedule) {
                $this->syncScheduleStatus($allocation->schedule, $actor, 'Allocated payment refunded');
            }
            if ($allocation?->invoice) {
                $this->invoiceBalances->reconcile($allocation->invoice, $actor, 'Allocated payment refunded');
            }
            $this->audit->record('payment.refunded', $refund, [], [
                'payment_id' => $payment->getKey(), 'refund_number' => $refund->refund_number,
                'amount' => $amount, 'currency_code' => $payment->currency_code,
                'allocation_id' => $allocation?->getKey(), 'reason' => $refund->reason,
            ], $actor);

            return $refund->fresh(['payment', 'allocation.schedule']);
        }, 3);
    }

    public function scheduleOutstanding(PaymentSchedule $schedule): string
    {
        if ($schedule->status === 'cancelled') {
            return '0.0000';
        }

        $outstanding = DecimalMath::subtract($schedule->amount_due, $this->netScheduleAllocated($schedule));

        return DecimalMath::compare($outstanding, '0') > 0 ? $outstanding : '0.0000';
    }

    public function paymentRefundable(Payment $payment): string
    {
        return DecimalMath::subtract($payment->amount, DecimalMath::sum(
            Refund::query()->where('payment_id', $payment->getKey())->where('status', 'posted')->pluck('amount')
        ));
    }

    public function allocationRefundable(PaymentAllocation $allocation): string
    {
        return DecimalMath::subtract($allocation->amount, DecimalMath::sum(
            Refund::query()->where('payment_allocation_id', $allocation->getKey())->where('status', 'posted')->pluck('amount')
        ));
    }

    public function unallocatedRefundable(Payment $payment): string
    {
        $allocated = DecimalMath::sum($payment->allocations()->pluck('amount'));
        $unallocatedRefunds = DecimalMath::sum($payment->refunds()->whereNull('payment_allocation_id')->where('status', 'posted')->pluck('amount'));
        $value = DecimalMath::subtract(DecimalMath::subtract($payment->amount, $allocated), $unallocatedRefunds);

        return DecimalMath::compare($value, '0') > 0 ? $value : '0.0000';
    }

    public function eventSummary(Event $event): array
    {
        return $this->summary(
            PaymentSchedule::query()->where('event_id', $event->getKey()),
            Payment::query()->where('event_id', $event->getKey()),
            Refund::query()->where('event_id', $event->getKey()),
        );
    }

    public function clientSummary(Client $client): array
    {
        $enabledEvent = fn (Builder $query) => $query->whereHas('moduleSettings', fn (Builder $settings) => $settings
            ->where('module_key', 'payments')->where('is_enabled', true));

        return $this->summary(
            PaymentSchedule::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvent),
            Payment::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvent),
            Refund::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvent),
        );
    }

    public function invoiceAllocatedAmount(int $invoiceId): string
    {
        $allocated = DecimalMath::sum(PaymentAllocation::query()->where('invoice_id', $invoiceId)->pluck('amount'));
        $refunded = DecimalMath::sum(Refund::query()->whereHas('allocation', fn (Builder $query) => $query->where('invoice_id', $invoiceId))
            ->where('status', 'posted')->pluck('amount'));

        return DecimalMath::subtract($allocated, $refunded);
    }

    public function invoiceDue(int $invoiceId, string $invoiceTotal): string
    {
        $due = DecimalMath::subtract($invoiceTotal, $this->invoiceAllocatedAmount($invoiceId));

        return DecimalMath::compare($due, '0') > 0 ? $due : '0.0000';
    }

    private function summary(Builder $scheduleQuery, Builder $paymentQuery, Builder $refundQuery): array
    {
        $schedules = $scheduleQuery->where('status', '!=', 'cancelled')->get();
        $payments = $paymentQuery->where('status', 'posted')->get();
        $refunds = $refundQuery->where('status', 'posted')->get();
        $scheduled = DecimalMath::sum($schedules->pluck('amount_due'));
        $outstanding = DecimalMath::sum($schedules->map(fn (PaymentSchedule $schedule) => $this->scheduleOutstanding($schedule)));
        $received = DecimalMath::sum($payments->pluck('amount'));
        $refunded = DecimalMath::sum($refunds->pluck('amount'));
        $unallocated = DecimalMath::sum($payments->map(fn (Payment $payment) => $this->unallocatedRefundable($payment)));

        return [
            'scheduled' => $scheduled, 'outstanding' => $outstanding,
            'received' => $received, 'refunded' => $refunded,
            'net_received' => DecimalMath::subtract($received, $refunded),
            'unallocated' => $unallocated,
        ];
    }

    private function netScheduleAllocated(PaymentSchedule $schedule): string
    {
        $allocated = DecimalMath::sum($schedule->allocations()->pluck('amount'));
        $refunded = DecimalMath::sum(Refund::query()->whereHas('allocation', fn (Builder $query) => $query
            ->where('payment_schedule_id', $schedule->getKey()))->where('status', 'posted')->pluck('amount'));

        return DecimalMath::subtract($allocated, $refunded);
    }

    private function syncScheduleStatus(PaymentSchedule $schedule, User $actor, string $reason): void
    {
        $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
        if ($schedule->status === 'cancelled') {
            return;
        }
        $paid = $this->netScheduleAllocated($schedule);
        $next = DecimalMath::compare($paid, '0') <= 0
            ? 'scheduled'
            : (DecimalMath::compare($paid, $schedule->amount_due) >= 0 ? 'paid' : 'partially_paid');
        if ($next !== $schedule->status) {
            $from = $schedule->status;
            $schedule->update(['status' => $next]);
            $this->recordStatus($schedule, $from, $next, $actor, $reason);
        }
    }

    private function ensureEventClient(Event $event): void
    {
        if (! $event->client_id) {
            throw ValidationException::withMessages(['client' => 'Assign a Client before recording payment activity.']);
        }
        Client::query()->lockForUpdate()->findOrFail($event->client_id);
    }

    private function ensureInvoice(?int $invoiceId, Event $event): ?Invoice
    {
        if (! $invoiceId) {
            return null;
        }

        $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);
        $this->ensurePayableInvoice($invoice, $event);

        return $invoice;
    }

    private function ensurePayableInvoice(?Invoice $invoice, Event $event): void
    {
        $invoiceModuleEnabled = $event->moduleSettings()->where('module_key', 'invoices')->where('is_enabled', true)->exists();
        if (! $invoice || ! $invoiceModuleEnabled || $invoice->event_id !== $event->getKey()
            || $invoice->client_id !== $event->client_id || $invoice->currency_code !== $event->currency_code
            || $invoice->document_type !== 'invoice' || ! in_array($invoice->status, ['issued', 'partially_paid'], true)) {
            throw ValidationException::withMessages(['invoice_id' => 'The selected issued Invoice is not payable for this Event and Client.']);
        }
    }

    private function positive(string|int $amount): string
    {
        $amount = DecimalMath::normalize($amount);
        if (DecimalMath::compare($amount, '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amounts must be greater than zero.']);
        }

        return $amount;
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('Y').'-'.Str::upper(substr((string) Str::ulid(), -12));
    }

    private function recordStatus($subject, string $from, string $to, User $actor, string $reason): void
    {
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $from, 'to_status' => $to, 'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => now(),
        ]);
    }
}
