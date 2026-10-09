<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    protected $fillable = [
        'normalized_product_id',
        'alias_raw',
        'alias_normalized',
    ];

    public function normalizedProduct(): BelongsTo
    {
        return $this->belongsTo(NormalizedProduct::class);
    }
}
