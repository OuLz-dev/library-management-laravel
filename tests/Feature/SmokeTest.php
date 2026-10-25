<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test C10: alur bisnis utama berjalan utuh dari ujung ke ujung.
 *
 * pinjam → cari kode → kembali terlambat → penalty muncul → bayar → lunas
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->member = User::factory()->create();
        $this->book = Book::factory()->create(['stock' => 2, 'available_stock' => 2]);
    }

    public function test_borrow_return_late_pay_flow(): void
    {
        // 1) PINJAM -------------------------------------------------------
        $this->actingAs($this->admin)->post('/admin/loans', [
            'member_id' => $this->member->id,
            'book_id' => $this->book->getKey(),
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect('/admin/loans');

        $loan = Loan::latest('loans_id')->firstOrFail();

        $this->assertSame('borrowed', $loan->status);
        $this->assertSame(1, $this->book->refresh()->available_stock);
        $this->assertFalse($loan->has_fine);

        // 2) CARI KODE PEMINJAMAN -----------------------------------------
        $this->actingAs($this->admin)
            ->post('/admin/returns', ['loan_code' => $loan->loan_code])
            ->assertRedirect('/admin/returns/'.$loan->loan_code);

        // 3) KEMBALI TERLAMBAT ----------------------------------------------
        // Mundurkan jatuh tempo supaya dihitung terlambat 3 hari.
        $loan->update(['due_date' => now()->subDays(3)->toDateString()]);

        $this->actingAs($this->admin)
            ->post('/admin/returns/'.$loan->loan_code, [
                'return_date' => now()->toDateString(),
            ])
            ->assertRedirect('/admin/returns');

        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertSame(2, $this->book->refresh()->available_stock);

        // 4) PENALTY MUNCUL ------------------------------------------------
        $penalty = Penalty::where('loan_id', $loan->getKey())->firstOrFail();

        $this->assertSame(3, $penalty->days_late);
        $this->assertSame(6000, (int) $penalty->fine_amount);
        $this->assertSame('unpaid', $penalty->status);
        $this->assertTrue($loan->has_fine);

        // 5) DENDA TERBACA DI DASHBOARD MEMBER ------------------------------
        $this->actingAs($loan->user)
            ->get('/member/dashboard')
            ->assertOk()
            ->assertViewHas('unpaidPenalty', 6000);

        // 6) BAYAR -----------------------------------------------------------
        $this->actingAs($this->admin)
            ->put('/admin/penalties/'.$penalty->getKey().'/pay')
            ->assertRedirect('/admin/penalties');

        $penalty->refresh();
        $this->assertSame('paid', $penalty->status);
        $this->assertNotNull($penalty->paid_date);

        // 7) LUNAS — tidak ada lagi denda berjalan ---------------------------
        $this->assertFalse($loan->user->refresh()->hasUnpaidPenalties());

        // 8) Sekarang member boleh meminjam lagi -----------------------------
        $anotherBook = Book::factory()->create(['stock' => 1, 'available_stock' => 1]);
        $this->actingAs($this->admin)->post('/admin/loans', [
            'member_id' => $this->member->id,
            'book_id' => $anotherBook->getKey(),
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect('/admin/loans');

        $this->assertDatabaseCount('loans', 2);
    }

    public function test_cover_upload_is_reachable_through_storage_link(): void
    {
        // C0: public/storage harus menunjuk ke storage/app/public proyek ini.
        $link = public_path('storage');

        $this->assertTrue(is_link($link), 'public/storage harus berupa symlink');
        $this->assertSame(
            realpath(storage_path('app/public')),
            realpath($link),
            'symlink harus menunjuk ke storage/app/public proyek ini'
        );

        // 153 cover bawaan seeder harus terjangkau lewat link itu.
        $this->assertDirectoryExists($link.'/covers');
        $this->assertGreaterThan(0, count(glob($link.'/covers/*')));
    }
}
