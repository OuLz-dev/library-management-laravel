@extends('layouts.app')

@section('title', 'Kembalikan Buku')

@section('content')
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-undo me-2"></i> Form Pengembalian Buku</h5>
        <a href="{{ route('admin.returns.index') }}" class="btn btn-light btn-sm">
            <i class="fas fa-history me-1"></i> Riwayat Pengembalian
        </a>
    </div>
    <div class="card-body">
        @if($loans->isEmpty())
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p class="text-muted mb-1">Tidak ada peminjaman aktif.</p>
                <p class="text-muted small">Semua buku sudah dikembalikan.</p>
                <a href="{{ route('admin.returns.index') }}" class="btn btn-success">
                    <i class="fas fa-history me-1"></i> Lihat Riwayat Pengembalian
                </a>
            </div>
        @else
            @if($errors->any())
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-light border">
                <i class="fas fa-info-circle me-1 text-muted"></i>
                Pilih peminjaman yang akan dikembalikan, isi <strong>tanggal pengembalian</strong>,
                lalu klik <strong>Kembalikan Buku</strong>.
                Denda dihitung otomatis bila melewati jatuh tempo
                (Rp {{ number_format($finePerDay, 0, ',', '.') }} per hari).
            </div>

            <form action="{{ route('admin.returns.store') }}" method="POST" id="returnForm">
                @csrf
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold">Pilih Peminjaman <span class="text-danger">*</span></label>
                        <select name="loan_id" id="loan_select"
                                class="form-control @error('loan_id') is-invalid @enderror" required>
                            <option value="">-- Ketik kode, nama member, atau judul buku --</option>
                            @foreach($loans as $loan)
                                @php
                                    $lateToday = max(0, -$loan->remaining_days);
                                @endphp
                                <option value="{{ $loan->getKey() }}"
                                        {{ old('loan_id') == $loan->getKey() ? 'selected' : '' }}>
                                    {{ $loan->loan_code }} — {{ $loan->user->name ?? '-' }} — {{ $loan->book->title ?? '-' }}
                                    (Jatuh tempo {{ $loan->due_date?->format('d/m/Y') ?? '-' }}{{ $lateToday > 0 ? ', terlambat '.$lateToday.' hari' : '' }})
                                </option>
                            @endforeach
                        </select>
                        @error('loan_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">Ketik untuk mencari peminjaman aktif</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Tanggal Pengembalian <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" id="return_date"
                               class="form-control @error('return_date') is-invalid @enderror"
                               value="{{ old('return_date', date('Y-m-d')) }}"
                               max="{{ date('Y-m-d') }}" required>
                        @error('return_date')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">Tidak boleh melebihi hari ini atau sebelum tanggal pinjam</small>
                    </div>
                </div>

                {{-- Ringkasan peminjaman terpilih, diperbarui lewat JavaScript. --}}
                <div id="loan_summary" class="alert alert-light border d-none">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Member</small>
                            <span id="summary_member" class="fw-semibold">-</span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Buku</small>
                            <span id="summary_book" class="fw-semibold">-</span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Tanggal Pinjam</small>
                            <span id="summary_borrow">-</span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Jatuh Tempo</small>
                            <span id="summary_due">-</span>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex flex-wrap gap-4">
                        <div>
                            <small class="text-muted d-block">Keterlambatan</small>
                            <span id="summary_late" class="fw-semibold">-</span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Estimasi Denda</small>
                            <span id="summary_fine" class="fw-semibold">-</span>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-undo me-2"></i> Kembalikan Buku
                    </button>
                    <a href="{{ route('admin.returns.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i> Batal
                    </a>
                </div>
            </form>
        @endif
    </div>
</div>

@if($loans->isNotEmpty())
    @push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var loans = @json($loanData);
            var finePerDay = {{ $finePerDay }};
            var loanSelect = document.getElementById('loan_select');
            var returnDateInput = document.getElementById('return_date');
            var summary = document.getElementById('loan_summary');

            function formatRupiah(value) {
                return 'Rp ' + Number(value).toLocaleString('id-ID');
            }

            function renderSummary() {
                var data = loans[loanSelect.value];

                if (!data) {
                    summary.classList.add('d-none');
                    return;
                }

                summary.classList.remove('d-none');
                document.getElementById('summary_member').textContent =
                    data.member + (data.member_code ? ' (' + data.member_code + ')' : '');
                document.getElementById('summary_book').textContent = data.book;
                document.getElementById('summary_borrow').textContent = data.borrow_date || '-';
                document.getElementById('summary_due').textContent = data.due_date_label || '-';

                var lateDays = 0;
                if (returnDateInput.value && data.due_date) {
                    var due = new Date(data.due_date + 'T00:00:00');
                    var returned = new Date(returnDateInput.value + 'T00:00:00');
                    lateDays = Math.max(0, Math.round((returned - due) / 86400000));
                }

                var lateEl = document.getElementById('summary_late');
                var fineEl = document.getElementById('summary_fine');

                if (lateDays > 0) {
                    lateEl.className = 'fw-semibold text-danger';
                    lateEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + lateDays + ' hari';
                    fineEl.className = 'fw-semibold text-danger';
                    fineEl.textContent = formatRupiah(lateDays * finePerDay);
                } else {
                    lateEl.className = 'fw-semibold text-success';
                    lateEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>Tepat waktu';
                    fineEl.className = 'fw-semibold text-success';
                    fineEl.textContent = 'Tidak ada denda';
                }
            }

            new TomSelect('#loan_select', {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: '-- Ketik kode, nama member, atau judul buku --',
            });

            loanSelect.addEventListener('change', renderSummary);
            returnDateInput.addEventListener('change', renderSummary);
            returnDateInput.addEventListener('input', renderSummary);
            renderSummary();
        });
    </script>
    @endpush
@endif
@endsection
