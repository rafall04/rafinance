<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Account;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kueri pelaporan.
 *
 * Semuanya bertumpu pada satu sumber yang sama: tabel entries. Tidak ada kolom
 * ringkasan yang di-cache di sini, dan itu disengaja — angka laporan yang
 * berbeda dari buku besarnya adalah cara tercepat kehilangan kepercayaan, dan
 * begitu itu terjadi tidak ada cara meyakinkan siapa pun mana yang benar.
 *
 * Semua agregat memakai booked_date, bukan created_at (aturan A10): transaksi
 * yang dicatat tanggal 3 untuk pengeluaran tanggal 30 bulan lalu masuk ke bulan
 * lalu, sebagaimana mestinya.
 */
final class Reports
{
    /**
     * Transaksi yang benar-benar terjadi, untuk laporan yang menampilkan
     * ARUS — pemasukan, pengeluaran, pendapatan, beban.
     *
     * Dua hal dikeluarkan, dan keduanya berpasangan: transaksi yang sudah
     * dibatalkan, dan transaksi pembalik yang membatalkannya. Aturan A3
     * melarang menghapus, jadi koreksi selalu berupa pasangan yang saling
     * meniadakan — dan pasangan itu menjumlahkan nol pada saldo, tapi TIDAK
     * pada arus.
     *
     * Sebelum ini keduanya ikut terhitung. Salah catat Rp 50.000 lalu
     * membatalkannya membuat laporan bulan itu menampilkan pengeluaran
     * Rp 50.000 DAN pemasukan Rp 50.000 — uang yang tidak pernah keluar dan
     * tidak pernah masuk. Netonya benar, dan justru itu yang membuatnya sulit
     * disadari: yang salah adalah dua angka yang paling sering dibaca orang.
     *
     * Neraca sengaja TIDAK memakai saringan ini. Di sana pasangan itu memang
     * harus ikut, supaya angkanya tetap sama dengan Account::signedBalance().
     */
    private const ARUS_NYATA = "t.status = 'posted' AND t.reverses_transaction_id IS NULL";

    /**
     * Arus kas per satuan waktu.
     *
     * @return Collection<int, object{periode: string, masuk: Money, keluar: Money, net: Money}>
     */
    public function arusKas(
        DateTimeInterface $dari,
        DateTimeInterface $sampai,
        string $satuan = 'day',
        string $currency = 'IDR',
    ): Collection {
        $idAkunUang = $this->idAkunUang();

        if ($idAkunUang === []) {
            return collect();
        }

        $trunc = match ($satuan) {
            'week' => 'week',
            'month' => 'month',
            default => 'day',
        };

        $nyata = self::ARUS_NYATA;

        $baris = DB::connection('pgsql')->select(
            <<<SQL
                SELECT
                    to_char(date_trunc('{$trunc}', t.booked_date), 'YYYY-MM-DD') AS periode,
                    COALESCE(SUM(e.amount_minor) FILTER (WHERE e.amount_minor > 0), 0) AS masuk,
                    COALESCE(-SUM(e.amount_minor) FILTER (WHERE e.amount_minor < 0), 0) AS keluar
                FROM entries e
                JOIN transactions t ON t.id = e.transaction_id
                WHERE {$nyata}
                  AND t.booked_date BETWEEN ? AND ?
                  AND e.account_id = ANY(?)
                GROUP BY 1
                ORDER BY 1
            SQL,
            [
                $dari->format('Y-m-d'),
                $sampai->format('Y-m-d'),
                '{'.implode(',', $idAkunUang).'}',
            ],
        );

        return collect($baris)->map(function (object $row) use ($currency): object {
            $masuk = Money::ofMinor((int) $row->masuk, $currency);
            $keluar = Money::ofMinor((int) $row->keluar, $currency);

            return (object) [
                'periode' => (string) $row->periode,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'net' => $masuk->minus($keluar),
            ];
        });
    }

    /**
     * Pengeluaran dan pemasukan dikelompokkan per kategori.
     *
     * @return Collection<int, object{nama: string, id: ?string, total: Money, jumlah: int}>
     */
    public function perKategori(
        DateTimeInterface $dari,
        DateTimeInterface $sampai,
        string $kind = 'expense',
        string $currency = 'IDR',
    ): Collection {
        $idAkunUang = $this->idAkunUang();

        if ($idAkunUang === []) {
            return collect();
        }

        // Pengeluaran melihat sisi kredit akun uang, pemasukan sisi debit.
        $arah = $kind === 'income' ? '>' : '<';
        $nyata = self::ARUS_NYATA;

        $baris = DB::connection('pgsql')->select(
            <<<SQL
                SELECT
                    c.id AS id,
                    COALESCE(c.name, 'Belum dikategorikan') AS nama,
                    ABS(SUM(e.amount_minor)) AS total,
                    COUNT(DISTINCT t.id) AS jumlah
                FROM entries e
                JOIN transactions t ON t.id = e.transaction_id
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE {$nyata}
                  AND t.kind = ?
                  AND t.booked_date BETWEEN ? AND ?
                  AND e.account_id = ANY(?)
                  AND e.amount_minor {$arah} 0
                GROUP BY c.id, c.name
                ORDER BY total DESC
            SQL,
            [
                $kind,
                $dari->format('Y-m-d'),
                $sampai->format('Y-m-d'),
                '{'.implode(',', $idAkunUang).'}',
            ],
        );

        return collect($baris)->map(fn (object $row): object => (object) [
            'id' => $row->id === null ? null : (string) $row->id,
            'nama' => (string) $row->nama,
            'total' => Money::ofMinor((int) $row->total, $currency),
            'jumlah' => (int) $row->jumlah,
        ]);
    }

    /**
     * Ringkasan per pengelompokan bebas: akun, proyek, atau kontak.
     *
     * @return Collection<int, object{nama: string, id: ?string, masuk: Money, keluar: Money, net: Money}>
     */
    public function perDimensi(
        string $dimensi,
        DateTimeInterface $dari,
        DateTimeInterface $sampai,
        string $currency = 'IDR',
    ): Collection {
        [$tabel, $kolom, $kosong] = match ($dimensi) {
            'project' => ['projects', 't.project_id', 'Tanpa proyek'],
            'contact' => ['contacts', 't.contact_id', 'Tanpa kontak'],
            default => ['accounts', 'e.account_id', 'Tanpa akun'],
        };

        $idAkunUang = $this->idAkunUang();

        if ($idAkunUang === []) {
            return collect();
        }

        $nyata = self::ARUS_NYATA;

        $baris = DB::connection('pgsql')->select(
            <<<SQL
                SELECT
                    d.id AS id,
                    COALESCE(d.name, ?) AS nama,
                    COALESCE(SUM(e.amount_minor) FILTER (WHERE e.amount_minor > 0), 0) AS masuk,
                    COALESCE(-SUM(e.amount_minor) FILTER (WHERE e.amount_minor < 0), 0) AS keluar
                FROM entries e
                JOIN transactions t ON t.id = e.transaction_id
                LEFT JOIN {$tabel} d ON d.id = {$kolom}
                WHERE {$nyata}
                  AND t.booked_date BETWEEN ? AND ?
                  AND e.account_id = ANY(?)
                GROUP BY d.id, d.name
                ORDER BY keluar DESC, masuk DESC
            SQL,
            [
                $kosong,
                $dari->format('Y-m-d'),
                $sampai->format('Y-m-d'),
                '{'.implode(',', $idAkunUang).'}',
            ],
        );

        return collect($baris)->map(function (object $row) use ($currency): object {
            $masuk = Money::ofMinor((int) $row->masuk, $currency);
            $keluar = Money::ofMinor((int) $row->keluar, $currency);

            return (object) [
                'id' => $row->id === null ? null : (string) $row->id,
                'nama' => (string) $row->nama,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'net' => $masuk->minus($keluar),
            ];
        });
    }

    /**
     * Laba rugi: pendapatan dikurangi beban dalam satu periode.
     *
     * @return object{pendapatan: Money, beban: Money, laba: Money}
     */
    public function labaRugi(DateTimeInterface $dari, DateTimeInterface $sampai, string $currency = 'IDR'): object
    {
        $nyata = self::ARUS_NYATA;

        $baris = DB::connection('pgsql')->selectOne(
            <<<SQL
                SELECT
                    COALESCE(-SUM(e.amount_minor) FILTER (WHERE a.type = 'income'), 0) AS pendapatan,
                    COALESCE(SUM(e.amount_minor) FILTER (WHERE a.type = 'expense'), 0) AS beban
                FROM entries e
                JOIN transactions t ON t.id = e.transaction_id
                JOIN accounts a ON a.id = e.account_id
                WHERE {$nyata}
                  AND t.booked_date BETWEEN ? AND ?
            SQL,
            [$dari->format('Y-m-d'), $sampai->format('Y-m-d')],
        );

        $pendapatan = Money::ofMinor((int) ($baris->pendapatan ?? 0), $currency);
        $beban = Money::ofMinor((int) ($baris->beban ?? 0), $currency);

        return (object) [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'laba' => $pendapatan->minus($beban),
        ];
    }

    /**
     * Neraca per satu tanggal.
     *
     * @return object{harta: Money, utang: Money, modal: Money, seimbang: bool}
     */
    public function neraca(DateTimeInterface $per, string $currency = 'IDR'): object
    {
        // Saldo awal tidak dijumlahkan terpisah: ia sudah berupa entries
        // berjenis `opening` lengkap dengan sisi lawannya di modal. Kalau
        // dijumlahkan lagi di sini, harta akan terhitung dua kali dan neraca
        // justru jadi tidak seimbang.
        $baris = DB::connection('pgsql')->select(
            <<<'SQL'
                SELECT a.type AS tipe,
                       COALESCE(SUM(e.amount_minor), 0) AS saldo
                FROM accounts a
                LEFT JOIN entries e ON e.account_id = a.id
                LEFT JOIN transactions t ON t.id = e.transaction_id
                    AND t.status <> ?
                    AND t.booked_date <= ?
                WHERE t.id IS NOT NULL OR e.id IS NULL
                GROUP BY a.type
            SQL,
            [TransactionStatus::Draft->value, $per->format('Y-m-d')],
        );

        $per_tipe = collect($baris)->mapWithKeys(fn (object $row): array => [
            (string) $row->tipe => (int) $row->saldo,
        ]);

        $harta = Money::ofMinor($per_tipe->get(AccountType::Asset->value, 0), $currency);
        $utang = Money::ofMinor(-$per_tipe->get(AccountType::Liability->value, 0), $currency);
        $modalTercatat = Money::ofMinor(-$per_tipe->get(AccountType::Equity->value, 0), $currency);

        // Laba berjalan masuk ke modal: pendapatan dan beban belum ditutup ke
        // ekuitas selama periodenya belum ditutup.
        $laba = Money::ofMinor(
            -$per_tipe->get(AccountType::Income->value, 0) - $per_tipe->get(AccountType::Expense->value, 0),
            $currency,
        );

        $modal = $modalTercatat->plus($laba);

        return (object) [
            'harta' => $harta,
            'utang' => $utang,
            'modal' => $modal,
            // Harta = Utang + Modal. Kalau ini pernah false, ada entries yang
            // masuk tanpa lewat trigger keseimbangan — dan itu berita besar.
            'seimbang' => $harta->equals($utang->plus($modal)),
        ];
    }

    /**
     * Perbandingan dua periode berdampingan.
     *
     * @return object{sekarang: object, sebelumnya: object, selisih: Money}
     */
    public function banding(
        DateTimeInterface $dari,
        DateTimeInterface $sampai,
        string $currency = 'IDR',
    ): object {
        $awal = CarbonImmutable::instance($dari);
        $akhir = CarbonImmutable::instance($sampai);
        $panjang = $awal->diffInDays($akhir) + 1;

        $sebelumnyaAkhir = $awal->subDay();
        $sebelumnyaAwal = $sebelumnyaAkhir->subDays($panjang - 1);

        $sekarang = $this->labaRugi($awal, $akhir, $currency);
        $sebelumnya = $this->labaRugi($sebelumnyaAwal, $sebelumnyaAkhir, $currency);

        return (object) [
            'sekarang' => $sekarang,
            'sebelumnya' => $sebelumnya,
            'selisih' => $sekarang->laba->minus($sebelumnya->laba),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function idAkunUang(): array
    {
        return Account::query()->uang()->pluck('id')->all();
    }
}
