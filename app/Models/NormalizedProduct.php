<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NormalizedProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_history_json' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'best_supplier_id');
    }
}
