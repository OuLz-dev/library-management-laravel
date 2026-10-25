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
     * Bagian 3: pengembalian punya halaman sendiri, berbasis kode peminjaman.
     *
     * Alur:
     *   1. index()   — satu bar pencarian + riwayat pengembalian
     *   2. search()  — validasi kode (dipakai alur lama / tautan langsung)
     *   3. show()    — detail peminjaman + form tanggal pengembalian (bisa
     *                  diedit manual), termasuk estimasi denda live
     *   4. process() — update status, kembalikan stok, buat denda bila telat
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        // Satu bar pencarian dipakai untuk dua kebutuhan:
        // - kode peminjaman aktif yang persis -> langsung ke halaman proses
        // - kata kunci lain -> filter riwayat + tampilkan peminjaman aktif
        //   yang cocok supaya bisa langsung diproses dari halaman ini.
        $activeLoans = collect();

        if ($search !== '') {
            $activeLoan = Loan::where('status', 'borrowed')
                ->where('loan_code', $search)
                ->first();

            if ($activeLoan) {
                return redirect()->route('admin.returns.show', $activeLoan->loan_code);
            }

            $activeLoans = Loan::with(['user', 'book'])
                ->where('status', 'borrowed')
                ->where(function ($q) use ($search) {
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
                })
                ->orderBy('due_date')
                ->limit(5)
                ->get();
        }

        // Riwayat pengembalian: hanya transaksi yang SUDAH dikembalikan.
        // Dipisahkan dari daftar peminjaman agar masing-masing halaman berisi
        // data yang berbeda dan tidak membingungkan.
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

        return view('admin.returns.index', compact('returns', 'activeLoans'));
    }

    public function search(Request $request)
    {
        $request->validate([
            'loan_code' => 'required|string|max:50',
        ]);

        $loan = Loan::with(['user', 'book'])
            ->where('loan_code', trim($request->loan_code))
            ->first();

        if (! $loan) {
            return back()
                ->withErrors(['loan_code' => 'Kode peminjaman tidak ditemukan'])
                ->withInput();
        }

        if ($loan->status === 'returned') {
            return back()->with('error', 'Buku untuk kode ini sudah dikembalikan');
        }

        return redirect()->route('admin.returns.show', $loan->loan_code);
    }

    public function show(string $loanCode)
    {
        $loan = Loan::with(['user', 'book', 'penalty', 'loanReturn'])
            ->where('loan_code', $loanCode)
            ->firstOrFail();

        if ($loan->status === 'returned' || $loan->loanReturn) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Buku untuk kode ini sudah dikembalikan');
        }

        return view('admin.returns.show', compact('loan'));
    }

    public function process(Request $request, string $loanCode)
    {
        $loan = Loan::with('book')
            ->where('loan_code', $loanCode)
            ->firstOrFail();

        // Guard dobel: lewat status loans DAN keberadaan baris returns
        // (kolom loan_id UNIQUE di tabel returns juga menolak dobel di DB).
        if ($loan->status === 'returned' || $loan->loanReturn()->exists()) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Buku untuk kode ini sudah dikembalikan');
        }

        // Tanggal pengembalian bisa diedit manual (Bagian 3), tapi harus
        // masuk akal: tidak boleh masa depan, tidak sebelum tanggal pinjam.
        $request->validate([
            'return_date' => [
                'required', 'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->gt(today())) {
                        $fail('Tanggal pengembalian tidak boleh di masa depan.');
                    }
                },
                function ($attribute, $value, $fail) use ($loan) {
                    if (Carbon::parse($value)->lt($loan->borrow_date)) {
                        $fail('Tanggal pengembalian tidak boleh sebelum tanggal pinjam.');
                    }
                },
            ],
        ]);

        DB::beginTransaction();
        try {
            $returnDate = Carbon::parse($request->return_date)->startOfDay();
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
