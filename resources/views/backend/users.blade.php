@extends('backend.layouts.app')

@section('title', 'Kelola Pengguna')

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
                    <h3 class="card-title">Daftar Pengguna Sistem</h3>

                </div>
                <div class="card-body p-0">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ $row->email }}</td>
                                    <td>
                                        <span class="badge {{ $row->role == 'admin' ? 'badge-danger' : 'badge-success' }}">
                                            {{ ucfirst($row->role) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-warning btn-xs" data-toggle="modal"
                                            data-target="#modal-edit-role-{{ $row->id }}">
                                            <i class="fas fa-user-tag"></i> Ganti Role
                                        </button>

                                        {{-- <a href="{{ route('users.edit', $row->id) }}" class="btn btn-info btn-xs">
                                            <i class="fas fa-edit"></i> Edit Profil
                                        </a> --}}
                                    </td>
                                </tr>

                                <div class="modal fade" id="modal-edit-role-{{ $row->id }}">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h4 class="modal-title">Ubah Role: {{ $row->name }}</h4>
                                                <button type="button" class="close" data-dismiss="modal"
                                                    aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form action="{{ route('user.update', $row->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    <input type="hidden" name="type" value="update_role">

                                                    <div class="form-group">
                                                        <label for="role">Pilih Role Baru</label>
                                                        <select name="role" class="form-control" required>
                                                            <option value="admin"
                                                                {{ $row->role == 'admin' ? 'selected' : '' }}>Admin
                                                            </option>
                                                            <option value="viewer"
                                                                {{ $row->role == 'viewer' ? 'selected' : '' }}>Viewer
                                                            </option>
                                                            <option value="pml"
                                                                {{ $row->role == 'pml' ? 'selected' : '' }}>PML
                                                            </option>
                                                            <option value="pcl"
                                                                {{ $row->role == 'pcl' ? 'selected' : '' }}>PCL
                                                            </option>
                                                            <option value="operator"
                                                                {{ $row->role == 'operator' ? 'selected' : '' }}>Operator
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <p class="text-muted small">* Perubahan role akan langsung mempengaruhi
                                                        hak akses pengguna ini.</p>
                                                </div>
                                                <div class="modal-footer justify-content-between">
                                                    <button type="button" class="btn btn-default"
                                                        data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">Data tidak ditemukan</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
@push('scripts')
    <script>
        $(function() {
            $("#example1").DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');

        });
    </script>
@endpush
