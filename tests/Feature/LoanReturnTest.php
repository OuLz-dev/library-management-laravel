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

    /** Proses pengembalian lewat form aksi. */
    private function processReturn(Loan $loan, string $returnDate)
    {
        return $this->actingAs($this->admin)
            ->post('/admin/returns', [
                'loan_id' => $loan->getKey(),
                'return_date' => $returnDate,
            ]);
    }

    public function test_return_on_time_creates_no_penalty_and_restores_stock(): void
    {
        $book = Book::factory()->create(['stock' => 3, 'available_stock' => 2]);
        $loan = Loan::factory()->for($book)->create([
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $response = $this->processReturn($loan, now()->toDateString());

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

        $response = $this->processReturn($loan, now()->toDateString());

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

        $this->processReturn($loan, now()->toDateString())
            ->assertSessionHas('success', fn ($message) => str_contains($message, '6.000'));
    }

    public function test_on_time_return_message_says_tepat_waktu(): void
    {
        $loan = Loan::factory()->create([
            'due_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);

        $this->processReturn($loan, now()->toDateString())
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'tepat waktu'));
    }

    public function test_borrowed_book_cannot_be_returned_twice(): void
    {
        $loan = Loan::factory()->returned()->create();

        $response = $this->processReturn($loan, now()->toDateString());

        $response->assertRedirect('/admin/returns');
        $response->assertSessionHas('error', 'Buku untuk kode ini sudah dikembalikan');
        $this->assertSame(1, $loan->loanReturn()->count());
        $this->assertSame(0, Penalty::where('loan_id', $loan->getKey())->count());
    }

    public function test_return_does_not_create_penalty_when_exactly_on_due_date(): void
    {
        $loan = Loan::factory()->create([
            'due_date' => now(), // jatuh tempo hari ini -> belum terlambat
            'status' => 'borrowed',
        ]);

        $this->processReturn($loan, now()->toDateString());

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

        $this->processReturn($loan, now()->toDateString());
        $firstReturn = $book->refresh()->available_stock;

        $this->processReturn($loan, now()->toDateString()); // ditolak
        $secondAttempt = $book->refresh()->available_stock;

        $this->assertSame(2, $firstReturn);
        $this->assertSame($firstReturn, $secondAttempt);
    }

    // ---------- ALUR LENGKAP BARU (riwayat -> form aksi -> proses) ----------

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

        // 2) Tombol "Kembalikan Buku" membuka form aksi berisi peminjaman aktif.
        $this->actingAs($this->admin)
            ->get('/admin/returns/create')
            ->assertOk()
            ->assertViewIs('admin.returns.create')
            ->assertSee('Kembalikan Buku')
            ->assertSee($loan->loan_code);

        // 3) Proses pengembalian terlambat lewat form.
        $this->processReturn($loan, now()->toDateString());

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
            'book_id' => $book->getKey(),
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
                'loan_id' => $loan->getKey(),
                'return_date' => now()->toDateString(),
            ])
            ->assertRedirect('/admin/returns');

        // Setelah dikembalikan, transaksi keluar dari daftar peminjaman aktif...
        $this->actingAs($admin1)->get('/admin/loans')
            ->assertOk()
            ->assertDontSee($loan->loan_code)
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

    public function test_return_form_lists_only_active_loans(): void
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
        $response->assertSee('name="loan_id"', false);
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

        $this->processReturn($loan, now()->addDays(2)->toDateString())
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

        $this->processReturn($loan, now()->subDays(7)->toDateString())
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
