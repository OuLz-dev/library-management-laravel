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
        $activeBorrows = Loan::where('status', 'borrowed')->count();
        $totalPenalties = Penalty::where('status', 'unpaid')->sum('fine_amount');

        $recentLoans = Loan::with(['user', 'book'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalBooks', 'totalMembers', 'activeBorrows',
            'totalPenalties', 'recentLoans'
        ));
    }
}
