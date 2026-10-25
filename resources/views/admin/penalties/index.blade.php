@extends('layouts.app')

@section('title', 'Denda')

@section('content')
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

        <!-- Statistik Denda -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center py-2">
                        <h6>Total Denda</h6>
                        <h4>Rp {{ number_format($totalUnpaid + $totalPaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center py-2">
                        <h6>Belum Dibayar</h6>
                        <h4>Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center py-2">
                        <h6>Sudah Dibayar</h6>
                        <h4>Rp {{ number_format($totalPaid, 0, ',', '.') }}</h4>
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
                        <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Belum Dibayar</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Lunas</option>
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
                                Menampilkan <strong>{{ $penalties->total() }}</strong> denda
                            </span>
                            @if(request('search'))
                                <span class="badge bg-info">Pencarian: "{{ request('search') }}"</span>
                            @endif
                            @if(request('status') == 'unpaid')
                                <span class="badge bg-danger">Status: Belum Dibayar</span>
                            @elseif(request('status') == 'paid')
                                <span class="badge bg-success">Status: Lunas</span>
                            @endif
                            <a href="{{ route('admin.penalties.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i> Reset Filter
                            </a>
                        </div>
                    @else
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Menampilkan <strong>{{ $penalties->total() }}</strong> denda
                        </span>
                    @endif
                </div>
            </div>
        </form>

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
                        @foreach($penalties as $index => $penalty)
                        <tr>
                            <td>{{ $penalties->firstItem() + $index }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $penalty->loan->loan_code }}</span>
                            </td>
                            <td>
                                {{ $penalty->loan->user->name ?? '-' }}
                                @if(!empty($penalty->loan->user->email) || !empty($penalty->loan->user->phone))
                                    <div class="small text-muted">
                                        {{ $penalty->loan->user->email ?? '' }}
                                        @if(!empty($penalty->loan->user->email) && !empty($penalty->loan->user->phone))
                                            <br>
                                        @endif
                                        {{ $penalty->loan->user->phone ?? '' }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $penalty->loan->book->title ?? '-' }}</td>
                            <td>
                                {{ Carbon\Carbon::parse($penalty->loan->due_date)->format('d/m/Y') }}
                                @if(Carbon\Carbon::parse($penalty->loan->due_date)->isPast() && $penalty->loan->status == 'borrowed')
                                    <span class="badge bg-danger ms-1">Terlambat!</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-danger fw-bold">{{ $penalty->days_late }} hari</span>
                            </td>
                            <td>
                                <span class="text-danger fw-bold">Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}</span>
                            </td>
                            <td>
                                @if($penalty->status == 'paid')
                                    <span class="badge bg-success">Lunas</span>
                                    <div class="small text-muted mt-1">
                                        Tgl: {{ $penalty->paid_date?->format('d/m/Y') ?? '-' }}
                                    </div>
                                @else
                                    <span class="badge bg-danger">Belum Dibayar</span>
                                @endif
                            </td>
                            <td>
                                @if($penalty->status == 'unpaid')
                                    {{--  ARAHKAN KE FORM PEMBAYARAN --}}
                                    <a href="{{ route('admin.penalties.pay.form', $penalty) }}" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-money-bill me-1"></i> Bayar
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="6" class="text-end">Total Denda (hasil filter):</th>
                            <th colspan="3">Rp {{ number_format($filteredTotal, 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{--  PAGINATION 10 DENDA PER HALAMAN --}}
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $penalties->firstItem() }}</strong> - <strong>{{ $penalties->lastItem() }}</strong>
                    dari <strong>{{ $penalties->total() }}</strong> denda
                </div>
                <div>
                    {{ $penalties->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p class="text-muted">Tidak ada denda yang ditemukan</p>
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.penalties.index') }}" class="btn btn-primary">
                        <i class="fas fa-undo me-1"></i> Lihat Semua Denda
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection