<?php

namespace App\Http\Controllers;

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

        $query = Penalty::with(['loan.user', 'loan.book']);

        // Filter berdasarkan status denda
        if ($request->filled('status') && in_array($request->status, ['paid', 'unpaid'])) {
            $query->where('status', $request->status);
        }

        // Search: kode peminjaman, nama/email/no. HP member, judul buku
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('loan', function ($tq) use ($search) {
                    $tq->where('loan_code', 'like', '%'.$search.'%')
                        ->orWhereHas('book', function ($bq) use ($search) {
                            $bq->where('title', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('member_code', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%');
                        });
                });
            });
        }

        // Total hasil filter (seluruh halaman) untuk footer tabel
        $filteredTotal = (clone $query)->sum('fine_amount');

        $penalties = $query->orderBy('created_at', 'desc')->paginate(10);

        // Menambahkan query string ke pagination
        $penalties->appends(request()->query());

        return view('admin.penalties.index', compact('penalties', 'totalUnpaid', 'totalPaid', 'filteredTotal'));
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
