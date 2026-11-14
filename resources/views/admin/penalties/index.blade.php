@extends('layouts.app')

@section('title', 'Denda')

@section('content')
<style>
    /* Satu baris kecil per buku, urutan sama di semua kolom (pola yang sama
       dengan halaman Riwayat Pengembalian) supaya nilai tetap sejajar. */
    .fee-line {
        line-height: 1.7;
        min-height: 1.7em;
        white-space: nowrap;
    }
    .fee-book-title {
        display: inline-block;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }
</style>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i> Daftar Denda</h5>
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

        <div class="alert alert-light border">
            <i class="fas fa-info-circle me-1 text-muted"></i>
            Satu baris tabel = <strong>satu kode transaksi</strong>. Detail per buku ditampilkan
            berurutan pada kolom Buku, Terlambat, Jumlah Denda, dan Aksi.
            Pembayaran denda tetap dilakukan <strong>per buku</strong>.
        </div>

        <!-- Statistik Denda -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center py-2">
                        <h6>Total Denda Tercatat</h6>
                        <h4>Rp {{ number_format($totalUnpaid + $totalPaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center py-2">
                        <h6>Belum Dibayar</h6>
                        <h4>Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center py-2">
                        <h6>Sudah Dibayar</h6>
                        <h4>Rp {{ number_format($totalPaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center py-2">
                        <h6>Denda Berjalan</h6>
                        <h4>Rp {{ number_format($totalRunning, 0, ',', '.') }}</h4>
                        <div class="small">Estimasi, belum tercatat</div>
                    </div>
                </div>
            </div>
        </div>

        @if($totalUnpaid > 0)
            <div class="alert alert-warning">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i> 
                    Total Denda Belum Dibayar: <strong>Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</strong>
                </h5>
            </div>
        @endif

        <!--  FORM SEARCH & FILTER -->
        <form method="GET" action="{{ route('admin.penalties.index') }}" class="mb-4">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Cari kode peminjaman, nama, email, no. HP, judul buku..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-control">
                        <option value="">-- Semua Status --</option>
                        <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Belum Dibayar (tercatat)</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Lunas (tercatat)</option>
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
                                Menampilkan <strong>{{ $penalties->total() }}</strong> transaksi denda tercatat
                            </span>
                            @if(request('search'))
                                <span class="badge bg-info">Pencarian: "{{ request('search') }}"</span>
                            @endif
                            @if(request('status') == 'unpaid')
                                <span class="badge bg-danger">Status denda tercatat: Belum Dibayar</span>
                            @elseif(request('status') == 'paid')
                                <span class="badge bg-success">Status denda tercatat: Lunas</span>
                            @endif
                            <a href="{{ route('admin.penalties.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i> Reset Filter
                            </a>
                        </div>
                    @else
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Menampilkan <strong>{{ $penalties->total() }}</strong> transaksi denda tercatat
                        </span>
                    @endif
                </div>
            </div>
        </form>

        {{--  DENDA BERJALAN: satu baris = satu transaksi; sub-baris = buku yang
              masih dipinjam dan sudah lewat jatuh tempo. Nilainya estimasi
              real-time, record final dibuat saat buku dikembalikan. --}}
        @php $runningTransactions = $runningLoans->groupBy('loan_code'); @endphp
        @if($runningTransactions->count() > 0)
            <div id="denda-berjalan" class="card border-warning mb-4">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">
                        <i class="fas fa-hourglass-half me-2"></i> Denda Berjalan (Buku Belum Dikembalikan)
                    </h6>
                    <span class="badge bg-dark">
                        Estimasi: Rp {{ number_format($filteredRunningTotal, 0, ',', '.') }}
                    </span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Dihitung otomatis dari keterlambatan Rp {{ number_format($finePerDay, 0, ',', '.') }}
                        per hari per buku. Denda final baru tercatat di tabel bawah setelah buku dikembalikan.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Member</th>
                                    <th>Buku</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Terlambat</th>
                                    <th>Estimasi Denda</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($runningTransactions as $code => $rows)
                                    @php
                                        $first = $rows->first();
                                        $runningBooks = $rows->count();
                                        $transactionRunning = $rows->sum(fn ($row) => $row->late_days * $finePerDay);
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $code }}</span>
                                            <div class="small text-muted mt-1">{{ $runningBooks }} buku</div>
                                        </td>
                                        <td>
                                            {{ $first->user->name ?? '-' }}
                                            @if(!empty($first->user?->member_code))
                                                <div class="small text-muted">{{ $first->user->member_code }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($rows as $row)
                                                <div class="fee-line">
                                                    <i class="fas fa-book-open text-primary me-1"></i>
                                                    <span class="fee-book-title" title="{{ $row->book->title ?? '-' }}">{{ $row->book->title ?? '-' }}</span>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach($rows as $row)
                                                <div class="fee-line">{{ $row->due_date?->format('d/m/Y') ?? '-' }}</div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach($rows as $row)
                                                <div class="fee-line">
                                                    <span class="text-danger fw-bold">{{ $row->late_days }} hari</span>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach($rows as $row)
                                                <div class="fee-line">
                                                    <span class="text-danger fw-bold">
                                                        Rp {{ number_format($row->late_days * $finePerDay, 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            @endforeach
                                            <div class="small fw-bold mt-1">
                                                Total: Rp {{ number_format($transactionRunning, 0, ',', '.') }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">Berjalan</span>
                                            <div class="small text-muted mt-1">{{ $runningBooks }} buku belum kembali</div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.returns.create') }}" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-undo me-1"></i> Kembalikan
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="6" class="text-end">Total estimasi denda berjalan (hasil filter):</th>
                                    <th colspan="3">Rp {{ number_format($filteredRunningTotal, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div id="denda-tercatat" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h6 class="mb-0">
                <i class="fas fa-file-invoice-dollar me-2"></i> Denda Tercatat (Buku Sudah Dikembalikan)
            </h6>
            <span class="text-muted small">Pembayaran dilakukan per buku</span>
        </div>

        @if($penalties->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode Peminjaman</th>
                            <th>Member</th>
                            <th>Judul Buku</th>
                            <th>Jatuh Tempo</th>
                            <th>Terlambat</th>
                            <th>Jumlah Denda</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($penalties as $index => $transaction)
                            @php
                                $code = $transaction->loan_code;
                                $rows = $transactions->get($code, collect());
                                $first = $rows->first();
                                $allPenalties = $rows->pluck('penalty')->filter();
                                $unpaidCount = $allPenalties->where('status', 'unpaid')->count();
                                $paidCount = $allPenalties->where('status', 'paid')->count();
                                $transactionTotal = $allPenalties->sum('fine_amount');

                                if ($allPenalties->isEmpty()) {
                                    $statusLabel = 'Tanpa denda';
                                    $statusClass = 'bg-secondary';
                                } elseif ($unpaidCount === 0) {
                                    $statusLabel = 'Lunas';
                                    $statusClass = 'bg-success';
                                } elseif ($paidCount === 0) {
                                    $statusLabel = 'Belum dibayar';
                                    $statusClass = 'bg-danger';
                                } else {
                                    $statusLabel = 'Sebagian dibayar';
                                    $statusClass = 'bg-warning text-dark';
                                }
                            @endphp
                            <tr>
                                <td>{{ $penalties->firstItem() + $index }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $code }}</span>
                                    <div class="small text-muted mt-1">{{ $rows->count() }} buku</div>
                                </td>
                                <td>
                                    {{ $first?->user?->name ?? '-' }}
                                    @if(!empty($first?->user?->member_code))
                                        <div class="small text-muted">{{ $first->user->member_code }}</div>
                                    @endif
                                    @if(!empty($first?->user?->email) || !empty($first?->user?->phone))
                                        <div class="small text-muted">
                                            {{ $first?->user?->email ?? '' }}
                                            @if(!empty($first->user?->email) && !empty($first->user?->phone))
                                                <br>
                                            @endif
                                            {{ $first?->user?->phone ?? '' }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @foreach($rows as $row)
                                        <div class="fee-line">
                                            <i class="fas fa-book-open text-primary me-1"></i>
                                            <span class="fee-book-title" title="{{ $row->book->title ?? '-' }}">{{ $row->book->title ?? '-' }}</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($rows as $row)
                                        <div class="fee-line">{{ $row->due_date?->format('d/m/Y') ?? '-' }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($rows as $row)
                                        <div class="fee-line">
                                            @if($row->penalty)
                                                <span class="text-danger fw-bold">{{ $row->penalty->days_late }} hari</span>
                                            @elseif($row->status === 'returned')
                                                <span class="text-success">Tepat waktu</span>
                                            @else
                                                <span class="text-muted">Belum kembali</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($rows as $row)
                                        <div class="fee-line">
                                            @if($row->penalty)
                                                <span class="text-danger fw-bold">Rp {{ number_format($row->penalty->fine_amount, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    <div class="small fw-bold mt-1">
                                        Total: Rp {{ number_format($transactionTotal, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <div class="small text-muted mt-1">
                                        {{ $paidCount }} lunas / {{ $unpaidCount }} belum
                                    </div>
                                </td>
                                <td>
                                    @foreach($rows as $row)
                                        <div class="fee-line">
                                            @if($row->penalty && $row->penalty->status === 'unpaid')
                                                {{--  PEMBAYARAN TETAP PER BUKU (PER RECORD DENDA) --}}
                                                <a href="{{ route('admin.penalties.pay.form', $row->penalty) }}"
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-money-bill me-1"></i> Bayar
                                                </a>
                                            @elseif($row->penalty && $row->penalty->status === 'paid')
                                                <span class="badge bg-success">Lunas</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="6" class="text-end">Total denda tercatat (hasil filter):</th>
                            <th colspan="3">Rp {{ number_format($filteredTotal, 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{--  PAGINATION 10 TRANSAKSI PER HALAMAN --}}
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $penalties->firstItem() }}</strong> - <strong>{{ $penalties->lastItem() }}</strong>
                    dari <strong>{{ $penalties->total() }}</strong> transaksi
                </div>
                <div>
                    {{ $penalties->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-4 border rounded bg-light">
                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                <p class="text-muted mb-2">
                    @if(request('status') == 'unpaid')
                        Tidak ada denda tercatat yang belum dibayar.
                    @elseif(request('status') == 'paid')
                        Belum ada denda tercatat yang lunas.
                    @elseif(request('search'))
                        Tidak ada denda tercatat yang cocok dengan pencarian.
                    @else
                        Belum ada denda tercatat.
                    @endif
                </p>

                @if($runningTransactions->count() > 0)
                    <p class="small text-muted mb-2">
                        <i class="fas fa-hourglass-half me-1"></i>
                        Ada <strong>{{ $runningTransactions->count() }}</strong> transaksi dengan denda berjalan
                        (estimasi Rp {{ number_format($filteredRunningTotal, 0, ',', '.') }}).
                        Denda ini baru tercatat setelah buku dikembalikan.
                    </p>
                    <a href="#denda-berjalan" class="btn btn-warning btn-sm">
                        <i class="fas fa-arrow-up me-1"></i> Lihat Denda Berjalan
                    </a>
                @endif

                @if(request('search') || request('status'))
                    <a href="{{ route('admin.penalties.index') }}"
                       class="btn btn-primary btn-sm {{ $runningTransactions->count() > 0 ? 'ms-1' : '' }}">
                        <i class="fas fa-undo me-1"></i> Lihat Semua Denda
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection