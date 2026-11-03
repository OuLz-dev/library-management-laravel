<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga graph factory tetap utuh — semua test domain bergantung padanya.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_factory_creates_valid_row(): void
    {
        $category = Category::factory()->create();

        $this->assertDatabaseHas('categories', ['categories_id' => $category->getKey()]);
        $this->assertNotEmpty($category->name);
    }

    public function test_book_factory_creates_valid_row(): void
    {
        $book = Book::factory()->create();

        $this->assertDatabaseHas('books', [
            'books_id' => $book->getKey(),
            'isbn' => $book->isbn,
        ]);
        $this->assertNotNull($book->category_id);
        $this->assertSame($book->stock, $book->available_stock);
    }

    public function test_book_factory_can_override_available_stock(): void
    {
        $book = Book::factory()->withAvailableStock(0)->create();

        $this->assertSame(0, $book->available_stock);
        $this->assertFalse($book->isAvailable());
    }

    public function test_user_factory_creates_active_member(): void
    {
        // Pasca-merge: User::factory() default = member aktif.
        $member = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        $this->assertNotNull($member->member_code);
        $this->assertTrue($member->canBorrow());
    }

    public function test_user_factory_blocked_state(): void
    {
        $member = User::factory()->blocked()->create();

        $this->assertSame('inactive', $member->status);
        $this->assertFalse($member->canBorrow());
    }

    public function test_loan_factory_creates_borrowed_loan(): void
    {
        $loan = Loan::factory()->create();

        $this->assertDatabaseHas('loans', [
            'loans_id' => $loan->getKey(),
            'status' => 'borrowed',
        ]);
        $this->assertNotNull($loan->user);
        $this->assertNotNull($loan->book);
        $this->assertNull($loan->loanReturn);
    }

    public function test_loan_code_is_unique_per_row(): void
    {
        $codes = Loan::factory()->count(50)->create()->pluck('loan_code');

        $this->assertSame(50, $codes->unique()->count());
    }

    public function test_loan_overdue_state_is_late(): void
    {
        $loan = Loan::factory()->overdue()->create();

        $this->assertTrue($loan->has_fine);
        $this->assertSame(3, $loan->late_days);
        $this->assertSame(6000, $loan->current_fine);
    }

    public function test_loan_returned_state_has_no_real_time_fine(): void
    {
        $loan = Loan::factory()->returned()->create();

        $this->assertSame('returned', $loan->status);
        $this->assertNotNull($loan->loanReturn?->return_date);
        $this->assertFalse($loan->has_fine);
        $this->assertSame(0, $loan->current_fine);
    }

    public function test_penalty_factory_derives_member_from_its_loan(): void
    {
        $penalty = Penalty::factory()->create();

        $this->assertSame($penalty->loan->member_id, $penalty->member_id);
        $this->assertSame($penalty->days_late * 2000, (int) $penalty->fine_amount);
        $this->assertSame('unpaid', $penalty->status);
    }

    public function test_penalty_factory_paid_state(): void
    {
        $penalty = Penalty::factory()->paid()->create();

        $this->assertSame('paid', $penalty->status);
        $this->assertNotNull($penalty->paid_date);
    }
}
