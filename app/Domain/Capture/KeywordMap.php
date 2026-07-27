<?php

declare(strict_types=1);

namespace App\Domain\Capture;

/**
 * Tebakan kategori dari kata yang benar-benar diketik orang Indonesia.
 *
 * Daftar ini sengaja memuat merek dan slang, bukan hanya istilah baku. Orang
 * mengetik "gojek", bukan "transportasi daring"; "indomaret", bukan "belanja
 * ritel"; "pertamax", bukan "bahan bakar". Parser yang hanya memahami istilah
 * baku akan gagal pada hampir setiap masukan sungguhan.
 *
 * Ini juga alasan aturan A12 masuk akal: pemetaan seperti ini menyelesaikan
 * mayoritas kasus dalam hitungan mikrodetik, tanpa panggilan jaringan, tanpa
 * biaya, dan tanpa mengirim catatan keuangan siapa pun ke pihak ketiga.
 */
final class KeywordMap
{
    /**
     * kata kunci => nama kategori yang dicari di workspace
     *
     * @var array<string, string>
     */
    private const PENGELUARAN = [
        // Transportasi
        'bensin' => 'Transportasi', 'bbm' => 'Transportasi', 'solar' => 'Transportasi',
        'pertamax' => 'Transportasi', 'pertalite' => 'Transportasi', 'dexlite' => 'Transportasi',
        'ojek' => 'Transportasi', 'ojol' => 'Transportasi', 'gojek' => 'Transportasi',
        'grab' => 'Transportasi', 'maxim' => 'Transportasi', 'angkot' => 'Transportasi',
        'busway' => 'Transportasi', 'krl' => 'Transportasi', 'kereta' => 'Transportasi',
        'parkir' => 'Transportasi', 'tol' => 'Transportasi', 'etoll' => 'Transportasi',
        'servis' => 'Transportasi', 'oli' => 'Transportasi', 'ban' => 'Transportasi',

        // Makan minum
        'makan' => 'Makan & minum', 'nasi' => 'Makan & minum', 'warteg' => 'Makan & minum',
        'warung' => 'Makan & minum', 'kopi' => 'Makan & minum', 'ngopi' => 'Makan & minum',
        'jajan' => 'Makan & minum', 'snack' => 'Makan & minum', 'gofood' => 'Makan & minum',
        'grabfood' => 'Makan & minum', 'shopeefood' => 'Makan & minum', 'catering' => 'Makan & minum',
        'sarapan' => 'Makan & minum', 'galon' => 'Makan & minum', 'air minum' => 'Makan & minum',

        // Tagihan
        'listrik' => 'Tagihan & utilitas', 'token' => 'Tagihan & utilitas', 'pln' => 'Tagihan & utilitas',
        'air' => 'Tagihan & utilitas', 'pdam' => 'Tagihan & utilitas', 'gas' => 'Tagihan & utilitas',
        'iuran' => 'Tagihan & utilitas', 'sampah' => 'Tagihan & utilitas', 'keamanan' => 'Tagihan & utilitas',
        'pajak' => 'Tagihan & utilitas', 'pbb' => 'Tagihan & utilitas', 'stnk' => 'Tagihan & utilitas',

        // Internet dan komunikasi
        'internet' => 'Internet', 'wifi' => 'Internet', 'indihome' => 'Internet',
        'pulsa' => 'Internet', 'kuota' => 'Internet', 'paket data' => 'Internet',

        // Belanja
        'belanja' => 'Belanja', 'indomaret' => 'Belanja', 'alfamart' => 'Belanja',
        'pasar' => 'Belanja', 'sayur' => 'Belanja', 'beras' => 'Belanja',
        'tokopedia' => 'Belanja', 'shopee' => 'Belanja', 'lazada' => 'Belanja',

        // Kesehatan
        'obat' => 'Kesehatan', 'apotek' => 'Kesehatan', 'dokter' => 'Kesehatan',
        'rumah sakit' => 'Kesehatan', 'bpjs' => 'Kesehatan', 'vitamin' => 'Kesehatan',

        // Usaha
        'gaji' => 'Gaji & upah', 'upah' => 'Gaji & upah', 'bonus karyawan' => 'Gaji & upah',
        'sewa' => 'Sewa', 'kontrakan' => 'Sewa', 'ruko' => 'Sewa',
        'stok' => 'Bahan & stok', 'bahan' => 'Bahan & stok', 'kulakan' => 'Bahan & stok',
        'genset' => 'Perbaikan', 'perbaikan' => 'Perbaikan', 'servis alat' => 'Perbaikan',
        'kabel' => 'Bahan & stok', 'router' => 'Bahan & stok', 'modem' => 'Bahan & stok',
    ];

    /**
     * @var array<string, string>
     */
    private const PEMASUKAN = [
        'gaji' => 'Gaji', 'gajian' => 'Gaji', 'thr' => 'Bonus', 'bonus' => 'Bonus',
        'jual' => 'Penjualan', 'penjualan' => 'Penjualan', 'omzet' => 'Penjualan',
        'bayaran' => 'Jasa', 'jasa' => 'Jasa', 'dp' => 'Jasa', 'termin' => 'Jasa',
        'iuran' => 'Iuran bulanan', 'abonemen' => 'Iuran bulanan', 'langganan' => 'Iuran bulanan',
        'hadiah' => 'Hadiah', 'angpao' => 'Hadiah',
    ];

    /**
     * Kata yang menandakan uang masuk meski tanpa tanda plus.
     *
     * @var array<int, string>
     */
    public const PENANDA_MASUK = ['terima', 'diterima', 'masuk', 'dapat', 'bayaran', 'gajian', 'omzet', 'setoran'];

    /**
     * Kata yang menandakan perpindahan antar akun sendiri.
     *
     * @var array<int, string>
     */
    public const PENANDA_PINDAH = ['pindah', 'transfer', 'tf', 'tarik', 'setor', 'topup', 'top up'];

    /**
     * Nama kategori tebakan untuk sebuah teks, atau null kalau tidak ada yang
     * cocok. Frasa dua kata diperiksa lebih dulu supaya "air minum" tidak
     * kalah oleh "air".
     */
    public static function tebak(string $teks, bool $pemasukan = false): ?string
    {
        $peta = $pemasukan ? self::PEMASUKAN : self::PENGELUARAN;
        $teks = ' '.mb_strtolower($teks).' ';

        $berfrasa = array_filter(array_keys($peta), static fn (string $k): bool => str_contains($k, ' '));

        foreach ($berfrasa as $kunci) {
            if (str_contains($teks, ' '.$kunci.' ')) {
                return $peta[$kunci];
            }
        }

        foreach ($peta as $kunci => $kategori) {
            if (str_contains($kunci, ' ')) {
                continue;
            }

            if (preg_match('/\b'.preg_quote($kunci, '/').'\b/u', $teks) === 1) {
                return $kategori;
            }
        }

        return null;
    }

    public static function menandakanMasuk(string $teks): bool
    {
        return self::mengandungSalahSatu($teks, self::PENANDA_MASUK);
    }

    public static function menandakanPindah(string $teks): bool
    {
        return self::mengandungSalahSatu($teks, self::PENANDA_PINDAH);
    }

    /**
     * @param  array<int, string>  $daftar
     */
    private static function mengandungSalahSatu(string $teks, array $daftar): bool
    {
        $teks = mb_strtolower($teks);

        foreach ($daftar as $kata) {
            if (preg_match('/\b'.preg_quote($kata, '/').'\b/u', $teks) === 1) {
                return true;
            }
        }

        return false;
    }
}
