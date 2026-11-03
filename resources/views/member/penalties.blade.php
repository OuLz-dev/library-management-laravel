@extends('layouts.app')

@section('title', 'Denda Saya')

@section('content')
<div class="card">
    <div class="card-header bg-danger text-white">
        <h5>Denda Saya</h5>
    </div>
    <div class="card-body">
        @if($totalUnpaid > 0)
            <div class="alert alert-warning">
                <h5>Total Denda Belum Dibayar: <strong>Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</strong></h5>
            </div>
        @endif

        @if($penalties->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th>Terlambat</th>
                            <th>Denda</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($penalties as $penalty)
                        <tr>
                            <td>{{ $penalty->loan->book->title ?? '-' }}</td>
                            <td class="text-danger">{{ $penalty->days_late }} hari</td>
                            <td class="text-danger">Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}</td>
                            <td>
                                @if($penalty->status == 'paid')
                                    <span class="badge bg-success">Lunas</span>
                                @else
                                    <span class="badge bg-danger">Belum Dibayar</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $penalties->firstItem() }}</strong> - <strong>{{ $penalties->lastItem() }}</strong>
                    dari <strong>{{ $penalties->total() }}</strong> denda
                </div>
                <div>
                    {{ $penalties->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <p class="text-muted">Tidak ada denda</p>
        @endif

        <hr class="my-4">

        <h5 class="mb-3">
            <i class="fas fa-hourglass-half me-2 text-secondary"></i>
            Denda Berjalan
        </h5>

        @if(($runningLoans ?? collect())->count() > 0)
            <div class="alert alert-warning">
                <h5 class="mb-0">
                    Estimasi Denda Berjalan:
                    <strong>Rp {{ number_format($totalRunning, 0, ',', '.') }}</strong>
                </h5>
                <small>Denda ini masih bertambah dan baru bisa dibayar setelah buku dikembalikan.</small>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th>Jatuh Tempo</th>
                            <th>Terlambat</th>
                            <th>Estimasi Denda</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runningLoans as $loan)
                        <tr>
                            <td>{{ $loan->book->title ?? '-' }}</td>
                            <td>{{ $loan->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="text-danger">{{ $loan->late_days }} hari</td>
                            <td class="text-danger">
                                Rp {{ number_format($loan->late_days * $finePerDay, 0, ',', '.') }}
                            </td>
                            <td><span class="badge bg-warning text-dark">Menunggu pengembalian</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">Tidak ada denda berjalan.</p>
        @endif
    </div>
</div>
@endsection