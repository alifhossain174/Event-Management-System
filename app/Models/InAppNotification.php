<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class InAppNotification extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Notification content is append-only.'));
        self::deleting(fn () => throw new LogicException('Notifications cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['route_parameters' => 'array'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }
}
