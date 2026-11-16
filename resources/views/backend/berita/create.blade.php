@extends('backend.layouts.app')
@section('title', 'Tambah Berita Kegiatan')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ $title }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('berita.index') }}">Berita</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('berita.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-8">
                        <div class="card card-primary">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="judul">Judul Kegiatan <span class="text-danger">*</span></label>
                                    <input type="text" name="judul" id="judul"
                                        class="form-control @error('judul') is-invalid @enderror"
                                        value="{{ old('judul') }}" placeholder="Masukkan judul kegiatan">
                                    @error('judul')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="deskripsi">Deskripsi / Laporan Singkat</label>
                                    <textarea name="deskripsi" id="deskripsi" class="form-control @error('deskripsi') is-invalid @enderror" rows="10"
                                        placeholder="Jelaskan detail kegiatan...">{{ old('deskripsi') }}</textarea>
                                    @error('deskripsi')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card card-info">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="id_jenis_laporan">Jenis Laporan <span class="text-danger">*</span></label>
                                    <select name="id_jenis_laporan" id="id_jenis_laporan"
                                        class="form-control @error('id_jenis_laporan') is-invalid @enderror select2bs4"
                                        style="width: 100%;">
                                        <option value="">-- Pilih Jenis --</option>
                                        @foreach ($jenisLaporan as $jenis)
                                            <option value="{{ $jenis->id }}"
                                                {{ old('id_jenis_laporan') == $jenis->id ? 'selected' : '' }}>
                                                {{ $jenis->nama_jenis }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_jenis_laporan')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="tanggal">Tanggal & Tempat <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal" id="tanggal"
                                        class="form-control mb-2 @error('tanggal') is-invalid @enderror"
                                        value="{{ old('tanggal', date('Y-m-d')) }}">
                                    @error('tanggal')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror

                                    <input type="text" name="tempat" id="tempat"
                                        class="form-control @error('tempat') is-invalid @enderror"
                                        placeholder="Lokasi kegiatan" value="{{ old('tempat') }}">
                                    @error('tempat')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="jumlah_peserta">Jumlah Peserta</label>
                                    <input type="number" name="jumlah_peserta" id="jumlah_peserta"
                                        class="form-control @error('jumlah_peserta') is-invalid @enderror"
                                        value="{{ old('jumlah_peserta', 0) }}">
                                    @error('jumlah_peserta')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                                <hr>
                                <div class="form-group">
                                    <label for="gambar">Upload Gambar (Bisa banyak)</label>
                                    <input type="file" name="gambar[]" id="gambar"
                                        class="form-control-file @error('gambar.*') is-invalid @enderror" multiple
                                        accept="image/*">
                                    <small class="text-muted">Format: jpg, png. Maks 2MB/file</small>
                                    @error('gambar.*')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="link_pdf">Upload PDF (Bisa banyak)</label>
                                    <input type="file" name="link_pdf[]" id="link_pdf"
                                        class="form-control-file @error('link_pdf.*') is-invalid @enderror" multiple
                                        accept=".pdf">
                                    <small class="text-muted">Format: pdf. Maks 10MB/file</small>
                                    @error('link_pdf.*')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary btn-block">Simpan Laporan</button>
                                <a href="{{ route('berita.index') }}" class="btn btn-default btn-block">Batal</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
