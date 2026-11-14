<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class MemberDashboardController extends Controller
{
    public function index()
    {
        // Pasca-merge: member = User yang login sendiri (tidak ada tabel members).
        $user = Auth::user();

        // Menghitung per BUKU (baris loans), bukan per transaksi — konsisten
        // dengan batas "maksimal 3 buku aktif".
        $activeBorrows = Loan::where('member_id', $user->id)
            ->where('status', 'borrowed')
            ->count();

        $totalBorrows = Loan::where('member_id', $user->id)->count();

        $unpaidPenalty = Penalty::where('member_id', $user->id)
            ->where('status', 'unpaid')
            ->sum('fine_amount');

        // Denda berjalan dari buku yang lewat jatuh tempo dan belum kembali.
        $finePerDay = (int) config('perpustakaan.fine_per_day');

        $runningPenalty = Loan::where('member_id', $user->id)
            ->where('status', 'borrowed')
            ->whereDate('due_date', '<', today())
            ->get()
            ->sum(fn (Loan $loan) => $loan->late_days * $finePerDay);

        // Buku yang sedang dipinjam, dikelompokkan per transaksi (loan_code).
        $currentBorrows = Loan::with('book')
            ->where('member_id', $user->id)
            ->where('status', 'borrowed')
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        $borrowHistory = Loan::with('book')
            ->where('member_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('member.dashboard', compact(
            'user', 'activeBorrows', 'totalBorrows',
            'unpaidPenalty', 'runningPenalty', 'currentBorrows', 'borrowHistory'
        ));
    }

    public function myBorrows(Request $request)
    {
        // Riwayat peminjaman per TRANSAKSI (kode): satu transaksi multi-buku
        // tampil satu baris berisi daftar bukunya.
        $codes = Loan::where('member_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('loans_id')
            ->pluck('loan_code')
            ->unique()
            ->values();

        $perPage = 10;
        $page = max(1, (int) $request->input('page', 1));
        $pageCodes = $codes->slice(($page - 1) * $perPage, $perPage)->values();

        $groups = Loan::with(['book', 'penalty'])
            ->where('member_id', Auth::id())
            ->whereIn('loan_code', $pageCodes)
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        $borrows = new LengthAwarePaginator(
            $groups,
            $codes->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => $request->query(),
                'pageName' => 'page',
            ]
        );

        $finePerDay = (int) config('perpustakaan.fine_per_day');

        return view('member.borrows', compact('borrows', 'finePerDay'));
    }

    public function myPenalties()
    {
        $totalUnpaid = Penalty::where('member_id', Auth::id())
            ->where('status', 'unpaid')
            ->sum('fine_amount');
        $penalties = Penalty::with('loan.book')
            ->where('member_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Denda berjalan milik member yang sedang login.
        $finePerDay = (int) config('perpustakaan.fine_per_day');

        $runningLoans = Loan::with('book')
            ->where('member_id', Auth::id())
            ->where('status', 'borrowed')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->get();

        $totalRunning = $runningLoans->sum(fn (Loan $loan) => $loan->late_days * $finePerDay);

        return view('member.penalties', compact(
            'penalties', 'totalUnpaid', 'finePerDay', 'runningLoans', 'totalRunning'
        ));
    }
}
