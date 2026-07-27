<?php

declare(strict_types=1);

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Support\Money;
use Tests\Concerns\RefreshRafinDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshRafinDatabase::class)
    ->in('Feature', 'Security');

// Unit dan Arch tetap butuh aplikasi Laravel — untuk config() dan base_path() —
// tapi tidak menyentuh database sama sekali, jadi tidak perlu migrasi maupun
// transaksi per test.
pest()->extend(TestCase::class)
    ->in('Unit', 'Arch');

/*
|--------------------------------------------------------------------------
| Pembantu tenancy
|--------------------------------------------------------------------------
|
| Test berjalan sebagai rafin_app, jadi policy RLS berlaku penuh — termasuk
| saat menyiapkan data. Itu bukan gangguan, itu justru buktinya: kalau data uji
| bisa dibuat tanpa konteks tenant yang benar, berarti policy-nya bocor.
|
*/

function tenant(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * Membuat satu pengguna beserta workspace miliknya, lengkap dengan
 * keanggotaan, dan menjadikannya tenant aktif.
 *
 * @return array{0: User, 1: Workspace}
 */
function makeWorkspaceFor(?User $owner = null, WorkspaceRole $role = WorkspaceRole::Owner, array $attributes = []): array
{
    $owner ??= User::factory()->create();

    // Policy workspaces_create menuntut owner_id sama dengan app.user_id.
    tenant()->setUserId((string) $owner->getKey());

    $workspace = Workspace::factory()->create(['owner_id' => $owner->getKey()] + $attributes);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->getKey(),
        'user_id' => $owner->getKey(),
        'role' => $role,
        'joined_at' => now(),
    ]);

    tenant()->setWorkspace($workspace);

    return [$owner, $workspace];
}

/**
 * Menambahkan anggota lain ke sebuah workspace.
 */
function addMember(Workspace $workspace, User $user, WorkspaceRole $role = WorkspaceRole::Editor): WorkspaceMember
{
    return tenant()->runFor($workspace, fn (): WorkspaceMember => WorkspaceMember::query()->create([
        'workspace_id' => $workspace->getKey(),
        'user_id' => $user->getKey(),
        'role' => $role,
        'joined_at' => now(),
    ]));
}

/**
 * Memindahkan konteks tenant ke workspace lain untuk sesaat.
 */
function actingInWorkspace(Workspace $workspace, ?User $user = null): void
{
    if ($user !== null) {
        tenant()->setUserId((string) $user->getKey());
    }

    tenant()->setWorkspace($workspace);
}

/*
|--------------------------------------------------------------------------
| Pembantu buku besar
|--------------------------------------------------------------------------
*/

function buatAkun(string $nama = 'Kas', int $saldoAwalRupiah = 0, AccountSubtype $subtype = AccountSubtype::Cash): Account
{
    return Account::factory()->create([
        'name' => $nama,
        'subtype' => $subtype,
        'opening_balance_minor' => $saldoAwalRupiah * 100,
    ]);
}

/**
 * Mencatat pengeluaran utuh lewat service, bukan lewat factory — supaya yang
 * diuji adalah jalur yang sungguhan dipakai aplikasi.
 */
function catatPengeluaran(int $rupiah, Account $dari, ?string $keterangan = null, ?string $tanggal = null, ?string $id = null): Transaction
{
    return app(PostTransaction::class)(DraftTransaction::pengeluaran(
        amount: Money::ofMajor($rupiah, 'IDR'),
        from: $dari,
        bookedDate: $tanggal,
        description: $keterangan,
        id: $id,
    ));
}

function catatPemasukan(int $rupiah, Account $ke, ?string $keterangan = null, ?string $tanggal = null): Transaction
{
    return app(PostTransaction::class)(DraftTransaction::pemasukan(
        amount: Money::ofMajor($rupiah, 'IDR'),
        to: $ke,
        bookedDate: $tanggal,
        description: $keterangan,
    ));
}

function catatPindah(int $rupiah, Account $dari, Account $ke, ?string $tanggal = null): Transaction
{
    return app(PostTransaction::class)(DraftTransaction::pindah(
        amount: Money::ofMajor($rupiah, 'IDR'),
        from: $dari,
        to: $ke,
        bookedDate: $tanggal,
    ));
}
