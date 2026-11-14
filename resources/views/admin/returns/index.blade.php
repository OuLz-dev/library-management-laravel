@extends('layouts.app')

@section('title', 'Riwayat Pengembalian')

@section('content')
<style>
    /* Kolom per buku (Cara 2): satu baris kecil per buku, urutan sama di
       semua kolom. Tinggi baris disamakan supaya nilai tetap sejajar. */
    .return-line {
        line-height: 1.7;
        min-height: 1.7em;
        white-space: nowrap;
    }
    .book-title {
        display: inline-block;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }
</style>

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
            Halaman ini berisi riwayat pengembalian <strong>per transaksi</strong> — satu baris = satu kode transaksi.
            Buku yang belum kembali tetap ditampilkan dan diberi label
            <span class="badge bg-warning text-dark">Masih dipinjam</span>.
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
            <span class="badge bg-success">{{ $returns->total() }} transaksi</span>
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
                        {{-- Satu baris tabel = SATU TRANSAKSI. Kolom Buku, Tgl
                             Kembali, Keterlambatan, Denda, dan Diproses oleh
                             berisi satu baris kecil PER BUKU (urutan sama). --}}
                        @foreach($returns as $index => $item)
                        @php
                            $code = $item->loan_code;
                            $rows = $transactions[$code] ?? collect();
                            $first = $rows->first();
                            $totalBooks = $rows->count();
                            $returnedCount = $rows->where('status', 'returned')->count();
                            $isPartial = $returnedCount < $totalBooks;
                        @endphp
                        <tr>
                            <td>{{ $returns->firstItem() + $index }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $code }}</span>
                                <div class="small text-muted mt-1">{{ $totalBooks }} buku</div>
                                @if($isPartial)
                                    <span class="badge bg-info mt-1">Sebagian ({{ $returnedCount }}/{{ $totalBooks }})</span>
                                @else
                                    <span class="badge bg-success mt-1">Selesai</span>
                                @endif
                            </td>
                            <td>
                                {{ $first->user->name ?? '-' }}
                                @if($first?->user?->member_code)
                                    <span class="badge bg-light text-dark border ms-1">{{ $first->user->member_code }}</span>
                                @endif
                                @if($first?->user?->email)
                                    <div class="small text-muted">{{ $first->user->email }}</div>
                                @endif
                            </td>
                            <td>
                                @foreach($rows as $row)
                                    <div class="return-line">
                                        @if($row->status === 'returned')
                                            <i class="fas fa-check text-success me-1"></i>
                                            <span class="book-title" title="{{ $row->book->title ?? '-' }}">{{ $row->book->title ?? '-' }}</span>
                                        @else
                                            <i class="fas fa-book-open text-primary me-1"></i>
                                            <span class="book-title text-muted" title="{{ $row->book->title ?? '-' }}">{{ $row->book->title ?? '-' }}</span>
                                            <span class="badge bg-warning text-dark ms-1">Masih dipinjam</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>{{ $first?->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $first?->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @foreach($rows as $row)
                                    <div class="return-line">
                                        @if($row->status === 'returned')
                                            <span class="fw-semibold">{{ $row->loanReturn?->return_date?->format('d/m/Y') ?? '-' }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @foreach($rows as $row)
                                    @php $penalty = $row->penalty; @endphp
                                    <div class="return-line">
                                        @if($row->status !== 'returned')
                                            <span class="text-muted">—</span>
                                        @elseif($penalty && $penalty->days_late > 0)
                                            <span class="text-danger fw-bold">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                {{ $penalty->days_late }} hari
                                            </span>
                                        @else
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-1"></i> Tepat waktu
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @foreach($rows as $row)
                                    @php $penalty = $row->penalty; @endphp
                                    <div class="return-line">
                                        @if($row->status !== 'returned')
                                            <span class="text-muted">—</span>
                                        @elseif($penalty)
                                            <span class="text-danger fw-bold">
                                                Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}
                                            </span>
                                            @if($penalty->status === 'paid')
                                                <span class="badge bg-success">Lunas</span>
                                                @if($penalty->paid_date)
                                                    <span class="small text-muted">{{ $penalty->paid_date->format('d/m/Y') }}</span>
                                                @endif
                                            @else
                                                <span class="badge bg-danger">Belum Dibayar</span>
                                            @endif
                                        @else
                                            <span class="text-success">-</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td class="small">
                                @foreach($rows as $row)
                                    <div class="return-line">
                                        @if($row->status === 'returned')
                                            {{ $row->loanReturn?->admin?->name ?? '-' }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $returns->firstItem() }}</strong> - <strong>{{ $returns->lastItem() }}</strong>
                    dari <strong>{{ $returns->total() }}</strong> transaksi
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
