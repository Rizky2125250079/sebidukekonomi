@extends('backend.layouts.app')

@section('title', 'Berita Kegiatan')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ $title }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/backend">Home</a></li>
                        <li class="breadcrumb-item active">{{ $title }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Berita Kegiatan</h3>
                    <div class="card-tools">
                        <a href="{{ route('berita.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Tambah Kegiatan
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Tanggal</th>
                                <th>Judul</th>
                                <th>Tempat</th>
                                <th>Peserta</th>
                                <th>Jenis</th>
                                <th>Aset</th>
                                <th style="width: 150px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $key => $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                                    <td>
                                        <strong>{{ $row->judul }}</strong><br>
                                        <small class="text-muted">Oleh: {{ $row->user->name ?? 'Anonim' }}</small>
                                    </td>
                                    <td>{{ $row->tempat }}</td>
                                    <td><span class="badge badge-info">{{ $row->jumlah_peserta }} Orang</span></td>
                                    <td>{{ $row->jenisLaporan->nama_jenis ?? '-' }}</td>
                                    <td>
                                        @if ($row->gambar)
                                            <i class="fas fa-image text-primary" title="Ada Gambar"></i>
                                        @endif
                                        @if ($row->link_pdf)
                                            <i class="fas fa-file-pdf text-danger" title="Ada PDF"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('berita.destroy', $row->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <a href="{{ route('berita.edit', $row->id) }}" class="btn btn-warning btn-xs">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button type="submit" class="btn btn-danger btn-xs"
                                                onclick="return confirm('Hapus data ini?')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Belum ada data berita kegiatan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{-- $data->links() --}} </div>
            </div>
        </div>
    </section>



@endsection
