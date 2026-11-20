@extends('backend.layouts.app')

@section('title', 'Sebaran Usaha SE2026')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet"
        href="{{ asset('css/sebaran-usaha.css') }}?v={{ @filemtime(public_path('css/sebaran-usaha.css')) }}">
    <style>
        #peta {
            height: 560px;
        }

        .su-kpi {
            text-align: center;
            padding: .55rem .4rem;
            margin-bottom: 0;
            border-top: 3px solid #adb5bd;
        }

        .su-kpi small {
            display: block;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #6c757d;
        }

        .su-kpi b {
            font-size: 1.35rem;
            color: #343a40;
        }

        .su-kpi.total {
            border-top-color: #007bff;
        }

        .su-kpi.total b {
            color: #007bff;
        }

        .su-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .su-tools {
            max-width: 58%;
            text-align: right;
        }

        .su-tools .su-loc {
            display: inline-block;
            max-width: calc(100% - 44px);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
            margin-right: .5rem;
        }

        .su-label {
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: .15rem;
        }
    </style>
@endpush

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-7">
                    <h1><i class="fas fa-store mr-2" style="color:#fd7e14"></i>Sebaran Usaha SE2026</h1>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ url('backend') }}">Home</a></li>
                        <li class="breadcrumb-item">Persiapan</li>
                        <li class="breadcrumb-item active">Sebaran Usaha</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid" id="suRoot">

            @if (!$master['siap'])
                <div class="callout callout-warning bg-white shadow-sm">
                    <h5><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Data SE2026 belum diimpor</h5>
                    <p class="mb-0">Jalankan <code>php artisan migrate</code> lalu <code>php artisan se2026:import</code>
                        di folder proyek, kemudian muat ulang halaman ini.</p>
                </div>
            @else
                <div id="suError" class="alert alert-danger d-none"></div>

                <div class="row">
                    {{-- ============== KOLOM KIRI: FILTER + TITIK ============== --}}
                    <div class="col-lg-3">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-sliders-h mr-1"></i> Parameter Data</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i
                                            class="fas fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-2">
                                    <label class="su-label" for="fKota">Kota</label>
                                    <select id="fKota" class="form-control form-control-sm" disabled>
                                        <option>Kota Lubuk Linggau (seluruh wilayah)</option>
                                    </select>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="su-label" for="fKec">Kecamatan</label>
                                    <select id="fKec" class="form-control form-control-sm"></select>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="su-label" for="fDesa">Kelurahan</label>
                                    <select id="fDesa" class="form-control form-control-sm" disabled></select>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="su-label" for="fSls">RT (SLS)</label>
                                    <select id="fSls" class="form-control form-control-sm" disabled></select>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="su-label" for="fKbli">Kode KBLI</label>
                                    <div class="input-group input-group-sm">
                                        <input id="fKbli" class="form-control" list="kbliList" inputmode="numeric"
                                            maxlength="5" autocomplete="off" placeholder="mis. 47 atau 47112">
                                        <div class="input-group-append">
                                            <button id="btnKbli" class="btn btn-outline-secondary" type="button"
                                                title="Hapus filter KBLI"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Ketik 2–5 digit (awalan) atau pilih dari daftar.
                                        Isi <b>-</b> untuk usaha tanpa KBLI.</small>
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
                                <button id="btnReset" class="btn btn-outline-primary btn-sm btn-block mt-3"
                                    type="button">
                                    <i class="fas fa-undo mr-1"></i> Atur ulang filter
                                </button>
                            </div>
                        </div>

                        <div class="card card-outline card-warning">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-map-pin mr-1"></i> Titik Koordinat Usaha</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i
                                            class="fas fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="swTitik" checked>
                                    <label class="custom-control-label" for="swTitik">Tampilkan titik</label>
                                </div>
                                <div class="custom-control custom-checkbox mt-1">
                                    <input type="checkbox" class="custom-control-input" id="cbTanpa">
                                    <label class="custom-control-label" for="cbTanpa">Sertakan titik tanpa data
                                        usaha</label>
                                </div>

                                <ul class="list-group list-group-flush small mt-3">
                                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                                        <span><span class="su-dot" style="background:#d9480f"></span>Titik dengan data
                                            usaha</span><b id="aTitikUsaha">–</b>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                                        <span><span class="su-dot" style="background:#adb5bd"></span>Titik tanpa data
                                            usaha</span><b id="aTitikTanpa">–</b>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-1">
                                        <span><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Usaha belum
                                            bertitik</span><b id="aUsahaTanpa">–</b>
                                    </li>
                                </ul>
                                <div id="infoTitik" class="small mt-2 text-muted" aria-live="polite"></div>
                            </div>
                            <div class="card-footer p-2">
                                <a href="{{ url('SebaranUsaha') }}" target="_blank" rel="noopener"
                                    class="btn btn-default btn-sm btn-block">
                                    <i class="fas fa-external-link-alt mr-1"></i> Lihat tampilan publik
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- ============== KOLOM KANAN: KPI + PETA + GRAFIK ============== --}}
                    <div class="col-lg-9">
                        <div class="row su-fade mb-3">
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi total"><small>Total Usaha</small><b id="kTotal">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi" style="border-top-color:#fd7e14"><small>UMKM</small><b
                                        id="kUMKM">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi" style="border-top-color:#ffc078"><small>UMK</small><b
                                        id="kUMK">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi" style="border-top-color:#2a2541"><small>UM</small><b
                                        id="kUM">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi" style="border-top-color:#c92a2a"><small>UB</small><b
                                        id="kUB">–</b></div>
                            </div>
                            <div class="col-6 col-md-4 col-xl-2 mb-2">
                                <div class="card su-kpi" style="border-top-color:#adb5bd"><small>Lainnya</small><b
                                        id="kLAINNYA">–</b></div>
                            </div>
                        </div>

                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-map-marked-alt mr-1"></i> Peta Wilayah</h3>
                                <div class="card-tools su-tools">
                                    <span class="text-muted small su-loc" id="lokasiPeta">Kota Lubuk Linggau</span>
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i
                                            class="fas fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="peta"></div>
                            </div>
                        </div>

                        <div class="card card-outline card-primary su-fade">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i> <span
                                        id="judulGrafik">Jumlah Usaha</span></h3>
                                <div class="card-tools su-tools">
                                    <span class="text-muted small su-loc" id="lokasiGrafik"></span>
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i
                                            class="fas fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="chartBox" style="height:380px"><canvas id="chart"></canvas></div>
                                <div class="small text-muted mt-1" id="hintGrafik"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection

@if ($master['siap'])
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('template/backend/plugins/chart.js/Chart.bundle.min.js') }}"></script>
        <script>
            window.SEBARAN_CFG = @json($cfg);
        </script>
        <script src="{{ asset('js/sebaran-usaha.js') }}?v={{ @filemtime(public_path('js/sebaran-usaha.js')) }}"></script>
    @endpush
@endif
