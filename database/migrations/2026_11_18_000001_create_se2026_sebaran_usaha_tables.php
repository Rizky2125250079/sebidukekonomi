<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pendukung tab "Sebaran Usaha SE2026".
 *
 * Relasi usaha <-> titik memakai assignment_id (kolom terindeks), TANPA constraint FOREIGN KEY di level
 * database: banyak assignment yang punya usaha tetapi belum punya titik (dan sebaliknya), sehingga
 * constraint akan menolak data tersebut. Relasinya didefinisikan di model Eloquent.
 */
return new class extends Migration
{
    public function up()
    {
        // Daftar RT/SLS beserta nama kecamatan & kelurahan (sumber: final_sls_1674_2026-1 + Excel)
        Schema::create('se2026_wilayah', function (Blueprint $table) {
            $table->char('idsls', 14)->primary();
            $table->string('nmsls', 100);
            $table->char('kdkec', 3);
            $table->string('nmkec', 100);
            $table->char('kddesa', 3);
            $table->string('nmdesa', 100);
            $table->boolean('ada_peta')->default(true);
            $table->index(['kdkec', 'kddesa']);
        });

        // Satu baris = satu usaha (hasil SE2026). Satu assignment_id bisa memuat beberapa usaha.
        Schema::create('se2026_usaha', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('assignment_id', 36)->index();
            $table->unsignedInteger('urut')->default(0);
            $table->char('idsubsls', 16);
            $table->char('idsls', 14);
            $table->char('kdkec', 3);
            $table->char('kddesa', 3);
            $table->string('nama', 255);
            $table->char('kbli', 5)->default('-');          // '-' = tidak terisi
            $table->char('kategori_kbli', 1)->nullable();
            $table->char('tahun_mulai', 4)->nullable();
            $table->string('skala', 10);                      // UMKM | UMK | UM | UB | LAINNYA
            $table->text('kegiatan')->nullable();             // Kegiatan utama (KBLI)
            $table->index(['kdkec', 'kddesa', 'idsls']);
            $table->index('kbli');
        });

        // Satu baris = satu titik geotagging (koordinat hasil edit). Wilayah dihitung dari poligon SLS.
        Schema::create('se2026_titik', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('assignment_id', 36)->unique();
            $table->double('latitude');
            $table->double('longitude');
            $table->char('idsubsls', 16);
            $table->char('idsls', 14);
            $table->char('kdkec', 3);
            $table->char('kddesa', 3);
            $table->index(['kdkec', 'kddesa', 'idsls']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('se2026_titik');
        Schema::dropIfExists('se2026_usaha');
        Schema::dropIfExists('se2026_wilayah');
    }
};
