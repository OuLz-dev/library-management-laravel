<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        // Daftar peminjaman HANYA berisi transaksi yang masih punya buku
        // belum dikembalikan. Riwayat pengembalian punya halamannya sendiri
        // (admin.returns.index) supaya satu transaksi tidak tampil di dua
        // tabel sekaligus.
        //
        // Transaksi = kumpulan baris loans yang berbagi loan_code (1-3 buku).
        // Transaksi tampil di sini selama masih ada minimal satu baris
        // 'borrowed' — buku yang sudah dikembalikan parsial tetap ditampilkan
        // (terkunci) di kolom buku.

        // Search: kode peminjaman, kode member, nama/email/no. HP member, judul buku
        $search = trim((string) $request->input('search'));

        // Langkah 1: kode transaksi yang masih aktif dan cocok pencarian.
        // (Baris yang cocok menarik seluruh transaksinya — pencarian judul
        // buku salah satu slot tetap menemukan transaksi lengkap.)
        $codes = Loan::where('status', 'borrowed')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('loan_code', 'like', '%'.$search.'%')
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
            })
            ->orderByDesc('created_at')
            ->orderByDesc('loans_id')
            ->pluck('loan_code')
            ->unique()
            ->values();

        // Langkah 2: paginasi manual per transaksi (kode), lalu muat SEMUA
        // baris transaksi di halaman ini — termasuk buku yang sudah
        // dikembalikan parsial.
        $perPage = 10;
        $page = max(1, (int) $request->input('page', 1));
        $pageCodes = $codes->slice(($page - 1) * $perPage, $perPage)->values();

        $groups = Loan::with(['user', 'book', 'penalty', 'processedBy'])
            ->whereIn('loan_code', $pageCodes)
            ->orderBy('loans_id')
            ->get()
            ->groupBy('loan_code');

        $loans = new LengthAwarePaginator(
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
        $maxBooksPerTransaction = (int) config('perpustakaan.max_books_per_transaction');

        return view('admin.loans.create', compact(
            'members', 'books', 'categories', 'maxLoanDays', 'defaultLoanDays', 'maxBooksPerTransaction'
        ));
    }

    public function store(Request $request)
    {
        $maxLoanDays = (int) config('perpustakaan.max_loan_days');
        $maxBooksPerTransaction = (int) config('perpustakaan.max_books_per_transaction');
        $maxActiveBorrows = (int) config('perpustakaan.max_active_borrows');

        // Slot opsional (2-3) mengirim nilai kosong dari form — buang dulu
        // supaya validasi array hanya melihat slot yang benar-benar diisi.
        $bookIds = collect($request->input('book_ids', []))
            ->filter(fn ($id) => filled($id))
            ->values()
            ->all();
        $request->merge(['book_ids' => $bookIds]);

        // Bagian 4: tanggal pinjam HANYA hari ini — tidak boleh masa lampau atau masa depan.
        $request->validate([
            'member_id' => 'required|exists:users,id',
            'book_ids' => ['required', 'array', 'min:1', 'max:'.$maxBooksPerTransaction],
            'book_ids.*' => ['required', 'distinct', 'exists:books,books_id'],
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
        ], [
            'book_ids.required' => 'Pilih minimal 1 buku (slot 1 wajib diisi).',
            'book_ids.min' => 'Pilih minimal 1 buku (slot 1 wajib diisi).',
            'book_ids.max' => 'Maksimal '.$maxBooksPerTransaction.' buku per transaksi.',
            'book_ids.*.distinct' => 'Buku tidak boleh sama antar slot — pilih buku yang berbeda.',
            'book_ids.*.exists' => 'Buku yang dipilih tidak valid.',
        ]);

        $member = User::find($request->member_id);

        if (! $member || $member->role !== 'member') {
            return back()->with('error', 'Data member tidak valid');
        }

        if (! $member->canBorrow()) {
            return back()->with('error', 'Member tidak dapat meminjam (maksimal '.config('perpustakaan.max_active_borrows').' buku, status tidak aktif, atau memiliki pinjaman terlambat)');
        }

        if ($member->hasUnpaidPenalties()) {
            return back()->with('error', 'Member memiliki denda belum dibayar');
        }

        // Batas buku aktif dihitung per BUKU (baris), bukan per transaksi:
        // member yang sudah meminjam 2 buku hanya boleh menambah 1 slot lagi.
        $activeCount = $member->getActiveBorrowsCount();
        if ($activeCount + count($bookIds) > $maxActiveBorrows) {
            $slotsLeft = max(0, $maxActiveBorrows - $activeCount);

            return back()->with('error', $slotsLeft > 0
                ? 'Member sudah meminjam '.$activeCount.' buku — hanya boleh menambah '.$slotsLeft.' buku lagi (maksimal '.$maxActiveBorrows.' buku aktif)'
                : 'Member sudah meminjam maksimal '.$maxActiveBorrows.' buku');
        }

        DB::beginTransaction();
        try {
            // C3: kunci SEMUA buku terpilih sekaligus, lalu cek stok. Dua
            // peminjaman serentak tidak bisa lolos bersamaan dan membuat
            // available_stock minus.
            $books = Book::whereKey($bookIds)->lockForUpdate()->get();

            if ($books->count() !== count($bookIds)) {
                DB::rollback();

                return back()->with('error', 'Buku yang dipilih tidak ditemukan');
            }

            foreach ($books as $book) {
                if (! $book->isAvailable()) {
                    DB::rollback();

                    return back()->with('error', 'Stok buku "'.$book->title.'" tidak tersedia');
                }
            }

            // C1: kode peminjaman dihasilkan lewat generator tunggal —
            // SATU kode untuk seluruh buku dalam transaksi ini. Semua baris
            // transaksi berbagi kode, member, tanggal, dan admin pemroses.
            // processed_by = admin yang sedang login (jejak siapa yang
            // meminjamkan — berbeda dengan returns.processed_by pengembalian).
            $loanCode = Loan::generateCode();

            foreach ($books as $book) {
                Loan::create([
                    'loan_code' => $loanCode,
                    'member_id' => $request->member_id,
                    'book_id' => $book->getKey(),
                    'borrow_date' => $request->borrow_date,
                    'due_date' => $request->due_date,
                    'status' => 'borrowed',
                    'processed_by' => auth()->id(),
                ]);

                $book->decreaseStock();
            }

            DB::commit();

            $titles = $books->pluck('title')->implode(', ');

            return redirect()->route('admin.loans.index')
                ->with('success', 'Peminjaman berhasil! Kode: '.$loanCode.' — '.count($bookIds).' buku ('.$titles.')');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Gagal memproses peminjaman: '.$e->getMessage());
        }
    }
}
