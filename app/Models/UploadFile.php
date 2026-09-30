<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadFile extends Model
{
    protected $guarded = [];

    public function uploadBatch()
    {
        return $this->belongsTo(UploadBatch::class);
    }
}
