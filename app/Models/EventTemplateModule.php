<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventTemplateModule extends Model
{
    protected $guarded = [];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'event_template_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ModuleDefinition::class, 'module_key', 'key');
    }
}
