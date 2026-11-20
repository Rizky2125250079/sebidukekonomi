<?php

namespace App\Http\Controllers;

use App\Models\Se2026Usaha;
use App\Models\Se2026Wilayah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Tab "Sebaran Usaha SE2026" - TAMPILAN GUEST (/SebaranUsaha).
 *  - index() : halaman publik (layout landing)
 *  - data()  : statistik + grafik + jumlah per RT/SLS untuk peta (publik)
 *
 * Tampilan admin (titik koordinat) ada di SebaranUsahaAdminController dan memakai kelas ini sebagai induk.
 */
class SebaranUsahaController extends Controller
{
    /** Role yang boleh membuka tampilan admin (titik koordinat). Dipakai di routes/web.php dan sidebar agar selalu sinkron. */
    public const ROLE_TITIK = ['admin'];

    /** Batas pengaman jumlah titik dalam satu permintaan (kecamatan terbesar saat ini ±17.500). */
    protected const TTL = 3600;

    protected const SKALA = [
        'UMKM'    => 'UMKM',
        'UMK'     => 'UMK',
        'UM'      => 'UM',
        'UB'      => 'UB',
        'LAINNYA' => 'Lainnya / Tidak Terisi',
    ];

    // ------------------------------------------------------------------ halaman

    public function index()
    {
        $master = $this->master();

        return view('landingpage.sebaran_usaha', [
            'master' => $master,
            'cfg'    => [
                'urlData' => route('sebaran.data'),
                'urlPeta' => asset('wilayah/sls_1674_2026-1.min.geojson'),
                'wilayah' => $master['wilayah'],
                'skala'   => self::SKALA,
            ],
        ]);
    }

    /** Pohon wilayah + daftar KBLI untuk dropdown (di-cache; dipakai halaman guest dan halaman admin). */
    protected function master(): array
    {
        return Cache::remember('se2026:index:' . $this->ver(), self::TTL, function () {
            $tree = [];
            $wil = Se2026Wilayah::orderBy('idsls')->get(['idsls', 'nmsls', 'kdkec', 'nmkec', 'kddesa', 'nmdesa']);
            foreach ($wil as $w) {
                $tree[$w->kdkec]['k'] = $w->kdkec;
                $tree[$w->kdkec]['n'] = $w->nmkec;
                $tree[$w->kdkec]['d'][$w->kddesa]['k'] = $w->kddesa;
                $tree[$w->kdkec]['d'][$w->kddesa]['n'] = $w->nmdesa;
                $tree[$w->kdkec]['d'][$w->kddesa]['s'][] = ['k' => $w->idsls, 'n' => $w->nmsls];
            }
            ksort($tree);
            $wilayah = [];
            foreach ($tree as $kec) {
                ksort($kec['d']);
                $kec['d'] = array_values($kec['d']);
                $wilayah[] = $kec;
            }

            $kbli = Se2026Usaha::where('kbli', '<>', '-')
                ->groupBy('kbli')->orderBy('kbli')
                ->selectRaw('kbli, MAX(kategori_kbli) as kat, COUNT(*) as n')
                ->get()->map(fn ($r) => [$r->kbli, $r->kat, (int) $r->n])->all();

            return [
                'wilayah'   => $wilayah,
                'kbli'      => $kbli,
                'tanpaKbli' => Se2026Usaha::where('kbli', '-')->count(),
                'siap'      => !empty($wilayah) && Se2026Usaha::exists(),
            ];
        });
    }

    // ------------------------------------------------------------------ statistik (publik)

    public function data(Request $r): JsonResponse
    {
        $f = $this->filter($r);
        $key = 'se2026:data:' . $this->ver() . ':' . md5(json_encode($f));

        return response()->json(Cache::remember($key, self::TTL, fn () => $this->hitung($f)));
    }

    protected function hitung(array $f): array
    {
        $rows = $this->scopeUsaha(Se2026Usaha::query(), $f)
            ->selectRaw('idsls, skala, COUNT(*) as n')
            ->groupBy('idsls', 'skala')->get();

        $perSls = [];
        $ringkasan = array_fill_keys(array_keys(self::SKALA), 0);
        $total = 0;
        foreach ($rows as $r) {
            $sk = isset(self::SKALA[$r->skala]) ? $r->skala : 'LAINNYA';
            $n = (int) $r->n;
            $perSls[$r->idsls][$sk] = ($perSls[$r->idsls][$sk] ?? 0) + $n;
            $ringkasan[$sk] += $n;
            $total += $n;
        }
        $ringkasan['total'] = $total;

        // jumlah total per RT/SLS (untuk pewarnaan peta)
        $sls = [];
        foreach ($perSls as $id => $sk) {
            $sls[$id] = array_sum($sk);
        }

        // satuan pada sumbu grafik, tergantung level filter
        $level = $f['level'];
        if ($level === 'sls') {
            $kode = array_keys(self::SKALA);
            $label = array_values(self::SKALA);
            $seri = ['Jumlah usaha' => array_map(fn ($k) => $ringkasan[$k], $kode)];
            $grafik = ['kode' => $kode, 'label' => $label, 'seri' => $seri, 'bertumpuk' => false];
            $unit = 'Skala Usaha';
        } else {
            if ($level === 'kota') {
                $units = Se2026Wilayah::selectRaw('kdkec as kode, MAX(nmkec) as label')->groupBy('kdkec')->orderBy('kdkec')->get();
                $keyOf = fn ($idsls) => substr($idsls, 4, 3);
                $unit = 'Kecamatan';
            } elseif ($level === 'kecamatan') {
                $units = Se2026Wilayah::where('kdkec', $f['kdkec'])
                    ->selectRaw('kddesa as kode, MAX(nmdesa) as label')->groupBy('kddesa')->orderBy('kddesa')->get();
                $keyOf = fn ($idsls) => substr($idsls, 7, 3);
                $unit = 'Kelurahan';
            } else { // kelurahan
                $units = Se2026Wilayah::where('kdkec', $f['kdkec'])->where('kddesa', $f['kddesa'])
                    ->orderBy('idsls')->get(['idsls as kode', 'nmsls as label']);
                $keyOf = fn ($idsls) => $idsls;
                $unit = 'RT/SLS';
            }

            $hit = []; // unit => skala => n
            foreach ($perSls as $id => $sk) {
                foreach ($sk as $k => $n) {
                    $hit[$keyOf($id)][$k] = ($hit[$keyOf($id)][$k] ?? 0) + $n;
                }
            }
            $kode = $units->pluck('kode')->all();
            $seri = [];
            foreach (self::SKALA as $k => $nama) {
                $seri[$nama] = array_map(fn ($u) => $hit[$u][$k] ?? 0, $kode);
            }
            $grafik = ['kode' => $kode, 'label' => $units->pluck('label')->all(), 'seri' => $seri, 'bertumpuk' => true];
        }

        return [
            'level'     => $level,
            'unit'      => $unit,
            'lokasi'    => $this->labelLokasi($f),
            'kbli'      => $f['kbli'],
            'ringkasan' => $ringkasan,
            'grafik'    => $grafik,
            'sls'       => $sls,
        ];
    }

    // ------------------------------------------------------------------ pembantu

    protected function ver()
    {
        return Cache::get('se2026:ver', 0);
    }

    /** Validasi + normalisasi filter wilayah/KBLI dari query string. */
    protected function filter(Request $r): array
    {
        $v = $r->validate([
            'kdkec'  => ['nullable', 'regex:/^\d{3}$/'],
            'kddesa' => ['nullable', 'regex:/^\d{3}$/'],
            'idsls'  => ['nullable', 'regex:/^\d{14}$/'],
            'kbli'   => ['nullable', 'regex:/^(\d{2,5}|-)$/'],
        ]);

        $kdkec = $v['kdkec'] ?? null;
        $kddesa = $v['kddesa'] ?? null;
        $idsls = $v['idsls'] ?? null;

        if ($idsls) {                       // idsls sudah memuat kode kecamatan & kelurahan
            $kdkec = substr($idsls, 4, 3);
            $kddesa = substr($idsls, 7, 3);
        } elseif ($kddesa && !$kdkec) {
            throw ValidationException::withMessages(['kdkec' => 'Kecamatan wajib diisi bila kelurahan dipilih.']);
        }

        return [
            'kdkec'  => $kdkec,
            'kddesa' => $kddesa,
            'idsls'  => $idsls,
            'kbli'   => $v['kbli'] ?? null,
            'level'  => $idsls ? 'sls' : ($kddesa ? 'kelurahan' : ($kdkec ? 'kecamatan' : 'kota')),
        ];
    }

    protected function scopeUsaha($q, array $f)
    {
        if ($f['idsls']) {
            $q->where('idsls', $f['idsls']);
        } elseif ($f['kddesa']) {
            $q->where('kdkec', $f['kdkec'])->where('kddesa', $f['kddesa']);
        } elseif ($f['kdkec']) {
            $q->where('kdkec', $f['kdkec']);
        }
        return $this->scopeKbli($q, $f['kbli']);
    }

    protected function scopeTitik($q, array $f)
    {
        if ($f['idsls']) {
            $q->where('idsls', $f['idsls']);
        } elseif ($f['kddesa']) {
            $q->where('kdkec', $f['kdkec'])->where('kddesa', $f['kddesa']);
        } elseif ($f['kdkec']) {
            $q->where('kdkec', $f['kdkec']);
        }
        return $q;
    }

    /** '-' = KBLI tidak terisi; selain itu cocok awalan (47 => semua 47xxx, 47112 => persis). */
    protected function scopeKbli($q, ?string $kbli)
    {
        if ($kbli === null || $kbli === '') {
            return $q;
        }
        return $kbli === '-' ? $q->where('kbli', '-') : $q->where('kbli', 'like', $kbli . '%');
    }

    protected function labelLokasi(array $f): string
    {
        $parts = ['Kota Lubuk Linggau'];
        if ($f['kdkec']) {
            $w = Se2026Wilayah::where('kdkec', $f['kdkec'])->first();
            $parts[] = $w->nmkec ?? ('Kec. ' . $f['kdkec']);
        }
        if ($f['kddesa']) {
            $w = Se2026Wilayah::where('kdkec', $f['kdkec'])->where('kddesa', $f['kddesa'])->first();
            $parts[] = $w->nmdesa ?? ('Kel. ' . $f['kddesa']);
        }
        if ($f['idsls']) {
            $w = Se2026Wilayah::find($f['idsls']);
            $parts[] = $w->nmsls ?? $f['idsls'];
        }
        return implode(' › ', $parts);
    }
}
