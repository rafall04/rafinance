<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Membuat header transaksi saja, tanpa entries.
 *
 * Sengaja berhenti di draft: transaksi berstatus posted tanpa entries yang
 * seimbang adalah keadaan yang tidak boleh ada, dan factory yang bisa
 * membuatnya akan dipakai untuk menulis test yang menguji keadaan mustahil.
 * Untuk transaksi utuh, pakai helper catatPengeluaran() di tests/Pest.php.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'booked_date' => now()->toDateString(),
            'description' => fake()->randomElement(['Bensin', 'Makan siang', 'Token listrik', 'Iuran bulanan']),
            'kind' => TransactionKind::Expense,
            'status' => TransactionStatus::Draft,
            'source' => TransactionSource::Web,
        ];
    }

    public function pemasukan(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => TransactionKind::Income]);
    }

    public function padaTanggal(string $tanggal): static
    {
        return $this->state(fn (array $attributes): array => ['booked_date' => $tanggal]);
    }
}
