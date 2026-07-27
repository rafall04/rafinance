<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

/**
 * Satu sisi calon transaksi.
 *
 * `amountMinor` bertanda: debit positif, kredit negatif — konvensi yang sama
 * persis dengan kolom entries.amount_minor, supaya tidak ada penerjemahan tanda
 * di tengah jalan yang bisa terbalik.
 */
final readonly class EntryLine
{
    public function __construct(
        public string $accountId,
        public int $amountMinor,
        public int $sortOrder = 0,
    ) {}

    public function isDebit(): bool
    {
        return $this->amountMinor > 0;
    }

    public function negate(): self
    {
        return new self($this->accountId, -$this->amountMinor, $this->sortOrder);
    }
}
