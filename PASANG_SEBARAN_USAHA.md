# Tab Sebaran Usaha SE2026 — versi 2 (tampilan guest dan admin dipisah)

| | Tampilan guest | Tampilan admin |
|---|---|---|
| Alamat | `/SebaranUsaha` | `/backend/sebaranusaha` |
| Layout | Landing (oranye, tanpa sidebar) | Backend AdminLTE + sidebar (menu Persiapan → Sebaran Usaha) |
| Akses | Siapa saja | Login + role `admin` (`SebaranUsahaController::ROLE_TITIK`) |
| Isi | Filter, KPI, peta berwarna, grafik batang | Semua yang ada di guest **+** titik koordinat usaha, popup keterangan usaha, tombol salin koordinat, penghitung cakupan titik |

Kedua tampilan memakai logika yang sama (`public/js/sebaran-usaha.js`). Halaman guest tidak memuat satu baris pun kode admin, bahkan saat
admin yang membukanya. Titik koordinat hanya bisa diambil lewat alamat di bawah `/backend/sebaranusaha/...`, yang dijaga login dan role di server.

## Isi paket

| File | Fungsi |
|---|---|
| `app/Http/Controllers/SebaranUsahaController.php` | Tampilan guest: halaman + statistik `/data` |
| `app/Http/Controllers/SebaranUsahaAdminController.php` | Tampilan admin: halaman, titik, detail titik (mewarisi controller guest) |
| `app/Models/Se2026Usaha.php`, `Se2026Titik.php`, `Se2026Wilayah.php` | Model; usaha ↔ titik berelasi lewat `assignment_id` |
| `app/Console/Commands/ImportSe2026.php` | `php artisan se2026:import` |
| `database/migrations/2026_11_18_000001_create_se2026_sebaran_usaha_tables.php` | Tabel `se2026_wilayah`, `se2026_usaha`, `se2026_titik` |
| `resources/views/landingpage/sebaran_usaha.blade.php` | Halaman guest |
| `resources/views/backend/sebaranusaha.blade.php` | Halaman admin |
| `public/js/sebaran-usaha.js`, `public/css/sebaran-usaha.css` | JS dan CSS bersama (filter, grafik, peta, titik, popup) |
| `public/wilayah/sls_1674_2026-1.min.geojson` | Peta ringan: 547 RT/SLS, 493 KB |
| `storage/app/se2026/{wilayah,usaha,titik}.csv` | Data siap impor |
| `tools/se2026/prepare_se2026.py` | Membuat ulang CSV dan peta bila data berubah |
| `opsional/sidebar.blade.php` | Sidebar Anda dengan menu Sebaran Usaha (lihat langkah 3) |

## A. Pasang dari awal

**1. Salin** isi folder ini ke root proyek (kecuali folder `opsional`). Tidak ada file lama yang ditimpa.

**2. Tempel di `routes/web.php`.** Dua baris `use` di bagian atas, blok rute di paling bawah:

```php
use App\Http\Controllers\SebaranUsahaController;
use App\Http\Controllers\SebaranUsahaAdminController;
```

```php
// --- SEBARAN USAHA SE2026 ---
// Tampilan guest (publik): halaman + statistik
Route::prefix('SebaranUsaha')->name('sebaran.')->group(function () {
    Route::get('/', [SebaranUsahaController::class, 'index'])->name('index');
    Route::get('/data', [SebaranUsahaController::class, 'data'])->middleware('throttle:120,1')->name('data');
});

// Tampilan admin (backend): halaman + titik koordinat usaha (hanya role di SebaranUsahaController::ROLE_TITIK)
Route::middleware(['auth', 'role:' . implode(',', SebaranUsahaController::ROLE_TITIK)])
    ->prefix('backend/sebaranusaha')->name('sebaran.admin.')->group(function () {
        Route::get('/', [SebaranUsahaAdminController::class, 'index'])->name('index');
        Route::get('/titik', [SebaranUsahaAdminController::class, 'titik'])->name('titik');
        Route::get('/titik/{id}', [SebaranUsahaAdminController::class, 'titikDetail'])->whereNumber('id')->name('titik.detail');
    });
```

**3. Menu.**

- *Sidebar backend* (`resources/views/backend/layouts/sidebar.blade.php`): ganti seluruh file dengan `opsional/sidebar.blade.php`. Bila sidebar Anda sudah berubah
  sejak paket ini dibuat, cukup terapkan dua hal: tambahkan `'backend/sebaranusaha*'` ke dua daftar `request()->is([...])` milik grup Persiapan, dan pasang item berikut
  di submenu Persiapan:
  ```blade
  @if (in_array(Auth::user()->role, \App\Http\Controllers\SebaranUsahaController::ROLE_TITIK))
      <li class="nav-item">
          <a href="{{ url('backend/sebaranusaha') }}"
              class="nav-link {{ request()->is('backend/sebaranusaha*') ? 'active' : '' }}">
              <i class="far fa-circle nav-icon text-info"></i>
              <p>Sebaran Usaha</p>
          </a>
      </li>
  @endif
  ```
- *Landing* (`resources/views/landingpage/layouts/app.blade.php`), tautan untuk guest di bawah Ground Check:
  ```html
  <li><a href="/SebaranUsaha">Sebaran Usaha</a></li>
  ```
  dan, bila mau, tombol Login menjadi Dashboard untuk yang sudah login:
  ```blade
  @auth
      <a class="btn-getstarted" href="{{ url('backend') }}">Dashboard</a>
  @else
      <a class="btn-getstarted" href="/login">Login</a>
  @endauth
  ```

**4. Migrasi dan impor data** (impor ±4 detik):
```
php artisan migrate
php artisan se2026:import
```

**5. Buka** `/SebaranUsaha` (guest) dan `/backend/sebaranusaha` (setelah login sebagai admin).

## B. Memperbarui dari versi 1 (yang sudah terpasang)

Data **tidak perlu diimpor ulang**.

1. Salin isi folder paket ke proyek dan **timpa** bila diminta. File yang berubah: `SebaranUsahaController.php`, `landingpage/sebaran_usaha.blade.php`.
   File baru: `SebaranUsahaAdminController.php`, `backend/sebaranusaha.blade.php`, `public/js/sebaran-usaha.js`, `public/css/sebaran-usaha.css`.
2. Di `routes/web.php`, **hapus blok rute versi 1** (`Route::prefix('SebaranUsaha')->name('sebaran.')` yang berisi `/titik` dan `/titik/{id}`) dan ganti dengan blok pada langkah A2.
   Tambahkan juga baris `use ...SebaranUsahaAdminController;`. Alamat lama `/SebaranUsaha/titik...` sengaja sudah tidak ada.
3. Perbarui sidebar (langkah A3).
4. Jalankan:
   ```
   php artisan route:clear
   php artisan view:clear
   ```
   lalu tekan Ctrl+F5 di browser (JS dan CSS lama bisa tertahan di cache).

## Cara kerja

- **Filter** Kota → Kecamatan → Kelurahan → RT/SLS + KBLI (`47` = semua 47xxx, `47112` = persis, `-` = tanpa KBLI). Alamat URL ikut berubah sehingga hasil filter bisa dibagikan.
  Klik batang grafik untuk turun satu level wilayah.
- **Titik (admin)** tidak pernah dimuat pada level Kota. Titik diminta setelah kecamatan/kelurahan/RT dipilih (terbanyak 17.449 titik). Keterangan usaha diambil saat titik diklik,
  lewat `assignment_id`. Panel kiri menampilkan jumlah titik dengan usaha, titik tanpa usaha, dan usaha yang belum bertitik pada wilayah terpilih.
- **Hak akses** dijaga di server (rute `auth` + `role`), bukan sekadar disembunyikan di tampilan. Untuk menambah role (mis. operator) ubah satu baris: `SebaranUsahaController::ROLE_TITIK`.
  Rute dan sidebar mengikutinya otomatis.

## Memperbarui data (bila Excel/GeoJSON berubah)

```
pip install pandas openpyxl shapely
python tools/se2026/prepare_se2026.py --excel USAHA_CLEAN_KOORDINAT.xlsx --titik 1674_geotagging_lat_long_only.geojson --sls final_sls_1674_2026-1.geojson
php artisan se2026:import
```
Skrip mencetak ringkasan cakupan data. Impor **mengganti seluruh isi** ketiga tabel.

## Prasyarat

- Laravel 9, PHP 8, MySQL/MariaDB.
- Chart.js 2.9.4 bawaan template: `public/template/backend/plugins/chart.js/Chart.bundle.min.js` (sudah ada di proyek Anda). Bila tidak ada, hanya grafik yang tidak tampil.
- Leaflet dimuat dari `unpkg.com` dan ubin peta dari OpenStreetMap (seperti halaman detail KOSEKA), jadi perlu internet.

## Yang perlu Anda ketahui tentang datanya

1. **`latitude_edited` / `longitude_edited` di GeoJSON titik bukan koordinat.** Isinya ID subSLS dan 0/1 pada seluruh 94.240 titik. Yang dipakai adalah **geometri** titik (hasil edit).
   Koordinat ini berbeda dari kolom Latitude/Longitude Excel (hanya 612 dari 34.873 yang sama persis).
2. **Titik dan usaha tidak 1:1.** Dari 94.217 titik terpakai, 36.702 punya data usaha dan 57.515 tidak. Sebaliknya 15.773 baris usaha (15.735 `assignment_id`) belum bertitik, 15.629 di antaranya KBLI-nya kosong.
   Satu titik bisa memuat beberapa usaha (maks. 11), semuanya tampil di popup. Titik tanpa data usaha disembunyikan (default) dan bisa ditampilkan lewat kotak centang.
3. **Relasi `assignment_id` tanpa FOREIGN KEY di database**, karena constraint akan menolak ribuan baris yang tidak berpasangan. Relasinya ada di model Eloquent dan kolomnya terindeks.
4. **Wilayah titik ditentukan dari poligon SLS** (spatial join); sama dengan `ID_SUBSLS` Excel pada 99,96% titik yang punya usaha. 23 titik dibuang: 17 jauh di luar kota, 6 di luar semua poligon.
5. **62 baris usaha berkode SLS `…0000`** tidak ada di peta (26 kode). Tetap dihitung di total dan muncul sebagai "SLS xxxx (tidak ada di peta)".
6. **Privasi**: NIK dan nama penanggung jawab **tidak dibawa** ke web. Nama usaha hanya tampil untuk admin. `storage/app/se2026/` otomatis diabaikan git; **jangan** menyalin CSV-nya ke folder yang di-commit, terutama bila repositori publik.
7. Nama usaha sering memuat `<` `>` (12.203 baris). Semua teks popup di-escape sehingga tampil apa adanya.

## Sudah diuji

- 7 test PHPUnit (87 assertion): akses guest/role, halaman guest tanpa jejak admin, halaman admin hanya untuk role admin, URL titik versi lama sudah 404, angka tiap level, filter KBLI, detail titik via `assignment_id`, relasi Eloquent.
- 39 pemeriksaan di browser (Chromium, dengan template AdminLTE asli): guest, tampilan HP, admin (sidebar, menu aktif, penghitung, titik, popup, salin koordinat termasuk halaman `http` tanpa Clipboard API), 17.449 titik sekaligus.
- Simulasi instalasi bersih mengikuti panduan ini; halaman lama tetap berfungsi.
- Dijalankan di SQLite dan MariaDB 10.11 (mode `ONLY_FULL_GROUP_BY`). **Belum diuji di MySQL 8 maupun PHP 8.1/8.2** (diuji di PHP 8.3, Laravel 9.52).
