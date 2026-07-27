<?php

declare(strict_types=1);

namespace App\Domain\Capture;

use App\Domain\Capture\Models\InputAlias;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Projects\Models\Project;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Membaca "50k bensin bca" menjadi transaksi.
 *
 * Tanpa LLM, dan itu keputusan produk bukan penghematan (aturan A12). Jalur
 * input utama harus punya waktu tanggap yang bisa diprediksi: orang mengetik di
 * Telegram sambil berdiri di SPBU, dan menunggu tiga detik untuk penyedia
 * pihak ketiga yang mungkin sedang lambat adalah cara tercepat membuat orang
 * berhenti mencatat.
 *
 * Yang lebih penting: parser ini TIDAK PERNAH menolak. Yang tidak bisa dibaca
 * tetap disimpan sebagai inbox item, dan dilengkapi nanti dari kanal mana pun.
 */
final class RuleBasedParser
{
    /**
     * Pengali akhiran. `m` diperlakukan sebagai juta, mengikuti kebiasaan
     * menulis "5m" untuk lima juta — bukan mili apa pun.
     *
     * @var array<string, int>
     */
    private const PENGALI = [
        'k' => 1_000,
        'rb' => 1_000,
        'ribu' => 1_000,
        'jt' => 1_000_000,
        'juta' => 1_000_000,
        'm' => 1_000_000,
    ];

    public function __invoke(string $teks, string $currency = 'IDR'): NormalizedDraft
    {
        $asli = trim($teks);

        // Notifikasi m-banking dan e-wallet dibaca lebih dulu dengan pola
        // khususnya. Ini jalur yang paling sering dipakai orang di lapangan —
        // dan yang paling akurat, karena nominalnya datang dari banknya sendiri
        // dan tidak ada kesempatan salah ketik.
        $dariBank = $this->bacaNotifikasiBank($asli, $currency);

        if ($dariBank !== null) {
            return $dariBank;
        }

        $sisa = $asli;
        $catatan = [];

        $alias = $this->cariAlias($sisa);

        [$sisa, $projectTag, $projectId] = $this->ambilProyek($sisa);
        [$sisa, $arah, $eksplisit] = $this->ambilArah($sisa);
        [$sisa, $nominal] = $this->ambilNominal($sisa, $currency);

        $akun = null;
        $akunTujuan = null;

        if ($arah === TransactionKind::Transfer) {
            [$sisa, $akun, $akunTujuan] = $this->ambilAkunPindah($sisa);
        } else {
            [$sisa, $akun] = $this->ambilAkun($sisa);
        }

        [$sisa, $kontak] = $this->ambilKontak($sisa);

        $keterangan = $this->rapikan($sisa);

        // Arah belum eksplisit dan tidak ada penanda apa pun: pakai tebakan
        // dari kata-katanya sendiri.
        if (! $eksplisit && $arah === TransactionKind::Expense && KeywordMap::menandakanMasuk($asli)) {
            $arah = TransactionKind::Income;
        }

        $kategori = $this->tebakKategori($keterangan !== '' ? $keterangan : $asli, $arah);

        // Alias buatan pengguna menimpa tebakan: ia tahu maksudnya sendiri.
        if ($alias !== null) {
            $akun ??= $alias->account_id !== null ? Account::query()->find($alias->account_id) : null;
            $kategori = $alias->category_id !== null ? Category::query()->find($alias->category_id) : $kategori;
            $projectId ??= $alias->project_id;

            if ($keterangan === '') {
                $keterangan = Str::ucfirst($alias->keyword);
            }
        }

        // Akun tidak disebut: pakai satu-satunya akun uang kalau memang cuma
        // ada satu. Kalau ada beberapa, biarkan kosong dan tanyakan — menebak
        // akun yang salah lebih merepotkan daripada bertanya.
        if ($akun === null && $arah !== TransactionKind::Transfer) {
            $akunUang = Account::query()->uang()->aktif()->get();

            if ($akunUang->count() === 1) {
                $akun = $akunUang->first();
            } elseif ($akunUang->count() > 1) {
                $catatan[] = 'Akun belum disebut.';
            }
        }

        if ($nominal === null) {
            $catatan[] = 'Nominal tidak terbaca.';
        }

        return new NormalizedDraft(
            rawText: $asli,
            kind: $arah,
            amount: $nominal,
            accountId: $akun?->getKey(),
            toAccountId: $akunTujuan?->getKey(),
            categoryId: $kategori?->getKey(),
            projectId: $projectId,
            projectTag: $projectTag,
            contactName: $kontak,
            description: $keterangan !== '' ? $keterangan : null,
            catatan: $catatan,
        );
    }

    /**
     * Membaca notifikasi bank yang diteruskan, dan mencocokkan penyedianya
     * dengan akun yang sudah ada.
     */
    private function bacaNotifikasiBank(string $teks, string $currency): ?NormalizedDraft
    {
        $bank = app(BankNotificationParser::class);

        if (! $bank->sepertinyaNotifikasiBank($teks)) {
            return null;
        }

        $hasil = $bank($teks, $currency);

        if ($hasil === null) {
            return null;
        }

        // Akun dicocokkan dari nama penyedianya: notifikasi BCA masuk ke akun
        // yang pengguna namai "BCA".
        $akun = $this->akunUang()->first(
            fn (Account $satu): bool => mb_strtolower($satu->name) === mb_strtolower($hasil['penyedia'])
        );

        $catatan = [];

        if ($akun === null) {
            $catatan[] = 'Belum ada akun bernama '.$hasil['penyedia'].'.';
        }

        return new NormalizedDraft(
            rawText: $teks,
            kind: $hasil['arah'],
            amount: $hasil['nominal'],
            accountId: $akun?->getKey(),
            categoryId: $this->tebakKategori($teks, $hasil['arah'])?->getKey(),
            contactName: $hasil['pihak'],
            description: $hasil['pihak'] !== null
                ? $hasil['penyedia'].' · '.$hasil['pihak']
                : $hasil['penyedia'],
            catatan: $catatan,
        );
    }

    /**
     * Pintasan buatan pengguna: satu kata yang sudah punya arti tetap baginya.
     */
    private function cariAlias(string $teks): ?InputAlias
    {
        $kata = collect(preg_split('/\s+/u', mb_strtolower(trim($teks))) ?: [])
            ->filter()
            ->all();

        if ($kata === []) {
            return null;
        }

        return InputAlias::query()->whereIn('keyword', $kata)->orderByDesc('use_count')->first();
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function ambilProyek(string $teks): array
    {
        if (preg_match('/#([\p{L}\p{N}_-]+)/u', $teks, $cocok) !== 1) {
            return [$teks, null, null];
        }

        $tag = $cocok[1];
        $sisa = trim(str_replace($cocok[0], ' ', $teks));

        $proyek = Project::query()
            ->whereRaw('lower(replace(name, \' \', \'\')) = ?', [mb_strtolower(str_replace(' ', '', $tag))])
            ->first();

        return [$sisa, $tag, $proyek?->getKey()];
    }

    /**
     * @return array{0: string, 1: TransactionKind, 2: bool} sisa, arah, apakah eksplisit
     */
    private function ambilArah(string $teks): array
    {
        if (KeywordMap::menandakanPindah($teks)) {
            $bersih = preg_replace('/\b(pindah|transfer|tf|topup|top up)\b/iu', ' ', $teks) ?? $teks;

            return [trim($bersih), TransactionKind::Transfer, true];
        }

        if (preg_match('/(^|\s)\+\s*(?=[0-9])/u', $teks) === 1) {
            return [trim(preg_replace('/(^|\s)\+\s*(?=[0-9])/u', ' ', $teks) ?? $teks), TransactionKind::Income, true];
        }

        if (preg_match('/(^|\s)-\s*(?=[0-9])/u', $teks) === 1) {
            return [trim(preg_replace('/(^|\s)-\s*(?=[0-9])/u', ' ', $teks) ?? $teks), TransactionKind::Expense, true];
        }

        return [$teks, TransactionKind::Expense, false];
    }

    /**
     * @return array{0: string, 1: ?Money}
     */
    private function ambilNominal(string $teks, string $currency): array
    {
        $akhiran = implode('|', array_keys(self::PENGALI));

        if (preg_match('/(?<![\p{L}\p{N}])([0-9][0-9.,]*)\s*('.$akhiran.')?(?![\p{L}\p{N}])/iu', $teks, $cocok) !== 1) {
            return [$teks, null];
        }

        try {
            $nominal = Money::parse($cocok[1], $currency);
        } catch (\InvalidArgumentException) {
            return [$teks, null];
        }

        $pengali = isset($cocok[2]) && $cocok[2] !== ''
            ? (self::PENGALI[mb_strtolower($cocok[2])] ?? 1)
            : 1;

        $nominal = $nominal->multipliedBy($pengali);

        // Hanya kemunculan pertama yang dibuang: "50k bensin 2 liter" tetap
        // menyisakan "2 liter" sebagai keterangan.
        $posisi = mb_strpos($teks, $cocok[0]);
        $sisa = $posisi === false
            ? $teks
            : mb_substr($teks, 0, $posisi).' '.mb_substr($teks, $posisi + mb_strlen($cocok[0]));

        return [trim($sisa), $nominal->isZero() ? null : $nominal];
    }

    /**
     * @return array{0: string, 1: ?Account}
     */
    private function ambilAkun(string $teks): array
    {
        $akun = $this->akunUang();

        foreach ($akun as $satu) {
            $pola = '/\b'.preg_quote(mb_strtolower($satu->name), '/').'\b/iu';

            if (preg_match($pola, $teks) === 1) {
                return [trim(preg_replace($pola, ' ', $teks) ?? $teks), $satu];
            }
        }

        return [$teks, null];
    }

    /**
     * "pindah 500k kas ke bca" — dipecah di kata "ke".
     *
     * @return array{0: string, 1: ?Account, 2: ?Account}
     */
    private function ambilAkunPindah(string $teks): array
    {
        $bagian = preg_split('/\bke\b/iu', $teks, 2);

        if ($bagian === false || count($bagian) < 2) {
            [$sisa, $akun] = $this->ambilAkun($teks);

            return [$sisa, $akun, null];
        }

        [$sisaAsal, $asal] = $this->ambilAkun($bagian[0]);
        [$sisaTujuan, $tujuan] = $this->ambilAkun($bagian[1]);

        return [trim($sisaAsal.' '.$sisaTujuan), $asal, $tujuan];
    }

    /**
     * Nama orang di balik sapaan. "dp event pak budi" => "Pak Budi".
     *
     * Sengaja hanya mengenali yang bersapaan. Menebak nama dari kata biasa akan
     * mengubah "beli solar genset" menjadi kontak bernama "Genset".
     *
     * @return array{0: string, 1: ?string}
     */
    private function ambilKontak(string $teks): array
    {
        $sapaan = 'pak|bu|bpk|ibu|mas|mbak|kak|om|tante|haji|hj';

        if (preg_match('/\b('.$sapaan.')\.?\s+(\p{L}+)\b/iu', $teks, $cocok) !== 1) {
            return [$teks, null];
        }

        $nama = Str::title($cocok[1].' '.$cocok[2]);
        $sisa = trim(str_replace($cocok[0], ' ', $teks));

        return [$sisa, $nama];
    }

    private function tebakKategori(string $teks, TransactionKind $arah): ?Category
    {
        $jenis = $arah->categoryKind();

        if ($jenis === null) {
            return null;
        }

        $tebakan = KeywordMap::tebak($teks, $arah === TransactionKind::Income);

        if ($tebakan === null) {
            return null;
        }

        return Category::query()
            ->aktif()
            ->where('kind', $jenis)
            ->whereRaw('lower(name) = lower(?)', [$tebakan])
            ->first();
    }

    /**
     * @return Collection<int, Account>
     */
    private function akunUang(): Collection
    {
        // Nama terpanjang diperiksa lebih dulu supaya "BCA Bisnis" tidak kalah
        // oleh "BCA".
        return Account::query()
            ->uang()
            ->aktif()
            ->get()
            ->sortByDesc(fn (Account $akun): int => mb_strlen($akun->name))
            ->values();
    }

    private function rapikan(string $teks): string
    {
        $bersih = preg_replace('/\s+/u', ' ', trim($teks)) ?? '';
        $bersih = trim($bersih, " \t\n\r\0\x0B-+.,");

        return $bersih === '' ? '' : Str::ucfirst($bersih);
    }
}
