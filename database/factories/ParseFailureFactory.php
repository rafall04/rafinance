<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Capture\Models\ParseFailure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParseFailure>
 */
class ParseFailureFactory extends Factory
{
    protected $model = ParseFailure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raw_text' => 'entah apa ini',
            'reason' => 'Nominal tidak terbaca.',
        ];
    }
}
