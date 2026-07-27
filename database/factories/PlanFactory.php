<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::lower(Str::random(8)),
            'name' => 'Gratis',
            'price_minor' => 0,
            'currency' => 'IDR',
            'interval' => 'monthly',
            'is_public' => true,
            'sort_order' => 0,
            'limits' => [
                'workspaces' => 1,
                'members' => 1,
                'transactions_per_month' => 500,
                'attachments_mb' => 0,
                'retention_months' => 12,
                'ocr' => false,
                'llm_parser' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $batas
     */
    public function denganBatas(array $batas): static
    {
        return $this->state(fn (array $attributes): array => [
            'limits' => array_merge($attributes['limits'], $batas),
        ]);
    }
}
