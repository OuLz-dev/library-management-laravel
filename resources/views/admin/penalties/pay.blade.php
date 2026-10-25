@extends('layouts.app')

@section('title', 'Bayar Denda')

@section('content')
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i> Konfirmasi Pembayaran Denda</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <table class="table table-bordered align-middle">
            <tr>
                <th class="table-light" style="width: 30%">Kode Peminjaman</th>
                <td><span class="badge bg-secondary">{{ $penalty->loan->loan_code ?? '-' }}</span></td>
            </tr>
            <tr>
                <th class="table-light">Member</th>
                <td>
                    {{ $penalty->loan->user->name ?? '-' }}
                    <div class="small text-muted">
                        {{ $penalty->loan->user->email ?? '' }}
                        {{ $penalty->loan->user->phone ?? '' }}
                    </div>
                </td>
            </tr>
            <tr>
                <th class="table-light">Judul Buku</th>
                <td>{{ $penalty->loan->book->title ?? '-' }}</td>
            </tr>
            <tr>
                <th class="table-light">Jatuh Tempo</th>
                <td>{{ $penalty->loan->due_date ? \Carbon\Carbon::parse($penalty->loan->due_date)->format('d/m/Y') : '-' }}</td>
            </tr>
            <tr>
                <th class="table-light">Terlambat</th>
                <td><span class="text-danger fw-bold">{{ $penalty->days_late }} hari</span></td>
            </tr>
            <tr>
                <th class="table-light">Jumlah Denda</th>
                <td><span class="text-danger fw-bold">Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}</span></td>
            </tr>
            <tr>
                <th class="table-light">Status</th>
                <td><span class="badge bg-danger">Belum Dibayar</span></td>
            </tr>
        </table>

        <form action="{{ route('admin.penalties.pay', $penalty) }}" method="POST"
              onsubmit="return confirm('Konfirmasi pembayaran denda Rp {{ number_format($penalty->fine_amount, 0, ',', '.') }}?')">
            @csrf
            @method('PUT')
            <button type="submit" class="btn btn-success">
                <i class="fas fa-money-bill me-1"></i> Konfirmasi Bayar
            </button>
            <a href="{{ route('admin.penalties.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
