<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Rsvp extends Model implements TracksStatusHistory
{
    use HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attending_count' => 'integer', 'plus_one_count' => 'integer', 'responded_at' => 'immutable_datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'pending' => ['accepted', 'declined', 'tentative'],
            'accepted' => ['declined', 'tentative'],
            'declined' => ['accepted', 'tentative'],
            'tentative' => ['accepted', 'declined'],
        ];
    }
}
