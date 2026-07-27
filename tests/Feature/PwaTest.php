<?php

declare(strict_types=1);

use App\Domain\Capture\Models\InboxItem;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Attachment;
use App\Domain\Ledger\Models\Transaction;
use App\Livewire\App\KunciAplikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

function kirimTransaksi(array $isi, ?string $kunci = null): TestResponse
{
    $id = $isi['id'] ?? (string) Str::ulid();

    return test()->withHeaders(['Idempotency-Key' => $kunci ?? $id])
        ->postJson('/app/transaksi', array_merge([
            'id' => $id,
            'kind' => 'expense',
            'amount_minor' => 5_000_000,
            'account_id' => test()->kas->getKey(),
        ], $isi));
}

/*
 * Setiap test di bawah memanggil actingInWorkspace() lagi SETELAH request HTTP.
 *
 * Bukan karena ada yang salah: middleware memang membersihkan konteks tenant di
 * terminate(), dan itu justru sifat yang diuji terpisah di AutentikasiTest.
 * Test yang ingin memeriksa isi database sesudahnya harus memasang konteksnya
 * sendiri, sama seperti request berikutnya nanti.
 */

it('mencatat transaksi yang dikirim antrean offline', function (): void {
    $this->actingAs($this->pengguna);

    kirimTransaksi(['description' => 'Bensin'])->assertCreated();

    actingInWorkspace($this->workspace, $this->pengguna);
    $transaksi = Transaction::query()->bukanSaldoAwal()->sole();

    expect($transaksi->source)->toBe(TransactionSource::PwaOffline)
        ->and($transaksi->description)->toBe('Bensin')
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('memakai ULID buatan client sebagai id transaksi', function (): void {
    $this->actingAs($this->pengguna);
    $id = (string) Str::ulid();

    kirimTransaksi(['id' => $id])->assertCreated();

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->sole()->getKey())->toBe($id);
});

it('tidak menggandakan transaksi saat antrean mengirim ulang', function (): void {
    $this->actingAs($this->pengguna);
    $id = (string) Str::ulid();

    // Ponsel kehilangan sinyal setelah server sebenarnya sudah menerima, lalu
    // mencoba lagi. Ini keadaan yang paling sering terjadi, bukan yang paling
    // jarang (aturan A9).
    kirimTransaksi(['id' => $id])->assertCreated();
    kirimTransaksi(['id' => $id])->assertOk()->assertJson(['duplikat' => true]);
    kirimTransaksi(['id' => $id])->assertOk()->assertJson(['duplikat' => true]);

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(1)
        ->and($this->kas->fresh()->balance()->format())->toBe('Rp 950.000');
});

it('menolak Idempotency-Key yang tidak sama dengan id transaksi', function (): void {
    $this->actingAs($this->pengguna);

    // Kalau keduanya boleh berbeda, jaminan idempotensinya hilang: dua kiriman
    // dengan id berbeda tapi kunci sama akan tetap jadi dua transaksi.
    kirimTransaksi([], kunci: (string) Str::ulid())->assertStatus(422);

    expect(Transaction::query()->count())->toBe(0);
});

it('menjawab 404 untuk akun milik workspace lain', function (): void {
    [, $workspaceLain] = makeWorkspaceFor();
    $akunOrangLain = buatAkun('Kas orang lain');

    actingInWorkspace($this->workspace, $this->pengguna);
    $this->actingAs($this->pengguna);

    kirimTransaksi(['account_id' => $akunOrangLain->getKey()])->assertNotFound();

    expect($workspaceLain->getKey())->not->toBe($this->workspace->getKey());
});

it('menolak nominal nol atau negatif', function (): void {
    $this->actingAs($this->pengguna);

    kirimTransaksi(['amount_minor' => 0])->assertStatus(422);
    kirimTransaksi(['amount_minor' => -100])->assertStatus(422);
});

it('menolak transaksi dari tamu', function (): void {
    kirimTransaksi([])->assertUnauthorized();
});

it('menerima teks yang dibagikan dari aplikasi lain', function (): void {
    $this->actingAs($this->pengguna);

    $this->post('/app/share', ['text' => '50k bensin'])
        ->assertRedirect(route('app.inbox'));

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(1)
        ->and(Transaction::query()->bukanSaldoAwal()->sole()->raw_input)->toBe('50k bensin');
});

it('menyimpan gambar yang dibagikan sebagai lampiran di disk privat', function (): void {
    Storage::fake('lampiran');
    $this->actingAs($this->pengguna);

    $this->post('/app/share', [
        'text' => 'struk indomaret',
        'berkas' => UploadedFile::fake()->image('struk.jpg', 800, 1200),
    ])->assertRedirect(route('app.inbox'));

    actingInWorkspace($this->workspace, $this->pengguna);
    $lampiran = Attachment::query()->sole();

    expect(InboxItem::query()->count())->toBe(1)
        ->and($lampiran->mime)->toContain('image/')
        ->and($lampiran->sha256)->toHaveLength(64);

    Storage::disk('lampiran')->assertExists($lampiran->disk_path);
});

it('menyimpan lampiran di disk yang tidak punya URL publik', function (): void {
    // Foto struk memuat nama toko, jam, dan nominal. Ia bukan berkas statis
    // biasa, dan tidak boleh bisa ditebak alamatnya (aturan A11).
    expect(config('filesystems.disks.lampiran.visibility'))->toBe('private')
        ->and(config('filesystems.disks.lampiran.serve'))->toBeFalse()
        ->and(config('filesystems.disks.lampiran'))->not->toHaveKey('url');
});

it('mengabaikan kiriman kosong tanpa membuat apa pun', function (): void {
    $this->actingAs($this->pengguna);

    $this->post('/app/share', [])->assertRedirect(route('app.inbox'));

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(InboxItem::query()->count())->toBe(0)
        ->and(Transaction::query()->bukanSaldoAwal()->count())->toBe(0);
});

it('membuka kunci aplikasi dengan PIN yang benar', function (): void {
    $this->pengguna->forceFill(['app_lock_pin_hash' => Hash::make('123456')])->save();

    Livewire::actingAs($this->pengguna)
        ->test(KunciAplikasi::class)
        ->set('pin', '123456')
        ->call('buka')
        ->assertHasNoErrors();
});

it('menolak PIN yang salah', function (): void {
    $this->pengguna->forceFill(['app_lock_pin_hash' => Hash::make('123456')])->save();

    Livewire::actingAs($this->pengguna)
        ->test(KunciAplikasi::class)
        ->set('pin', '999999')
        ->call('buka')
        ->assertHasErrors(['pin']);
});

it('membatasi tebakan PIN beruntun', function (): void {
    $this->pengguna->forceFill(['app_lock_pin_hash' => Hash::make('123456')])->save();

    $komponen = Livewire::actingAs($this->pengguna)->test(KunciAplikasi::class);

    foreach (range(1, 5) as $percobaan) {
        $komponen->set('pin', '000000')->call('buka');
    }

    $komponen->set('pin', '000000')->call('buka')
        ->assertHasErrors(['pin']);

    expect(session()->all())->toBeArray();
});

it('menandai halaman dengan data-app-lock hanya kalau PIN dipasang', function (): void {
    // Diperiksa pada tag <body> sungguhan, bukan dengan mencari substring:
    // Livewire menyisipkan sumber template mentah ke dalam snapshot-nya saat
    // APP_DEBUG menyala, dan pencarian substring akan menemukannya di sana.
    $tagBody = function (string $html): string {
        preg_match('/<body[^>]*>/', $html, $cocok);

        return $cocok[0] ?? '';
    };

    $tanpaPin = $this->actingAs($this->pengguna)->get('/app')->getContent();
    expect($tagBody($tanpaPin))->not->toContain('data-app-lock');

    $this->pengguna->forceFill(['app_lock_pin_hash' => Hash::make('123456')])->save();

    $denganPin = $this->actingAs($this->pengguna->fresh())->get('/app')->getContent();
    expect($tagBody($denganPin))->toContain('data-app-lock="1"');
});

it('menyediakan manifest PWA dengan shortcut dan share target', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(public_path('build/manifest.webmanifest')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['name'])->toBe('Rafin')
        ->and($manifest['id'])->toBe('/app')
        ->and($manifest['start_url'])->toBe('/app')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['shortcuts'])->toHaveCount(4)
        ->and($manifest['share_target']['action'])->toBe('/app/share')
        ->and($manifest['share_target']['method'])->toBe('POST')
        ->and($manifest['share_target']['enctype'])->toBe('multipart/form-data');

    $maskable = collect($manifest['icons'])->firstWhere('purpose', 'maskable');
    expect($maskable)->not->toBeNull()
        ->and($maskable['sizes'])->toBe('512x512');
});

it('menyertakan antrean offline di dalam service worker', function (): void {
    $sw = (string) file_get_contents(public_path('build/sw.js'));

    expect($sw)->toContain('Idempotency-Key')
        ->and($sw)->toContain('rafin-antrean');
});
