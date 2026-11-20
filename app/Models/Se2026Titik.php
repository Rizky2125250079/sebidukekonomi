<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Se2026Titik extends Model
{
    protected $table = 'se2026_titik';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    /** Semua usaha pada assignment ini (0..n). */
    public function usaha(): HasMany
    {
        return $this->hasMany(Se2026Usaha::class, 'assignment_id', 'assignment_id');
    }
}
