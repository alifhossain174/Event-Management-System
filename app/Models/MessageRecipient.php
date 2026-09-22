<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class MessageRecipient extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $recipient) {
            if ($recipient->isDirty(['outbound_message_id', 'recipient_type', 'address', 'display_name', 'consent_basis'])) {
                throw new LogicException('Message recipient snapshots are immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Message recipients cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class, 'outbound_message_id');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }
}
