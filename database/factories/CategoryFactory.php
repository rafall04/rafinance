<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Transportasi', 'Makan', 'Listrik', 'Gaji', 'Sewa', 'Internet']),
            'kind' => CategoryKind::Expense,
            'is_archived' => false,
        ];
    }

    public function pemasukan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => CategoryKind::Income,
            'name' => fake()->randomElement(['Penjualan', 'Jasa', 'Iuran bulanan']),
        ]);
    }
}
