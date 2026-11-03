@extends('layouts.app')

@section('title', 'Manajemen Member')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i> Daftar Member</h5>
        <a href="{{ route('admin.members.create') }}" class="btn btn-light btn-sm">
            <i class="fas fa-plus me-1"></i> Tambah Member
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!--  FORM SEARCH & FILTER -->
        <form method="GET" action="{{ route('admin.members.index') }}" class="mb-4">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Cari nama, email, kode member, no. HP..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="blocked" {{ request('status') == 'blocked' ? 'selected' : '' }}>Diblokir</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    @if(request('search') || request('status'))
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <span class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Menampilkan <strong>{{ $members->total() }}</strong> member
                            </span>
                            @if(request('search'))
                                <span class="badge bg-info">Pencarian: "{{ request('search') }}"</span>
                            @endif
                            @if(request('status') == 'active')
                                <span class="badge bg-success">Status: Aktif</span>
                            @elseif(request('status') == 'blocked')
                                <span class="badge bg-danger">Status: Diblokir</span>
                            @endif
                            <a href="{{ route('admin.members.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i> Reset Filter
                            </a>
                        </div>
                    @else
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Menampilkan <strong>{{ $members->total() }}</strong> member
                        </span>
                    @endif
                </div>
            </div>
        </form>

        @if($members->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode Member</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. Telepon</th>
                            <th>Tgl Daftar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $index => $member)
                        <tr>
                            <td>{{ $members->firstItem() + $index }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $member->member_code }}</span>
                            </td>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone ?? '-' }}</td>
                            <td>{{ $member->join_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @if($member->status == 'active')
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-danger">Diblokir</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.members.toggle-status', $member) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    @if($member->status == 'active')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Blokir member ini?')">
                                            <i class="fas fa-ban me-1"></i> Blokir
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Aktifkan member ini?')">
                                            <i class="fas fa-check me-1"></i> Aktifkan
                                        </button>
                                    @endif
                                </form>
                                <form action="{{ route('admin.members.destroy', $member) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Yakin hapus member {{ $member->member_code }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash me-1"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{--  PAGINATION 10 MEMBER PER HALAMAN --}}
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $members->firstItem() }}</strong> - <strong>{{ $members->lastItem() }}</strong> 
                    dari <strong>{{ $members->total() }}</strong> member
                </div>
                <div>
                    {{ $members->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <p class="text-muted">Tidak ada member yang ditemukan</p>
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.members.index') }}" class="btn btn-primary">
                        <i class="fas fa-undo me-1"></i> Lihat Semua Member
                    </a>
                @else
                    <a href="{{ route('admin.members.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Tambah Member
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection