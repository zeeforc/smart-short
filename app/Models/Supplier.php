<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_ppn_included' => 'boolean',
        'is_active' => 'boolean',
        'column_mapping' => 'array',
    ];

    public function supplierDiscountTiers(): HasMany
    {
        return $this->hasMany(SupplierDiscountTier::class);
    }

    public function productDiscountTiers(): HasMany
    {
        return $this->hasMany(ProductDiscountTier::class);
    }
}
