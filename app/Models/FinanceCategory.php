<?php

namespace App\Models;

use App\Models\Concerns\HasCategoryFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceCategory extends Model
{
    use HasCategoryFields, HasFactory, SoftDeletes;

    protected $guarded = [];
}
