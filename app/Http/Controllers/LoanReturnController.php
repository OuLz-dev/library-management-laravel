<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanReturn;
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
     * Alur:
     *   1. index()  — riwayat pengembalian; pencarian HANYA untuk riwayat
     *   2. create() — form pengembalian berisi pilihan peminjaman aktif
     *   3. store()  — validasi tanggal, update status, kembalikan stok,
     *                 buat denda bila telat
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        // Riwayat pengembalian: hanya transaksi yang SUDAH dikembalikan.
        // Pencarian di halaman ini tidak menyentuh peminjaman aktif.
        $query = LoanReturn::with(['loan.user', 'loan.book', 'loan.penalty', 'admin'])
            ->whereHas('loan', fn ($q) => $q->where('status', 'returned'));

        // Pencarian riwayat: kode peminjaman, data member, atau judul buku.
        if ($search !== '') {
            $query->whereHas('loan', function ($q) use ($search) {
                $q->where('loan_code', 'like', '%'.$search.'%')
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

        $returns = $query->orderByDesc('return_date')
            ->orderByDesc('returns_id')
            ->paginate(10)
            ->appends($request->query());

        return view('admin.returns.index', compact('returns'));
    }

    /**
     * Form aksi pengembalian: admin memilih satu peminjaman aktif, mengisi
     * tanggal kembali, lalu sistem menghitung keterlambatan/denda.
     */
    public function create()
    {
        $loans = Loan::with(['user', 'book', 'penalty'])
            ->where('status', 'borrowed')
            ->orderBy('due_date')
            ->get();

        // Ringkasan per peminjaman untuk panel detail di form (diperbarui
        // lewat JavaScript saat admin memilih peminjaman).
        $loanData = $loans->mapWithKeys(function (Loan $loan) {
            return [$loan->getKey() => [
                'member' => $loan->user->name ?? '-',
                'member_code' => $loan->user->member_code,
                'book' => $loan->book->title ?? '-',
                'borrow_date' => $loan->borrow_date?->format('d/m/Y'),
                'due_date' => $loan->due_date?->toDateString(),
                'due_date_label' => $loan->due_date?->format('d/m/Y'),
                'remaining_days' => $loan->remaining_days,
            ]];
        });

        return view('admin.returns.create', [
            'loans' => $loans,
            'loanData' => $loanData,
            'finePerDay' => (int) config('perpustakaan.fine_per_day'),
        ]);
    }

    /**
     * Proses pengembalian dari form aksi.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer'],
            'return_date' => [
                'required', 'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->gt(today())) {
                        $fail('Tanggal pengembalian tidak boleh di masa depan.');
                    }
                },
                function ($attribute, $value, $fail) use ($request) {
                    $borrowDate = Loan::whereKey($request->input('loan_id'))->value('borrow_date');

                    if ($borrowDate && Carbon::parse($value)->lt(Carbon::parse($borrowDate))) {
                        $fail('Tanggal pengembalian tidak boleh sebelum tanggal pinjam.');
                    }
                },
            ],
        ], [
            'loan_id.required' => 'Pilih peminjaman yang akan dikembalikan.',
            'loan_id.integer' => 'Peminjaman yang dipilih tidak valid.',
        ]);

        $loan = Loan::with('book')->find($validated['loan_id']);

        if (! $loan) {
            return back()
                ->withErrors(['loan_id' => 'Peminjaman tidak ditemukan'])
                ->withInput();
        }

        // Guard dobel: lewat status loans DAN keberadaan baris returns
        // (kolom loan_id UNIQUE di tabel returns juga menolak dobel di DB).
        if ($loan->status === 'returned' || $loan->loanReturn()->exists()) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Buku untuk kode ini sudah dikembalikan');
        }

        DB::beginTransaction();
        try {
            $returnDate = Carbon::parse($validated['return_date'])->startOfDay();
            $dueDate = Carbon::parse($loan->due_date)->startOfDay();

            // Hitung hari keterlambatan (jika ada)
            $daysLate = 0;
            $fineAmount = 0;

            if ($returnDate->gt($dueDate)) {
                $daysLate = $returnDate->diffInDays($dueDate);
                $fineAmount = $daysLate * config('perpustakaan.fine_per_day');
            }

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

            // HANYA BUAT PENALTY JIKA TERLAMBAT (daysLate > 0)
            if ($daysLate > 0 && $fineAmount > 0) {
                Penalty::create([
                    'loan_id' => $loan->getKey(),
                    'member_id' => $loan->member_id,
                    'days_late' => $daysLate,
                    'fine_amount' => $fineAmount,
                    'status' => 'unpaid',
                    'paid_date' => null,
                ]);
            }

            DB::commit();

            // Buat pesan sukses
            $message = 'Buku berhasil dikembalikan';
            if ($daysLate > 0) {
                $message .= ". Terlambat $daysLate hari, denda Rp ".number_format($fineAmount, 0, ',', '.');
            } else {
                $message .= ' tepat waktu!';
            }

            return redirect()->route('admin.returns.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Gagal memproses pengembalian: '.$e->getMessage());
        }
    }
}
