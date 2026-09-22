<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class TicketOrder extends Model
{
    public const STATUSES = ['issued', 'cancelled', 'partially_refunded', 'refunded'];

    public const SOURCES = ['manual', 'free', 'registration'];

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $order) {
            if ($order->isDirty([
                'event_id', 'ticket_type_id', 'client_id', 'registration_id', 'payment_id', 'promo_code_id',
                'reference_number', 'idempotency_key', 'source', 'attendee_name', 'attendee_email',
                'attendee_phone', 'quantity', 'unit_price_snapshot', 'subtotal', 'discount_total',
                'total', 'currency_code', 'promo_snapshot', 'issued_by_user_id', 'issued_at',
            ])) {
                throw new LogicException('Issued Ticket Order snapshots are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Ticket Order history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_price_snapshot' => 'decimal:4', 'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4', 'total' => 'decimal:4', 'promo_snapshot' => 'array',
            'issued_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id')->withTrashed();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(TicketRefund::class);
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        return $query->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
            $query->where('reference_number', 'like', "%{$search}%")
                ->orWhere('attendee_name', 'like', "%{$search}%")
                ->orWhere('attendee_email', 'like', "%{$search}%")
                ->orWhereHas('tickets', fn (Builder $tickets) => $tickets->where('ticket_number', 'like', "%{$search}%"));
        }));
    }
}
