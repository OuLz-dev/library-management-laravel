@extends('layouts.app')

@section('title', 'Riwayat Peminjaman')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5>Riwayat Peminjaman</h5>
    </div>
    <div class="card-body">
        @if($borrows->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($borrows as $borrow)
                        <tr>
                            <td>{{ $borrow->loan_code }}</td>
                            <td>{{ $borrow->book->title ?? '-' }}</td>
                            <td>{{ $borrow->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $borrow->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @if($borrow->status == 'borrowed')
                                    <span class="badge bg-warning">Dipinjam</span>
                                @else
                                    <span class="badge bg-success">Dikembalikan</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $borrows->links() }}
        @else
            <p class="text-muted">Belum ada riwayat peminjaman</p>
        @endif
    </div>
</div>
@endsection