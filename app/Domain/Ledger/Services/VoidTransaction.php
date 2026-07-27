<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\EntryLine;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Entry;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Membatalkan transaksi dengan cara yang benar (aturan A3).
 *
 * Bukan menghapus, dan bukan mengubah angkanya. Yang dibuat adalah transaksi
 * pembalik dengan tanda terbalik, lalu yang lama ditandai void. Hasilnya: saldo
 * kembali benar, dan riwayat tetap menunjukkan bahwa pernah ada kesalahan serta
 * kapan diperbaiki.
 *
 * Buku kas yang bisa dihapus adalah buku kas yang tidak bisa dipercaya, dan
 * satu-satunya orang yang dirugikan oleh riwayat yang bersih adalah pemiliknya
 * sendiri, enam bulan kemudian, saat mencoba mengerti ke mana uangnya pergi.
 */
final class VoidTransaction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Transaction $transaction, ?string $reason = null): Transaction
    {
        if ($transaction->isVoid()) {
            throw new RuntimeException('Transaksi ini sudah dibatalkan sebelumnya.');
        }

        if (! $transaction->isPosted()) {
            throw new RuntimeException(
                'Hanya transaksi yang sudah tercatat yang perlu dibalik. Draft cukup dihapus.'
            );
        }

        return DB::connection('pgsql')->transaction(function () use ($transaction, $reason): Transaction {
            $transaction->loadMissing('entries');

            $pembalik = Transaction::query()->create([
                'id' => (string) Str::ulid(),
                'booked_date' => $transaction->booked_date->toDateString(),
                'description' => $this->deskripsiPembalik($transaction, $reason),
                'kind' => $transaction->kind,
                'category_id' => $transaction->category_id,
                'project_id' => $transaction->project_id,
                'contact_id' => $transaction->contact_id,
                'status' => TransactionStatus::Draft,
                'source' => $transaction->source,
                'reverses_transaction_id' => $transaction->getKey(),
                'created_by' => auth()->id(),
            ]);

            foreach ($transaction->entries as $entry) {
                $line = new EntryLine($entry->account_id, -$entry->amount_minor, $entry->sort_order);

                Entry::query()->create([
                    'id' => (string) Str::ulid(),
                    'transaction_id' => $pembalik->getKey(),
                    'account_id' => $line->accountId,
                    'amount_minor' => $line->amountMinor,
                    'sort_order' => $line->sortOrder,
                ]);
            }

            DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance IMMEDIATE');
            DB::connection('pgsql')->statement('SET CONSTRAINTS entries_must_balance DEFERRED');

            $pembalik->forceFill([
                'status' => TransactionStatus::Posted,
                'posted_at' => now(),
            ])->save();

            // Satu-satunya perubahan yang diizinkan trigger pada transaksi
            // posted: status menjadi void.
            $transaction->forceFill([
                'status' => TransactionStatus::Void,
                'voided_at' => now(),
            ])->save();

            $this->audit->record(
                action: AuditAction::TransactionReversed,
                auditable: $transaction,
                before: ['status' => TransactionStatus::Posted->value],
                after: [
                    'status' => TransactionStatus::Void->value,
                    'reversal_transaction_id' => $pembalik->getKey(),
                    'reason' => $reason,
                ],
            );

            return $pembalik->fresh(['entries']) ?? $pembalik;
        });
    }

    private function deskripsiPembalik(Transaction $transaction, ?string $reason): string
    {
        $dasar = 'Pembalik: '.($transaction->description ?? 'transaksi '.$transaction->booked_date->format('d/m/Y'));

        return $reason !== null && $reason !== ''
            ? $dasar.' — '.$reason
            : $dasar;
    }

    /**
     * Membuang draft. Tidak perlu pembalikan karena draft belum pernah
     * mempengaruhi saldo.
     */
    public function discardDraft(Transaction $transaction): void
    {
        if ($transaction->isPosted()) {
            throw new RuntimeException('Transaksi yang sudah tercatat tidak bisa dibuang. Balik saja.');
        }

        DB::connection('pgsql')->transaction(function () use ($transaction): void {
            $transaction->entries()->delete();
            $transaction->delete();
        });
    }

    /**
     * Draft dari sebuah transaksi, dengan seluruh sisinya dibalik. Dipakai kalau
     * pembalikan perlu tanggal buku yang berbeda dari aslinya.
     */
    public static function draftPembalikDari(Transaction $transaction): DraftTransaction
    {
        $transaction->loadMissing('entries');

        return DraftTransaction::mentah(
            kind: $transaction->kind,
            lines: $transaction->entries
                ->map(fn (Entry $entry): EntryLine => new EntryLine($entry->account_id, -$entry->amount_minor, $entry->sort_order))
                ->all(),
            bookedDate: $transaction->booked_date,
            description: 'Pembalik: '.($transaction->description ?? ''),
            categoryId: $transaction->category_id,
            projectId: $transaction->project_id,
            contactId: $transaction->contact_id,
        );
    }
}
