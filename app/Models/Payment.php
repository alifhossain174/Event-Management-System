<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const TYPES = ['advance', 'installment', 'partial', 'final'];

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $payment) {
            if ($payment->getOriginal('status') === 'posted') {
                throw new LogicException('Posted Payment records are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Payment records cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'received_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function incomeEntry(): BelongsTo
    {
        return $this->belongsTo(Income::class, 'income_entry_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id')->withTrashed();
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id')->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }
}
