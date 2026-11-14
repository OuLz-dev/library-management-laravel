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
        @if($transactions->isEmpty())
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
                Pilih <strong>kode transaksi</strong>, centang buku yang dibawa member hari ini
                (boleh sebagian — pengembalian parsial), isi <strong>tanggal pengembalian</strong>,
                lalu klik <strong>Kembalikan Buku</strong>.
                Denda dihitung per buku yang melewati jatuh tempo
                (Rp {{ number_format($finePerDay, 0, ',', '.') }} per hari).
            </div>

            <form action="{{ route('admin.returns.store') }}" method="POST" id="returnForm">
                @csrf
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold">Kode Transaksi <span class="text-danger">*</span></label>
                        <select name="loan_code" id="code_select"
                                class="form-control @error('loan_code') is-invalid @enderror" required>
                            <option value="">-- Ketik kode transaksi atau nama member --</option>
                            @foreach($transactions as $code => $rows)
                                @php
                                    $first = $rows->first();
                                    $remaining = $rows->where('status', 'borrowed')->count();
                                @endphp
                                <option value="{{ $code }}" {{ old('loan_code') === $code ? 'selected' : '' }}>
                                    {{ $code }} — {{ $first->user->name ?? '-' }} ({{ $remaining }} buku belum kembali)
                                </option>
                            @endforeach
                        </select>
                        @error('loan_code')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">Ketik untuk mencari transaksi aktif — kode bisa di-copy dari Daftar Peminjaman</small>
                    </div>

                    {{-- Daftar buku transaksi terpilih — dirender JavaScript,
                         satu checkbox per buku yang belum dikembalikan. --}}
                    <div class="col-md-12 mb-3">
                        <div id="books_panel" class="d-none">
                            <label class="form-label fw-semibold">Buku yang Dikembalikan <span class="text-danger">*</span></label>
                            <div id="books_list"></div>
                            <small class="text-muted">Centang buku yang dibawa member; biarkan kosong yang tidak kembali</small>
                            @error('loan_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
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

                {{-- Ringkasan transaksi terpilih, diperbarui lewat JavaScript. --}}
                <div id="loan_summary" class="alert alert-light border d-none">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Member</small>
                            <span id="summary_member" class="fw-semibold">-</span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Tanggal Pinjam</small>
                            <span id="summary_borrow" class="fw-semibold">-</span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Jatuh Tempo</small>
                            <span id="summary_due" class="fw-semibold">-</span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Buku dikembalikan</small>
                            <span id="summary_count" class="fw-semibold">-</span>
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
                            <div class="small text-muted" id="summary_fine_detail"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-success" id="submit_return">
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

@if($transactions->isNotEmpty())
    @push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var transactions = @json($transactionData);
            var oldSelected = @json(collect(old('loan_ids', []))->map(fn ($id) => (int) $id)->all());
            var finePerDay = {{ $finePerDay }};

            var codeSelect = document.getElementById('code_select');
            var returnDateInput = document.getElementById('return_date');
            var booksPanel = document.getElementById('books_panel');
            var booksList = document.getElementById('books_list');
            var summary = document.getElementById('loan_summary');
            var submitBtn = document.getElementById('submit_return');

            function formatRupiah(value) {
                return 'Rp ' + Number(value).toLocaleString('id-ID');
            }

            function escapeHtml(text) {
                var el = document.createElement('span');
                el.textContent = text;
                return el.innerHTML;
            }

            function selectedCheckboxes() {
                return Array.prototype.slice.call(booksList.querySelectorAll('input[name="loan_ids[]"]'));
            }

            function selectedCount() {
                return selectedCheckboxes().filter(function (cb) { return cb.checked; }).length;
            }

            function renderBooks() {
                var data = transactions[codeSelect.value];

                if (!data) {
                    booksPanel.classList.add('d-none');
                    summary.classList.add('d-none');
                    return;
                }

                booksPanel.classList.remove('d-none');

                var html = '';
                data.books.forEach(function (book) {
                    if (book.status === 'borrowed') {
                        var checked = oldSelected.indexOf(book.id) !== -1 || oldSelected.length === 0;
                        html += '<div class="form-check border rounded p-2 mb-1">'
                            + '<input class="form-check-input" type="checkbox" name="loan_ids[]" '
                            + 'value="' + book.id + '" id="loan_' + book.id + '"' + (checked ? ' checked' : '') + '>'
                            + '<label class="form-check-label" for="loan_' + book.id + '">'
                            + '<strong>' + escapeHtml(book.title) + '</strong></label>'
                            + '</div>';
                    } else {
                        // Buku yang sudah pernah dikembalikan (parsial sebelumnya): terkunci.
                        html += '<div class="form-check border rounded p-2 mb-1 bg-light">'
                            + '<input class="form-check-input" type="checkbox" checked disabled>'
                            + '<label class="form-check-label text-muted">'
                            + '<s>' + escapeHtml(book.title) + '</s> — sudah dikembalikan'
                            + (book.return_date ? ' (' + book.return_date + ')' : '')
                            + '</label></div>';
                    }
                });

                booksList.innerHTML = html;

                selectedCheckboxes().forEach(function (cb) {
                    cb.addEventListener('change', renderSummary);
                });

                renderSummary();
            }

            function renderSummary() {
                var data = transactions[codeSelect.value];
                var count = selectedCount();

                if (!data) {
                    summary.classList.add('d-none');
                    return;
                }

                summary.classList.remove('d-none');
                document.getElementById('summary_member').textContent =
                    data.member + (data.member_code ? ' (' + data.member_code + ')' : '');
                document.getElementById('summary_borrow').textContent = data.borrow_date || '-';
                document.getElementById('summary_due').textContent = data.due_date_label || '-';
                document.getElementById('summary_count').textContent =
                    count + ' dari ' + data.remaining + ' buku yang belum kembali';

                // Tombol submit hanya aktif bila ada minimal satu buku dicentang.
                submitBtn.disabled = count === 0;

                var lateDays = 0;
                if (returnDateInput.value && data.due_date) {
                    var due = new Date(data.due_date + 'T00:00:00');
                    var returned = new Date(returnDateInput.value + 'T00:00:00');
                    lateDays = Math.max(0, Math.round((returned - due) / 86400000));
                }

                var lateEl = document.getElementById('summary_late');
                var fineEl = document.getElementById('summary_fine');
                var fineDetail = document.getElementById('summary_fine_detail');

                if (lateDays > 0) {
                    lateEl.className = 'fw-semibold text-danger';
                    lateEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + lateDays + ' hari';
                    fineEl.className = 'fw-semibold text-danger';
                    fineEl.textContent = count > 0 ? formatRupiah(lateDays * finePerDay * count) : '-';
                    fineDetail.textContent = count > 0
                        ? formatRupiah(lateDays * finePerDay) + ' per buku x ' + count + ' buku'
                        : '';
                } else {
                    lateEl.className = 'fw-semibold text-success';
                    lateEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>Tepat waktu';
                    fineEl.className = 'fw-semibold text-success';
                    fineEl.textContent = 'Tidak ada denda';
                    fineDetail.textContent = '';
                }
            }

            new TomSelect('#code_select', {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: '-- Ketik kode transaksi atau nama member --',
                onChange: function () { oldSelected = []; renderBooks(); }
            });

            codeSelect.addEventListener('change', renderBooks);
            returnDateInput.addEventListener('change', renderSummary);
            returnDateInput.addEventListener('input', renderSummary);
            renderBooks();
        });
    </script>
    @endpush
@endif
@endsection
