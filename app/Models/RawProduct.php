<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawProduct extends Model
{
    protected $guarded = [];

    public function uploadFile()
    {
        return $this->belongsTo(UploadFile::class);
    }
}
