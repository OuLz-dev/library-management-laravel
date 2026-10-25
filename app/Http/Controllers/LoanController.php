<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        // Daftar peminjaman HANYA berisi transaksi yang belum dikembalikan.
        // Riwayat pengembalian punya halamannya sendiri (admin.returns.index)
        // supaya satu transaksi tidak tampil di dua tabel sekaligus.
        $query = Loan::with(['user', 'book', 'penalty', 'processedBy'])
            ->where('status', 'borrowed');

        // Search: kode peminjaman, kode member, nama/email/no. HP member, judul buku
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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

        $loans = $query->orderBy('created_at', 'desc')->paginate(10);

        // Menambahkan query string ke pagination
        $loans->appends(request()->query());

        return view('admin.loans.index', compact('loans'));
    }

    public function create()
    {
        // Pasca-merge: daftar member itu User ber-role 'member' yang aktif.
        $members = User::where('role', 'member')->where('status', 'active')->get();
        $books = Book::with('category')->where('available_stock', '>', 0)->get();
        $categories = Category::all();
        $maxLoanDays = (int) config('perpustakaan.max_loan_days');
        $defaultLoanDays = (int) config('perpustakaan.loan_days_default', 7);

        return view('admin.loans.create', compact('members', 'books', 'categories', 'maxLoanDays', 'defaultLoanDays'));
    }

    public function store(Request $request)
    {
        $maxLoanDays = (int) config('perpustakaan.max_loan_days');

        // Bagian 4: tanggal pinjam HANYA hari ini — tidak boleh masa lampau atau masa depan.
        $request->validate([
            'member_id' => 'required|exists:users,id',
            'book_id' => 'required|exists:books,books_id',
            'borrow_date' => [
                'required', 'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->toDateString() !== today()->toDateString()) {
                        $fail('Tanggal pinjam hanya boleh hari ini.');
                    }
                },
            ],
            'due_date' => [
                'required', 'date', 'after:borrow_date',
                // Jatuh tempo dihitung dari TANGGAL PINJAM, bukan hari ini
                // (Bug lama: "before_or_equal:+N hari" berbasis hari ini membuat
                // kombinasi tanggal pinjam bergeser menjadi tidak logis.)
                function ($attribute, $value, $fail) use ($request, $maxLoanDays) {
                    $maxDue = Carbon::parse($request->borrow_date)->copy()->addDays($maxLoanDays);
                    if (Carbon::parse($value)->gt($maxDue)) {
                        $fail('Jatuh tempo maksimal '.$maxLoanDays.' hari sejak tanggal pinjam.');
                    }
                },
            ],
        ]);

        $member = User::find($request->member_id);

        if (! $member || $member->role !== 'member') {
            return back()->with('error', 'Data member tidak valid');
        }

        if (! $member->canBorrow()) {
            return back()->with('error', 'Member tidak dapat meminjam (maksimal '.config('perpustakaan.max_active_borrows').' buku atau status tidak aktif)');
        }

        if ($member->hasUnpaidPenalties()) {
            return back()->with('error', 'Member memiliki denda belum dibayar');
        }

        DB::beginTransaction();
        try {
            // C3: kunci baris buku, lalu cek stok. Dua peminjaman serentak
            // tidak bisa lolos bersamaan dan membuat available_stock minus.
            $book = Book::whereKey($request->book_id)->lockForUpdate()->first();

            if (! $book || ! $book->isAvailable()) {
                DB::rollback();

                return back()->with('error', 'Stok buku tidak tersedia');
            }

            // C1: kode peminjaman dihasilkan lewat generator tunggal.
            // processed_by = admin yang sedang login (jejak siapa yang
            // meminjamkan — berbeda dengan returns.processed_by pengembalian).
            $loan = Loan::create([
                'loan_code' => Loan::generateCode(),
                'member_id' => $request->member_id,
                'book_id' => $request->book_id,
                'borrow_date' => $request->borrow_date,
                'due_date' => $request->due_date,
                'status' => 'borrowed',
                'processed_by' => auth()->id(),
            ]);

            $book->decreaseStock();
            DB::commit();

            return redirect()->route('admin.loans.index')
                ->with('success', 'Peminjaman berhasil! Kode: '.$loan->loan_code);
        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Gagal memproses peminjaman: '.$e->getMessage());
        }
    }
}
