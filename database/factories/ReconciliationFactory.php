<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Models\Account;
use App\Domain\Reconciliation\Models\Reconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    protected $model = Reconciliation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'as_of_date' => now()->toDateString(),
            'system_balance_minor' => 0,
            'counted_balance_minor' => 0,
            'difference_minor' => 0,
        ];
    }
}
