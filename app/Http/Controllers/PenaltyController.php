<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenaltyController extends Controller
{
    // ========== INDEX: TAMPILKAN SEMUA DENDA (DENGAN SEARCH & FILTER) ==========
    public function index(Request $request)
    {
        // Statistik atas tetap global (seluruh DB), tidak ikut filter
        $totalUnpaid = Penalty::where('status', 'unpaid')->sum('fine_amount');
        $totalPaid = Penalty::where('status', 'paid')->sum('fine_amount');

        $search = trim((string) $request->input('search'));
        $finePerDay = (int) config('perpustakaan.fine_per_day');

        // Denda berjalan: pinjaman yang masih dipinjam dan sudah melewati
        // jatuh tempo. Nilainya TIDAK disimpan di tabel penalties — dihitung
        // real-time agar admin bisa melihat potensi denda sebelum buku
        // dikembalikan (record final tetap dibuat oleh alur pengembalian).
        $totalRunning = Loan::where('status', 'borrowed')
            ->whereDate('due_date', '<', today())
            ->get()
            ->sum(fn (Loan $loan) => $loan->late_days * $finePerDay);

        $runningQuery = Loan::with(['user', 'book'])
            ->where('status', 'borrowed')
            ->whereDate('due_date', '<', today());

        // Search yang sama juga berlaku untuk daftar denda berjalan.
        if ($search !== '') {
            $runningQuery->where(function ($q) use ($search) {
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

        // Saat admin memfilter "Lunas", daftar denda berjalan disembunyikan
        // (kartu statistik global tetap ditampilkan).
        $runningLoans = $request->input('status') === 'paid'
            ? collect()
            : $runningQuery->orderBy('due_date')->orderBy('loans_id')->get();

        $filteredRunningTotal = $runningLoans->sum(fn (Loan $loan) => $loan->late_days * $finePerDay);

        // ---- Denda tercatat: satu baris tabel = satu transaksi (loan_code) ----
        // Record denda tetap per buku. Pengelompokan hanya untuk tampilan dan
        // pagination, bukan mengubah cara pembayaran (tetap per record).
        $status = $request->input('status');
        $status = in_array($status, ['paid', 'unpaid'], true) ? $status : null;

        $base = Penalty::query()
            ->join('loans', 'loans.loans_id', '=', 'penalties.loan_id');

        // Filter berdasarkan status denda (record, bukan transaksi).
        if ($status !== null) {
            $base->where('penalties.status', $status);
        }

        // Search: kode peminjaman, nama/email/no. HP member, judul buku.
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('loans.loan_code', 'like', '%'.$search.'%')
                    ->orWhereHas('loan.book', function ($bq) use ($search) {
                        $bq->where('title', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('loan.user', function ($uq) use ($search) {
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

        // Total hasil filter (seluruh halaman) untuk footer tabel.
        $filteredTotal = (clone $base)->sum('penalties.fine_amount');

        // Pagination per TRANSAKSI: hitung kode unik, bukan jumlah record denda.
        $penalties = (clone $base)
            ->select('loans.loan_code')
            ->selectRaw('MAX(penalties.created_at) as last_penalty_at')
            ->groupBy('loans.loan_code')
            ->orderByDesc('last_penalty_at')
            ->orderByDesc('loans.loan_code')
            ->paginate(10)
            ->appends($request->query());

        // Muat SEMUA buku dalam transaksi di halaman ini — termasuk yang tidak
        // kena denda — supaya konteks transaksi tetap terlihat (pola yang sama
        // dengan halaman Peminjaman & Riwayat Pengembalian).
        $transactions = Loan::with(['user', 'book', 'penalty'])
            ->whereIn('loan_code', $penalties->pluck('loan_code'))
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        return view('admin.penalties.index', compact(
            'penalties',
            'transactions',
            'totalUnpaid',
            'totalPaid',
            'filteredTotal',
            'runningLoans',
            'totalRunning',
            'filteredRunningTotal',
            'finePerDay'
        ));
    }

    // ========== SHOW PAY FORM: TAMPILKAN FORM BAYAR DENDA ==========
    public function showPayForm(Penalty $penalty)
    {
        // Cek apakah denda sudah lunas
        if ($penalty->status === 'paid') {
            return redirect()->route('admin.penalties.index')
                ->with('error', 'Denda ini sudah dibayar!');
        }

        return view('admin.penalties.pay', compact('penalty'));
    }

    // ========== PAY PENALTY: PROSES BAYAR DENDA ==========
    public function payPenalty(Request $request, Penalty $penalty)
    {
        // Cek apakah denda sudah lunas
        if ($penalty->status === 'paid') {
            return redirect()->route('admin.penalties.index')
                ->with('error', 'Denda ini sudah dibayar!');
        }

        DB::beginTransaction();
        try {
            // Update status denda
            $penalty->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);

            DB::commit();

            return redirect()->route('admin.penalties.index')
                ->with('success', 'Denda berhasil dibayar!');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->route('admin.penalties.index')
                ->with('error', 'Gagal membayar denda: '.$e->getMessage());
        }
    }
}
