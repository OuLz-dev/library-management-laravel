<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Penalty;
use Illuminate\Support\Facades\Auth;

class MemberDashboardController extends Controller
{
    public function index()
    {
        // Pasca-merge: member = User yang login sendiri (tidak ada tabel members).
        $user = Auth::user();

        $activeBorrows = Loan::where('member_id', $user->id)
            ->where('status', 'borrowed')
            ->count();

        $totalBorrows = Loan::where('member_id', $user->id)->count();

        $unpaidPenalty = Penalty::where('member_id', $user->id)
            ->where('status', 'unpaid')
            ->sum('fine_amount');

        $currentBorrows = Loan::with('book')
            ->where('member_id', $user->id)
            ->where('status', 'borrowed')
            ->get();

        $borrowHistory = Loan::with('book')
            ->where('member_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('member.dashboard', compact(
            'user', 'activeBorrows', 'totalBorrows',
            'unpaidPenalty', 'currentBorrows', 'borrowHistory'
        ));
    }

    public function myBorrows()
    {
        $borrows = Loan::with('book')
            ->where('member_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('member.borrows', compact('borrows'));
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

        return view('member.penalties', compact('penalties', 'totalUnpaid'));
    }
}
