<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci aplikasi, ditegakkan di server.
 *
 * Sebelumnya seluruh penegakannya ada di JavaScript: sebuah pewaktu menaruh
 * penanda di sessionStorage lalu memindahkan halaman ke /app/kunci. Itu
 * menghentikan orang yang menunggu, dan tidak menghentikan siapa pun yang
 * mengetik /app/laporan di bilah alamat, menekan tombol kembali, atau membuka
 * ponsel dengan JavaScript dimatikan. Untuk fitur yang alasan keberadaannya
 * adalah "seseorang sedang memegang ponsel Anda", itu bukan kunci — itu gambar
 * kunci.
 *
 * Yang menentukan sekarang adalah sesi di server. Pewaktu di sisi klien tetap
 * ada dan tetap berguna: ia memindahkan layar lebih cepat daripada menunggu
 * permintaan berikutnya, sehingga nominal tidak menganggur di layar. Tapi ia
 * bukan lagi satu-satunya yang menjaga.
 *
 * Jendela diamnya bergeser: setiap permintaan yang lolos memperbarui stempel
 * waktunya. Orang yang sedang memakai aplikasinya tidak pernah terkunci di
 * tengah pengetikan, dan ponsel yang ditinggalkan di meja terkunci sendiri.
 */
final class PastikanAplikasiTerbuka
{
    /** Kunci sesi tempat stempel waktu pembukaan terakhir disimpan. */
    public const SESSION_KEY = 'rafin.kunci_dibuka_pada';

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        // Tanpa pengguna, middleware auth yang bertugas. Tanpa PIN terpasang,
        // tidak ada yang perlu dibuka.
        if ($pengguna === null || ! $pengguna->punyaKunciAplikasi()) {
            return $next($request);
        }

        // Halaman kuncinya sendiri harus selalu bisa dibuka, kalau tidak
        // pengalihannya berputar tanpa akhir.
        if ($request->routeIs('app.kunci')) {
            return $next($request);
        }

        if ($this->terkunci($request)) {
            // Permintaan latar — Livewire, antrean PWA — tidak diberi
            // pengalihan HTML yang tidak bisa mereka pakai. 423 Locked
            // menyatakan keadaannya dengan jujur, dan klien memutuskan sendiri
            // apa yang ditampilkan.
            if ($request->expectsJson()) {
                abort(423, 'Aplikasi sedang terkunci.');
            }

            return redirect()->route('app.kunci');
        }

        $this->perbaruiStempel($request);

        return $next($request);
    }

    private function terkunci(Request $request): bool
    {
        $dibukaPada = $request->session()->get(self::SESSION_KEY);

        if (! is_int($dibukaPada)) {
            return true;
        }

        return (time() - $dibukaPada) > $this->jendelaDetik();
    }

    private function perbaruiStempel(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, time());
    }

    /**
     * Menandai sesi ini sudah dibuka. Dipanggil sesudah PIN cocok.
     *
     * Memakai helper session() dan bukan $request->session(): komponen
     * Livewire memanggilnya dari luar daur permintaan biasa, dan
     * $request->session() melempar RuntimeException kalau penyimpanan sesinya
     * belum terpasang di objek Request itu.
     */
    public static function tandaiTerbuka(): void
    {
        session()->put(self::SESSION_KEY, time());
    }

    /**
     * Mengunci kembali sekarang juga.
     */
    public static function kunci(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function jendelaDetik(): int
    {
        return max(1, (int) config('rafin.app_lock_idle_minutes', 5)) * 60;
    }
}
