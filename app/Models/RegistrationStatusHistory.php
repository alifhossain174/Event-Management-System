<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class RegistrationStatusHistory extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Registration status history is append-only.'));
        self::deleting(fn () => throw new LogicException('Registration status history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'changed_at' => 'immutable_datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
