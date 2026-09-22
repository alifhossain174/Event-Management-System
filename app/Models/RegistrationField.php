<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RegistrationField extends Model
{
    public const TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'checkbox', 'consent'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_constraints' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
