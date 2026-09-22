<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class Ticket extends Model
{
    public const STATUSES = ['issued', 'used', 'refunded', 'cancelled'];

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $ticket) {
            if ($ticket->isDirty([
                'event_id', 'ticket_order_id', 'ticket_type_id', 'ticket_number', 'attendee_name',
                'attendee_email', 'attendee_phone', 'price_snapshot', 'discount_snapshot',
                'final_price_snapshot', 'currency_code', 'token_hash', 'token_encrypted', 'issued_at',
            ])) {
                throw new LogicException('Issued Ticket snapshots are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Ticket history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:4', 'discount_snapshot' => 'decimal:4',
            'final_price_snapshot' => 'decimal:4', 'issued_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function validation(): HasOne
    {
        return $this->hasOne(TicketValidation::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(TicketRefund::class);
    }

    public function plainToken(): string
    {
        return Crypt::decryptString($this->token_encrypted);
    }
}
