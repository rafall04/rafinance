<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceInvite;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Aturan A4 — dua lapis, diuji terpisah
|--------------------------------------------------------------------------
|
| Menguji keduanya lewat Eloquent saja tidak ada gunanya: selama global scope
| bekerja, RLS tidak pernah tersentuh, dan kita tidak pernah tahu apakah jaring
| pengamannya benar-benar terpasang. Karena itu sebagian test di berkas ini
| sengaja memakai SQL mentah, melewati Eloquent sepenuhnya.
|
*/

it('menyaring query Eloquent ke workspace aktif', function (): void {
    [$satu, $workspaceSatu] = makeWorkspaceFor();
    WorkspaceInvite::factory()->create(['email' => 'orang@satu.test']);

    [$dua, $workspaceDua] = makeWorkspaceFor();
    WorkspaceInvite::factory()->create(['email' => 'orang@dua.test']);

    actingInWorkspace($workspaceSatu, $satu);
    expect(WorkspaceInvite::query()->pluck('email')->all())->toBe(['orang@satu.test']);

    actingInWorkspace($workspaceDua, $dua);
    expect(WorkspaceInvite::query()->pluck('email')->all())->toBe(['orang@dua.test']);
});

it('mengisi workspace_id dari konteks tenant saat menyimpan', function (): void {
    [, $workspace] = makeWorkspaceFor();

    $invite = WorkspaceInvite::factory()->create();

    expect($invite->workspace_id)->toBe($workspace->getKey());
});

it('tidak menampilkan apa pun ketika tidak ada workspace aktif', function (): void {
    makeWorkspaceFor();
    WorkspaceInvite::factory()->create();

    tenant()->clear();

    // Gagal tertutup, bukan gagal terbuka. Query yang lupa konteks harus
    // kosong, bukan menampilkan seluruh isi tabel.
    expect(WorkspaceInvite::query()->count())->toBe(0);
});

it('menegakkan RLS pada SQL mentah yang melewati Eloquent sepenuhnya', function (): void {
    [$satu, $workspaceSatu] = makeWorkspaceFor();
    WorkspaceInvite::factory()->create(['email' => 'orang@satu.test']);

    [$dua, $workspaceDua] = makeWorkspaceFor();
    WorkspaceInvite::factory()->create(['email' => 'orang@dua.test']);

    // Tanpa Eloquent, tanpa global scope. Yang menyaring di sini hanya
    // PostgreSQL sendiri.
    actingInWorkspace($workspaceSatu, $satu);
    $terlihat = DB::connection('pgsql')->select('SELECT email FROM workspace_invites');

    expect($terlihat)->toHaveCount(1)
        ->and($terlihat[0]->email)->toBe('orang@satu.test');

    actingInWorkspace($workspaceDua, $dua);
    $terlihat = DB::connection('pgsql')->select('SELECT email FROM workspace_invites');

    expect($terlihat)->toHaveCount(1)
        ->and($terlihat[0]->email)->toBe('orang@dua.test');
});

it('menyembunyikan seluruh baris dari SQL mentah tanpa konteks tenant', function (): void {
    makeWorkspaceFor();
    WorkspaceInvite::factory()->create();

    tenant()->clear();

    expect(DB::connection('pgsql')->select('SELECT email FROM workspace_invites'))->toBeEmpty();
});

it('menolak penulisan ke workspace lain di tingkat database', function (): void {
    [, $workspaceSatu] = makeWorkspaceFor();
    [$dua, $workspaceDua] = makeWorkspaceFor();

    actingInWorkspace($workspaceDua, $dua);

    // WITH CHECK pada policy menolak baris yang ditulis ke tenant lain, meski
    // workspace_id-nya diisi tangan dan global scope Eloquent dilangkahi.
    DB::connection('pgsql')->insert(
        'INSERT INTO workspace_invites (id, workspace_id, email, role, token, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, now(), now())',
        [
            (string) Str::ulid(),
            $workspaceSatu->getKey(),
            'penyusup@dua.test',
            'editor',
            Str::random(48),
        ],
    );
})->throws(QueryException::class);

it('tidak menampilkan workspace milik orang lain', function (): void {
    [$satu, $workspaceSatu] = makeWorkspaceFor();
    [, $workspaceDua] = makeWorkspaceFor();

    actingInWorkspace($workspaceSatu, $satu);

    expect(Workspace::query()->pluck('id')->all())->toBe([$workspaceSatu->getKey()])
        ->and(Workspace::query()->find($workspaceDua->getKey()))->toBeNull();
});

it('menampilkan semua workspace yang diikuti seseorang', function (): void {
    [$pemilik, $workspaceSatu] = makeWorkspaceFor();
    [, $workspaceDua] = makeWorkspaceFor();

    addMember($workspaceDua, $pemilik);

    actingInWorkspace($workspaceSatu, $pemilik);

    $terlihat = Workspace::query()->pluck('id')->all();

    expect($terlihat)->toHaveCount(2)
        ->and($terlihat)->toContain($workspaceSatu->getKey())
        ->and($terlihat)->toContain($workspaceDua->getKey());
});

it('menjalankan aplikasi sebagai role yang tidak bisa melewati RLS', function (): void {
    // Dicor ke int supaya tidak bergantung pada cara driver memetakan boolean.
    $peran = DB::connection('pgsql')->selectOne(
        'SELECT rolbypassrls::int AS bypass, rolsuper::int AS super
         FROM pg_roles WHERE rolname = current_user'
    );

    // Kalau salah satu dari ini bukan nol, seluruh test di berkas ini hijau
    // tanpa membuktikan apa pun.
    expect((int) $peran->bypass)->toBe(0)
        ->and((int) $peran->super)->toBe(0);
});

it('memasang FORCE ROW LEVEL SECURITY pada tabel workspace', function (): void {
    // Tanpa FORCE, pemilik tabel kebal terhadap policy-nya sendiri.
    $tabel = DB::connection('pgsql')->selectOne(
        "SELECT relrowsecurity::int AS aktif, relforcerowsecurity::int AS dipaksa
         FROM pg_class WHERE relname = 'workspace_invites'"
    );

    expect((int) $tabel->aktif)->toBe(1)
        ->and((int) $tabel->dipaksa)->toBe(1);
});

it('tidak memberi role aplikasi hak membuat tabel', function (): void {
    DB::connection('pgsql')->statement('CREATE TABLE percobaan_ddl (id int)');
})->throws(QueryException::class);

it('memulihkan konteks sebelumnya setelah runFor melempar', function (): void {
    [$satu, $workspaceSatu] = makeWorkspaceFor();
    [, $workspaceDua] = makeWorkspaceFor();

    actingInWorkspace($workspaceSatu, $satu);

    try {
        tenant()->runFor($workspaceDua, function (): void {
            throw new RuntimeException('gagal di tengah jalan');
        });
    } catch (RuntimeException) {
        // memang disengaja
    }

    expect(tenant()->id())->toBe($workspaceSatu->getKey());

    $aktif = DB::connection('pgsql')->selectOne("SELECT current_setting('app.workspace_id', true) AS ws");
    expect($aktif->ws)->toBe($workspaceSatu->getKey());
});

it('membersihkan konteks tenant agar tidak menular antar job antrean', function (): void {
    [, $workspace] = makeWorkspaceFor();

    expect(tenant()->id())->toBe($workspace->getKey());

    tenant()->clear();

    $aktif = DB::connection('pgsql')->selectOne("SELECT current_setting('app.workspace_id', true) AS ws");

    expect(tenant()->id())->toBeNull()
        ->and($aktif->ws)->toBe('');
});

it('tidak menampilkan workspace apa pun kepada orang yang bukan anggota', function (): void {
    // Batas yang perlu dinyatakan terang-terangan: app.workspace_id adalah
    // masukan tepercaya dari lapis aplikasi — middleware sudah memverifikasi
    // keanggotaan sebelum mengisinya. Yang dijaga RLS adalah apa yang terlihat
    // ketika konteksnya TIDAK diisi, dan di situ keanggotaan yang menentukan.
    makeWorkspaceFor();
    $orangLain = User::factory()->create();

    tenant()->clear();
    tenant()->setUserId((string) $orangLain->getKey());

    expect(Workspace::query()->count())->toBe(0);
});
