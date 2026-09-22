<?php

namespace App\Models;

use App\Contracts\TracksStatusHistory;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\EventTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventTemplate extends Model implements TracksStatusHistory
{
    /** @use HasFactory<EventTemplateFactory> */
    use HasFactory, HasStatusHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starter_tasks' => 'array',
            'budget_lines' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id')->withTrashed();
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_template_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(EventTemplateModule::class)->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function statusHistoryColumn(): string
    {
        return 'status';
    }

    public function allowedStatusTransitions(): array
    {
        return ['active' => ['archived'], 'archived' => ['active']];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"),
        ));
    }
}
