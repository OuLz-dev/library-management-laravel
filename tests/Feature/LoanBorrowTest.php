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

    private function borrow(User $member, Book $book, array $overrides = [])
    {
        return $this->actingAs($this->admin)->post('/admin/loans', array_merge([
            'member_id' => $member->id,
            'book_id' => $book->getKey(),
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

        $this->borrow($member, $book, ['book_id' => 999999])
            ->assertSessionHasErrors('book_id');
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

        // Kolom loan_code berindex unique, jadi tabrakan akan melempar
        // QueryException di baris ini.
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
