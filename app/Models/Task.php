<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasDocuments, HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'blocked', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'event_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'progress_percent' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id')->withTrashed();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderByDesc('created_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->orderByDesc('changed_at');
    }

    public function isOverdue(?\DateTimeInterface $at = null): bool
    {
        return $this->due_at !== null
            && ! in_array($this->status, ['completed', 'cancelled'], true)
            && $this->archived_at === null
            && $this->due_at->isBefore($at ?? now());
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
        ));
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('assignments', fn (Builder $assignments) => $assignments
            ->where('user_id', $user->getKey())
            ->orWhereHas('staff', fn (Builder $staff) => $staff->where('user_id', $user->getKey())));
    }
}
