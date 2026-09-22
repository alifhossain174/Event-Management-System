<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\StaffAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffAssignment extends Model implements TracksStatusHistory
{
    /** @use HasFactory<StaffAssignmentFactory> */
    use HasFactory, HasStatusHistory;

    public const ACTIVE_STATUSES = ['planned', 'confirmed', 'in_progress'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_starts_at' => 'immutable_datetime', 'scheduled_ends_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'conflict_overridden' => 'boolean',
            'conflict_details' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function responsibleManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_manager_user_id')->withTrashed();
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return [
            'planned' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], 'cancelled' => [],
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('role_title', 'like', "%{$search}%")
                ->orWhereHas('staff', fn (Builder $staff) => $staff->where('display_name', 'like', "%{$search}%"))
                ->orWhereHas('event', fn (Builder $events) => $events->where('reference_number', 'like', "%{$search}%")),
        ));
    }
}
