<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Se2026Usaha extends Model
{
    protected $table = 'se2026_usaha';
    public $timestamps = false;
    protected $guarded = [];

    /** Titik geotagging milik usaha ini (relasi lewat assignment_id; bisa null). */
    public function titik(): BelongsTo
    {
        return $this->belongsTo(Se2026Titik::class, 'assignment_id', 'assignment_id');
    }
}
