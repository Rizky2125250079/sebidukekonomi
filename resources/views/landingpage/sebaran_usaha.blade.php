@extends('landingpage.layouts.app')

@section('title', 'Sebaran Usaha SE2026 - SEbiduk')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('css/sebaran-usaha.css') }}?v={{ @filemtime(public_path('css/sebaran-usaha.css')) }}">
    <style>
        /* Palet mengikuti landing page: aksen oranye #fd7e14, teks #2a2541, judul #1b1535 */
        #suRoot {
            --su-orange: #fd7e14;
            --su-orange-dark: #d9480f;
            --su-orange-soft: #fff4e6;
            --su-ink: #2a2541;
            --su-head: #1b1535;
            --su-line: #f1e3d3;
            padding-top: 110px;
            padding-bottom: 40px;
            color: var(--su-ink);
        }

        #suRoot .su-title {
            color: var(--su-head);
            font-weight: 700;
        }

        #suRoot .su-title i {
            color: var(--su-orange);
        }

        #suRoot .su-crumb a {
            color: var(--su-orange-dark);
            text-decoration: none;
        }

        #suRoot .card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(42, 37, 65, .08);
        }

        #suRoot .su-card-head {
            background: linear-gradient(135deg, var(--su-orange), var(--su-orange-dark));
            color: #fff;
            border-radius: 14px 14px 0 0;
            padding: .75rem 1rem;
            font-weight: 700;
        }

        #suRoot .su-filter {
            position: sticky;
            top: 96px;
        }

        #suRoot .form-label {
            font-weight: 600;
            font-size: .82rem;
            color: var(--su-head);
            margin-bottom: .2rem;
        }

        #suRoot .form-select:focus,
        #suRoot .form-control:focus {
            border-color: var(--su-orange);
            box-shadow: 0 0 0 .2rem rgba(253, 126, 20, .2);
        }

        #suRoot .form-check-input:checked {
            background-color: var(--su-orange);
            border-color: var(--su-orange);
        }

        #suRoot .btn-su {
            background: var(--su-orange);
            border-color: var(--su-orange);
            color: #fff;
        }

        #suRoot .btn-su:hover {
            background: var(--su-orange-dark);
            border-color: var(--su-orange-dark);
            color: #fff;
        }

        #suRoot .btn-su-outline {
            border: 1px solid var(--su-orange);
            color: var(--su-orange-dark);
            background: #fff;
        }

        #suRoot .btn-su-outline:hover {
            background: var(--su-orange-soft);
        }

        /* kartu angka */
        #suRoot .su-kpi {
            padding: .7rem .5rem;
            text-align: center;
            height: 100%;
            background: #fff;
        }

        #suRoot .su-kpi small {
            display: block;
            font-size: .66rem;
            letter-spacing: .4px;
            font-weight: 700;
            color: #6c6a7d;
            text-transform: uppercase;
        }

        #suRoot .su-kpi b {
            font-size: 1.35rem;
            color: var(--su-head);
        }

        #suRoot .su-kpi.total {
            border: 2px solid var(--su-orange);
            border-radius: 14px;
        }

        #suRoot .su-kpi.total b {
            color: var(--su-orange);
        }

        #suRoot .su-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-right: 4px;
        }

        /* peta */
        #peta {
            height: 540px;
            border-radius: 0 0 14px 14px;
            position: relative;
            z-index: 0;
            isolation: isolate;
        }

        #suRoot .su-chart-box {
            position: relative;
            padding: .5rem 1rem 1rem;
        }

        @media (max-width: 991px) {
            #suRoot .su-filter {
                position: static;
            }

            #peta {
                height: 440px;
            }
        }
    </style>

    <div id="suRoot">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h2 class="su-title mb-1"><i class="bi bi-shop-window"></i> Sebaran Usaha SE2026</h2>
                    <div class="text-muted">Jumlah usaha hasil Sensus Ekonomi 2026 Kota Lubuk Linggau menurut wilayah dan KBLI
                    </div>
                </div>
                <div class="su-crumb"><a href="{{ url('/') }}">Beranda</a> / Sebaran Usaha</div>
            </div>

            @if (!$master['siap'])
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> Data SE2026 belum tersedia di server ini.
                </div>
            @else
                <div id="suError" class="alert alert-danger d-none"></div>

                <div class="row g-3">
                    {{-- ============== FILTER ============== --}}
                    <div class="col-lg-3">
                        <div class="card su-filter">
                            <div class="su-card-head"><i class="bi bi-sliders"></i> Parameter Data</div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <label class="form-label" for="fKota">Kota</label>
                                    <select id="fKota" class="form-select form-select-sm" disabled>
                                        <option>Kota Lubuk Linggau (seluruh wilayah)</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" for="fKec">Kecamatan</label>
                                    <select id="fKec" class="form-select form-select-sm"></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" for="fDesa">Kelurahan</label>
                                    <select id="fDesa" class="form-select form-select-sm" disabled></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" for="fSls">RT (SLS)</label>
                                    <select id="fSls" class="form-select form-select-sm" disabled></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" for="fKbli">Kode KBLI</label>
                                    <div class="input-group input-group-sm">
                                        <input id="fKbli" class="form-control" list="kbliList" inputmode="numeric"
                                            maxlength="5" autocomplete="off" placeholder="mis. 47 atau 47112">
                                        <button id="btnKbli" class="btn btn-su-outline" type="button"
                                            title="Hapus filter KBLI"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <div class="form-text" id="kbliHelp">Ketik 2–5 digit (awalan), atau pilih dari daftar.
                                        Isi <b>-</b> untuk usaha tanpa KBLI.</div>
                                    <datalist id="kbliList">
                                        <option value="-"
                                            label="Tanpa KBLI / tidak terisi · {{ number_format($master['tanpaKbli'], 0, ',', '.') }} usaha">
                                        </option>
                                        @foreach ($master['kbli'] as [$kode, $kat, $n])
                                            <option value="{{ $kode }}"
                                                label="{{ $kat ? 'Kat. ' . $kat . ' · ' : '' }}{{ number_format($n, 0, ',', '.') }} usaha">
                                            </option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <button id="btnReset" class="btn btn-su-outline btn-sm w-100 mt-2" type="button">
                                    <i class="bi bi-arrow-counterclockwise"></i> Atur ulang filter
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ============== HASIL ============== --}}
                    <div class="col-lg-9">
                        <div class="row g-2 mb-3 su-fade" id="kpi">
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi total"><small>Total Usaha</small><b id="kTotal">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi"><small><span class="su-dot"
                                            style="background:#fd7e14"></span>UMKM</small><b id="kUMKM">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi"><small><span class="su-dot"
                                            style="background:#ffc078"></span>UMK</small><b id="kUMK">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi"><small><span class="su-dot"
                                            style="background:#2a2541"></span>UM</small><b id="kUM">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi"><small><span class="su-dot"
                                            style="background:#c92a2a"></span>UB</small><b id="kUB">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="card su-kpi"><small><span class="su-dot"
                                            style="background:#adb5bd"></span>Lainnya</small><b id="kLAINNYA">–</b></div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="su-card-head d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <span><i class="bi bi-map-fill"></i> Peta Wilayah</span>
                                <small class="fw-normal" id="lokasiPeta">Kota Lubuk Linggau</small>
                            </div>
                            <div id="peta"></div>
                        </div>

                        <div class="card su-fade">
                            <div class="su-card-head d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <span><i class="bi bi-bar-chart-fill"></i> <span id="judulGrafik">Jumlah Usaha</span></span>
                                <small class="fw-normal" id="lokasiGrafik"></small>
                            </div>
                            <div class="su-chart-box">
                                <div id="chartBox" style="height:380px"><canvas id="chart"></canvas></div>
                                <div class="small text-muted mt-1" id="hintGrafik"></div>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">Sumber data: Sensus Ekonomi 2026 (BPS Kota Lubuk Linggau).
                            Batas wilayah: peta SLS 2026.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($master['siap'])
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('template/backend/plugins/chart.js/Chart.bundle.min.js') }}"></script>
        <script>
            window.SEBARAN_CFG = @json($cfg);
        </script>
        <script src="{{ asset('js/sebaran-usaha.js') }}?v={{ @filemtime(public_path('js/sebaran-usaha.js')) }}"></script>
    @endif
@endsection
