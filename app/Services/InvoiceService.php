<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\InvoiceStatusHistory;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly InvoiceBalanceService $balances,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    public function createDraft(Event $event, array $data, array $items, User $actor): Invoice
    {
        return DB::transaction(function () use ($event, $data, $items, $actor) {
            $event = Event::query()->with(['client', 'booking'])->lockForUpdate()->findOrFail($event->getKey());
            if (! $event->client) {
                throw ValidationException::withMessages(['client' => 'Assign a Client before creating an Invoice.']);
            }
            $this->validateBooking($event, $data['booking_id'] ?? null);
            $calculation = $this->calculator->calculate($items, $data['default_tax_rate'] ?? $this->settings->decimal('finance.default_tax_rate'));
            $invoice = Invoice::query()->create(array_merge([
                'event_id' => $event->getKey(), 'client_id' => $event->client_id,
                'booking_id' => $data['booking_id'] ?? null, 'branch_id' => $event->branch_id,
                'document_type' => 'invoice', 'status' => 'draft', 'currency_code' => $event->currency_code,
                'due_date' => $data['due_date'], 'subject' => $data['subject'] ?? null,
                'notes' => $data['notes'] ?? null, 'tax_label' => $data['tax_label'] ?? 'Tax',
                'default_tax_rate' => $data['default_tax_rate'] ?? $this->settings->decimal('finance.default_tax_rate'),
                'created_by_user_id' => $actor->getKey(),
            ], $this->partySnapshot($event->client), collect($calculation)->except('items')->all()));
            $invoice->items()->createMany($calculation['items']);
            $this->history($invoice, null, 'draft', $actor, 'Invoice draft created');
            $this->audit->record('invoice.draft_created', $invoice, [], $invoice->only([
                'event_id', 'client_id', 'booking_id', 'currency_code', 'due_date', 'subtotal',
                'discount_total', 'tax_total', 'total', 'status',
            ]), $actor);

            return $invoice->fresh(['items', 'event', 'client']);
        }, 3);
    }

    public function updateDraft(Invoice $invoice, array $data, array $items, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $items, $actor) {
            $invoice = Invoice::query()->with(['event.client', 'items'])->lockForUpdate()->findOrFail($invoice->getKey());
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only Draft Invoices can be edited.']);
            }
            $this->validateBooking($invoice->event, $data['booking_id'] ?? null);
            $before = $invoice->only(['booking_id', 'due_date', 'subject', 'subtotal', 'discount_total', 'tax_total', 'total']);
            $calculation = $this->calculator->calculate($items, $data['default_tax_rate'] ?? $invoice->default_tax_rate);
            $invoice->items()->delete();
            $invoice->update(array_merge([
                'booking_id' => $data['booking_id'] ?? null, 'due_date' => $data['due_date'],
                'subject' => $data['subject'] ?? null, 'notes' => $data['notes'] ?? null,
                'tax_label' => $data['tax_label'] ?? 'Tax',
                'default_tax_rate' => $data['default_tax_rate'] ?? $invoice->default_tax_rate,
            ], $this->partySnapshot($invoice->event->client), collect($calculation)->except('items')->all()));
            $invoice->items()->createMany($calculation['items']);
            $this->audit->record('invoice.draft_updated', $invoice, $before, $invoice->only(array_keys($before)), $actor);

            return $invoice->fresh(['items', 'event', 'client']);
        }, 3);
    }

    public function issue(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $invoice = Invoice::query()->with(['event.client', 'items'])->lockForUpdate()->findOrFail($invoice->getKey());
            if ($invoice->status !== 'draft' || $invoice->items->isEmpty() || DecimalMath::compare($invoice->total, '0') <= 0) {
                throw ValidationException::withMessages(['invoice' => 'A positive Draft Invoice with at least one item is required.']);
            }
            $number = $this->nextNumber($invoice->event);
            $invoice->update(array_merge($this->partySnapshot($invoice->event->client), [
                'invoice_number' => $number, 'status' => 'issued', 'issue_date' => now()->toDateString(),
                'issued_by_user_id' => $actor->getKey(), 'issued_at' => now(),
            ]));
            $this->history($invoice, 'draft', 'issued', $actor, 'Invoice issued');
            $this->audit->record('invoice.issued', $invoice, ['status' => 'draft'], [
                'status' => 'issued', 'invoice_number' => $number, 'total' => $invoice->total,
                'currency_code' => $invoice->currency_code,
            ], $actor);

            return $invoice->refresh()->load(['items', 'event', 'client']);
        }, 3);
    }

    public function cancel(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            if (! in_array($invoice->status, ['draft', 'issued'], true) || DecimalMath::compare($this->balances->applied($invoice), '0') > 0) {
                throw ValidationException::withMessages(['invoice' => 'Only an unpaid Draft or Issued Invoice can be cancelled. Refund allocations first.']);
            }
            $from = $invoice->status;
            $invoice->update(['status' => 'cancelled', 'cancelled_by_user_id' => $actor->getKey(), 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->history($invoice, $from, 'cancelled', $actor, $reason);
            $this->audit->record('invoice.cancelled', $invoice, ['status' => $from], ['status' => 'cancelled', 'reason' => $reason], $actor);

            return $invoice->refresh();
        }, 3);
    }

    public function credit(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor) {
            $invoice = Invoice::query()->with(['event', 'items'])->lockForUpdate()->findOrFail($invoice->getKey());
            if (! in_array($invoice->status, ['issued', 'partially_paid', 'paid'], true)
                || $invoice->document_type !== 'invoice' || $invoice->creditNote()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'This Invoice cannot be credited.']);
            }
            if (DecimalMath::compare($this->balances->applied($invoice), '0') > 0) {
                throw ValidationException::withMessages(['invoice' => 'Refund all allocated Payments before crediting this Invoice.']);
            }
            $credit = Invoice::query()->create([
                ...$invoice->only([
                    'event_id', 'client_id', 'booking_id', 'branch_id', 'currency_code', 'subject', 'notes',
                    'seller_name', 'seller_address', 'seller_contact', 'client_name', 'client_address',
                    'client_contact', 'tax_label', 'default_tax_rate',
                ]),
                'credit_for_invoice_id' => $invoice->getKey(), 'document_type' => 'credit_note',
                'invoice_number' => $this->nextNumber($invoice->event), 'status' => 'issued',
                'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
                'subtotal' => DecimalMath::negate($invoice->subtotal),
                'discount_total' => DecimalMath::negate($invoice->discount_total),
                'taxable_total' => DecimalMath::negate($invoice->taxable_total),
                'tax_total' => DecimalMath::negate($invoice->tax_total), 'total' => DecimalMath::negate($invoice->total),
                'created_by_user_id' => $actor->getKey(), 'issued_by_user_id' => $actor->getKey(), 'issued_at' => now(),
            ]);
            $credit->items()->createMany($invoice->items->map(fn ($item) => [
                ...$item->only(['sort_order', 'description', 'quantity', 'discount_type', 'discount_value', 'tax_label', 'tax_rate', 'source_type', 'source_id']),
                'unit_price' => DecimalMath::negate($item->unit_price),
                'line_subtotal' => DecimalMath::negate($item->line_subtotal),
                'discount_amount' => DecimalMath::negate($item->discount_amount),
                'taxable_amount' => DecimalMath::negate($item->taxable_amount),
                'tax_amount' => DecimalMath::negate($item->tax_amount), 'line_total' => DecimalMath::negate($item->line_total),
            ])->all());
            $from = $invoice->status;
            $invoice->update(['status' => 'credited', 'credited_by_user_id' => $actor->getKey(), 'credited_at' => now(), 'credit_reason' => $reason]);
            $this->history($invoice, $from, 'credited', $actor, $reason, ['credit_note_id' => $credit->getKey()]);
            $this->history($credit, null, 'issued', $actor, $reason, ['credited_invoice_id' => $invoice->getKey()]);
            $this->audit->record('invoice.credited', $invoice, ['status' => $from], ['status' => 'credited', 'credit_note_id' => $credit->getKey(), 'reason' => $reason], $actor);

            return $credit->fresh(['items', 'event', 'client', 'creditFor']);
        }, 3);
    }

    private function nextNumber(Event $event): string
    {
        $year = (int) now()->format('Y');
        $prefix = trim($this->settings->string('invoice.prefix')) ?: 'INV-';
        $padding = max(1, min(12, $this->settings->integer('invoice.number_padding')));
        $scopeKey = $this->settings->boolean('features.branches_enabled') && $event->branch_id ? 'branch:'.$event->branch_id : 'global';
        DB::table('invoice_sequences')->insertOrIgnore([
            'scope_key' => $scopeKey,
            'branch_id' => $scopeKey === 'global' ? null : $event->branch_id,
            'prefix' => $prefix,
            'sequence_year' => $year,
            'next_value' => max(1, $this->settings->integer('invoice.next_number')),
            'number_padding' => $padding,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = InvoiceSequence::query()
            ->where('scope_key', $scopeKey)
            ->where('prefix', $prefix)
            ->where('sequence_year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $value = $sequence->next_value;
        $sequence->update(['next_value' => $value + 1, 'number_padding' => $padding]);

        return $prefix.$year.'-'.str_pad((string) $value, $padding, '0', STR_PAD_LEFT);
    }

    private function partySnapshot(Client $client): array
    {
        $company = Company::query()->first();
        $address = fn ($record) => collect([$record?->address_line_1, $record?->address_line_2, $record?->city, $record?->state, $record?->postal_code, $record?->country_code])->filter()->join(', ');
        $contact = fn ($email, $phone) => collect([$email, $phone])->filter()->join(' · ');

        return [
            'seller_name' => $company?->name, 'seller_address' => $address($company) ?: null,
            'seller_contact' => $contact($company?->email, $company?->phone) ?: null,
            'client_name' => $client->display_name, 'client_address' => $address($client) ?: null,
            'client_contact' => $contact($client->primary_email, $client->primary_phone) ?: null,
        ];
    }

    private function validateBooking(Event $event, ?int $bookingId): void
    {
        if ($bookingId && ($event->booking_id !== $bookingId || ! $event->booking()->whereKey($bookingId)->exists())) {
            throw ValidationException::withMessages(['booking_id' => 'The Booking must be the optional Booking linked to this Event.']);
        }
    }

    private function history(Invoice $invoice, ?string $from, string $to, User $actor, string $reason, array $metadata = []): void
    {
        InvoiceStatusHistory::query()->create([
            'invoice_id' => $invoice->getKey(), 'from_status' => $from, 'to_status' => $to,
            'actor_user_id' => $actor->getKey(), 'reason' => $reason,
            'metadata' => $metadata ?: null, 'changed_at' => now(),
        ]);
    }
}
