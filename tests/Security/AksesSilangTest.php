<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\WorkspaceInvite;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aturan A8 — sumber daya milik workspace lain menjawab 404, bukan 403
|--------------------------------------------------------------------------
|
| 403 berarti "ada, tapi Anda tidak boleh". Bagi penyerang itu jawaban yang
| berguna: ia baru saja memastikan sebuah ID benar-benar ada. 404 tidak
| memberi tahu apa pun.
|
| Rute uji didaftarkan di dalam test ini, bukan mengarang halaman produk yang
| belum waktunya. Yang diuji adalah mekanismenya — route model binding di atas
| model ber-global-scope — dan mekanisme itulah yang akan dipakai setiap
| halaman workspace di fase berikutnya.
|
*/

beforeEach(function (): void {
    Route::middleware(['web', 'auth'])
        ->scopeBindings()
        ->get('/uji/undangan/{workspaceInvite}', fn (WorkspaceInvite $workspaceInvite) => response()->json([
            'email' => $workspaceInvite->email,
        ]))->name('uji.undangan');
});

it('menampilkan sumber daya milik workspace sendiri', function (): void {
    [$user] = makeWorkspaceFor();
    $undangan = WorkspaceInvite::factory()->create(['email' => 'orang@satu.test']);

    $this->actingAs($user)
        ->get("/uji/undangan/{$undangan->getKey()}")
        ->assertOk()
        ->assertJson(['email' => 'orang@satu.test']);
});

it('menjawab 404 untuk sumber daya milik workspace lain', function (): void {
    [, $workspaceSatu] = makeWorkspaceFor();
    $undanganOrangLain = WorkspaceInvite::factory()->create(['email' => 'orang@satu.test']);

    [$dua] = makeWorkspaceFor();

    $this->actingAs($dua)
        ->get("/uji/undangan/{$undanganOrangLain->getKey()}")
        ->assertNotFound();

    expect($workspaceSatu->getKey())->not->toBe(tenant()->id());
});

it('menjawab 404 dan bukan 403, supaya keberadaannya tidak bocor', function (): void {
    makeWorkspaceFor();
    $undangan = WorkspaceInvite::factory()->create();

    [$dua] = makeWorkspaceFor();

    $balasan = $this->actingAs($dua)->get("/uji/undangan/{$undangan->getKey()}");

    expect($balasan->getStatusCode())->toBe(404)
        ->and($balasan->getStatusCode())->not->toBe(403);
});

it('menjawab 404 yang sama untuk ID yang memang tidak ada', function (): void {
    [$user] = makeWorkspaceFor();

    // Jawaban untuk "ada tapi bukan milikmu" dan "memang tidak ada" harus
    // tidak bisa dibedakan dari luar.
    $this->actingAs($user)
        ->get('/uji/undangan/01JZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertNotFound();
});

it('menolak tamu tanpa membocorkan apakah sumber dayanya ada', function (): void {
    makeWorkspaceFor();
    $undangan = WorkspaceInvite::factory()->create();

    $this->get("/uji/undangan/{$undangan->getKey()}")->assertRedirect(route('login'));
});
