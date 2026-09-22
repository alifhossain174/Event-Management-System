<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ModuleDefinition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_event_scoped' => 'boolean', 'is_active' => 'boolean'];
    }

    public function templateModules(): HasMany
    {
        return $this->hasMany(EventTemplateModule::class, 'module_key', 'key');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query->where('key', 'like', "%{$search}%")
                ->orWhere('label', 'like', "%{$search}%")
                ->orWhere('scope', 'like', "%{$search}%"),
        ));
    }
}
