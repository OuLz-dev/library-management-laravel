@extends('layouts.app')

@section('title', 'Proses Pengembalian')

@section('content')
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-undo me-2"></i> Proses Pengembalian — {{ $loan->loan_code }}</h5>
        <a href="{{ route('admin.returns.index') }}" class="btn btn-light btn-sm">Batal</a>
    </div>
    <div class="card-body">
        <table class="table table-bordered align-middle mb-4">
            <tr>
                <th class="table-light" style="width: 30%">Member</th>
                <td>
                    {{ $loan->user->name ?? '-' }}
                    <span class="badge bg-secondary ms-1">{{ $loan->user->member_code ?? '-' }}</span>
                    <div class="small text-muted">{{ $loan->user->email ?? '' }}</div>
                </td>
            </tr>
            <tr>
                <th class="table-light">Buku</th>
                <td>{{ $loan->book->title ?? '-' }}</td>
            </tr>
            <tr>
                <th class="table-light">Tanggal Pinjam</th>
                <td>{{ $loan->borrow_date?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            <tr>
                <th class="table-light">Jatuh Tempo</th>
                <td>{{ $loan->due_date?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            <tr>
                <th class="table-light">Sisa Hari (hari ini)</th>
                <td>
                    @if($loan->remaining_days < 0)
                        <span class="text-danger fw-bold">Telat {{ abs($loan->remaining_days) }} hari</span>
                    @elseif($loan->remaining_days == 0)
                        <span class="text-warning fw-bold">Jatuh tempo hari ini</span>
                    @else
                        <span class="text-success">{{ $loan->remaining_days }} hari</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th class="table-light">Estimasi Denda (berdasar tanggal di bawah)</th>
                <td><span id="fine-preview" class="fw-bold">Rp 0</span></td>
            </tr>
        </table>

        <form action="{{ route('admin.returns.process', $loan->loan_code) }}" method="POST" id="returnForm">
            @csrf
            {{-- Bagian 3: tanggal pengembalian bisa diisi manual — default hari ini.
                 Rentang valid: dari tanggal pinjam sampai hari ini (tidak boleh masa depan). --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Tanggal Pengembalian <span class="text-danger">*</span></label>
                    <input type="date" name="return_date" id="return_date"
                           class="form-control @error('return_date') is-invalid @enderror"
                           value="{{ old('return_date', date('Y-m-d')) }}"
                           min="{{ $loan->borrow_date?->format('Y-m-d') }}"
                           max="{{ date('Y-m-d') }}" required>
                    @error('return_date')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">
                        Default hari ini; bisa mundur untuk pencatatan terlambat. Denda Rp {{ number_format(config('perpustakaan.fine_per_day'), 0, ',', '.') }}/hari bila lewat jatuh tempo.
                    </small>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-success" onclick="return confirm('Kembalikan buku dengan kode {{ $loan->loan_code }}?')">
                    <i class="fas fa-check me-2"></i> Proses Pengembalian
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Pratinjau denda berdasarkan tanggal pengembalian yang diketik.
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('return_date');
        var preview = document.getElementById('fine-preview');
        var dueDate = new Date('{{ $loan->due_date?->format('Y-m-d') }}');
        var finePerDay = {{ (int) config('perpustakaan.fine_per_day') }};

        function refreshPreview() {
            if (!input.value) { return; }
            var parts = input.value.split('-');
            var returnDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            var diffDays = Math.round((returnDate - dueDate) / 86400000);
            var fine = diffDays > 0 ? diffDays * finePerDay : 0;
            preview.textContent = 'Rp ' + fine.toLocaleString('id-ID');
            preview.className = diffDays > 0 ? 'fw-bold text-danger' : 'fw-bold text-success';
        }

        input.addEventListener('change', refreshPreview);
        refreshPreview();
    });
</script>
@endpush
@endsection
