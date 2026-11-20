<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Se2026Wilayah extends Model
{
    protected $table = 'se2026_wilayah';
    protected $primaryKey = 'idsls';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
