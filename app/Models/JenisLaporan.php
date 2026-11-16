<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisLaporan extends Model
{
    use HasFactory;

    protected $table = 'jenis_laporan';

    protected $fillable = [
        'nama_jenis',
        'slug',
        'keterangan',
    ];

    // Relasi: Satu jenis laporan memiliki banyak berita kegiatan
    public function beritaKegiatan()
    {
        return $this->hasMany(BeritaKegiatan::class, 'id_jenis_laporan');
    }
}
