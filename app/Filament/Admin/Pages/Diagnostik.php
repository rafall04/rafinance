<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Halaman diagnostik untuk pengelola.
 *
 * SENGAJA HANYA MEMBACA.
 *
 * Godaannya besar untuk menambahkan formulir "isi Google Client Secret di sini",
 * dan itu memang lebih nyaman. Tapi ia memindahkan rahasia dari berkas di server
 * — yang hanya bisa dibaca lewat SSH — menjadi sesuatu yang bisa diambil siapa
 * pun yang berhasil menguasai satu sesi admin, dari mana pun di internet.
 *
 * Di susunan Docker Rafin hal itu juga tidak akan bekerja: .env tidak ada di
 * dalam kontainer, nilainya masuk sebagai variabel lingkungan compose, dan
 * perubahannya baru berlaku setelah kontainer dimulai ulang.
 *
 * Karena itu halaman ini menjawab pertanyaan "apa yang kurang" dan menyerahkan
 * "bagaimana mengisinya" ke deploy/set-secret.sh di server.
 *
 * ATURAN A5. Halaman ini berada di App\Filament\Admin dengan sengaja: arch test
 * memindai direktori itu dan akan menggagalkan build kalau ada yang menambahkan
 * rujukan ke model finansial di sini. Menaruhnya di luar sana akan membuatnya
 * lolos dari pemindaian — kenyamanan yang harganya adalah satu-satunya penjaga
 * yang kita punya.
 *
 * ATURAN A6. Tidak ada satu pun nilai rahasia yang ditampilkan. Yang dilaporkan
 * hanya "terisi" atau "kosong", dan panjangnya pun tidak.
 */
class Diagnostik extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Diagnostik';

    protected static ?string $title = 'Diagnostik sistem';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.admin.pages.diagnostik';

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'integrasi' => $this->integrasi(),
            'infrastruktur' => $this->infrastruktur(),
            'bendera' => $this->bendera(),
        ];
    }

    /**
     * Status tiap integrasi luar.
     *
     * "kosong" bukan galat: Google yang belum diisi berarti tombolnya memang
     * tidak muncul, dan itu perilaku yang benar. Yang dibedakan adalah antara
     * "belum disiapkan" dan "disiapkan setengah" — yang kedua itulah yang
     * menghasilkan kegagalan membingungkan di tangan pengguna.
     *
     * @return list<array{nama: string, keadaan: string, catatan: string}>
     */
    private function integrasi(): array
    {
        $google = [
            'id' => filled(config('services.google.client_id')),
            'rahasia' => filled(config('services.google.client_secret')),
        ];

        $telegram = [
            // config(), bukan env(). env() mengembalikan null begitu config:cache
            // dijalankan — yaitu tepat di produksi, satu-satunya tempat halaman
            // ini benar-benar dipakai.
            'token' => filled(config('rafin.telegram.token')),
            'rahasia' => filled(config('rafin.telegram.webhook_secret')),
        ];

        $surel = config('mail.default');

        return [
            [
                'nama' => 'Masuk lewat Google',
                'keadaan' => match (true) {
                    $google['id'] && $google['rahasia'] => 'siap',
                    ! $google['id'] && ! $google['rahasia'] => 'mati',
                    default => 'separuh',
                },
                'catatan' => match (true) {
                    $google['id'] && $google['rahasia'] => 'Tombol "Masuk dengan Google" muncul di halaman masuk dan daftar.',
                    ! $google['id'] && ! $google['rahasia'] => 'Belum disiapkan. Tombolnya sengaja tidak muncul.',
                    $google['id'] => 'Client ID terisi tapi secret kosong. Isi dengan deploy/set-secret.sh GOOGLE_CLIENT_SECRET.',
                    default => 'Secret terisi tapi Client ID kosong.',
                },
            ],
            [
                'nama' => 'Bot Telegram',
                'keadaan' => match (true) {
                    $telegram['token'] && $telegram['rahasia'] => 'siap',
                    ! $telegram['token'] => 'mati',
                    default => 'separuh',
                },
                'catatan' => match (true) {
                    $telegram['token'] && $telegram['rahasia'] => 'Token dan rahasia webhook terisi. Pastikan webhook sudah didaftarkan dengan rafin:telegram:webhook.',
                    ! $telegram['token'] => 'Token belum diisi. Kanal Telegram tidak menerima apa pun.',
                    default => 'Token terisi tapi rahasia webhook kosong — webhook akan menolak setiap kiriman.',
                },
            ],
            [
                'nama' => 'Pengiriman surel',
                'keadaan' => $surel === 'log' ? 'separuh' : 'siap',
                'catatan' => $surel === 'log'
                    ? 'MAIL_MAILER=log. Surel setel-ulang kata sandi hanya masuk ke log — pengguna yang lupa sandinya akan terjebak.'
                    : 'Driver: '.$surel.'.',
            ],
        ];
    }

    /**
     * Kesehatan infrastruktur. Semua pemeriksaan dibungkus try: halaman
     * diagnostik yang ikut tumbang saat ada yang tidak beres adalah halaman
     * yang gagal justru di satu-satunya saat ia dibutuhkan.
     *
     * @return list<array{nama: string, nilai: string, sehat: bool}>
     */
    private function infrastruktur(): array
    {
        $hasil = [];

        $hasil[] = ['nama' => 'PHP', 'nilai' => PHP_VERSION, 'sehat' => version_compare(PHP_VERSION, '8.3', '>=')];

        try {
            $versi = (string) DB::selectOne('SHOW server_version')->server_version;
            $hasil[] = ['nama' => 'PostgreSQL', 'nilai' => $versi, 'sehat' => version_compare($versi, '16', '>=')];
        } catch (Throwable $e) {
            $hasil[] = ['nama' => 'PostgreSQL', 'nilai' => 'tidak terjangkau', 'sehat' => false];
        }

        try {
            Redis::connection()->ping();
            $hasil[] = ['nama' => 'Redis', 'nilai' => 'menjawab', 'sehat' => true];
        } catch (Throwable $e) {
            $hasil[] = [
                'nama' => 'Redis',
                'nilai' => config('queue.default') === 'redis' ? 'TIDAK menjawab' : 'tidak dipakai',
                'sehat' => config('queue.default') !== 'redis',
            ];
        }

        try {
            Cache::put('rafin:diagnostik', 1, 5);
            $hasil[] = ['nama' => 'Cache', 'nilai' => config('cache.default').' — bisa ditulis', 'sehat' => true];
        } catch (Throwable $e) {
            $hasil[] = ['nama' => 'Cache', 'nilai' => config('cache.default').' — GAGAL ditulis', 'sehat' => false];
        }

        // Migration yang belum jalan adalah penyebab galat paling membingungkan
        // di produksi: kolom yang dicari kode ternyata belum ada.
        try {
            $terpasang = DB::table('migrations')->count();
            $berkas = count(File::files(database_path('migrations')));
            $hasil[] = [
                'nama' => 'Migration',
                'nilai' => $terpasang.' terpasang dari '.$berkas.' berkas',
                'sehat' => $terpasang >= $berkas,
            ];
        } catch (Throwable $e) {
            $hasil[] = ['nama' => 'Migration', 'nilai' => 'tidak bisa dibaca', 'sehat' => false];
        }

        try {
            $gagal = DB::table('failed_jobs')->count();
            $hasil[] = [
                'nama' => 'Job gagal',
                'nilai' => $gagal === 0 ? 'tidak ada' : $gagal.' menunggu diperiksa',
                'sehat' => $gagal === 0,
            ];
        } catch (Throwable $e) {
            $hasil[] = ['nama' => 'Job gagal', 'nilai' => 'tabel tidak terbaca', 'sehat' => false];
        }

        $hasil[] = [
            'nama' => 'Debug',
            'nilai' => config('app.debug') ? 'MENYALA' : 'mati',
            'sehat' => ! config('app.debug'),
        ];

        return $hasil;
    }

    /**
     * Feature flag. Aturan A12 menuntut keduanya mati; halaman ini
     * memperlihatkannya supaya tidak ada yang menyala diam-diam.
     *
     * @return list<array{nama: string, aktif: bool, catatan: string}>
     */
    private function bendera(): array
    {
        return [
            [
                'nama' => 'Parser LLM',
                'aktif' => (bool) config('rafin.features.llm_parser', false),
                'catatan' => 'Aturan A12: tidak ada LLM di jalur input utama.',
            ],
            [
                'nama' => 'OCR struk',
                'aktif' => (bool) config('rafin.features.ocr', false),
                'catatan' => 'Belum diputuskan sebagai produk.',
            ],
        ];
    }
}
