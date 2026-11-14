<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanBorrowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function borrow(User $member, array|Book $books, array $overrides = [])
    {
        $bookIds = $books instanceof Book ? [$books->getKey()] : $books;

        return $this->actingAs($this->admin)->post('/admin/loans', array_merge([
            'member_id' => $member->id,
            'book_ids' => $bookIds,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    public function test_admin_can_borrow_a_book_and_stock_is_decreased(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create(['stock' => 4, 'available_stock' => 4]);

        $response = $this->borrow($member, $book);

        $response->assertRedirect('/admin/loans');

        $this->assertDatabaseHas('loans', [
            'member_id' => $member->id,
            'book_id' => $book->getKey(),
            'status' => 'borrowed',
            // Jejak: admin yang login dicatat sebagai pemroses peminjaman.
            'processed_by' => $this->admin->getKey(),
        ]);
        $this->assertSame(3, $book->refresh()->available_stock);
        $this->assertSame(4, $book->stock);
    }

    public function test_admin_can_borrow_three_books_in_one_transaction(): void
    {
        // 3 slot: slot 1 wajib, slot 2-3 opsional — semua berbagi SATU kode.
        $member = User::factory()->create();
        $books = Book::factory()->count(3)->create(['stock' => 5, 'available_stock' => 5]);

        $response = $this->borrow($member, $books->pluck('books_id')->all());

        $response->assertRedirect('/admin/loans');

        $loans = Loan::orderBy('loans_id')->get();
        $this->assertCount(3, $loans);

        // Satu kode transaksi untuk semua buku.
        $this->assertSame(1, $loans->pluck('loan_code')->unique()->count());
        $code = $loans->first()->loan_code;
        $this->assertMatchesRegularExpression('/^PJM-\d{8}-[A-Z0-9]{6}$/', $code);

        // Tanggal, member, dan admin pemroses seragam untuk semua baris.
        foreach ($loans as $loan) {
            $this->assertSame($member->id, $loan->member_id);
            $this->assertSame($this->admin->getKey(), $loan->processed_by);
            $this->assertSame(now()->toDateString(), $loan->borrow_date->toDateString());
            $this->assertSame(now()->addDays(7)->toDateString(), $loan->due_date->toDateString());
        }

        // Stok tiap buku berkurang 1.
        $books->each(function ($book) {
            $this->assertSame(4, $book->refresh()->available_stock);
        });

        // Pesan sukses menyebut jumlah buku.
        $response->assertSessionHas('success', fn ($message) => str_contains($message, '3 buku'));
    }

    public function test_empty_optional_slots_are_ignored(): void
    {
        // Slot opsional yang tidak diisi mengirim string kosong dari form —
        // server harus membuangnya sebelum validasi.
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/loans', [
            'member_id' => $member->id,
            'book_ids' => [$book->getKey(), '', ''],
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertRedirect('/admin/loans');
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_at_least_one_book_is_required(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin)->post('/admin/loans', [
            'member_id' => $member->id,
            'book_ids' => ['', '', ''],
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertSessionHasErrors([
            'book_ids' => 'Pilih minimal 1 buku (slot 1 wajib diisi).',
        ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_more_than_three_books_per_transaction_rejected(): void
    {
        $member = User::factory()->create();
        $books = Book::factory()->count(4)->create();

        $this->borrow($member, $books->pluck('books_id')->all())
            ->assertSessionHasErrors([
                'book_ids' => 'Maksimal 3 buku per transaksi.',
            ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_duplicate_book_across_slots_rejected(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create(['stock' => 5, 'available_stock' => 5]);

        $this->borrow($member, [$book->getKey(), $book->getKey()])
            ->assertSessionHasErrors([
                'book_ids.1' => 'Buku tidak boleh sama antar slot — pilih buku yang berbeda.',
            ]);

        // Tidak ada baris yang tersimpan — stok tidak berkurang.
        $this->assertDatabaseCount('loans', 0);
        $this->assertSame(5, $book->refresh()->available_stock);
    }

    public function test_transaction_rejected_when_one_book_has_no_stock(): void
    {
        // Satu buku habis di antara slot terpilih → SELURUH transaksi gagal.
        $member = User::factory()->create();
        $available = Book::factory()->create();
        $outOfStock = Book::factory()->withAvailableStock(0)->create();
        $stockBefore = $available->available_stock;

        $response = $this->borrow($member, [$available->getKey(), $outOfStock->getKey()]);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'tidak tersedia'));
        $this->assertDatabaseCount('loans', 0);
        // Tidak ada stok yang berubah.
        $this->assertSame($stockBefore, $available->refresh()->available_stock);
        $this->assertSame(0, $outOfStock->refresh()->available_stock);
    }

    public function test_borrow_rejected_when_member_already_has_three_active_borrows(): void
    {
        $member = User::factory()->create();
        Loan::factory()->count(3)->for($member)->create();
        $extraBook = Book::factory()->create();

        $response = $this->borrow($member, $extraBook);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'maksimal 3 buku'));
        $this->assertDatabaseMissing('loans', ['book_id' => $extraBook->getKey()]);
        $this->assertSame(3, $member->getActiveBorrowsCount());
    }

    public function test_borrow_rejected_when_requested_slots_exceed_remaining_quota(): void
    {
        // Kuota dihitung per BUKU: member dengan 2 buku aktif hanya boleh
        // menambah 1 slot — transaksi 2 buku harus ditolak.
        $member = User::factory()->create();
        Loan::factory()->count(2)->for($member)->create();
        $books = Book::factory()->count(2)->create();

        $response = $this->borrow($member, $books->pluck('books_id')->all());

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'hanya boleh menambah 1 buku lagi'));
        $this->assertDatabaseCount('loans', 2);
        $this->assertSame(2, $member->getActiveBorrowsCount());
    }

    public function test_blocked_member_cannot_borrow(): void
    {
        $member = User::factory()->blocked()->create();
        $book = Book::factory()->create();

        $response = $this->borrow($member, $book);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'status tidak aktif'));
        $this->assertDatabaseMissing('loans', ['book_id' => $book->getKey()]);
    }

    public function test_member_with_unpaid_penalty_cannot_borrow(): void
    {
        $member = User::factory()->create();
        $lateLoan = Loan::factory()->for($member)->returnedLate(3)->create();
        Penalty::factory()->create([
            'loan_id' => $lateLoan->getKey(),
            'member_id' => $member->id,
        ]);

        $book = Book::factory()->create();

        $response = $this->borrow($member, $book);

        $response->assertSessionHas('error', 'Member memiliki denda belum dibayar');
        $this->assertDatabaseMissing('loans', ['book_id' => $book->getKey()]);
    }

    public function test_member_with_overdue_loan_cannot_borrow(): void
    {
        $member = User::factory()->create();
        // Pinjaman aktif yang sudah lewat jatuh tempo (belum dikembalikan).
        Loan::factory()->for($member)->overdue()->create();
        $book = Book::factory()->create();

        $response = $this->borrow($member, $book);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'pinjaman terlambat'));
        $this->assertDatabaseMissing('loans', ['book_id' => $book->getKey()]);
        $this->assertSame(1, $member->getActiveBorrowsCount());
    }

    public function test_borrow_rejected_when_no_stock_available(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->withAvailableStock(0)->create();

        $response = $this->borrow($member, $book);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('loans', ['book_id' => $book->getKey()]);
        $this->assertSame(0, $book->refresh()->available_stock);
    }

    public function test_borrow_date_must_be_today(): void
    {
        // Bagian 4: tanggal pinjam HANYA hari ini.
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->borrow($member, $book, [
            'borrow_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors([
            'borrow_date' => 'Tanggal pinjam hanya boleh hari ini.',
        ]);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_due_date_must_be_after_borrow_date(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create();

        // Tanggal pinjam sah (hari ini), jatuh tempo lebih dulu -> gagal
        // aturan 'after:borrow_date'.
        $response = $this->borrow($member, $book, [
            'due_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('due_date');
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_due_date_cannot_exceed_max_loan_days(): void
    {
        // C7: batas durasi pinjam dari config('perpustakaan.max_loan_days') = 30.
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $this->borrow($member, $book, [
            'due_date' => now()->addDays(31)->toDateString(),
        ])->assertSessionHasErrors([
            'due_date' => 'Jatuh tempo maksimal 30 hari sejak tanggal pinjam.',
        ]);

        $this->borrow($member, $book, [
            'due_date' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('loans', 1);
    }

    public function test_borrow_requires_existing_member_and_book(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $this->borrow($member, $book, ['member_id' => 999999])
            ->assertSessionHasErrors('member_id');

        $this->borrow($member, $book, ['book_ids' => [999999]])
            ->assertSessionHasErrors('book_ids.*');
    }

    public function test_loan_code_is_generated_and_prefixed(): void
    {
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $this->borrow($member, $book);

        $code = Loan::latest('loans_id')->first()->loan_code;
        // C1: suffix kini 6 karakter acak (bukan rand(100,999)).
        $this->assertMatchesRegularExpression('/^PJM-\d{8}-[A-Z0-9]{6}$/', $code);
    }

    public function test_two_hundred_generated_loan_codes_are_unique(): void
    {
        // C1: generator lama hanya punya 900 kombinasi per hari.
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $codes = [];
        $rows = [];

        foreach (range(1, 200) as $i) {
            $code = Loan::generateCode();
            $codes[] = $code;

            $rows[] = [
                'loan_code' => $code,
                'member_id' => $member->id,
                'book_id' => $book->getKey(),
                'borrow_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'status' => 'borrowed',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // loan_code kini index biasa (bukan unique) — baris-baris transaksi
        // multi-buku berbagi kode — jadi keunikan murni tanggung jawab
        // generator; di sinilah tabrakan kode akan terdeteksi.
        DB::table('loans')->insert($rows);

        $this->assertCount(200, $rows);
        $this->assertCount(200, array_unique($codes));
        $this->assertDatabaseCount('loans', 200);
    }

    public function test_generated_loan_code_fits_varchar_50(): void
    {
        $code = Loan::generateCode();

        // 'PJM-20260930-ABC123' = 19 karakter, jauh di bawah varchar(50).
        $this->assertLessThanOrEqual(50, strlen($code));
    }
}
