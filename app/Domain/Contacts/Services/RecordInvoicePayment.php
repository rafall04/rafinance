<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Services;

use App\Domain\Contacts\Models\Invoice;
use App\Domain\Contacts\Models\InvoicePayment;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Services\PostTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mencatat pembayaran tagihan, sekaligus memasukkan uangnya ke buku besar.
 *
 * Dua hal terjadi bersamaan dan tidak boleh terpisah: baris pembayaran di
 * invoice_payments, dan transaksi pemasukan di akun yang menerima uangnya.
 * Kalau salah satu bisa terjadi tanpa yang lain, piutang bisa lunas tanpa uang
 * pernah masuk — dan itu tidak akan ketahuan sampai ada yang menghitung kas
 * fisik.
 */
final class RecordInvoicePayment
{
    public function __construct(
        private readonly PostTransaction $post,
    ) {}

    public function __invoke(Invoice $tagihan, Money $jumlah, Account $keAkun, ?string $tanggal = null): InvoicePayment
    {
        if ($jumlah->isNegative() || $jumlah->isZero()) {
            throw new InvalidArgumentException('Jumlah pembayaran harus lebih dari nol.');
        }

        if ($jumlah->compareTo($tagihan->sisa()) > 0) {
            throw new InvalidArgumentException(
                'Pembayaran melebihi sisa tagihan. Sisa '.$tagihan->sisa()->format().'.'
            );
        }

        return DB::connection('pgsql')->transaction(function () use ($tagihan, $jumlah, $keAkun, $tanggal): InvoicePayment {
            $transaksi = ($this->post)(DraftTransaction::pemasukan(
                amount: $jumlah,
                to: $keAkun,
                bookedDate: $tanggal,
                description: 'Pembayaran tagihan '.$tagihan->number,
                contactId: $tagihan->contact_id,
            ));

            $pembayaran = InvoicePayment::query()->create([
                'invoice_id' => $tagihan->getKey(),
                'transaction_id' => $transaksi->getKey(),
                'amount_minor' => $jumlah,
                'paid_at' => $tanggal ?? now(),
            ]);

            $tagihan->refresh();

            $tagihan->forceFill([
                'status' => $tagihan->sisa()->isZero() ? 'paid' : 'partial',
            ])->save();

            return $pembayaran;
        });
    }
}
