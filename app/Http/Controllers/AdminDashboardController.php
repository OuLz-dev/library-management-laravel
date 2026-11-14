<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalBooks = Book::count();
        $totalMembers = User::where('role', 'member')->count();
        // Menghitung per BUKU (baris loans) — satu transaksi 3 buku = 3 buku dipinjam.
        $activeBorrows = Loan::where('status', 'borrowed')->count();
        $totalPenalties = Penalty::where('status', 'unpaid')->sum('fine_amount');

        // 10 transaksi terbaru (per loan_code). Transaksi multi-buku tampil
        // satu baris berisi daftar bukunya, bukan tiga baris berkode sama.
        $recentCodes = Loan::orderByDesc('created_at')
            ->orderByDesc('loans_id')
            ->pluck('loan_code')
            ->unique()
            ->take(10);

        $grouped = Loan::with(['user', 'book'])
            ->whereIn('loan_code', $recentCodes)
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        // Pertahankan urutan transaksi terbaru.
        $recentLoans = $recentCodes->map(fn ($code) => $grouped->get($code))->filter()->values();

        return view('admin.dashboard', compact(
            'totalBooks', 'totalMembers', 'activeBorrows',
            'totalPenalties', 'recentLoans'
        ));
    }
}
