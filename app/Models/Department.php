<?php

namespace App\Models;

use App\Models\Concerns\HasCategoryFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Department extends Model
{
    use HasCategoryFields, SoftDeletes;

    protected $guarded = [];

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }
}
