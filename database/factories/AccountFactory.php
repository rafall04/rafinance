<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Kas', 'BCA', 'Mandiri', 'GoPay', 'OVO', 'Dana']),
            'type' => AccountType::Asset,
            'subtype' => AccountSubtype::Cash,
            'currency' => 'IDR',
            'opening_balance_minor' => 0,
            'sort_order' => 0,
            'is_archived' => false,
            'is_system' => false,
        ];
    }

    /**
     * Lihat catatan di UserFactory: kolom nullable seperti `color` tidak ikut
     * terbawa dari create(), dan mode ketat melempar saat diakses.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Account $akun) => $akun->refresh());
    }

    public function bank(string $name = 'BCA'): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
            'subtype' => AccountSubtype::Bank,
        ]);
    }

    public function ewallet(string $name = 'GoPay'): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
            'subtype' => AccountSubtype::Ewallet,
        ]);
    }

    public function saldoAwal(int $rupiah): static
    {
        return $this->state(fn (array $attributes): array => [
            'opening_balance_minor' => $rupiah * 100,
        ]);
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type]);
    }
}
