<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Entry;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'account_id' => Account::factory(),
            'amount_minor' => 0,
            'sort_order' => 0,
        ];
    }

    public function debit(int $minor): static
    {
        return $this->state(fn (array $attributes): array => ['amount_minor' => abs($minor)]);
    }

    public function kredit(int $minor): static
    {
        return $this->state(fn (array $attributes): array => ['amount_minor' => -abs($minor)]);
    }
}
