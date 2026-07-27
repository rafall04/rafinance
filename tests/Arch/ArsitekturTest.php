<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Aturan mutlak yang ditegakkan terhadap kode, bukan terhadap perilaku
|--------------------------------------------------------------------------
|
| Test perilaku menangkap kesalahan yang sudah terjadi. Test di berkas ini
| menangkap kesalahan sebelum sempat ditulis — ia menggagalkan build ketika
| seseorang menambahkan sesuatu yang secara struktur dilarang.
|
| Sebagian ditulis sebagai pemindai berkas dan bukan sebagai ekspektasi Pest
| terhadap namespace. Alasannya praktis: App\Filament\Admin belum berisi kelas
| apa pun sampai FASE 6, dan ekspektasi terhadap namespace kosong lolos begitu
| saja tanpa memeriksa apa-apa. Pemindai berkas sudah menjaga sejak hari ini.
|
*/

/**
 * @return array<string, string> path relatif => isi berkas
 */
function berkasPhp(string $direktori): array
{
    $absolut = base_path($direktori);

    if (! is_dir($absolut)) {
        return [];
    }

    $hasil = [];

    foreach (Finder::create()->files()->in($absolut)->name('*.php') as $berkas) {
        $hasil[str_replace('\\', '/', $berkas->getRelativePathname())] = (string) file_get_contents($berkas->getRealPath());
    }

    return $hasil;
}

function harusMemuat(string $isi, string $jarum, string $pesan): void
{
    expect(str_contains($isi, $jarum))->toBeTrue($pesan);
}

function tidakBolehMemuat(string $isi, string $jarum, string $pesan): void
{
    expect(str_contains($isi, $jarum))->toBeFalse($pesan);
}

function tidakBolehCocok(string $isi, string $pola, string $pesan): void
{
    expect(preg_match($pola, $isi))->toBe(0, $pesan);
}

/*
|--------------------------------------------------------------------------
| A5 — panel admin platform tidak boleh menyentuh data finansial
|--------------------------------------------------------------------------
*/

it('tidak menyentuh model finansial dari panel admin platform', function (): void {
    // Daftar dari bagian 2 dokumen rancangan, ditambah Account dan
    // Reconciliation yang sama-sama membuka jalan ke saldo pengguna.
    //
    // Money TIDAK ada di daftar ini, dan itu disengaja: ia value object umum
    // yang juga dipakai untuk menampilkan harga plan dan pembayaran langganan
    // — uang yang dibayarkan KEPADA Rafin, bukan uang di dalam buku pengguna.
    // Yang dijaga adalah jalan menuju datanya, bukan tipe datanya.
    $terlarang = [
        'Transaction', 'Entry', 'Attachment', 'AuditLog', 'Budget', 'BudgetPeriod',
        'Invoice', 'InvoiceItem', 'InvoicePayment', 'Account', 'Reconciliation',
    ];

    // Nama tabel, bukan hanya nama kelas: DB::table('transactions') akan lolos
    // dari pemindaian kelas dan tetap membaca nominal orang.
    $tabelTerlarang = ['transactions', 'entries', 'attachments', 'audit_logs', 'budgets', 'invoices', 'period_locks'];

    $berkas = berkasPhp('app/Filament');
    $diperiksa = 0;

    foreach ($berkas as $path => $isi) {
        foreach ($terlarang as $kelas) {
            tidakBolehCocok(
                $isi,
                "/\\b(use\\s+App\\\\[\\w\\\\]*\\b{$kelas}\\b|\\b{$kelas}::)/",
                "app/Filament/{$path} menyentuh {$kelas}. Panel admin platform tidak boleh punya "
                .'jalan menuju nominal transaksi pengguna (aturan A5).'
            );
            $diperiksa++;
        }

        foreach ($tabelTerlarang as $tabel) {
            tidakBolehCocok(
                $isi,
                "/['\"]{$tabel}['\"]/",
                "app/Filament/{$path} menyebut tabel {$tabel} secara langsung. Angka untuk dasbor "
                .'harus lewat PlatformStats, yang bentuk kembaliannya hanya int (aturan A5).'
            );
            $diperiksa++;
        }
    }

    // FASE 0 belum punya resource admin. Yang dipastikan di sini adalah
    // penjaganya sudah terpasang dan menunjuk ke tempat yang benar.
    expect(is_dir(base_path('app/Filament/Admin')))->toBeTrue(
        'Direktori app/Filament/Admin hilang. Panel admin platform harus tetap di sana supaya '
        .'pemindai A5 punya sesuatu untuk dijaga.'
    );

    expect($diperiksa)->toBeGreaterThanOrEqual(0);
});

/*
|--------------------------------------------------------------------------
| A1 — tidak ada nominal bertipe pecahan, di mana pun
|--------------------------------------------------------------------------
*/

it('tidak memakai float, double, atau decimal di migration mana pun', function (): void {
    $migration = berkasPhp('database/migrations');

    expect($migration)->not->toBeEmpty();

    foreach ($migration as $path => $isi) {
        foreach (['float', 'double', 'decimal', 'unsignedDecimal'] as $tipe) {
            tidakBolehMemuat(
                $isi,
                "->{$tipe}(",
                "database/migrations/{$path} memakai kolom {$tipe}. Nominal disimpan sebagai "
                .'BIGINT minor unit (aturan A1).'
            );
        }
    }
});

it('menyimpan setiap kolom nominal sebagai bigInteger', function (): void {
    $kolomNominal = 0;

    foreach (berkasPhp('database/migrations') as $path => $isi) {
        preg_match_all("/->(\\w+)\\('(\\w*_minor)'/", $isi, $cocok, PREG_SET_ORDER);

        foreach ($cocok as $baris) {
            expect($baris[1])->toBe(
                'bigInteger',
                "database/migrations/{$path}: kolom {$baris[2]} dibuat dengan {$baris[1]}(), "
                .'seharusnya bigInteger() (aturan A1).'
            );
            $kolomNominal++;
        }
    }

    // FASE 0 belum punya kolom nominal — tabel ledger baru lahir di FASE 1.
    // Penjagaannya sudah berjalan supaya kolom pertama pun tidak lolos.
    expect($kolomNominal)->toBeGreaterThanOrEqual(0);
});

it('tidak menerima float di seluruh jalur nominal', function (): void {
    foreach (berkasPhp('app') as $path => $isi) {
        tidakBolehCocok(
            $isi,
            '/(float|double)\s+\$\w*(amount|minor|balance|saldo|total|harga|price)/i',
            "app/{$path} punya parameter nominal bertipe pecahan (aturan A1)."
        );
    }
});

/*
|--------------------------------------------------------------------------
| A4 — query yang tidak ter-scope
|--------------------------------------------------------------------------
*/

it('tidak mencari model dengan ID mentah dari controller atau komponen Livewire', function (): void {
    foreach (['app/Http/Controllers', 'app/Livewire'] as $direktori) {
        foreach (berkasPhp($direktori) as $path => $isi) {
            tidakBolehCocok(
                $isi,
                '/\b[A-Z]\w+::(find|findOrFail|firstWhere)\s*\(/',
                "{$direktori}/{$path} mencari model lewat ID mentah. Pakai route model binding "
                .'dengan scopeBindings() supaya penyaringan workspace tidak bisa terlupakan (aturan A4).'
            );
        }
    }
});

it('tidak melangkahi global scope workspace dari lapis HTTP', function (): void {
    foreach (['app/Http', 'app/Livewire'] as $direktori) {
        foreach (berkasPhp($direktori) as $path => $isi) {
            foreach (['withoutGlobalScope', 'withoutGlobalScopes', 'DB::table('] as $lubang) {
                tidakBolehMemuat(
                    $isi,
                    $lubang,
                    "{$direktori}/{$path} memakai {$lubang}. Itu melangkahi penyaringan workspace (aturan A4)."
                );
            }
        }
    }
});

/*
|--------------------------------------------------------------------------
| A7 — ULID di mana-mana
|--------------------------------------------------------------------------
*/

it('tidak memakai primary key auto-increment di tabel milik Rafin', function (): void {
    // Tabel infrastruktur Laravel tidak pernah terekspos ke luar: tidak muncul
    // di URL, tidak di API, tidak di antrean offline.
    $dikecualikan = [
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
    ];

    foreach (berkasPhp('database/migrations') as $path => $isi) {
        if (in_array($path, $dikecualikan, true)) {
            continue;
        }

        tidakBolehMemuat(
            $isi,
            '$table->id()',
            "database/migrations/{$path} memakai primary key auto-increment. Semua ID milik "
            .'Rafin adalah ULID, supaya bisa dibuat di sisi client untuk antrean offline dan '
            .'idempotency (aturan A7).'
        );
    }
});

/*
|--------------------------------------------------------------------------
| Kebersihan umum
|--------------------------------------------------------------------------
*/

it('memakai strict_types di seluruh kode aplikasi', function (): void {
    // Inilah yang membuat Money::ofMinor(1.5) melempar TypeError alih-alih
    // diam-diam memotong jadi 1. PHP memakai directive berkas PEMANGGIL, jadi
    // penjagaan tipe di dalam Money tidak berarti apa-apa tanpa aturan ini —
    // termasuk di berkas hasil publish paket pihak ketiga.
    $berkas = berkasPhp('app');

    expect($berkas)->not->toBeEmpty();

    foreach ($berkas as $path => $isi) {
        harusMemuat(
            $isi,
            'declare(strict_types=1)',
            "app/{$path} tidak memakai declare(strict_types=1)."
        );
    }
});

it('tidak menyimpan rahasia apa pun di luar .env', function (): void {
    $polaRahasia = [
        '/\bbot\d{6,}:[A-Za-z0-9_-]{30,}/' => 'token bot Telegram',
        '/\bsk_live_[A-Za-z0-9]{16,}/' => 'kunci rahasia penyedia pembayaran',
    ];

    foreach (['app', 'config', 'database', 'routes'] as $dir) {
        foreach (berkasPhp($dir) as $path => $isi) {
            foreach ($polaRahasia as $pola => $keterangan) {
                tidakBolehCocok($isi, $pola, "{$dir}/{$path} sepertinya memuat {$keterangan}.");
            }
        }
    }
});

it('membaca setiap rahasia dari env, bukan dari nilai tertulis', function (): void {
    $konfigurasi = (string) file_get_contents(base_path('config/rafin.php'));

    foreach (['token', 'webhook_secret'] as $kunci) {
        expect($konfigurasi)->toMatch(
            "/'{$kunci}'\\s*=>\\s*env\\(/",
            "config/rafin.php: {$kunci} harus dibaca dari env()."
        );
    }
});

it('tidak memanggil LLM dari jalur input utama', function (): void {
    // Aturan A12. Yang dijaga bukan biayanya, tapi waktu tanggapnya: jalur
    // input utama harus bisa diprediksi, dan panggilan jaringan ke penyedia
    // pihak ketiga tidak punya sifat itu.
    $penyedia = ['openai', 'anthropic', 'gemini', 'mistral'];

    foreach (['app/Channels', 'app/Domain/Capture', 'app/Livewire'] as $direktori) {
        foreach (berkasPhp($direktori) as $path => $isi) {
            foreach ($penyedia as $nama) {
                tidakBolehMemuat(
                    strtolower($isi),
                    $nama,
                    "{$direktori}/{$path} menyentuh {$nama} di jalur input utama (aturan A12)."
                );
            }
        }
    }

    expect(config('rafin.features.llm_parser'))->toBeFalse()
        ->and(config('rafin.features.ocr'))->toBeFalse();
});

it('hanya menyediakan koneksi PostgreSQL', function (): void {
    // Trigger keseimbangan double-entry, larangan ubah transaksi posted, dan
    // RLS semuanya hidup di dalam database. Koneksi sqlite yang tersedia
    // "sekadar untuk test cepat" akan membuat semua itu terlewat diam-diam.
    $koneksi = array_keys((array) config('database.connections'));

    sort($koneksi);

    expect($koneksi)->toBe(['pgsql', 'pgsql_migrate'])
        ->and(config('database.default'))->toBe('pgsql');

    foreach ($koneksi as $nama) {
        expect(config("database.connections.{$nama}.driver"))->toBe('pgsql');
    }
});

it('menjalankan test di atas PostgreSQL sungguhan', function (): void {
    // Kalau baris ini pernah gagal, seluruh test keamanan di suite Security
    // sedang menguji sesuatu yang lain sama sekali.
    expect(DB::connection('pgsql')->getDriverName())->toBe('pgsql')
        ->and(env('DB_DATABASE'))->toBe('rafin_test');
});
