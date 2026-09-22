<?php

namespace App\Models;

use App\Models\Concerns\HasCategoryFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventCategory extends Model
{
    use HasCategoryFields, HasFactory, SoftDeletes;

    protected $guarded = [];

    public function eventTemplates(): HasMany
    {
        return $this->hasMany(EventTemplate::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
