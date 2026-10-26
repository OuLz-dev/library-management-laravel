@extends('layouts.app')

@section('title', 'Riwayat Pengembalian')

@section('content')
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Riwayat Pengembalian</h5>
        <a href="{{ route('admin.returns.create') }}" class="btn btn-light btn-sm">
            <i class="fas fa-plus me-1"></i> Kembalikan Buku
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-light border">
            <i class="fas fa-info-circle me-1 text-muted"></i>
            Halaman ini berisi riwayat buku yang <strong>sudah dikembalikan</strong>.
            Pencarian di bawah hanya mencari di riwayat pengembalian.
        </div>

        {{-- Pencarian khusus riwayat pengembalian. --}}
        <form method="GET" action="{{ route('admin.returns.index') }}" class="mb-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control form-control-lg"
                       placeholder="Cari kode peminjaman, nama member, atau judul buku di riwayat..."
                       value="{{ request('search') }}">
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
                @if(request('search'))
                    <a href="{{ route('admin.returns.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                @endif
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-history me-2 text-success"></i> Daftar Riwayat</h5>
            <span class="badge bg-success">{{ $returns->total() }} pengembalian</span>
        </div>

        @if($returns->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Member</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Tgl Kembali</th>
                            <th>Keterlambatan</th>
                            <th>Denda</th>
                            <th>Diproses oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returns as $index => $item)
                        @php
                            $loan = $item->loan;
                            $penalty = $loan?->penalty;
                        @endphp
                        <tr>
                            <td>{{ $returns->firstItem() + $index }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $loan->loan_code ?? '-' }}</span>
                            </td>
                            <td>
                                {{ $loan->user->name ?? '-' }}
                                @if($loan?->user?->member_code)
                                    <span class="badge bg-light text-dark border ms-1">{{ $loan->user->member_code }}</span>
                                @endif
                                @if($loan?->user?->email)
                                    <div class="small text-muted">{{ $loan->user->email }}</div>
                                @endif
                            </td>
                            <td>{{ $loan->book->title ?? '-' }}</td>
                            <td>{{ $loan?->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $loan?->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $item->return_date?->format('d/m/Y') ?? '-' }}</span>
                            </td>
                            <td>
                                @if($penalty && $penalty->days_late > 0)
                                    <span class="text-danger fw-bold">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        {{ $penalty->days_late }} hari
                                    </span>
                                @else
                                    <span class="text-success">
                                        <i class="fas fa-check-circle me-1"></i> Tepat waktu
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($penalty)
                                    <span class="text-danger fw-bold">
                                        Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}
                                    </span>
                                    <div class="mt-1">
                                        @if($penalty->status === 'paid')
                                            <span class="badge bg-success">Lunas</span>
                                            @if($penalty->paid_date)
                                                <span class="small text-muted">{{ $penalty->paid_date->format('d/m/Y') }}</span>
                                            @endif
                                        @else
                                            <span class="badge bg-danger">Belum Dibayar</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-success">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $item->admin?->name ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $returns->firstItem() }}</strong> - <strong>{{ $returns->lastItem() }}</strong>
                    dari <strong>{{ $returns->total() }}</strong> pengembalian
                </div>
                <div>
                    {{ $returns->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-1">
                    {{ request('search') ? 'Tidak ada riwayat pengembalian yang cocok' : 'Belum ada riwayat pengembalian' }}
                </p>
                <p class="text-muted small">
                    Pengembalian yang diproses akan muncul di sini.
                </p>
                @if(request('search'))
                    <a href="{{ route('admin.returns.index') }}" class="btn btn-primary">
                        <i class="fas fa-list me-1"></i> Lihat Semua Riwayat
                    </a>
                @else
                    <a href="{{ route('admin.returns.create') }}" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Kembalikan Buku
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
