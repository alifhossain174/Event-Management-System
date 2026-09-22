<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\StatusHistory;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketRefund;
use App\Models\TicketType;
use App\Models\TicketValidation;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TicketService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PaymentService $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public function issue(Event $event, TicketType $type, array $data, User $actor): TicketOrder
    {
        return DB::transaction(function () use ($event, $type, $data, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $type = TicketType::query()->lockForUpdate()->findOrFail($type->getKey());
            $this->guardType($event, $type);

            if ($existing = TicketOrder::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if ($existing->event_id !== $event->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another Event.']);
                }

                return $existing->load(['tickets', 'type', 'promoCode']);
            }

            $quantity = (int) $data['quantity'];
            if ($quantity < 1 || $quantity > 100) {
                throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 100.']);
            }
            $this->ensureSaleWindow($type);
            $remaining = $type->quantity_total - $type->tickets()->whereIn('status', ['issued', 'used'])->count();
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(['quantity' => "Only {$remaining} ticket(s) remain in this category."]);
            }

            $promo = $this->promo($event, $data['promo_code'] ?? null, $quantity);
            $unitPrice = DecimalMath::normalize($type->price);
            $unitDiscount = $this->discountPerTicket($unitPrice, $promo);
            $unitTotal = DecimalMath::subtract($unitPrice, $unitDiscount);
            $subtotal = DecimalMath::multiply($unitPrice, (string) $quantity);
            $discountTotal = DecimalMath::multiply($unitDiscount, (string) $quantity);
            $total = DecimalMath::multiply($unitTotal, (string) $quantity);
            $payment = $this->payment($event, $data['payment_id'] ?? null, $total);
            $source = DecimalMath::compare($total, '0') === 0 ? 'free' : ($data['source'] ?? 'manual');

            $order = TicketOrder::query()->create([
                'event_id' => $event->getKey(), 'ticket_type_id' => $type->getKey(),
                'client_id' => $event->client_id, 'registration_id' => $data['registration_id'] ?? null,
                'payment_id' => $payment?->getKey(), 'promo_code_id' => $promo?->getKey(),
                'reference_number' => $this->reference('TO'), 'idempotency_key' => $data['idempotency_key'],
                'source' => $source, 'status' => 'issued', 'attendee_name' => $data['attendee_name'],
                'attendee_email' => $data['attendee_email'] ?? null, 'attendee_phone' => $data['attendee_phone'] ?? null,
                'quantity' => $quantity, 'unit_price_snapshot' => $unitPrice, 'subtotal' => $subtotal,
                'discount_total' => $discountTotal, 'total' => $total, 'currency_code' => $type->currency_code,
                'promo_snapshot' => $promo ? $promo->only(['code', 'name', 'discount_type', 'discount_value']) : null,
                'notes' => $data['notes'] ?? null, 'issued_by_user_id' => $actor->getKey(), 'issued_at' => now(),
            ]);

            for ($number = 1; $number <= $quantity; $number++) {
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                Ticket::query()->create([
                    'event_id' => $event->getKey(), 'ticket_order_id' => $order->getKey(),
                    'ticket_type_id' => $type->getKey(), 'ticket_number' => $this->reference('TKT'),
                    'attendee_name' => $data['attendee_name'], 'attendee_email' => $data['attendee_email'] ?? null,
                    'attendee_phone' => $data['attendee_phone'] ?? null, 'price_snapshot' => $unitPrice,
                    'discount_snapshot' => $unitDiscount, 'final_price_snapshot' => $unitTotal,
                    'currency_code' => $type->currency_code, 'status' => 'issued',
                    'token_hash' => hash('sha256', $token), 'token_encrypted' => Crypt::encryptString($token),
                    'issued_at' => now(),
                ]);
            }
            $this->recordStatus($order, 'none', 'issued', $actor, 'Ticket order issued');
            if ($type->tickets()->whereIn('status', ['issued', 'used'])->count() >= $type->quantity_total) {
                $type->update(['status' => 'sold_out', 'updated_by_user_id' => $actor->getKey()]);
            }
            $this->audit->record('ticket.order_issued', $order, [], [
                'event_id' => $event->getKey(), 'reference_number' => $order->reference_number,
                'ticket_type_id' => $type->getKey(), 'quantity' => $quantity, 'total' => $total,
                'currency_code' => $type->currency_code, 'promo_code' => $promo?->code,
                'payment_id' => $payment?->getKey(),
            ], $actor);

            return $order->load(['tickets', 'type', 'promoCode', 'payment']);
        }, 3);
    }

    /** @return array{state: string, ticket: Ticket, validation: TicketValidation|null} */
    public function validate(Event $event, string $token, User $actor, string $method = 'qr'): array
    {
        return DB::transaction(function () use ($event, $token, $actor, $method) {
            $token = trim($token);
            if (filter_var($token, FILTER_VALIDATE_URL)) {
                $token = basename((string) parse_url($token, PHP_URL_PATH));
            }
            $ticket = Ticket::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $ticket) {
                throw ValidationException::withMessages(['token' => 'Ticket token is invalid.']);
            }
            if ($ticket->event_id !== $event->getKey()) {
                throw ValidationException::withMessages(['token' => 'This ticket belongs to another Event.']);
            }
            if ($ticket->status === 'used') {
                return ['state' => 'already_used', 'ticket' => $ticket, 'validation' => $ticket->validation()->with('validatedBy')->first()];
            }
            if (in_array($ticket->status, ['refunded', 'cancelled'], true)) {
                throw ValidationException::withMessages(['token' => 'This ticket is no longer valid.']);
            }
            $validation = TicketValidation::query()->create([
                'event_id' => $event->getKey(), 'ticket_id' => $ticket->getKey(),
                'validated_by_user_id' => $actor->getKey(), 'validated_at' => now(), 'method' => $method,
            ]);
            $ticket->update(['status' => 'used', 'used_at' => $validation->validated_at]);
            $this->recordStatus($ticket, 'issued', 'used', $actor, 'Ticket validated');
            $this->audit->record('ticket.validated', $ticket, ['status' => 'issued'], [
                'status' => 'used', 'event_id' => $event->getKey(), 'ticket_number' => $ticket->ticket_number,
                'method' => $method,
            ], $actor);

            return ['state' => 'validated', 'ticket' => $ticket->refresh(), 'validation' => $validation];
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function refund(Event $event, Ticket $ticket, array $data, User $actor): TicketRefund
    {
        return DB::transaction(function () use ($event, $ticket, $data, $actor) {
            $ticket = Ticket::query()->lockForUpdate()->with('order.payment')->findOrFail($ticket->getKey());
            if ($ticket->event_id !== $event->getKey()) {
                abort(404);
            }
            if ($existing = TicketRefund::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                if ($existing->ticket_id !== $ticket->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another Ticket.']);
                }

                return $existing;
            }
            if ($ticket->status === 'used') {
                throw ValidationException::withMessages(['ticket' => 'A used ticket cannot be refunded.']);
            }
            if (in_array($ticket->status, ['refunded', 'cancelled'], true)) {
                throw ValidationException::withMessages(['ticket' => 'This ticket is already inactive.']);
            }

            $amount = DecimalMath::normalize($ticket->final_price_snapshot);
            $financialRefund = null;
            $method = 'free';
            if (DecimalMath::compare($amount, '0') > 0) {
                if (! $ticket->order->payment) {
                    throw ValidationException::withMessages(['payment' => 'A priced ticket must be linked to a posted manual Payment before its refund can be recorded.']);
                }
                $financialRefund = $this->payments->refund($ticket->order->payment, [
                    'amount' => $amount, 'idempotency_key' => $data['idempotency_key'],
                    'refunded_at' => $data['refunded_at'], 'channel' => $data['channel'] ?? 'Ticket correction',
                    'external_reference' => $data['external_reference'] ?? null, 'reason' => $data['reason'],
                ], $actor);
                $method = 'manual_payment_refund';
            }

            $refund = TicketRefund::query()->create([
                'event_id' => $event->getKey(), 'ticket_order_id' => $ticket->ticket_order_id,
                'ticket_id' => $ticket->getKey(), 'financial_refund_id' => $financialRefund?->getKey(),
                'refund_number' => $this->reference('TRF'), 'idempotency_key' => $data['idempotency_key'],
                'amount' => $amount, 'currency_code' => $ticket->currency_code, 'method' => $method,
                'reason' => $data['reason'], 'refunded_by_user_id' => $actor->getKey(),
                'refunded_at' => $data['refunded_at'],
            ]);
            $ticket->update(['status' => 'refunded', 'refunded_at' => $data['refunded_at']]);
            $this->recordStatus($ticket, 'issued', 'refunded', $actor, $data['reason']);
            $this->syncOrderStatus($ticket->order, $actor, $data['reason']);
            $this->restoreTypeAvailability($ticket->type()->lockForUpdate()->firstOrFail(), $actor);
            $this->audit->record('ticket.refunded', $refund, [], [
                'event_id' => $event->getKey(), 'ticket_id' => $ticket->getKey(),
                'ticket_number' => $ticket->ticket_number, 'amount' => $amount,
                'financial_refund_id' => $financialRefund?->getKey(), 'reason' => $data['reason'],
            ], $actor);

            return $refund->load('financialRefund');
        }, 3);
    }

    public function cancelOrder(Event $event, TicketOrder $order, string $reason, User $actor): TicketOrder
    {
        return DB::transaction(function () use ($event, $order, $reason, $actor) {
            $order = TicketOrder::query()->lockForUpdate()->with('tickets')->findOrFail($order->getKey());
            if ($order->event_id !== $event->getKey()) {
                abort(404);
            }
            if ($order->status === 'cancelled') {
                return $order;
            }
            if ($order->tickets->contains(fn (Ticket $ticket) => $ticket->status === 'used')) {
                throw ValidationException::withMessages(['order' => 'An order with a used ticket cannot be cancelled.']);
            }
            if (DecimalMath::compare($order->total, '0') > 0 && $order->tickets->contains(fn (Ticket $ticket) => $ticket->status === 'issued')) {
                throw ValidationException::withMessages(['order' => 'Refund each active paid ticket before cancelling the order.']);
            }
            $from = $order->status;
            foreach ($order->tickets->where('status', 'issued') as $ticket) {
                $ticket->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason]);
                $this->recordStatus($ticket, 'issued', 'cancelled', $actor, $reason);
            }
            $order->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason]);
            $this->recordStatus($order, $from, 'cancelled', $actor, $reason);
            $this->restoreTypeAvailability($order->type()->lockForUpdate()->firstOrFail(), $actor);
            $this->audit->record('ticket.order_cancelled', $order, ['status' => $from], ['status' => 'cancelled', 'reason' => $reason], $actor);

            return $order->refresh();
        }, 3);
    }

    public function publish(Event $event, bool $publish, User $actor): Event
    {
        return DB::transaction(function () use ($event, $publish, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $before = $event->ticketing_published_at;
            $event->update([
                'ticketing_public_slug' => $publish ? ($event->ticketing_public_slug ?: Str::random(48)) : $event->ticketing_public_slug,
                'ticketing_published_at' => $publish ? now() : null,
            ]);
            $this->audit->record($publish ? 'ticket.catalogue_published' : 'ticket.catalogue_unpublished', $event,
                ['ticketing_published_at' => $before?->toIso8601String()],
                ['ticketing_published_at' => $event->ticketing_published_at?->toIso8601String()], $actor);

            return $event->refresh();
        });
    }

    private function ensureSaleWindow(TicketType $type): void
    {
        if (! in_array($type->status, ['on_sale', 'sold_out'], true) || $type->archived_at) {
            throw ValidationException::withMessages(['ticket_type_id' => 'This Ticket Type is not on sale.']);
        }
        if ($type->sale_starts_at && now()->lt($type->sale_starts_at)) {
            throw ValidationException::withMessages(['ticket_type_id' => 'This Ticket Type sale has not started.']);
        }
        if ($type->sale_ends_at && now()->gt($type->sale_ends_at)) {
            throw ValidationException::withMessages(['ticket_type_id' => 'This Ticket Type sale has ended.']);
        }
    }

    private function promo(Event $event, ?string $code, int $quantity): ?PromoCode
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $promo = PromoCode::query()->where('event_id', $event->getKey())->where('code', $code)->lockForUpdate()->first();
        if (! $promo || ! $promo->is_active || $promo->archived_at) {
            throw ValidationException::withMessages(['promo_code' => 'Promo Code is invalid.']);
        }
        if ($promo->valid_from && now()->lt($promo->valid_from) || $promo->valid_until && now()->gt($promo->valid_until)) {
            throw ValidationException::withMessages(['promo_code' => 'Promo Code is outside its validity window.']);
        }
        $used = TicketOrder::query()->where('promo_code_id', $promo->getKey())->sum('quantity');
        if ($promo->usage_limit !== null && $used + $quantity > $promo->usage_limit) {
            throw ValidationException::withMessages(['promo_code' => 'Promo Code usage limit would be exceeded.']);
        }

        return $promo;
    }

    private function discountPerTicket(string $price, ?PromoCode $promo): string
    {
        if (! $promo) {
            return '0.0000';
        }
        $discount = $promo->discount_type === 'percentage'
            ? DecimalMath::percentage($price, $promo->discount_value)
            : DecimalMath::normalize($promo->discount_value);

        return DecimalMath::compare($discount, $price) > 0 ? $price : $discount;
    }

    private function payment(Event $event, ?int $paymentId, string $total): ?Payment
    {
        if (DecimalMath::compare($total, '0') === 0) {
            return null;
        }
        if (! $paymentId) {
            throw ValidationException::withMessages(['payment_id' => 'Select a posted manual Payment for a priced Ticket Order.']);
        }
        $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();
        if (! $payment || $payment->event_id !== $event->getKey() || $payment->client_id !== $event->client_id || $payment->status !== 'posted') {
            throw ValidationException::withMessages(['payment_id' => 'The selected posted Payment does not belong to this Event and Client.']);
        }
        $committed = Ticket::query()->whereIn('status', ['issued', 'used'])
            ->whereHas('order', fn ($query) => $query->where('payment_id', $payment->getKey()))
            ->sum('final_price_snapshot');
        $available = DecimalMath::subtract($this->payments->unallocatedRefundable($payment), $committed);
        if (DecimalMath::compare($total, $available) > 0) {
            throw ValidationException::withMessages(['payment_id' => 'The Payment does not have enough unallocated refundable value for this Ticket Order.']);
        }

        return $payment;
    }

    private function syncOrderStatus(TicketOrder $order, User $actor, string $reason): void
    {
        $order = TicketOrder::query()->lockForUpdate()->findOrFail($order->getKey());
        $active = $order->tickets()->whereIn('status', ['issued', 'used'])->count();
        $next = $active === 0 ? 'refunded' : 'partially_refunded';
        if ($next !== $order->status) {
            $from = $order->status;
            $order->update(['status' => $next]);
            $this->recordStatus($order, $from, $next, $actor, $reason);
        }
    }

    private function restoreTypeAvailability(TicketType $type, User $actor): void
    {
        if ($type->status === 'sold_out' && $type->activeIssuedCount() < $type->quantity_total && ! $type->archived_at) {
            $type->update(['status' => 'on_sale', 'updated_by_user_id' => $actor->getKey()]);
        }
    }

    private function guardType(Event $event, TicketType $type): void
    {
        if ($type->event_id !== $event->getKey() || $event->archived_at) {
            throw ValidationException::withMessages(['ticket_type_id' => 'Ticket Type is unavailable for this Event.']);
        }
    }

    private function recordStatus($subject, string $from, string $to, User $actor, string $reason): void
    {
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $from, 'to_status' => $to, 'actor_user_id' => $actor->getKey(),
            'reason' => $reason, 'metadata' => [], 'changed_at' => now(),
        ]);
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.now()->format('ymd').'-'.strtoupper(Str::random(10));
        } while (TicketOrder::query()->where('reference_number', $reference)->exists()
            || Ticket::query()->where('ticket_number', $reference)->exists()
            || TicketRefund::query()->where('refund_number', $reference)->exists());

        return $reference;
    }
}
