@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h3 class="mb-4">Dashboard Admin</h3>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5>Total Buku</h5>
                <h2 class="mb-0">{{ $totalBooks ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5>Total Member</h5>
                <h2 class="mb-0">{{ $totalMembers ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5>Dipinjam</h5>
                <h2 class="mb-0">{{ $activeBorrows ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5>Total Denda</h5>
                <h2 class="mb-0">Rp {{ number_format($totalPenalties ?? 0, 0, ',', '.') }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Peminjaman Terbaru</h5>
    </div>
    <div class="card-body">
        @if(isset($recentLoans) && $recentLoans->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Member</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Satu baris = satu transaksi (kode); bukunya bisa 1-3. --}}
                        @foreach($recentLoans as $rows)
                        @php
                            $first = $rows->first();
                            $totalBooks = $rows->count();
                            $returnedCount = $rows->where('status', 'returned')->count();
                        @endphp
                        <tr>
                            <td>{{ $first->loan_code }}</td>
                            <td>{{ $first->user->name ?? '-' }}</td>
                            <td>
                                @foreach($rows as $row)
                                    <div class="{{ $row->status === 'returned' ? 'text-muted' : '' }}">
                                        @if($row->status === 'returned')
                                            <i class="fas fa-check text-success"></i>
                                        @else
                                            <i class="fas fa-book-open text-primary"></i>
                                        @endif
                                        {{ $row->book->title ?? '-' }}
                                    </div>
                                @endforeach
                                @if($totalBooks > 1)
                                    <span class="badge bg-light text-dark border">{{ $totalBooks }} buku</span>
                                @endif
                            </td>
                            <td>{{ $first->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @if($returnedCount === $totalBooks)
                                    <span class="badge bg-success">Dikembalikan</span>
                                @elseif($returnedCount > 0)
                                    <span class="badge bg-info">Sebagian ({{ $returnedCount }}/{{ $totalBooks }})</span>
                                @else
                                    <span class="badge bg-warning">Dipinjam</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">Belum ada peminjaman</p>
        @endif
    </div>
</div>
@endsection