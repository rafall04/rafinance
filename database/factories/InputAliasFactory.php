<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Capture\Models\InputAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InputAlias>
 */
class InputAliasFactory extends Factory
{
    protected $model = InputAlias::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'keyword' => fake()->unique()->randomElement(['bbm', 'kopi', 'ojek', 'galon']),
            'use_count' => 0,
        ];
    }
}
