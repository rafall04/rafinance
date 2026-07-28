<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Services\QuotaGuard;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\EntryLine;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Entry;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menulis transaksi ke buku besar.
 *
 * Tiga hal yang membuat service ini bukan sekadar pembungkus create():
 *
 * 1. **Idempoten.** ULID yang sudah ada mengembalikan transaksi yang sudah
 *    tersimpan, bukan galat. Antrean offline PWA mengandalkan ini — ponsel yang
 *    kehilangan sinyal setelah server menerima kiriman akan mencoba lagi, dan
 *    percobaan kedua tidak boleh menggandakan pengeluaran seseorang.
 *
 * 2. **Urutan yang benar.** Transaksi lahir sebagai draft, entries disisipkan,
 *    baru statusnya jadi posted. Terbalik sedikit saja dan trigger penjaga
 *    entries akan menolaknya — memang begitu seharusnya.
 *
 * 3. **Pemeriksaan keseimbangan di tempat yang bisa dijelaskan.** Constraint
 *    trigger keseimbangan bersifat DEFERRED, jadi biasanya baru meledak saat
 *    COMMIT, jauh dari kode yang menyebabkannya. Di sini ia dipaksa berjalan
 *    lebih awal, lalu dikembalikan ke DEFERRED.
 */
final class PostTransaction
{
    public function __invoke(DraftTransaction $draft): Transaction
    {
        $sudahAda = Transaction::query()->find($draft->id);

        if ($sudahAda !== null) {
            return $sudahAda;
        }

        // Kuota diperiksa sebelum apa pun ditulis. Saldo awal tidak ikut
        // dihitung: ia satu per akun dan bukan aktivitas mencatat.
        if ($draft->kind !== TransactionKind::Opening) {
            app(QuotaGuard::class)->pastikanBolehMenambah(UsageCounter::TRANSAKSI);
        }

        try {
            return $this->tulis($draft);
        } catch (UniqueConstraintViolationException) {
            // Dua kiriman dengan ULID yang sama tiba nyaris bersamaan, dan
            // keduanya lolos pemeriksaan di atas sebelum salah satunya sempat
            // menulis. Itu bukan keadaan langka di sini: antrean offline
            // mengirim ulang, dan halaman yang sama bisa terbuka di dua tab.
            //
            // Yang menang sudah menyimpan transaksinya; yang kalah cukup
            // mengembalikan hasil yang sama. Primary key-lah yang menjadikan
            // ini aman — bukan pemeriksaan di atas, yang hanya menghemat
            // pekerjaan pada kasus yang tidak berbenturan.
            $sudahAda = Transaction::query()->find($draft->id);

            if ($sudahAda !== null) {
                return $sudahAda;
            }

            throw new RuntimeException(
                'Transaksi '.$draft->id.' bentrok di primary key tapi tidak bisa dibaca kembali. '
                .'Kemungkinan besar ia milik workspace lain.'
            );
        }
    }

    private function tulis(DraftTransaction $draft): Transaction
    {
        return DB::connection('pgsql')->transaction(function () use ($draft): Transaction {
            $transaction = Transaction::query()->create([
                'id' => $draft->id,
                'booked_date' => $draft->bookedDate->toDateString(),
                'description' => $draft->description,
                'kind' => $draft->kind,
                'category_id' => $draft->categoryId,
                'project_id' => $draft->projectId,
                'contact_id' => $draft->contactId,
                'status' => TransactionStatus::Draft,
                'source' => $draft->source,
                'source_ref' => $draft->sourceRef,
                'raw_input' => $draft->rawInput,
                'created_by' => $draft->createdBy ?? auth()->id(),
            ]);

            foreach ($draft->lines as $line) {
                Entry::query()->create([
                    'id' => (string) Str::ulid(),
                    'transaction_id' => $transaction->getKey(),
                    'account_id' => $line->accountId,
                    'amount_minor' => $line->amountMinor,
                    'sort_order' => $line->sortOrder,
                ]);
            }

            $this->periksaKeseimbanganSekarang();

            $transaction->forceFill([
                'status' => TransactionStatus::Posted,
                'posted_at' => now(),
            ])->save();

            if ($draft->kind !== TransactionKind::Opening) {
                app(QuotaGuard::class)->catatPemakaian(UsageCounter::TRANSAKSI);
            }

            return $transaction->fresh(['entries']) ?? $transaction;
        });
    }

    /**
     * Membalik sebuah transaksi posted: buat kebalikannya, lalu tandai yang
     * lama void. Inilah satu-satunya jalur koreksi yang diizinkan aturan A3.
     */
    public function reverse(Transaction $transaction, ?string $reason = null): Transaction
    {
        return app(VoidTransaction::class)($transaction, $reason);
    }

    /**
     * Nominal transaksi dari sisi debitnya.
     */
    public static function amountOf(DraftTransaction $draft, string $currency): Money
    {
        $debit = array_sum(array_map(
            fn (EntryLine $line): int => max(0, $line->amountMinor),
            $draft->lines,
        ));

        return Money::ofMinor($debit, $currency);
    }

    /**
     * Memaksa constraint trigger keseimbangan berjalan sekarang, lalu
     * mengembalikannya ke DEFERRED.
     *
     * Baris kedua penting: tanpanya, seluruh sisa transaksi database berjalan
     * dalam mode IMMEDIATE, dan penyisipan entries berikutnya akan gagal di
     * baris pertama — karena satu entry saja memang tidak pernah seimbang.
     */
    private function periksaKeseimbanganSekarang(): void
    {
        $connection = DB::connection('pgsql');

        $connection->statement('SET CONSTRAINTS entries_must_balance IMMEDIATE');
        $connection->statement('SET CONSTRAINTS entries_must_balance DEFERRED');
    }
}
