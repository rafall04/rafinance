<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reconciliation\Models\PeriodLock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodLock>
 */
class PeriodLockFactory extends Factory
{
    protected $model = PeriodLock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locked_through' => now()->subMonth()->endOfMonth()->toDateString(),
            'locked_at' => now(),
        ];
    }

    public function sampai(string $tanggal): static
    {
        return $this->state(fn (array $attributes): array => ['locked_through' => $tanggal]);
    }
}
