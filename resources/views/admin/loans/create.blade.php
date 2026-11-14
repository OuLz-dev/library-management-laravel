@extends('layouts.app')

@section('title', 'Pinjam Buku')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i> Form Peminjaman Buku</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('admin.loans.store') }}" method="POST" id="borrowForm">
            @csrf
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold">Cari Member <span class="text-danger">*</span></label>
                    <select name="member_id" id="member_select" class="form-control @error('member_id') is-invalid @enderror" required>
                        <option value="">-- Ketik nama, kode, email, atau no. HP --</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }} ({{ $member->member_code }}) - {{ $member->email }}{{ $member->phone ? ' - '.$member->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('member_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">Ketik untuk mencari member</small>
                </div>

                {{--  3 SLOT BUKU: slot 1 wajib, slot 2-3 opsional. Semua slot berbagi
                      SATU kode transaksi. Buku tidak boleh sama antar slot
                      (dicegah di JS dan divalidasi server via rule distinct). --}}
                @php
                    $oldBookIds = old('book_ids', []);
                @endphp
                @foreach(range(1, $maxBooksPerTransaction) as $slot)
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold">
                            Buku - Slot {{ $slot }}
                            @if($slot === 1)
                                <span class="text-danger">*</span>
                            @else
                                <span class="text-muted small">(opsional)</span>
                            @endif
                        </label>
                        <select name="book_ids[]" id="book_select_{{ $slot }}"
                                class="form-control @error('book_ids.*') is-invalid @enderror"
                                {{ $slot === 1 ? 'required' : '' }}>
                            <option value="">-- Ketik judul atau kategori buku --</option>
                            @foreach($books as $book)
                                <option value="{{ $book->getKey() }}"
                                        {{ (int) ($oldBookIds[$slot - 1] ?? 0) === (int) $book->getKey() ? 'selected' : '' }}>
                                    {{ $book->title }} (Stok: {{ $book->available_stock }}) - {{ $book->category->name ?? 'Tanpa Kategori' }}
                                </option>
                            @endforeach
                        </select>
                        @if($slot === 1)
                            @error('book_ids')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        @endif
                        <small class="text-muted">
                            @if($slot === 1)
                                Slot ini wajib diisi minimal 1 buku
                            @else
                                Biarkan kosong bila hanya meminjam {{ $slot - 1 }} buku
                            @endif
                        </small>
                    </div>
                @endforeach

                <div class="col-12 mb-3">
                    <small class="text-muted" id="book_count">Total buku tersedia: {{ $books->count() }} — buku dipilih: 0</small>
                    @error('book_ids.*')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Bagian 4: tanggal pinjam = HARI INI saja (readonly, divalidasi server). --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Tanggal Pinjam <span class="text-danger">*</span></label>
                    <input type="date" name="borrow_date" class="form-control @error('borrow_date') is-invalid @enderror"
                           value="{{ old('borrow_date', date('Y-m-d')) }}" readonly>
                    <small class="text-muted">Peminjaman dicatat hari ini (tidak bisa diubah)</small>
                    @error('borrow_date')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Bagian 4: jatuh tempo mengikuti tanggal pinjam, default +7 hari,
                     rentang valid +1 s/d +{{ $maxLoanDays }} hari (dijaga min/max + server). --}}
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', date('Y-m-d', strtotime('+7 days'))) }}"
                           min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                           max="{{ date('Y-m-d', strtotime('+'.$maxLoanDays.' days')) }}" required>
                    @error('due_date')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">Masa pinjam maksimal {{ $maxLoanDays }} hari sejak tanggal pinjam</small>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Catatan:</strong> Satu transaksi bisa memuat <strong>1-{{ $maxBooksPerTransaction }} buku</strong>
                (slot 1 wajib, sisanya opsional) dengan satu kode transaksi.
                Member maksimal memegang {{ config('perpustakaan.max_active_borrows') }} buku aktif
                dan tidak boleh memiliki denda.
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Pinjam
                </button>
                <a href="{{ route('admin.loans.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new TomSelect('#member_select', {
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: '-- Ketik nama, kode, email, atau no. HP --',
        });

        var slotIds = [];
        for (var i = 1; i <= {{ $maxBooksPerTransaction }}; i++) {
            slotIds.push('book_select_' + i);
        }

        var bookInstances = [];
        var lastChanged = null;

        function chosenValues() {
            return bookInstances
                .map(function (ts) { return ts.getValue(); })
                .filter(Boolean);
        }

        function updateCounter() {
            var chosen = chosenValues();
            var el = document.getElementById('book_count');
            if (el) {
                el.textContent = 'Total buku tersedia: {{ $books->count() }} — buku dipilih: ' + chosen.length;
            }
        }

        function guardDuplicate() {
            var values = chosenValues();
            var seen = {};
            var duplicate = null;

            values.forEach(function (v) {
                if (seen[v]) { duplicate = v; } else { seen[v] = true; }
            });

            // Slot yang BARUS diubah jadi duplikat dikosongkan kembali.
            if (duplicate !== null && lastChanged && lastChanged.getValue() === duplicate) {
                alert('Buku ini sudah dipilih di slot lain. Pilih buku yang berbeda untuk tiap slot.');
                lastChanged.setValue('', true); // silent: tidak memicu onChange lagi
            }

            updateCounter();
        }

        slotIds.forEach(function (id) {
            var ts = new TomSelect('#' + id, {
                create: false,
                sortField: { field: 'text', direction: 'asc' },
                placeholder: '-- Ketik judul atau kategori buku --',
                onChange: function () { lastChanged = ts; guardDuplicate(); }
            });
            bookInstances.push(ts);
        });

        // Bagian 4: jatuh tempo mengikuti tanggal pinjam.
        // Tanggal pinjam terkunci hari ini, jadi cukup dihitung dari "sekarang":
        // default +7 hari, rentang valid +1 .. +{{ $maxLoanDays }} hari.
        var defaultDueDays = {{ $defaultLoanDays }};
        var dueInput = document.getElementById('due_date');

        function formatDate(d) {
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        }

        document.getElementById('borrowForm').addEventListener('reset', function () {
            var d = new Date();
            d.setDate(d.getDate() + defaultDueDays);
            dueInput.value = formatDate(d);
        });

        updateCounter();
    });
</script>
@endpush
@endsection
