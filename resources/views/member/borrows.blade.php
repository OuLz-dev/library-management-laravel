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
                            <th>Terlambat</th>
                            <th>Denda / Estimasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Satu baris = satu transaksi (kode); bukunya bisa 1-3 dan
                              tiap buku punya status/denda sendiri (parsial). --}}
                        @foreach($borrows as $rows)
                        @php
                            $code = $rows->first()->loan_code;
                            $first = $rows->first();
                            $totalBooks = $rows->count();
                            $returnedCount = $rows->where('status', 'returned')->count();
                            $isOverdue = $first->due_date && $first->due_date->isPast();

                            // Terlambat terparah di transaksi ini (berjalan atau tercatat).
                            $maxLate = $rows->max(fn ($r) => $r->late_days);
                            // Denda tercatat (buku kembali telat) + denda berjalan (buku belum kembali).
                            $recordedFine = $rows->sum(fn ($r) => (float) ($r->penalty?->fine_amount ?? 0));
                            $runningFine = $rows->where('status', 'borrowed')
                                ->sum(fn ($r) => $r->late_days * $finePerDay);
                            $totalFine = $recordedFine + $runningFine;
                        @endphp
                        <tr>
                            <td>
                                <span class="badge bg-secondary">{{ $code }}</span>
                                @if($totalBooks > 1)
                                    <div class="small text-muted mt-1">{{ $totalBooks }} buku</div>
                                @endif
                            </td>
                            <td>
                                @foreach($rows as $row)
                                    <div class="d-flex align-items-center gap-1">
                                        @if($row->status === 'returned')
                                            <i class="fas fa-check text-success" title="Sudah dikembalikan"></i>
                                        @else
                                            <i class="fas fa-book-open text-primary" title="Masih dipinjam"></i>
                                        @endif
                                        <span class="{{ $row->status === 'returned' ? 'text-muted' : '' }}">
                                            {{ $row->book->title ?? '-' }}
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                            <td>{{ $first->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="{{ $isOverdue && $returnedCount < $totalBooks ? 'text-danger fw-bold' : '' }}">
                                {{ $first->due_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td>
                                @if($maxLate > 0)
                                    <span class="text-danger fw-bold">{{ $maxLate }} hari</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($totalFine > 0)
                                    <span class="text-danger fw-bold">
                                        Rp {{ number_format($totalFine, 0, ',', '.') }}
                                    </span>
                                    @if($runningFine > 0)
                                        <div class="small text-muted">
                                            termasuk berjalan Rp {{ number_format($runningFine, 0, ',', '.') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
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
            {{ $borrows->appends(request()->query())->links('pagination::bootstrap-5') }}
        @else
            <p class="text-muted">Belum ada riwayat peminjaman</p>
        @endif
    </div>
</div>
@endsection
