<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mengimpor data "Sebaran Usaha SE2026" dari CSV hasil tools/se2026/prepare_se2026.py.
 *
 *   php artisan se2026:import
 *   php artisan se2026:import --path=/lokasi/lain
 *
 * Data lama pada ketiga tabel DIGANTI seluruhnya setiap kali perintah ini dijalankan.
 */
class ImportSe2026 extends Command
{
    protected $signature = 'se2026:import {--path= : Folder berisi wilayah.csv, usaha.csv, titik.csv (default storage/app/se2026)}';
    protected $description = 'Impor data usaha, titik, dan wilayah SE2026 dari CSV ke database';

    /** tabel => [file, kolom wajib, ukuran chunk] */
    private const BERKAS = [
        'se2026_wilayah' => ['wilayah.csv', ['idsls', 'nmsls', 'kdkec', 'nmkec', 'kddesa', 'nmdesa', 'ada_peta'], 500],
        'se2026_usaha'   => ['usaha.csv', ['assignment_id', 'urut', 'idsubsls', 'idsls', 'kdkec', 'kddesa', 'nama', 'kbli', 'kategori_kbli', 'tahun_mulai', 'skala', 'kegiatan'], 500],
        'se2026_titik'   => ['titik.csv', ['assignment_id', 'latitude', 'longitude', 'idsubsls', 'idsls', 'kdkec', 'kddesa'], 800],
    ];

    public function handle(): int
    {
        $dir = rtrim($this->option('path') ?: storage_path('app/se2026'), '/\\');

        // Pastikan semua berkas ada & headernya benar SEBELUM ada data yang dihapus.
        foreach (self::BERKAS as $tabel => [$nama, $kolom]) {
            $file = $dir . DIRECTORY_SEPARATOR . $nama;
            if (!is_file($file)) {
                $this->error("Berkas tidak ditemukan: $file");
                $this->line('Jalankan dulu: python tools/se2026/prepare_se2026.py (lihat PASANG_SEBARAN_USAHA.md)');
                return self::FAILURE;
            }
            $fh = fopen($file, 'r');
            $header = fgetcsv($fh);
            fclose($fh);
            if (isset($header[0])) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF"); // buang BOM bila ada
            }
            if (array_diff($kolom, $header ?: [])) {
                $this->error("Header $nama tidak sesuai. Dibutuhkan: " . implode(', ', $kolom));
                return self::FAILURE;
            }
        }

        DB::disableQueryLog();

        foreach (self::BERKAS as $tabel => [$nama, $kolom, $chunk]) {
            $this->info("Mengimpor $nama ...");
            DB::table($tabel)->delete();   // delete (bukan truncate) agar aman di dalam transaksi
            $total = $this->isi($tabel, $dir . DIRECTORY_SEPARATOR . $nama, $chunk);
            $this->line('  ' . number_format($total, 0, ',', '.') . ' baris');
        }

        // Naikkan versi cache agar statistik lama tidak terpakai lagi.
        Cache::forever('se2026:ver', time());

        $this->newLine();
        $this->info('Selesai.');
        $this->table(['Tabel', 'Baris'], collect(array_keys(self::BERKAS))
            ->map(fn ($t) => [$t, number_format(DB::table($t)->count(), 0, ',', '.')])->all());

        return self::SUCCESS;
    }

    private function isi(string $tabel, string $file, int $chunk): int
    {
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh);
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

        $buf = [];
        $n = 0;

        DB::transaction(function () use ($fh, $header, $tabel, $chunk, &$buf, &$n) {
            while (($r = fgetcsv($fh)) !== false) {
                if ($r === [null] || count($r) !== count($header)) {
                    continue; // baris kosong / rusak
                }
                $row = array_combine($header, $r);
                $buf[] = $this->normal($tabel, $row);
                if (count($buf) >= $chunk) {
                    DB::table($tabel)->insert($buf);
                    $n += count($buf);
                    $buf = [];
                }
            }
            if ($buf) {
                DB::table($tabel)->insert($buf);
                $n += count($buf);
            }
        });

        fclose($fh);
        return $n;
    }

    /** Ubah tipe nilai sesuai kolom tabel. */
    private function normal(string $tabel, array $row): array
    {
        switch ($tabel) {
            case 'se2026_wilayah':
                $row['ada_peta'] = (int) $row['ada_peta'];
                break;
            case 'se2026_usaha':
                $row['urut'] = (int) $row['urut'];
                foreach (['kategori_kbli', 'tahun_mulai', 'kegiatan'] as $k) {
                    $row[$k] = $row[$k] === '' ? null : $row[$k];
                }
                break;
            case 'se2026_titik':
                $row['latitude'] = (float) $row['latitude'];
                $row['longitude'] = (float) $row['longitude'];
                break;
        }
        return $row;
    }
}
