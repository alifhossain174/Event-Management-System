<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

final class Invitation extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    protected $guarded = [];

    protected $hidden = ['check_in_token_encrypted', 'check_in_token_hash'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id')->withTrashed();
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id')->withTrashed();
    }

    public function plainToken(): string
    {
        return Crypt::decryptString($this->check_in_token_encrypted);
    }

    public function isUsable(): bool
    {
        return $this->status !== 'revoked' && ! $this->revoked_at && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return ['issued' => ['sent', 'revoked'], 'sent' => ['revoked'], 'revoked' => []];
    }
}
