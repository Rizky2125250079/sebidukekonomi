<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class LandingPageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Path ke file CSV rekap
        $filePath = public_path('wilayah/datarekap.csv');

        // Inisialisasi variabel rekap
        $perKoseka  = [];
        $totalSemua = 0;
        $totalUB    = 0;
        $totalUM    = 0;
        $totalUMK   = 0;

        // 1. Cek keberadaan file CSV Rekap
        if (file_exists($filePath) && is_readable($filePath)) {
            try {
                if (($handle = fopen($filePath, "r")) !== FALSE) {
                    fgetcsv($handle); // Lewati baris judul
                    $header = fgetcsv($handle);

                    if ($header !== FALSE) {
                        $cleanHeader = array_map('trim', $header);

                        $idxId    = array_search('KDWIL', $cleanHeader);
                        $idxUB    = array_search('UB', $cleanHeader);
                        $idxUM    = array_search('UM', $cleanHeader);
                        $idxUMK   = array_search('UMK', $cleanHeader);
                        $idxTotal = array_search('Grand Total', $cleanHeader);

                        while (($data = fgetcsv($handle)) !== FALSE) {
                            if ($idxId !== false && isset($data[$idxId])) {
                                $kode = trim($data[$idxId]);

                                if (strtolower($kode) === 'grand total' || $kode === '') continue;

                                $valUB    = ($idxUB !== false && isset($data[$idxUB])) ? (int) preg_replace('/[^0-9]/', '', $data[$idxUB]) : 0;
                                $valUM    = ($idxUM !== false && isset($data[$idxUM])) ? (int) preg_replace('/[^0-9]/', '', $data[$idxUM]) : 0;
                                $valUMK   = ($idxUMK !== false && isset($data[$idxUMK])) ? (int) preg_replace('/[^0-9]/', '', $data[$idxUMK]) : 0;
                                $valTotal = ($idxTotal !== false && isset($data[$idxTotal])) ? (int) preg_replace('/[^0-9]/', '', $data[$idxTotal]) : 0;

                                $kode7 = substr($kode, 0, 7);

                                if (!isset($perKoseka[$kode7])) {
                                    $perKoseka[$kode7] = ['ub' => 0, 'um' => 0, 'umk' => 0, 'total' => 0];
                                }

                                $perKoseka[$kode7]['ub']    += $valUB;
                                $perKoseka[$kode7]['um']    += $valUM;
                                $perKoseka[$kode7]['umk']   += $valUMK;
                                $perKoseka[$kode7]['total'] += $valTotal;

                                $totalSemua += $valTotal;
                                $totalUB    += $valUB;
                                $totalUM    += $valUM;
                                $totalUMK   += $valUMK;
                            }
                        }
                    }
                    fclose($handle);
                }
            } catch (\Exception $e) {
                // Biarkan fallback ke default
            }
        }

        // 2. Tarik Data Petugas langsung dari Google Sheets (DATA NO HP)
        $petugasPerKecamatan = [];
        $sheetUrl = "https://docs.google.com/spreadsheets/d/1vKJ4j-X2LeMrCLeGActk8YDkWYabuYCbBCehF1D_yJk/export?format=csv&gid=1894208140";

        try {
            $response = Http::get($sheetUrl);
            if ($response->successful()) {
                $lines = explode("\n", $response->body());
                $header = [];

                foreach ($lines as $index => $line) {
                    $row = str_getcsv($line);

                    if ($index === 0) {
                        $header = array_map('trim', $row);
                        continue;
                    }

                    if (count($row) >= 10 && !empty($row[0])) {
                        $nama      = trim($row[0] ?? '-');
                        $kecamatan = strtoupper(trim($row[1] ?? ''));
                        $posisi    = trim($row[3] ?? 'Petugas');
                        $noHpRaw   = trim($row[9] ?? '-');

                        // Bersihkan karakter aneh pada No HP (menghilangkan petik/spasi)
                        $noHpClean = preg_replace('/[^0-9]/', '', $noHpRaw);

                        // Ringkas sebutan posisi (PML Sensus -> PML, PPL Sensus -> PPL)
                        $jabatanSingkat = str_contains($posisi, 'Pemeriksa') ? 'PML' : 'PPL';

                        $petugasPerKecamatan[$kecamatan][] = [
                            'nama'    => $nama,
                            'jabatan' => $jabatanSingkat,
                            'no_hp'   => $noHpClean
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Jika gagal tarik dari Google Sheets, biarkan data petugas kosong
        }

        // 3. Data Master List KOSEKA
        // Array Lookup Nomor HP KOSEKA (Key berdasarkan nama panggil/koseka)
        $hpKoseka = [
            'Ade'   => '081211114811',
            'Arya'  => '0895700937744',
            'Arif'  => '081279969344',
            'Fathu' => '087783362694',
            'Novi'  => '081377606845',
            'Yessy' => '085126618291',
            'Luluk' => '085649442371',
            'Raden' => '081532315619',
        ];

        // Data Utama KOSEKA (Nomor HP diambil otomatis dari lookup di atas)
        $listKoseka = [
            [
                'id'        => '1674011',
                'wilayah'   => 'Lubuk Linggau Barat I',
                'koseka'    => 'Ade',
                'korwil1'   => 'Rani',
                'korwil2'   => 'Resa',
                'nama'      => 'Ade Suteja',
                'jabatan'   => 'KOSEKA Barat I',
                'no_hp'     => $hpKoseka['Ade'] ?? '',
                'foto_wil'  => 'barati.png',
                'foto'      => 'ade.png',
                'quote'     => '"Masa depanmu dimulai dari apa yang kamu pelajari hari ini."',
                'deskripsi' => 'Bertanggung jawab dalam koordinasi lapangan, pengawasan pendaftaran usaha, dan menjamin kualitas data di seluruh kelurahan wilayah Lubuk Linggau Barat I.',
            ],
            [
                'id'        => '1674012',
                'wilayah'   => 'Lubuk Linggau Barat II',
                'koseka'    => 'Arya',
                'korwil1'   => 'Rani',
                'korwil2'   => 'Resa',
                'nama'      => 'M. Arya Septa Kovitra, S.Tr.Stat.',
                'jabatan'   => 'KOSEKA Barat II',
                'no_hp'     => $hpKoseka['Arya'] ?? '',
                'foto_wil'  => 'baratii.png',
                'foto'      => 'arya_fix.png',
                'quote'     => '"Talk is Cheap. Show me The Code."',
                'deskripsi' => 'Memastikan integritas data sensus ekonomi di wilayah Barat II melalui pemantauan intensif dan dukungan teknis kepada para petugas lapangan.',
            ],
            [
                'id'        => '1674021',
                'wilayah'   => 'Lubuk Linggau Selatan I',
                'koseka'    => 'Arif',
                'korwil1'   => 'Nia',
                'korwil2'   => 'Canggih',
                'nama'      => 'M. Arif Hidayatullah, A.Md.T.',
                'jabatan'   => 'KOSEKA Selatan I',
                'no_hp'     => $hpKoseka['Arif'] ?? '',
                'foto_wil'  => 'selatani.png',
                'foto'      => 'arif.png',
                'quote'     => '"Fokuslah mengembangkan diri, agar tak larut dalam opini mereka yang tak peduli."',
                'deskripsi' => 'Mengawal jalannya Sensus Ekonomi 2026 di wilayah Selatan I demi mewujudkan basis data ekonomi yang akurat dan terpercaya.',
            ],
            [
                'id'        => '1674022',
                'wilayah'   => 'Lubuk Linggau Selatan II',
                'koseka'    => 'Fathu',
                'korwil1'   => 'Nia',
                'korwil2'   => 'Canggih',
                'nama'      => 'M. Fathu Rahman, S.Tr.Stat.',
                'jabatan'   => 'KOSEKA Selatan II',
                'no_hp'     => $hpKoseka['Fathu'] ?? '',
                'foto_wil'  => 'selatanii.png',
                'foto'      => 'fathur.png',
                'quote'     => '"Don\'t watch the clock; do what it does. Keep going!"',
                'deskripsi' => 'Berkomitmen tinggi dalam mensukseskan pendataan ekonomi di wilayah Selatan II dengan koordinasi tim yang solid.',
            ],
            [
                'id'        => '1674031',
                'wilayah'   => 'Lubuk Linggau Timur I',
                'koseka'    => 'Novi',
                'korwil1'   => 'Dian',
                'korwil2'   => 'Rini',
                'nama'      => 'Novi Marlina, S.Si.',
                'jabatan'   => 'KOSEKA Timur I',
                'no_hp'     => $hpKoseka['Novi'] ?? '',
                'foto_wil'  => 'timuri.png',
                'foto'      => 'novi.png',
                'quote'     => '"Dedikasi dan ketelitian adalah kunci kualitas data yang berharga."',
                'deskripsi' => 'Bertanggung jawab penuh dalam mengoordinasikan pendataan ekonomi di wilayah Timur I untuk memastikan setiap unit usaha tercatat dengan akurat.',
            ],
            [
                'id'        => '1674032',
                'wilayah'   => 'Lubuk Linggau Timur II',
                'koseka'    => 'Yessy',
                'korwil1'   => 'Dian',
                'korwil2'   => 'Rini',
                'nama'      => 'Yessy Zaliah Purnama Sari, A.Md.',
                'jabatan'   => 'KOSEKA Timur II',
                'no_hp'     => $hpKoseka['Yessy'] ?? '',
                'foto_wil'  => 'timurii.png',
                'foto'      => 'yessy.png',
                'quote'     => '"Bekerja keras dalam diam, biarkan data yang berbicara."',
                'deskripsi' => 'Mengawal kualitas pendaftaran usaha dan sinergi tim lapangan di wilayah Timur II guna mensukseskan Sensus Ekonomi 2026.',
            ],
            [
                'id'        => '1674041',
                'wilayah'   => 'Lubuk Linggau Utara I',
                'koseka'    => 'Luluk',
                'korwil1'   => 'Alma',
                'korwil2'   => 'Tamara',
                'nama'      => 'Luluk Indryas Mufida, S.Tr.Stat.',
                'jabatan'   => 'KOSEKA Utara I',
                'no_hp'     => $hpKoseka['Luluk'] ?? '',
                'foto_wil'  => 'utarai.png',
                'foto'      => 'luluk.png',
                'quote'     => '"Kualitas data adalah cerminan integritas dalam setiap langkah pendataan."',
                'deskripsi' => 'Memastikan kelancaran koordinasi dan akurasi cakupan unit usaha di wilayah Utara I demi mewujudkan basis data ekonomi yang handal dan terpercaya.',
            ],
            [
                'id'        => '1674042',
                'wilayah'   => 'Lubuk Linggau Utara II',
                'koseka'    => 'Raden',
                'korwil1'   => 'Alma',
                'korwil2'   => 'Tamara',
                'nama'      => 'Raden Mulia, S.Tr.Stat.',
                'jabatan'   => 'KOSEKA Utara II',
                'no_hp'     => $hpKoseka['Raden'] ?? '',
                'foto_wil'  => 'utaraii.png',
                'foto'      => 'raden.png',
                'quote'     => '"Sinergi lapangan yang kuat melahirkan data statistik yang tepat."',
                'deskripsi' => 'Mengawasi jalannya pendaftaran usaha di wilayah Utara II dengan pendekatan strategis untuk mendukung kesuksesan sensus ekonomi yang komprehensif.',
            ],
        ];

        // Sisipkan array petugas ke masing-masing KOSEKA
        foreach ($listKoseka as &$koseka) {
            $keyKec = strtoupper(trim($koseka['wilayah']));
            $koseka['petugas'] = $petugasPerKecamatan[$keyKec] ?? [];
        }

        return view('landingpage.index', [
            'totalUsahaAktif' => $totalSemua,
            'totalUB'         => $totalUB,
            'totalUM'         => $totalUM,
            'totalUMK'        => $totalUMK,
            'rekapPerKoseka'  => $perKoseka,
            'listKoseka'      => $listKoseka
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateRekap()
    {
        $sheetUrl = "https://docs.google.com/spreadsheets/d/1VHTGdZYKxUew_m79vkcauEVrUnduPG8V/export?format=csv&gid=2017343805";
        $directory = public_path('wilayah');
        $filePath = $directory . '/datarekap.csv';

        try {
            // Set timeout lebih lama karena download antar server bisa lambat
            $response = Http::timeout(30)->get($sheetUrl);

            if ($response->successful()) {
                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }

                // Simpan/Timpah file CSV di public path
                File::put($filePath, $response->body());

                // Hapus cache rekap_sheets_koseka jika masih ada sisa-sisa cache lama
                Cache::forget('rekap_sheets_koseka');

                return redirect('/')->with('success', 'Data rekap berhasil diperbarui dari Google Sheets!');
            }

            return "Gagal download. Status: " . $response->status();
        } catch (\Exception $e) {
            return "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
