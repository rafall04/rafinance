<?php

declare(strict_types=1);

namespace App\Domain\Capture;

use App\Domain\Ledger\Enums\TransactionKind;
use App\Support\Money;

/**
 * Hasil pembacaan satu baris teks, sebelum jadi transaksi.
 *
 * Setiap bagiannya boleh kosong. Itu bukan kelalaian desain — itu inti prinsip
 * produk ini: sistem menerima input seadanya dan membiarkan orang melengkapinya
 * nanti. Draft yang tidak lengkap tetap disimpan sebagai inbox_item, tidak
 * ditolak.
 */
final readonly class NormalizedDraft
{
    /**
     * @param  array<int, string>  $catatan  hal yang tidak bisa dipastikan parser
     */
    public function __construct(
        public string $rawText,
        public TransactionKind $kind = TransactionKind::Expense,
        public ?Money $amount = null,
        public ?string $accountId = null,
        public ?string $toAccountId = null,
        public ?string $categoryId = null,
        public ?string $projectId = null,
        public ?string $projectTag = null,
        public ?string $contactName = null,
        public ?string $description = null,
        public array $catatan = [],
    ) {}

    /**
     * Cukup lengkap untuk langsung dicatat tanpa bertanya apa pun.
     */
    public function isComplete(): bool
    {
        if ($this->amount === null || $this->amount->isZero() || $this->accountId === null) {
            return false;
        }

        return $this->kind !== TransactionKind::Transfer || $this->toAccountId !== null;
    }

    /**
     * Nilai antara nol dan satu, dipakai untuk memutuskan apakah balasan bot
     * berupa konfirmasi ("✓ tersimpan") atau ajakan melengkapi.
     */
    public function confidence(): float
    {
        $skor = 0.0;

        $skor += $this->amount !== null && ! $this->amount->isZero() ? 0.5 : 0.0;
        $skor += $this->accountId !== null ? 0.25 : 0.0;
        $skor += $this->categoryId !== null ? 0.15 : 0.0;
        $skor += $this->description !== null && $this->description !== '' ? 0.10 : 0.0;

        return round($skor, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw_text' => $this->rawText,
            'kind' => $this->kind->value,
            'amount_minor' => $this->amount?->minor,
            'currency' => $this->amount?->currency,
            'account_id' => $this->accountId,
            'to_account_id' => $this->toAccountId,
            'category_id' => $this->categoryId,
            'project_id' => $this->projectId,
            'project_tag' => $this->projectTag,
            'contact_name' => $this->contactName,
            'description' => $this->description,
            'catatan' => $this->catatan,
            'confidence' => $this->confidence(),
        ];
    }

    public function with(mixed ...$perubahan): self
    {
        return new self(
            rawText: $perubahan['rawText'] ?? $this->rawText,
            kind: $perubahan['kind'] ?? $this->kind,
            amount: $perubahan['amount'] ?? $this->amount,
            accountId: $perubahan['accountId'] ?? $this->accountId,
            toAccountId: $perubahan['toAccountId'] ?? $this->toAccountId,
            categoryId: $perubahan['categoryId'] ?? $this->categoryId,
            projectId: $perubahan['projectId'] ?? $this->projectId,
            projectTag: $perubahan['projectTag'] ?? $this->projectTag,
            contactName: $perubahan['contactName'] ?? $this->contactName,
            description: $perubahan['description'] ?? $this->description,
            catatan: $perubahan['catatan'] ?? $this->catatan,
        );
    }
}
