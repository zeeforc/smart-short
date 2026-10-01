<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDiscountTier extends Model
{
    protected $fillable = [
        'supplier_id',
        'min_qty',
        'discount_pct',
    ];

    protected $casts = [
        'min_qty' => 'integer',
        'discount_pct' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
