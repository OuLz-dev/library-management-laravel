<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** Proses pengembalian lewat form aksi berbasis kode transaksi (bisa parsial). */
    private function processReturn(string $loanCode, array $loanIds, string $returnDate)
    {
        return $this->actingAs($this->admin)
            ->post('/admin/returns', [
                'loan_code' => $loanCode,
                'loan_ids' => $loanIds,
                'return_date' => $returnDate,
            ]);
    }

    /** Singkatan untuk transaksi 1 buku / mengembalikan satu baris tertentu. */
    private function processReturnLoan(Loan $loan, string $returnDate)
    {
        return $this->processReturn($loan->loan_code, [$loan->getKey()], $returnDate);
    }

    public function test_return_on_time_creates_no_penalty_and_restores_stock(): void
    {
        $book = Book::factory()->create(['stock' => 3, 'available_stock' => 2]);
        $loan = Loan::factory()->for($book)->create([
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $response = $this->processReturnLoan($loan, now()->toDateString());

        $response->assertRedirect('/admin/returns');
        $response->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertDatabaseHas('returns', [
            'loan_id' => $loan->getKey(),
            'return_date' => now()->toDateString(),
            'processed_by' => $this->admin->getKey(),
        ]);
        $this->assertSame(3, $book->refresh()->available_stock);

        $this->assertDatabaseMissing('penalties', ['loan_id' => $loan->getKey()]);
    }

    public function test_return_late_creates_penalty_with_correct_fine(): void
    {
        $loan = Loan::factory()->overdue()->create(); // jatuh tempo 3 hari lalu
        $book = $loan->book;
        $stockBefore = $book->available_stock;

        $response = $this->processReturnLoan($loan, now()->toDateString());

        $response->assertRedirect('/admin/returns');
        $response->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertSame($stockBefore + 1, $book->refresh()->available_stock);

        $penalty = Penalty::where('loan_id', $loan->getKey())->firstOrFail();
        $this->assertSame(3, $penalty->days_late);
        $this->assertSame(6000, (int) $penalty->fine_amount);
        $this->assertSame('unpaid', $penalty->status);
        $this->assertSame($loan->member_id, $penalty->member_id);
    }

    public function test_late_return_message_contains_fine_amount(): void
    {
        $loan = Loan::factory()->overdue()->create();

        $this->processReturnLoan($loan, now()->toDateString())
            ->assertSessionHas('success', fn ($message) => str_contains($message, '6.000'));
    }

    public function test_on_time_return_message_says_tepat_waktu(): void
    {
        $loan = Loan::factory()->create([
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $this->processReturnLoan($loan, now()->toDateString())
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'tepat waktu'));
    }

    public function test_borrowed_book_cannot_be_returned_twice(): void
    {
        $loan = Loan::factory()->returned()->create();

        $response = $this->processReturnLoan($loan, now()->toDateString());

        $response->assertRedirect('/admin/returns');
        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'sudah dikembalikan'));
        $this->assertSame(1, $loan->loanReturn()->count());
        $this->assertSame(0, Penalty::where('loan_id', $loan->getKey())->count());
    }

    public function test_return_does_not_create_penalty_when_exactly_on_due_date(): void
    {
        $loan = Loan::factory()->create([
            'due_date' => now(), // jatuh tempo hari ini -> belum terlambat
            'status' => 'borrowed',
        ]);

        $this->processReturnLoan($loan, now()->toDateString());

        $this->assertSame('returned', $loan->refresh()->status);
        $this->assertDatabaseMissing('penalties', ['loan_id' => $loan->getKey()]);
    }

    public function test_returning_a_borrowed_book_decrements_then_reincrements_exactly_once(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create(['stock' => 2, 'available_stock' => 1]);
        $loan = Loan::factory()->for($member)->for($book)->create([
            'due_date' => now()->addDays(2),
            'status' => 'borrowed',
        ]);

        $this->processReturnLoan($loan, now()->toDateString());
        $firstReturn = $book->refresh()->available_stock;

        $this->processReturnLoan($loan, now()->toDateString()); // ditolak
        $secondAttempt = $book->refresh()->available_stock;

        $this->assertSame(2, $firstReturn);
        $this->assertSame($firstReturn, $secondAttempt);
    }

    // ---------- PENGGEMBALIAN PARSIAL (transaksi multi-buku) ----------

    /** Susun transaksi multi-buku: beberapa baris berbagi satu loan_code. */
    private function makeMultiBookTransaction(User $member, array $books, array $attributes = []): array
    {
        $code = Loan::generateCode();

        $loans = collect($books)->map(fn (Book $book) => Loan::factory()
            ->forCode($code)
            ->for($member)
            ->for($book)
            ->create($attributes));

        return [$code, $loans];
    }

    public function test_partial_return_keeps_remaining_books_borrowed(): void
    {
        $member = User::factory()->create();
        // available_stock = stock - 1 per buku: factory tidak men-decrement
        // stok saat membuat baris loan, jadi angka awal mensimulasikan kondisi
        // "satu eksemplar tiap buku sedang dipinjam" transaksi ini.
        $books = Book::factory()->count(3)->create(['stock' => 3, 'available_stock' => 2]);
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // Hanya buku pertama yang dibawa member hari ini.
        $response = $this->processReturn($code, [$loans[0]->getKey()], now()->toDateString());

        $response->assertRedirect('/admin/returns');
        $response->assertSessionHas('success', fn ($message) =>
            str_contains($message, '1 buku berhasil dikembalikan')
            && str_contains($message, 'Sisa 2 buku masih dipinjam')
        );

        // Baris pertama kembali, dua lainnya tetap dipinjam — dalam kode yang sama.
        $this->assertSame('returned', $loans[0]->refresh()->status);
        $this->assertSame('borrowed', $loans[1]->refresh()->status);
        $this->assertSame('borrowed', $loans[2]->refresh()->status);
        $this->assertSame(3, Loan::forCode($code)->count());

        // Stok hanya buku pertama yang kembali.
        $this->assertSame(3, $books[0]->refresh()->available_stock);
        $this->assertSame(2, $books[1]->refresh()->available_stock);
        $this->assertSame(2, $books[2]->refresh()->available_stock);

        // Pengembalian tercatat per buku.
        $this->assertDatabaseHas('returns', ['loan_id' => $loans[0]->getKey()]);
        $this->assertDatabaseMissing('returns', ['loan_id' => $loans[1]->getKey()]);
    }

    public function test_returning_remaining_books_completes_the_transaction(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(2)->create(['stock' => 2, 'available_stock' => 1]);
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // Parsial: satu buku dulu.
        $this->processReturn($code, [$loans[0]->getKey()], now()->toDateString());

        // Lanjutan: sisanya — sekaligus dalam satu event.
        $response = $this->processReturn($code, [$loans[1]->getKey()], now()->toDateString());

        $response->assertSessionHas('success', fn ($message) =>
            str_contains($message, 'Transaksi '.$code.' selesai')
        );

        $this->assertSame(0, Loan::forCode($code)->where('status', 'borrowed')->count());
        $this->assertSame(2, Loan::forCode($code)->where('status', 'returned')->count());
        $this->assertSame(2, $books[1]->refresh()->available_stock);
    }

    public function test_multiple_books_returned_in_one_event_each_get_their_own_return_row(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(3)->create(['stock' => 2, 'available_stock' => 1]);
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $this->processReturn($code, $loans->pluck('loans_id')->all(), now()->toDateString());

        foreach ($loans as $loan) {
            $this->assertDatabaseHas('returns', [
                'loan_id' => $loan->getKey(),
                'processed_by' => $this->admin->getKey(),
            ]);
            $this->assertSame(2, $loan->book->refresh()->available_stock);
        }
    }

    public function test_late_partial_return_penalizes_only_the_late_books(): void
    {
        // Denda PER BUKU (keputusan user): buku yang kembali tepat waktu tidak
        // ikut didenda walau buku lain pada transaksi yang sama telat.
        $member = User::factory()->create();
        $books = Book::factory()->count(2)->create();
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'borrow_date' => now()->subDays(10),
            'due_date' => now()->subDays(5),
            'status' => 'borrowed',
        ]);

        // Buku A kembali 6 hari lalu — sebelum jatuh tempo (5 hari lalu).
        $this->processReturn($code, [$loans[0]->getKey()], now()->subDays(6)->toDateString());
        $this->assertDatabaseMissing('penalties', ['loan_id' => $loans[0]->getKey()]);

        // Buku B baru kembali hari ini — telat 5 hari.
        $this->processReturn($code, [$loans[1]->getKey()], now()->toDateString());

        $penalty = Penalty::where('loan_id', $loans[1]->getKey())->firstOrFail();
        $this->assertSame(5, $penalty->days_late);
        $this->assertSame(10000, (int) $penalty->fine_amount);
        $this->assertSame('unpaid', $penalty->status);

        // Hanya buku B yang didenda.
        $this->assertSame(1, Penalty::whereIn('loan_id', $loans->pluck('loans_id'))->count());
    }

    public function test_return_form_marks_already_returned_books_as_locked(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(2)->create();
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // Satu buku sudah dikembalikan parsial sebelumnya.
        $this->processReturn($code, [$loans[0]->getKey()], now()->toDateString());

        $this->actingAs($this->admin)
            ->get('/admin/returns/create')
            ->assertOk()
            ->assertViewIs('admin.returns.create')
            // Form berbasis kode transaksi.
            ->assertSee('name="loan_code"', false)
            ->assertSee($code)
            // Data transaksi membawa status per buku untuk JS (terkunci di UI).
            ->assertViewHas('transactionData', function ($data) use ($code, $loans) {
                $books = collect($data[$code]['books']);

                return $books->firstWhere('id', $loans[0]->getKey())['status'] === 'returned'
                    && $books->firstWhere('id', $loans[1]->getKey())['status'] === 'borrowed'
                    && $data[$code]['remaining'] === 1;
            });
    }

    public function test_selected_book_must_belong_to_the_transaction_code(): void
    {
        $member = User::factory()->create();
        [$codeA, $loansA] = $this->makeMultiBookTransaction($member, Book::factory()->count(2)->create()->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);
        [$codeB, $loansB] = $this->makeMultiBookTransaction($member, Book::factory()->count(1)->create()->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // loan_id milik transaksi B dikirim dengan kode transaksi A -> ditolak.
        $this->processReturn($codeA, [$loansA[0]->getKey(), $loansB[0]->getKey()], now()->toDateString())
            ->assertSessionHasErrors('loan_ids');

        $this->assertSame('borrowed', $loansA[0]->refresh()->status);
        $this->assertSame('borrowed', $loansB[0]->refresh()->status);
    }

    public function test_at_least_one_book_must_be_selected(): void
    {
        $member = User::factory()->create();
        [$code, $loans] = $this->makeMultiBookTransaction($member, Book::factory()->count(2)->create()->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/returns', [
                'loan_code' => $code,
                'loan_ids' => [],
                'return_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors([
                'loan_ids' => 'Centang minimal satu buku yang dikembalikan.',
            ]);

        $this->assertSame(2, Loan::forCode($code)->where('status', 'borrowed')->count());
    }

    public function test_unknown_transaction_code_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/returns', [
                'loan_code' => 'PJM-19700101-XXXXXX',
                'loan_ids' => [1],
                'return_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('loan_code');
    }

    // ---------- ALUR LENGKAP (riwayat -> form aksi -> proses) ----------

    public function test_full_return_flow_from_form_then_processes_late_return(): void
    {
        $member = User::factory()->create(['name' => 'Budi Pengembali']);
        $book = Book::factory()->create([
            'title' => 'Buku Terlambat',
            'stock' => 2,
            'available_stock' => 1,
        ]);
        // Jatuh tempo 5 hari lalu; dikembalikan hari ini -> telat 5 hari.
        $loan = Loan::factory()->for($member)->for($book)->create([
            'due_date' => now()->subDays(5),
            'status' => 'borrowed',
        ]);

        // 1) Riwayat pengembalian tidak boleh menampilkan peminjaman aktif,
        //    meskipun kata kunci cocok dengan member peminjamnya.
        $this->actingAs($this->admin)
            ->get('/admin/returns?search=Budi Pengembali')
            ->assertOk()
            ->assertDontSee($loan->loan_code);

        // 2) Tombol "Kembalikan Buku" membuka form aksi berisi transaksi aktif.
        $this->actingAs($this->admin)
            ->get('/admin/returns/create')
            ->assertOk()
            ->assertViewIs('admin.returns.create')
            ->assertSee('Kembalikan Buku')
            ->assertSee($loan->loan_code);

        // 3) Proses pengembalian terlambat lewat form (berbasis kode).
        $this->processReturnLoan($loan, now()->toDateString());

        $loan->refresh();
        $loanReturn = $loan->loanReturn()->firstOrFail();
        $this->assertSame(now()->toDateString(), $loanReturn->return_date->toDateString());
        // Admin yang memproses tercatat di kolom processed_by.
        $this->assertSame($this->admin->getKey(), $loanReturn->processed_by);
        $this->assertSame('returned', $loan->status);
        $this->assertSame(2, $book->refresh()->available_stock);

        $penalty = Penalty::where('loan_id', $loan->getKey())->firstOrFail();
        $this->assertSame(5, $penalty->days_late);
        $this->assertSame(10000, (int) $penalty->fine_amount);
        $this->assertSame('unpaid', $penalty->status);
        $this->assertSame($loan->member_id, $penalty->member_id);
    }

    public function test_loan_and_return_processors_are_shown_on_their_own_pages(): void
    {
        // Skenario user: admin 1 meminjamkan, admin 2 mengembalikan.
        // Dua jejak terpisah (loans.processed_by vs returns.processed_by):
        // pemroses peminjaman tampil di daftar peminjaman aktif, sedangkan
        // pemroses pengembalian tampil di riwayat pengembalian.
        $admin1 = $this->admin;
        $admin2 = User::factory()->admin()->create();
        $member = User::factory()->create();
        $book = Book::factory()->create(['stock' => 1, 'available_stock' => 1]);

        // Admin 1 meminjamkan.
        $this->actingAs($admin1)->post('/admin/loans', [
            'member_id' => $member->id,
            'book_ids' => [$book->getKey()],
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect('/admin/loans');

        $loan = Loan::latest('loans_id')->firstOrFail();

        // Saat masih dipinjam, transaksi tampil di daftar peminjaman aktif.
        $this->actingAs($admin1)->get('/admin/loans')
            ->assertOk()
            ->assertSee('Diproses oleh', false)
            ->assertSee($loan->loan_code);

        // Admin 2 memproses pengembalian.
        $this->actingAs($admin2)
            ->post('/admin/returns', [
                'loan_code' => $loan->loan_code,
                'loan_ids' => [$loan->getKey()],
                'return_date' => now()->toDateString(),
            ])
            ->assertRedirect('/admin/returns');

        // Setelah dikembalikan, transaksi keluar dari daftar peminjaman aktif...
        // (assertDontSee(kode) tidak bisa dipakai di sini: pesan sukses
        // pengembalian memuat kode transaksi dan flash-nya masih terbawa
        // di session untuk request berikutnya, jadi kekosongan daftar dicek
        // lewat data view.)
        $this->actingAs($admin1)->get('/admin/loans')
            ->assertOk()
            ->assertViewHas('loans', fn ($loans) => $loans->total() === 0)
            ->assertDontSee('Diproses oleh', false)
            ->assertDontSee('Dikembalikan oleh:', false);

        // ...dan pindah ke riwayat pengembalian beserta admin pemrosesnya.
        $this->actingAs($admin1)->get('/admin/returns')
            ->assertOk()
            ->assertSee('Riwayat Pengembalian', false)
            ->assertSee($loan->loan_code)
            ->assertSee($admin2->name);
    }

    public function test_returns_history_page_only_searches_returned_loans(): void
    {
        $member = User::factory()->create(['name' => 'Budi Riwayat']);
        $activeLoan = Loan::factory()->for($member)->create([
            'status' => 'borrowed',
            'due_date' => now()->addDays(3),
        ]);
        $returnedLoan = Loan::factory()->returned()->for($member)->create();

        $response = $this->actingAs($this->admin)
            ->get('/admin/returns?search=Budi Riwayat');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'name="search"'));
        $response->assertSee($returnedLoan->loan_code);
        $response->assertDontSee($activeLoan->loan_code);

        // Kode peminjaman aktif yang persis pun tidak mengalihkan ke mana pun;
        // halaman riwayat tetap kosong karena tidak ada riwayat yang cocok.
        $this->actingAs($this->admin)
            ->get('/admin/returns?search='.$activeLoan->loan_code)
            ->assertOk()
            ->assertSee('Tidak ada riwayat pengembalian yang cocok')
            ->assertViewHas('returns', fn ($returns) => $returns->total() === 0);
    }

    public function test_returns_history_shows_one_row_per_transaction(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(3)->create(['stock' => 2, 'available_stock' => 1]);
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // Ketiga buku dikembalikan sekaligus dalam satu event.
        $this->processReturn($code, $loans->pluck('loans_id')->all(), now()->toDateString());

        $response = $this->actingAs($this->admin)->get('/admin/returns');

        $response->assertOk()
            ->assertViewHas('returns', fn ($returns) => $returns->total() === 1)
            ->assertViewHas('transactions', fn ($transactions) =>
                $transactions->has($code)
                && $transactions[$code]->count() === 3
                && $transactions[$code]->where('status', 'returned')->count() === 3
            );

        // Tiga buku dalam satu transaksi tetap dirender sebagai SATU baris:
        // badge kode hanya muncul sekali di tabel.
        $this->assertSame(1, substr_count(
            $response->getContent(),
            '<span class="badge bg-secondary">'.$code.'</span>'
        ));

        foreach ($books as $book) {
            $response->assertSee($book->title);
        }
        $response->assertSee('Selesai');
    }

    public function test_returns_history_shows_remaining_books_of_partial_transaction(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(3)->create(['stock' => 3, 'available_stock' => 2]);
        [$code, $loans] = $this->makeMultiBookTransaction($member, $books->all(), [
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        // Dua buku kembali lebih dulu; satu masih dipinjam.
        $this->processReturn($code, [$loans[0]->getKey(), $loans[1]->getKey()], now()->toDateString());

        $response = $this->actingAs($this->admin)->get('/admin/returns');

        $response->assertOk()
            ->assertViewHas('returns', fn ($returns) => $returns->total() === 1)
            ->assertViewHas('transactions', fn ($transactions) =>
                $transactions->has($code)
                && $transactions[$code]->count() === 3
                && $transactions[$code]->where('status', 'returned')->count() === 2
            );

        // Transaksi parsial tetap muncul sebagai satu baris, lengkap dengan
        // buku yang belum kembali (opsi A).
        $response->assertSee('Sebagian (2/3)');
        $response->assertSee($books[2]->title);
        $this->assertSame(1, substr_count(
            $response->getContent(),
            '<span class="badge bg-secondary">'.$code.'</span>'
        ));
    }

    public function test_return_form_lists_only_active_transactions(): void
    {
        $member = User::factory()->create(['name' => 'Budi Aksi']);
        $activeLoan = Loan::factory()->for($member)->create([
            'status' => 'borrowed',
            'due_date' => now()->addDays(3),
        ]);
        $returnedLoan = Loan::factory()->returned()->for($member)->create();

        $response = $this->actingAs($this->admin)->get('/admin/returns/create');

        $response->assertOk();
        $response->assertViewIs('admin.returns.create');
        $response->assertSee('name="loan_code"', false);
        $response->assertSee('Kembalikan Buku');
        $response->assertSee($activeLoan->loan_code);
        $response->assertDontSee($returnedLoan->loan_code);
    }

    public function test_return_date_cannot_be_in_the_future(): void
    {
        $loan = Loan::factory()->create([
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);
        $book = $loan->book;
        $stockBefore = $book->available_stock;

        $this->processReturnLoan($loan, now()->addDays(2)->toDateString())
            ->assertSessionHasErrors([
                'return_date' => 'Tanggal pengembalian tidak boleh di masa depan.',
            ]);

        // Tidak ada yang berubah.
        $loan->refresh();
        $this->assertSame('borrowed', $loan->status);
        $this->assertDatabaseMissing('returns', ['loan_id' => $loan->getKey()]);
        $this->assertSame($stockBefore, $book->refresh()->available_stock);
        $this->assertDatabaseMissing('penalties', ['loan_id' => $loan->getKey()]);
    }

    public function test_return_date_cannot_be_before_borrow_date(): void
    {
        $loan = Loan::factory()->create([
            'borrow_date' => now()->subDays(5),
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $this->processReturnLoan($loan, now()->subDays(7)->toDateString())
            ->assertSessionHasErrors([
                'return_date' => 'Tanggal pengembalian tidak boleh sebelum tanggal pinjam.',
            ]);

        // Tidak ada yang berubah.
        $loan->refresh();
        $this->assertSame('borrowed', $loan->status);
        $this->assertDatabaseMissing('returns', ['loan_id' => $loan->getKey()]);
        $this->assertDatabaseMissing('penalties', ['loan_id' => $loan->getKey()]);
    }
}
