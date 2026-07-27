<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Niat mencatat sebuah transaksi, sebelum ia jadi baris di buku besar.
 *
 * ID-nya sengaja bagian dari DTO dan boleh datang dari luar: antrean offline
 * PWA membuat ULID di ponsel dan memakainya sekaligus sebagai Idempotency-Key,
 * sehingga pengiriman ulang menghasilkan transaksi yang sama, bukan yang kedua
 * (aturan A7 dan A9).
 */
final readonly class DraftTransaction
{
    /**
     * @param  array<int, EntryLine>  $lines
     */
    private function __construct(
        public string $id,
        public CarbonImmutable $bookedDate,
        public TransactionKind $kind,
        public array $lines,
        public ?string $description = null,
        public ?string $categoryId = null,
        public ?string $projectId = null,
        public ?string $contactId = null,
        public TransactionSource $source = TransactionSource::Web,
        public ?string $sourceRef = null,
        public ?string $rawInput = null,
        public ?string $createdBy = null,
    ) {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Transaksi butuh setidaknya dua sisi (aturan A2).');
        }
    }

    /**
     * Pengeluaran: uang keluar dari sebuah akun, masuk ke akun beban.
     */
    public static function pengeluaran(
        Money $amount,
        Account $from,
        DateTimeInterface|string|null $bookedDate = null,
        ?string $description = null,
        ?string $categoryId = null,
        ?string $id = null,
        TransactionSource $source = TransactionSource::Web,
        ?string $projectId = null,
        ?string $contactId = null,
        ?string $rawInput = null,
        ?string $createdBy = null,
    ): self {
        $beban = Account::sistem(Enums\AccountType::Expense, $amount->currency);

        return new self(
            id: $id ?? (string) Str::ulid(),
            bookedDate: self::tanggal($bookedDate),
            kind: TransactionKind::Expense,
            lines: [
                new EntryLine($beban->getKey(), $amount->minor, 0),
                new EntryLine($from->getKey(), -$amount->minor, 1),
            ],
            description: $description,
            categoryId: $categoryId,
            projectId: $projectId,
            contactId: $contactId,
            source: $source,
            rawInput: $rawInput,
            createdBy: $createdBy,
        );
    }

    /**
     * Pemasukan: uang masuk ke sebuah akun, dari akun pendapatan.
     */
    public static function pemasukan(
        Money $amount,
        Account $to,
        DateTimeInterface|string|null $bookedDate = null,
        ?string $description = null,
        ?string $categoryId = null,
        ?string $id = null,
        TransactionSource $source = TransactionSource::Web,
        ?string $projectId = null,
        ?string $contactId = null,
        ?string $rawInput = null,
        ?string $createdBy = null,
    ): self {
        $pendapatan = Account::sistem(Enums\AccountType::Income, $amount->currency);

        return new self(
            id: $id ?? (string) Str::ulid(),
            bookedDate: self::tanggal($bookedDate),
            kind: TransactionKind::Income,
            lines: [
                new EntryLine($to->getKey(), $amount->minor, 0),
                new EntryLine($pendapatan->getKey(), -$amount->minor, 1),
            ],
            description: $description,
            categoryId: $categoryId,
            projectId: $projectId,
            contactId: $contactId,
            source: $source,
            rawInput: $rawInput,
            createdBy: $createdBy,
        );
    }

    /**
     * Pindah antar akun sendiri. Bukan pemasukan, bukan pengeluaran — jumlah
     * uangnya tidak berubah, hanya tempatnya.
     */
    public static function pindah(
        Money $amount,
        Account $from,
        Account $to,
        DateTimeInterface|string|null $bookedDate = null,
        ?string $description = null,
        ?string $id = null,
        TransactionSource $source = TransactionSource::Web,
        ?string $rawInput = null,
        ?string $createdBy = null,
    ): self {
        if ($from->is($to)) {
            throw new InvalidArgumentException('Akun asal dan tujuan harus berbeda.');
        }

        return new self(
            id: $id ?? (string) Str::ulid(),
            bookedDate: self::tanggal($bookedDate),
            kind: TransactionKind::Transfer,
            lines: [
                new EntryLine($to->getKey(), $amount->minor, 0),
                new EntryLine($from->getKey(), -$amount->minor, 1),
            ],
            description: $description,
            source: $source,
            rawInput: $rawInput,
            createdBy: $createdBy,
        );
    }

    /**
     * Bentuk mentah, untuk pembalikan dan penyesuaian yang sisi-sisinya sudah
     * ditentukan pemanggil.
     *
     * @param  array<int, EntryLine>  $lines
     */
    public static function mentah(
        TransactionKind $kind,
        array $lines,
        DateTimeInterface|string|null $bookedDate = null,
        ?string $description = null,
        ?string $id = null,
        TransactionSource $source = TransactionSource::Web,
        ?string $categoryId = null,
        ?string $projectId = null,
        ?string $contactId = null,
        ?string $createdBy = null,
    ): self {
        return new self(
            id: $id ?? (string) Str::ulid(),
            bookedDate: self::tanggal($bookedDate),
            kind: $kind,
            lines: $lines,
            description: $description,
            categoryId: $categoryId,
            projectId: $projectId,
            contactId: $contactId,
            source: $source,
            createdBy: $createdBy,
        );
    }

    public function total(): int
    {
        return array_sum(array_map(fn (EntryLine $line): int => $line->amountMinor, $this->lines));
    }

    public function isBalanced(): bool
    {
        return $this->total() === 0;
    }

    private static function tanggal(DateTimeInterface|string|null $value): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::now();
        }

        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);
    }
}
