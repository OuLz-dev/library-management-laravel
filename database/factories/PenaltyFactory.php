<?php

namespace Database\Factories;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Penalty>
 */
class PenaltyFactory extends Factory
{
    public function definition(): array
    {
        $daysLate = fake()->numberBetween(1, 10);

        return [
            // loan_id HARUS didefinisikan sebelum member_id:
            // closure di bawah membaca $attributes['loan_id'] yang sudah
            // ter-resolve menjadi ID, sehingga member_id selalu konsisten
            // dengan peminjamannya.
            'loan_id' => Loan::factory()->returnedLate($daysLate),
            'member_id' => fn (array $attributes) => Loan::query()
                ->findOrFail($attributes['loan_id'])->member_id,
            'days_late' => $daysLate,
            'fine_amount' => $daysLate * 2000,
            'status' => 'unpaid',
            'paid_date' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_date' => now(),
        ]);
    }
}
