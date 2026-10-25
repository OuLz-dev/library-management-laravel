<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        // Format sama dengan production: PJM-<Ymd>-<6 karakter acak>
        $code = 'PJM-'.now()->format('Ymd').'-'.strtoupper(fake()->regexify('[A-Z0-9]{6}'));

        // Data pengembalian tidak lagi di loans — dibuat di tabel returns
        // oleh state returned()/returnedLate() lewat afterCreating().
        return [
            'loan_code' => $code,
            'member_id' => User::factory(),
            'book_id' => Book::factory(),
            'borrow_date' => now()->subDays(2),
            'due_date' => now()->addDays(5),
            'status' => 'borrowed',
        ];
    }

    /** Sudah dikembalikan tepat waktu (tanpa denda). */
    public function returned(): static
    {
        return $this->state(fn () => ['status' => 'returned'])
            ->afterCreating(function (Loan $loan) {
                $loan->loanReturn()->create([
                    // processed_by boleh null di factory — hanya alur controller
                    // (LoanReturnController::process) yang mengisi admin.
                    'return_date' => now()->subDay(),
                ]);
            });
    }

    /** Masih dipinjam tapi sudah lewat jatuh tempo (denda real-time jalan). */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'borrowed',
            'due_date' => now()->subDays(3),
        ]);
    }

    /** Sudah dikembalikan terlambat 3 hari — pasangan alami untuk PenaltyFactory. */
    public function returnedLate(int $days = 3): static
    {
        $due = now()->subDays($days + 1);

        return $this->state(fn () => [
            'status' => 'returned',
            'due_date' => $due,
        ])->afterCreating(function (Loan $loan) use ($days, $due) {
            $loan->loanReturn()->create([
                // processed_by boleh null di factory — hanya alur controller
                // (LoanReturnController::process) yang mengisi admin.
                'return_date' => $due->copy()->addDays($days),
            ]);
        });
    }
}
