<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class DeliveryLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Delivery logs are append-only.'));
        self::deleting(fn () => throw new LogicException('Delivery logs cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class, 'outbound_message_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(MessageRecipient::class, 'message_recipient_id');
    }

    public function attemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by_user_id')->withTrashed();
    }
}
