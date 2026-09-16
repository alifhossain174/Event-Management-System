<?php

namespace App\Models\Concerns;

use App\Models\DocumentLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
