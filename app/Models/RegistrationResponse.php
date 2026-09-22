<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RegistrationResponse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(RegistrationField::class, 'registration_field_id');
    }
}
