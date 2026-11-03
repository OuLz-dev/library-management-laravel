<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $stock = fake()->numberBetween(1, 10);

        return [
            'category_id' => Category::factory(),
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
            'publisher' => fake()->company(),
            'year' => fake()->numberBetween(1950, 2026),
            'isbn' => fake()->unique()->isbn13(),
            'cover' => null,
            'stock' => $stock,
            'available_stock' => $stock,
            'description' => fake()->paragraph(),
        ];
    }

    /** Buku dengan stok tersedia tertentu (untuk menguji guard ketersediaan). */
    public function withAvailableStock(int $available): static
    {
        return $this->state(fn () => [
            'available_stock' => $available,
        ]);
    }
}
