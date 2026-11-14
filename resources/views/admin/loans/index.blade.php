@extends('layouts.app')

@section('title', 'Peminjaman')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i> Daftar Peminjaman Aktif</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.returns.create') }}" class="btn btn-success btn-sm">
                <i class="fas fa-undo me-1"></i> Kembalikan Buku
            </a>
            <a href="{{ route('admin.loans.create') }}" class="btn btn-light btn-sm">
                <i class="fas fa-plus me-1"></i> Pinjam Buku
            </a>
        </div>
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

        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Halaman ini hanya menampilkan transaksi yang <strong>masih memiliki buku belum dikembalikan</strong>.
                Riwayat buku yang sudah kembali ada di halaman Riwayat Pengembalian.
            </span>
            <a href="{{ route('admin.returns.index') }}" class="btn btn-outline-success btn-sm">
                <i class="fas fa-history me-1"></i> Riwayat Pengembalian
            </a>
        </div>

        <!--  SATU BAR PENCARIAN -->
        <form method="GET" action="{{ route('admin.loans.index') }}" class="mb-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control"
                       placeholder="Cari kode transaksi, nama, email, no. HP, atau judul buku..."
                       value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
                @if(request('search'))
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                @endif
            </div>
            <div class="mt-2">
                <span class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $loans->total() }}</strong> transaksi aktif
                </span>
                @if(request('search'))
                    <span class="badge bg-info ms-1">Pencarian: "{{ request('search') }}"</span>
                @endif
            </div>
        </form>

        @if($loans->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Member</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Sisa Hari</th>
                            <th>Status</th>
                            <th>Diproses oleh</th>
                            <th>Denda</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Satu baris tabel = SATU TRANSAKSI (semua baris loans berbagi loan_code). --}}
                        @foreach($loans as $rows)
                        @php
                            $code = $rows->first()->loan_code;
                            $totalBooks = $rows->count();
                            $returnedCount = $rows->where('status', 'returned')->count();
                            // Baris aktif mewakili tanggal/status transaksi (jatuh tempo berlaku
                            // untuk semua buku dalam transaksi yang sama).
                            $activeRow = $rows->firstWhere('status', 'borrowed') ?? $rows->first();
                            $days = $activeRow->remaining_days;
                            $hasFine = $activeRow->has_fine;
                            $fineAmount = $activeRow->current_fine;
                            // Denda transaksi = denda tercatat (buku sudah kembali telat)
                            //   + denda berjalan (buku belum kembali tapi lewat jatuh tempo).
                            $recordedFine = $rows->sum(fn ($r) => (float) ($r->penalty?->fine_amount ?? 0));
                            $runningFine = $rows->where('status', 'borrowed')->sum(fn ($r) => $r->current_fine);
                            $unpaidPenalties = $rows->filter(fn ($r) => $r->penalty && $r->penalty->status === 'unpaid')->count();
                        @endphp
                        <tr>
                            <td>
                                <div class="d-inline-flex align-items-center gap-1 copy-code-wrap">
                                    <span class="badge bg-secondary loan-code">{{ $code }}</span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-copy-code p-1"
                                            data-code="{{ $code }}"
                                            title="Copy kode (cari di form Kembalikan Buku)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div class="small text-muted mt-1">{{ $totalBooks }} buku</div>
                            </td>
                            <td>{{ $activeRow->user->name ?? '-' }}</td>
                            <td>
                                @foreach($rows as $row)
                                    <div class="d-flex align-items-center gap-1">
                                        @if($row->status === 'borrowed')
                                            <i class="fas fa-book-open text-primary" title="Masih dipinjam"></i>
                                        @else
                                            <i class="fas fa-check text-success" title="Sudah dikembalikan"></i>
                                        @endif
                                        <span class="{{ $row->status === 'returned' ? 'text-muted' : '' }}">{{ $row->book->title ?? '-' }}</span>
                                    </div>
                                @endforeach
                                @if($returnedCount > 0 && $returnedCount < $totalBooks)
                                    <span class="badge bg-info mt-1">{{ $returnedCount }}/{{ $totalBooks }} sudah dikembalikan</span>
                                @endif
                            </td>
                            <td>{{ $activeRow->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                {{ $activeRow->due_date?->format('d/m/Y') ?? '-' }}
                                @if($activeRow->due_date)
                                    @if($days < 0)
                                        <span class="badge bg-danger ms-1">Terlambat!</span>
                                    @elseif($days == 0)
                                        <span class="badge bg-warning ms-1">Hari Ini!</span>
                                    @elseif($days <= 2)
                                        <span class="badge bg-warning ms-1">Segera!</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($days < 0)
                                    <span class="text-danger fw-bold">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        {{ abs($days) }} hari terlambat
                                    </span>
                                @elseif($days == 0)
                                    <span class="text-warning fw-bold">
                                        <i class="fas fa-clock me-1"></i>
                                        Hari ini!
                                    </span>
                                @elseif($days <= 2)
                                    <span class="text-warning">
                                        <i class="fas fa-hourglass-half me-1"></i>
                                        {{ $days }} hari lagi
                                    </span>
                                @else
                                    <span class="text-success">
                                        <i class="fas fa-hourglass-start me-1"></i>
                                        {{ $days }} hari
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($returnedCount > 0 && $returnedCount < $totalBooks)
                                    <span class="badge bg-info">Sebagian dikembalikan ({{ $totalBooks - $returnedCount }} buku tersisa)</span>
                                @else
                                    <span class="badge bg-{{ $activeRow->status_color }}">
                                        {{ $activeRow->status_text }}
                                    </span>
                                @endif
                                @if($activeRow->warning_message)
                                    <div class="small text-danger mt-1">
                                        {{ $activeRow->warning_message }}
                                    </div>
                                @endif
                            </td>
                            <td class="small">
                                {{-- Admin yang meminjamkan (membuat) peminjaman --}}
                                {{ $activeRow->processedBy?->name ?? '-' }}
                            </td>
                            <td>
                                {{--  DENDA TRANSAKSI: gabungan denda tercatat + denda berjalan --}}
                                @if($recordedFine > 0)
                                    <span class="text-danger fw-bold">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Rp {{ number_format($recordedFine, 0, ',', '.') }}
                                    </span>
                                    <div class="small {{ $unpaidPenalties > 0 ? 'text-warning' : 'text-muted' }}">
                                        {{ $unpaidPenalties > 0 ? 'Belum bayar' : 'Lunas' }}
                                    </div>
                                    @if($runningFine > 0)
                                        <div class="small text-warning">+ berjalan Rp {{ number_format($runningFine, 0, ',', '.') }}</div>
                                    @endif
                                @elseif($runningFine > 0)
                                    <span class="text-danger fw-bold">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Rp {{ number_format($runningFine, 0, ',', '.') }}
                                    </span>
                                    <div class="small text-warning">
                                        Denda berjalan
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $loans->firstItem() }}</strong> - <strong>{{ $loans->lastItem() }}</strong>
                    dari <strong>{{ $loans->total() }}</strong> transaksi aktif
                </div>
                <div>
                    {{ $loans->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-hand-holding fa-3x text-muted mb-3"></i>
                <p class="text-muted">Tidak ada peminjaman aktif yang ditemukan</p>
                @if(request('search'))
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-primary">
                        <i class="fas fa-undo me-1"></i> Lihat Semua Peminjaman Aktif
                    </a>
                @else
                    <p class="text-muted small">
                        Semua buku sudah dikembalikan. Riwayatnya bisa dilihat di
                        <a href="{{ route('admin.returns.index') }}">halaman Pengembalian</a>.
                    </p>
                    <a href="{{ route('admin.loans.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Pinjam Buku Sekarang
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<style>
    .btn-copy-code i { pointer-events: none; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var FALLBACK_MS = 1500;

        function writeClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }

            // Fallback untuk lingkungan non-HTTPS tanpa clipboard API.
            return new Promise(function (resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    var ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    ok ? resolve() : reject(new Error('copy failed'));
                } catch (e) {
                    document.body.removeChild(ta);
                    reject(e);
                }
            });
        }

        document.addEventListener('click', function (event) {
            var btn = event.target.closest('.btn-copy-code');
            if (!btn) return;

            var code = btn.dataset.code;
            var icon = btn.querySelector('i');
            var oldTitle = btn.getAttribute('title');

            function flashSuccess() {
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check', 'text-success');
                    setTimeout(function () {
                        icon.classList.remove('fa-check', 'text-success');
                        icon.classList.add('fa-copy');
                    }, FALLBACK_MS);
                }
                btn.setAttribute('title', 'Kode ' + code + ' tercopy!');
                setTimeout(function () {
                    btn.setAttribute('title', oldTitle);
                }, FALLBACK_MS);
            }

            function flashError() {
                btn.setAttribute('title', 'Gagal copy kode');
                setTimeout(function () {
                    btn.setAttribute('title', oldTitle);
                }, FALLBACK_MS);
            }

            writeClipboard(code).then(flashSuccess).catch(function () {
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-exclamation-triangle', 'text-danger');
                    setTimeout(function () {
                        icon.classList.remove('fa-exclamation-triangle', 'text-danger');
                        icon.classList.add('fa-copy');
                    }, FALLBACK_MS);
                }
                flashError();
            });
        });
    });
</script>
@endpush
