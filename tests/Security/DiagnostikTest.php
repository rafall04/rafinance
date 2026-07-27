<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Diagnostik;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Halaman diagnostik
|--------------------------------------------------------------------------
|
| Halaman ini menyebut-nyebut rahasia produksi — token bot, secret Google, sandi
| SMTP — untuk melaporkan apakah masing-masing sudah terisi. Justru karena itu ia
| adalah tempat paling mungkin di seluruh aplikasi untuk tanpa sengaja mencetak
| nilainya.
|
| Yang diuji di sini bukan tampilannya, melainkan bahwa ia tetap bisu.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_platform_admin' => true]);
});

it('menolak pengguna biasa', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(Diagnostik::getUrl(panel: 'admin'))
        ->assertForbidden();
});

it('menolak tamu', function (): void {
    $this->get(Diagnostik::getUrl(panel: 'admin'))->assertRedirect();
});

it('bisa dibuka admin platform', function (): void {
    $this->actingAs($this->admin)
        ->get(Diagnostik::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Integrasi luar')
        ->assertSee('Infrastruktur');
});

it('tidak pernah mencetak nilai rahasia apa pun', function (): void {
    // Nilai yang benar-benar rahasia dipasang dulu, lalu halamannya dibuka.
    // Kalau suatu saat ada yang menambahkan "biar kelihatan sedikit untuk
    // memastikan", test ini yang menolaknya.
    config([
        'services.google.client_secret' => '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pil',
        'services.google.client_id' => '1234-idnya.apps.googleusercontent.com',
        'rafin.telegram.token' => '8888888888:token-bot-yang-tidak-boleh-tampil',
        'rafin.telegram.webhook_secret' => 'rahasia-webhook-yang-tidak-boleh-tampil',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(Diagnostik::getUrl(panel: 'admin'))
        ->assertOk()
        ->getContent();

    foreach ([
        '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pil',
        '1234-idnya.apps.googleusercontent.com',
        '8888888888:token-bot-yang-tidak-boleh-tampil',
        'rahasia-webhook-yang-tidak-boleh-tampil',
    ] as $rahasia) {
        expect((string) $html)->not->toContain($rahasia);
    }
});

it('membedakan belum disiapkan dari disiapkan separuh', function (): void {
    // Perbedaan ini yang membuat halamannya berguna. "Kosong" berarti fiturnya
    // memang dimatikan dan itu benar; "separuh" berarti ada yang akan gagal di
    // tangan pengguna dengan pesan yang tidak menjelaskan apa-apa.
    config([
        'services.google.client_id' => 'ada-idnya',
        'services.google.client_secret' => null,
    ]);

    $this->actingAs($this->admin)
        ->get(Diagnostik::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Belum lengkap')
        ->assertSee('set-secret.sh');
});

it('tidak menyentuh satu pun model finansial', function (): void {
    // Aturan A5 dijaga arch test yang memindai seluruh App\Filament. Test ini
    // menegaskannya khusus untuk halaman ini, karena halaman diagnostik adalah
    // tempat yang paling menggoda untuk "sekalian tampilkan total transaksi".
    $kode = (string) file_get_contents(app_path('Filament/Admin/Pages/Diagnostik.php'));

    foreach (['Transaction', 'Entry', 'Attachment', 'Budget', 'Invoice', 'AuditLog'] as $terlarang) {
        expect($kode)->not->toContain($terlarang);
    }

    foreach (['transactions', 'entries', 'attachments', 'budgets', 'invoices'] as $tabel) {
        expect($kode)->not->toContain("'".$tabel."'");
    }
});
