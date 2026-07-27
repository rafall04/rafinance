<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan daftar transaksi beserta kolom saldo berjalannya.
 *
 * Inilah elemen tanda tangan Rafin. Hampir semua aplikasi keuangan mengubur
 * catatan transaksi di balik grafik; di sini buku besarnya yang jadi halaman
 * utama, dengan kolom saldo di sisi kanan persis seperti buku kas kertas.
 *
 * Saldo dihitung mundur dari saldo terkini, bukan maju dari nol. Alasannya
 * praktis: yang ditampilkan hanya satu halaman, dan menghitung maju berarti
 * membaca seluruh riwayat setiap kali seseorang membuka beranda.
 */
final class LedgerView
{
    /**
     * @return Collection<int, object{
     *     tanggal: CarbonImmutable,
     *     netHarian: Money,
     *     baris: Collection<int, object{transaksi: Transaction, delta: Money, saldo: Money}>
     * }>
     */
    public function harian(?Account $akun = null, int $limit = 50, string $currency = 'IDR'): Collection
    {
        $idAkunUang = $this->idAkunUang($akun);

        if ($idAkunUang === []) {
            return collect();
        }

        $saldoSekarang = $this->saldoTotal($idAkunUang, $currency);

        $transaksi = Transaction::query()
            ->where('status', '!=', TransactionStatus::Draft->value)
            ->whereHas('entries', fn (Builder $q) => $q->whereIn('account_id', $idAkunUang))
            ->with(['category', 'project', 'contact', 'entries'])
            ->orderByDesc('booked_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $saldo = $saldoSekarang;
        $baris = collect();

        foreach ($transaksi as $satu) {
            $delta = Money::ofMinor(
                (int) $satu->entries->whereIn('account_id', $idAkunUang)->sum('amount_minor'),
                $currency,
            );

            // Saldo yang ditampilkan adalah saldo SESUDAH transaksi ini.
            $baris->push((object) [
                'transaksi' => $satu,
                'delta' => $delta,
                'saldo' => $saldo,
            ]);

            $saldo = $saldo->minus($delta);
        }

        return $baris
            ->groupBy(fn (object $row): string => $row->transaksi->booked_date->toDateString())
            ->map(function (Collection $rows, string $tanggal) use ($currency): object {
                $net = $rows->reduce(
                    fn (Money $carry, object $row): Money => $carry->plus($row->delta),
                    Money::zero($currency),
                );

                return (object) [
                    'tanggal' => $rows->first()->transaksi->booked_date,
                    'netHarian' => $net,
                    'baris' => $rows->values(),
                ];
            })
            ->values();
    }

    /**
     * Saldo total seluruh akun uang, atau satu akun saja kalau disaring.
     */
    public function saldoTotal(array $idAkun, string $currency = 'IDR'): Money
    {
        if ($idAkun === []) {
            return Money::zero($currency);
        }

        // Saldo awal sudah masuk sebagai entries berjenis `opening`, jadi tidak
        // ditambahkan lagi di sini.
        $mutasi = (int) DB::connection('pgsql')
            ->table('entries')
            ->join('transactions', 'transactions.id', '=', 'entries.transaction_id')
            ->whereIn('entries.account_id', $idAkun)
            ->where('transactions.status', '!=', TransactionStatus::Draft->value)
            ->sum('entries.amount_minor');

        return Money::ofMinor($mutasi, $currency);
    }

    /**
     * @return array<int, string>
     */
    public function idAkunUang(?Account $akun = null): array
    {
        if ($akun !== null) {
            return [(string) $akun->getKey()];
        }

        return Account::query()->uang()->aktif()->pluck('id')->all();
    }

    /**
     * Chip saldo per akun yang tergeser di kepala beranda.
     *
     * @return Collection<int, Account>
     */
    public function akunUang(): Collection
    {
        return Account::query()
            ->uang()
            ->aktif()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
