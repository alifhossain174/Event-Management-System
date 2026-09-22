<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class OutboundMessage extends Model
{
    public const STATUSES = ['pending', 'sent', 'failed'];

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $message) {
            if ($message->isDirty(['message_template_id', 'event_id', 'client_id', 'booking_id', 'registration_id', 'ticket_order_id', 'channel', 'category', 'subject', 'body', 'idempotency_key', 'created_by_user_id'])) {
                throw new LogicException('Outbound message snapshots are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Outbound message history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function ticketOrder(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class)->orderByDesc('attempted_at');
    }
}
