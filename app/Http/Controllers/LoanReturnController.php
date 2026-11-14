<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Penalty;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanReturnController extends Controller
{
    /**
     * Bagian 3: satu halaman riwayat + satu form aksi "Kembalikan Buku".
     *
     * Alur (transaksi multi-buku + pengembalian parsial):
     *   1. index()  — riwayat pengembalian PER TRANSAKSI (satu baris = satu
     *                loan_code; buku yang belum kembali tetap ditampilkan);
     *                pencarian HANYA untuk riwayat
     *   2. create() — form pengembalian berbasis KODE TRANSAKSI: admin memilih
     *                kode, lalu mencentang buku mana saja yang dikembalikan
     *                (buku yang sudah pernah kembali tampil terkunci)
     *   3. store()  — validasi tanggal, update status per buku, kembalikan
     *                stok, buat denda per buku yang telat
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        // Riwayat pengembalian dikelompokkan PER TRANSAKSI (satu baris tabel =
        // satu loan_code), bukan per buku. Transaksi muncul begitu ada minimal
        // satu buku yang sudah kembali; sisa buku yang belum kembali tetap
        // ditampilkan di baris yang sama (opsi A).
        //
        // Pagination dihitung per transaksi: join ke returns, group by
        // loan_code, urut berdasarkan tanggal pengembalian terakhir.
        // Pencarian di halaman ini tidak menyentuh peminjaman aktif.
        $query = Loan::query()
            ->select('loans.loan_code')
            ->selectRaw('MAX(returns.return_date) as last_return_date')
            ->join('returns', 'returns.loan_id', '=', 'loans.loans_id')
            ->where('loans.status', 'returned')
            ->groupBy('loans.loan_code');

        // Pencarian riwayat: kode peminjaman, data member, atau judul buku.
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('loans.loan_code', 'like', '%'.$search.'%')
                    ->orWhereHas('book', function ($bq) use ($search) {
                        $bq->where('title', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('role', 'member')
                            ->where(function ($m) use ($search) {
                                $m->where('member_code', 'like', '%'.$search.'%')
                                    ->orWhere('name', 'like', '%'.$search.'%')
                                    ->orWhere('email', 'like', '%'.$search.'%')
                                    ->orWhere('phone', 'like', '%'.$search.'%');
                            });
                    });
            });
        }

        // Satu halaman = 10 transaksi (bukan 10 buku).
        $returns = $query->orderByDesc('last_return_date')
            ->orderByDesc('loans.loan_code')
            ->paginate(10)
            ->appends($request->query());

        // Muat SEMUA baris (buku) milik kode transaksi di halaman ini —
        // termasuk yang belum kembali — supaya tabel bisa menampilkan kondisi
        // lengkap satu transaksi dalam satu baris.
        $transactions = Loan::with([
            'user',
            'book',
            'penalty',
            'loanReturn.admin',
        ])
            ->whereIn('loan_code', $returns->pluck('loan_code'))
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        return view('admin.returns.index', compact('returns', 'transactions'));
    }

    /**
     * Form aksi pengembalian: admin memilih KODE TRANSAKSI aktif, sistem
     * menampilkan semua buku transaksi itu, lalu admin mencentang buku yang
     * dibawa member hari ini (pengembalian parsial dimungkinkan).
     */
    public function create()
    {
        // Transaksi aktif = kode yang masih punya minimal satu baris borrowed.
        $activeCodes = Loan::where('status', 'borrowed')
            ->orderBy('due_date')
            ->pluck('loan_code')
            ->unique()
            ->values();

        // Muat SEMUA baris (termasuk buku yang sudah dikembalikan parsial)
        // supaya form menampilkan kondisi lengkap transaksi.
        $transactions = Loan::with(['user', 'book', 'loanReturn'])
            ->whereIn('loan_code', $activeCodes)
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        // Ringkasan per transaksi untuk panel detail di form (diperbarui
        // lewat JavaScript saat admin memilih kode).
        $transactionData = $transactions->mapWithKeys(function ($rows, $code) {
            $first = $rows->first();
            $remaining = $rows->where('status', 'borrowed')->count();

            return [$code => [
                'member' => $first->user->name ?? '-',
                'member_code' => $first->user->member_code,
                'borrow_date' => $first->borrow_date?->format('d/m/Y'),
                'due_date' => $first->due_date?->toDateString(),
                'due_date_label' => $first->due_date?->format('d/m/Y'),
                'remaining' => $remaining,
                'books' => $rows->map(fn (Loan $row) => [
                    'id' => $row->getKey(),
                    'title' => $row->book->title ?? '-',
                    'status' => $row->status,
                    'return_date' => $row->loanReturn?->return_date?->format('d/m/Y'),
                ])->values()->all(),
            ]];
        });

        return view('admin.returns.create', [
            'transactions' => $transactions,
            'transactionData' => $transactionData,
            'finePerDay' => (int) config('perpustakaan.fine_per_day'),
        ]);
    }

    /**
     * Proses pengembalian (bisa parsial) dari form aksi berbasis kode transaksi.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_code' => ['required', 'string'],
            'loan_ids' => ['required', 'array', 'min:1'],
            'loan_ids.*' => ['integer', 'distinct'],
            'return_date' => [
                'required', 'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->gt(today())) {
                        $fail('Tanggal pengembalian tidak boleh di masa depan.');
                    }
                },
                function ($attribute, $value, $fail) use ($request) {
                    $borrowDate = Loan::forCode((string) $request->input('loan_code'))
                        ->value('borrow_date');

                    if ($borrowDate && Carbon::parse($value)->lt(Carbon::parse($borrowDate))) {
                        $fail('Tanggal pengembalian tidak boleh sebelum tanggal pinjam.');
                    }
                },
            ],
        ], [
            'loan_code.required' => 'Pilih kode transaksi yang akan dikembalikan.',
            'loan_ids.required' => 'Centang minimal satu buku yang dikembalikan.',
            'loan_ids.min' => 'Centang minimal satu buku yang dikembalikan.',
            'loan_ids.*.distinct' => 'Ada buku yang dipilih lebih dari sekali.',
        ]);

        // Semua baris milik kode transaksi ini (untuk guard kepemilikan).
        $transactionLoans = Loan::with('book')
            ->forCode($validated['loan_code'])
            ->orderBy('loans_id')
            ->get();

        if ($transactionLoans->isEmpty()) {
            return back()
                ->withErrors(['loan_code' => 'Kode transaksi tidak ditemukan'])
                ->withInput();
        }

        // Hanya baris milik kode ini yang boleh diproses.
        $selected = $transactionLoans->whereIn('loans_id', $validated['loan_ids']);

        if ($selected->count() !== count($validated['loan_ids'])) {
            return back()
                ->withErrors(['loan_ids' => 'Ada buku yang tidak termasuk dalam transaksi '.$validated['loan_code']])
                ->withInput();
        }

        // Guard dobel: lewat status loans DAN keberadaan baris returns
        // (kolom loan_id UNIQUE di tabel returns juga menolak dobel di DB).
        foreach ($selected as $loan) {
            if ($loan->status === 'returned' || $loan->loanReturn()->exists()) {
                return redirect()->route('admin.returns.index')
                    ->with('error', 'Buku "'.$loan->book->title.'" untuk kode ini sudah dikembalikan');
            }
        }

        DB::beginTransaction();
        try {
            $returnDate = Carbon::parse($validated['return_date'])->startOfDay();
            $dueDate = Carbon::parse($transactionLoans->first()->due_date)->startOfDay();

            // Hitung hari keterlambatan (jika ada) — jatuh tempo transaksi
            // berlaku untuk semua bukunya.
            $daysLate = 0;
            $finePerBook = 0;

            if ($returnDate->gt($dueDate)) {
                $daysLate = $returnDate->diffInDays($dueDate);
                $finePerBook = $daysLate * config('perpustakaan.fine_per_day');
            }

            // Proses PER BUKU yang dicentang (pengembalian parsial): status,
            // data returns, stok, dan denda masing-masing tercatat sendiri.
            foreach ($selected as $loan) {
                // Status tetap disimpan di loans (keputusan desain), sedangkan
                // data pengembalian masuk tabel returns terpisah.
                $loan->update([
                    'status' => 'returned',
                ]);

                // Data pengembalian tercatat di tabel returns, berikut admin
                // yang memprosesnya (kolom processed_by).
                $loan->loanReturn()->create([
                    'processed_by' => Auth::id(),
                    'return_date' => $returnDate,
                ]);

                // Kembalikan stok buku
                $loan->book->increaseStock();

                // DENDA PER BUKU (keputusan user): hanya buku yang dikembalikan
                // melewati jatuh tempo yang didenda — buku yang tepat waktu
                // tidak terdampak buku lain yang telat dalam transaksi sama.
                if ($daysLate > 0 && $finePerBook > 0) {
                    Penalty::create([
                        'loan_id' => $loan->getKey(),
                        'member_id' => $loan->member_id,
                        'days_late' => $daysLate,
                        'fine_amount' => $finePerBook,
                        'status' => 'unpaid',
                        'paid_date' => null,
                    ]);
                }
            }

            DB::commit();

            // Sisa buku pada transaksi ini setelah pengembalian parsial.
            $remaining = Loan::forCode($validated['loan_code'])
                ->where('status', 'borrowed')
                ->count();

            // Buat pesan sukses
            $returnedCount = $selected->count();
            $message = $returnedCount.' buku berhasil dikembalikan';
            if ($daysLate > 0) {
                $message .= '. Terlambat '.$daysLate.' hari — denda Rp '
                    .number_format($finePerBook, 0, ',', '.')
                    .' per buku (total Rp '.number_format($finePerBook * $returnedCount, 0, ',', '.').')';
            } else {
                $message .= ' tepat waktu!';
            }

            if ($remaining > 0) {
                $message .= ' Sisa '.$remaining.' buku masih dipinjam di transaksi '.$validated['loan_code'].'.';
            } else {
                $message .= ' Transaksi '.$validated['loan_code'].' selesai — semua buku sudah kembali.';
            }

            return redirect()->route('admin.returns.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Gagal memproses pengembalian: '.$e->getMessage());
        }
    }
}
