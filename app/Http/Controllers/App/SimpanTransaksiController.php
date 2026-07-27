<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\PostTransaction;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menerima transaksi dari antrean offline PWA.
 *
 * Endpoint ini idempoten dengan cara yang paling sederhana yang bisa
 * diandalkan: ULID yang dibuat di ponsel dipakai sebagai primary key transaksi
 * DAN sebagai Idempotency-Key. Kiriman kedua dengan ULID yang sama menemukan
 * transaksi yang sudah ada dan mengembalikannya dengan 200 — bukan galat, dan
 * bukan pengeluaran kedua (aturan A9).
 *
 * Kenapa 200 dan bukan 409: dari sudut pandang ponsel yang kehilangan sinyal
 * setelah server sebenarnya sudah menerima, kiriman ulang itu BERHASIL. Menjawab
 * galat akan membuat antrean menahannya selamanya.
 */
final class SimpanTransaksiController
{
    public function __invoke(Request $request, PostTransaction $post): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'ulid'],
            'kind' => ['required', Rule::enum(TransactionKind::class)],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'account_id' => ['required', 'ulid'],
            'to_account_id' => ['nullable', 'ulid', 'different:account_id'],
            'category_id' => ['nullable', 'ulid'],
            'project_id' => ['nullable', 'ulid'],
            'description' => ['nullable', 'string', 'max:255'],
            'booked_date' => ['nullable', 'date'],
        ]);

        $kunci = $request->header('Idempotency-Key');

        if (is_string($kunci) && $kunci !== '' && $kunci !== $data['id']) {
            return response()->json([
                'message' => 'Idempotency-Key harus sama dengan id transaksi.',
            ], 422);
        }

        $sudahAda = Transaction::query()->find($data['id']);

        if ($sudahAda !== null) {
            return response()->json([
                'id' => $sudahAda->getKey(),
                'duplikat' => true,
            ]);
        }

        $akun = Account::query()->find($data['account_id']);

        if ($akun === null) {
            // 404, bukan 403: akun milik workspace lain tidak boleh ketahuan
            // keberadaannya (aturan A8). Global scope sudah menyembunyikannya.
            abort(404);
        }

        $kind = TransactionKind::from($data['kind']);
        $nominal = Money::ofMinor($data['amount_minor'], $akun->currency);

        $draft = match ($kind) {
            TransactionKind::Income => DraftTransaction::pemasukan(
                amount: $nominal,
                to: $akun,
                bookedDate: $data['booked_date'] ?? null,
                description: $data['description'] ?? null,
                categoryId: $data['category_id'] ?? null,
                id: $data['id'],
                source: TransactionSource::PwaOffline,
                projectId: $data['project_id'] ?? null,
            ),
            TransactionKind::Transfer => DraftTransaction::pindah(
                amount: $nominal,
                from: $akun,
                to: Account::query()->find($data['to_account_id']) ?? abort(404),
                bookedDate: $data['booked_date'] ?? null,
                description: $data['description'] ?? null,
                id: $data['id'],
                source: TransactionSource::PwaOffline,
            ),
            default => DraftTransaction::pengeluaran(
                amount: $nominal,
                from: $akun,
                bookedDate: $data['booked_date'] ?? null,
                description: $data['description'] ?? null,
                categoryId: $data['category_id'] ?? null,
                id: $data['id'],
                source: TransactionSource::PwaOffline,
                projectId: $data['project_id'] ?? null,
            ),
        };

        $transaksi = $post($draft);

        return response()->json([
            'id' => $transaksi->getKey(),
            'duplikat' => false,
            'saldo' => $akun->fresh()->balance()->minor,
        ], 201);
    }
}
