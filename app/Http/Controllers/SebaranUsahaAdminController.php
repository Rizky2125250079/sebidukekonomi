<?php

namespace App\Http\Controllers;

use App\Models\Se2026Titik;
use App\Models\Se2026Usaha;
use App\Models\Se2026Wilayah;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tab "Sebaran Usaha SE2026" - TAMPILAN ADMIN (/backend/sebaranusaha).
 *  - index()       : halaman admin (layout backend + sidebar)
 *  - titik()       : titik koordinat usaha untuk satu wilayah
 *  - titikDetail() : keterangan usaha pada satu titik (lewat assignment_id)
 *
 * Statistik/grafik memakai endpoint publik SebaranUsahaController@data (angkanya sama dan sudah di-cache).
 * Akses dijaga di routes/web.php: middleware auth + role (SebaranUsahaController::ROLE_TITIK).
 */
class SebaranUsahaAdminController extends SebaranUsahaController
{
    /** Batas pengaman jumlah titik dalam satu permintaan (kecamatan terbesar saat ini ±17.500). */
    private const MAX_TITIK = 25000;

    public function index()
    {
        $master = $this->master();

        return view('backend.sebaranusaha', [
            'master' => $master,
            'cfg'    => [
                'urlData'    => route('sebaran.data'),
                'urlTitik'   => route('sebaran.admin.titik'),
                'urlDetail'  => url('backend/sebaranusaha/titik'),
                'urlPeta'    => asset('wilayah/sls_1674_2026-1.min.geojson'),
                'bolehTitik' => true,
                'wilayah'    => $master['wilayah'],
                'skala'      => self::SKALA,
            ],
        ]);
    }

    // ------------------------------------------------------------------ titik

    public function titik(Request $r): JsonResponse
    {
        $f = $this->filter($r);

        if ($f['level'] === 'kota') {
            return response()->json(['message' => 'Pilih kecamatan, kelurahan, atau RT/SLS terlebih dahulu untuk menampilkan titik.'], 422);
        }

        $kbli = $f['kbli'];
        $sertakanTanpa = $r->boolean('tanpa_usaha') && $kbli === null;
        $scope = fn () => $this->scopeTitik(Se2026Titik::query(), $f);

        $dengan = $scope()->whereExists($this->usahaAda($kbli))->count();
        $tanpa = $kbli === null ? $scope()->whereNotExists($this->usahaAda(null))->count() : null;

        $tampil = $dengan + ($sertakanTanpa ? (int) $tanpa : 0);
        if ($tampil > self::MAX_TITIK) {
            return response()->json(['message' => 'Terlalu banyak titik (' . number_format($tampil, 0, ',', '.') . '). Persempit filter wilayah.'], 422);
        }

        $q = $scope();
        if (!$sertakanTanpa) {
            $q->whereExists($this->usahaAda($kbli));
        }
        $titik = $q->selectRaw('id, latitude, longitude, CASE WHEN EXISTS (SELECT 1 FROM se2026_usaha u WHERE u.assignment_id = se2026_titik.assignment_id) THEN 1 ELSE 0 END AS m')
            ->orderBy('m')->orderBy('id')->get()
            ->map(fn ($x) => [(int) $x->id, round((float) $x->latitude, 6), round((float) $x->longitude, 6), (int) $x->m])
            ->all();

        $usahaTanpaTitik = $this->scopeUsaha(Se2026Usaha::query(), $f)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('se2026_titik')
                    ->whereColumn('se2026_titik.assignment_id', 'se2026_usaha.assignment_id');
            })->count();

        return response()->json([
            'titik'             => $titik,
            'dengan_usaha'      => $dengan,
            'tanpa_usaha'       => $tanpa,           // null saat filter KBLI aktif (tidak relevan)
            'tanpa_ditampilkan' => $sertakanTanpa,
            'usaha_tanpa_titik' => $usahaTanpaTitik,
        ]);
    }

    public function titikDetail(int $id): JsonResponse
    {
        $t = Se2026Titik::findOrFail($id);

        $usaha = Se2026Usaha::where('assignment_id', $t->assignment_id)
            ->orderBy('urut')->orderBy('id')
            ->get(['nama', 'kbli', 'tahun_mulai', 'skala', 'kegiatan', 'kdkec', 'kddesa', 'idsls']);

        $kec = $usaha->pluck('kdkec')->push($t->kdkec)->unique()->all();
        $des = $usaha->pluck('kddesa')->push($t->kddesa)->unique()->all();
        $idsls = $usaha->pluck('idsls')->push($t->idsls)->unique()->all();

        $nama = Se2026Wilayah::whereIn('kdkec', $kec)->whereIn('kddesa', $des)->get()
            ->keyBy(fn ($w) => $w->kdkec . $w->kddesa);
        $rt = Se2026Wilayah::whereIn('idsls', $idsls)->pluck('nmsls', 'idsls');

        $kecDari = fn ($kd, $ds) => optional($nama->get($kd . $ds))->nmkec;
        $kelDari = fn ($kd, $ds) => optional($nama->get($kd . $ds))->nmdesa;

        return response()->json([
            'id'            => $t->id,
            'assignment_id' => $t->assignment_id,
            'latitude'      => $t->latitude,
            'longitude'     => $t->longitude,
            'kecamatan'     => $kecDari($t->kdkec, $t->kddesa),
            'kelurahan'     => $kelDari($t->kdkec, $t->kddesa),
            'rt'            => $rt->get($t->idsls),
            'usaha'         => $usaha->map(fn ($u) => [
                'nama'        => $u->nama,
                'kbli'        => $u->kbli,
                'tahun_mulai' => $u->tahun_mulai,
                'skala'       => self::SKALA[$u->skala] ?? $u->skala,
                'kegiatan'    => $u->kegiatan,
                'kecamatan'   => $kecDari($u->kdkec, $u->kddesa),
                'kelurahan'   => $kelDari($u->kdkec, $u->kddesa),
            ])->all(),
        ]);
    }

    /** Subquery "titik ini punya usaha (yang cocok KBLI)" lewat assignment_id. */
    private function usahaAda(?string $kbli): Closure
    {
        return function ($q) use ($kbli) {
            $q->select(DB::raw(1))->from('se2026_usaha')
                ->whereColumn('se2026_usaha.assignment_id', 'se2026_titik.assignment_id');
            $this->scopeKbli($q, $kbli);
        };
    }
}
