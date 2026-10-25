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
    </div>
</div>
@endsection