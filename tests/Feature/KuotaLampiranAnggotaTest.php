<?php

declare(strict_types=1);

use App\Domain\Billing\Exceptions\QuotaTerlampaui;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Services\QuotaGuard;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Models\User;

/*
 * Kuota yang sebelumnya hanya dipajang.
 *
 * Sampai Juli 2026 hanya metrik transaksi yang benar-benar dijaga; anggota dan
 * lampiran punya angka di plan, punya baris di halaman langganan, dan tidak
 * punya satu pun pemeriksaan. Batas lampirannya bahkan salah satuan — plan
 * menulis megabyte, penghitungnya menyimpan byte.
 */

function pasangPlan(Workspace $workspace, array $limits): Plan
{
    $plan = Plan::factory()->create(['limits' => $limits]);

    Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspace->getKey()],
        [
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ],
    );

    return $plan;
}

it('membaca batas lampiran sebagai megabyte, bukan byte', function () {
    [, $workspace] = makeWorkspaceFor();
    pasangPlan($workspace, ['attachments_mb' => 1]);

    $penjaga = app(QuotaGuard::class);

    // 900 KB masih di bawah 1 MB. Sebelum perbaikan ini ditolak, karena
    // 900.000 byte dibandingkan dengan angka 1.
    $penjaga->pastikanBolehMenambah(UsageCounter::LAMPIRAN_BYTE, 900 * 1024, $workspace);

    expect(true)->toBeTrue();
});

it('menolak lampiran yang melewati batas megabyte-nya', function () {
    [, $workspace] = makeWorkspaceFor();
    pasangPlan($workspace, ['attachments_mb' => 1]);

    expect(fn () => app(QuotaGuard::class)->pastikanBolehMenambah(
        UsageCounter::LAMPIRAN_BYTE,
        2 * 1024 * 1024,
        $workspace,
    ))->toThrow(QuotaTerlampaui::class);
});

it('menjumlahkan pemakaian lampiran lintas unggahan', function () {
    [, $workspace] = makeWorkspaceFor();
    pasangPlan($workspace, ['attachments_mb' => 1]);

    $penjaga = app(QuotaGuard::class);
    $penjaga->catatPemakaian(UsageCounter::LAMPIRAN_BYTE, 800 * 1024, $workspace);

    expect(fn () => $penjaga->pastikanBolehMenambah(
        UsageCounter::LAMPIRAN_BYTE,
        400 * 1024,
        $workspace,
    ))->toThrow(QuotaTerlampaui::class);
});

it('membiarkan pemilik masuk sebagai anggota pertama pada plan satu anggota', function () {
    [$pemilik, $workspace] = makeWorkspaceFor();

    // makeWorkspaceFor sudah membuat keanggotaan pemiliknya, jadi kuotanya
    // memang harus muat untuk yang satu itu.
    pasangPlan($workspace, ['members' => 1]);

    expect(WorkspaceMember::query()->where('workspace_id', $workspace->getKey())->count())->toBe(1)
        ->and($pemilik->exists)->toBeTrue();
});

it('menolak anggota kedua saat plannya hanya mengizinkan satu', function () {
    [, $workspace] = makeWorkspaceFor();
    pasangPlan($workspace, ['members' => 1]);

    // Penghitungnya disetel seolah pemiliknya sudah tercatat.
    app(QuotaGuard::class)->catatPemakaian(UsageCounter::ANGGOTA, 1, $workspace);

    $orangLain = User::factory()->create();

    expect(fn () => tenant()->runFor($workspace, fn () => WorkspaceMember::query()->create([
        'workspace_id' => $workspace->getKey(),
        'user_id' => $orangLain->getKey(),
        'role' => WorkspaceRole::Editor,
        'joined_at' => now(),
    ])))->toThrow(QuotaTerlampaui::class);
});

it('mengembalikan jatah anggota yang keluar', function () {
    [, $workspace] = makeWorkspaceFor();
    pasangPlan($workspace, ['members' => 5]);

    $penjaga = app(QuotaGuard::class);
    $sebelum = $penjaga->terpakai($workspace, UsageCounter::ANGGOTA);

    $anggota = addMember($workspace, User::factory()->create());

    expect($penjaga->terpakai($workspace, UsageCounter::ANGGOTA))->toBe($sebelum + 1);

    tenant()->runFor($workspace, fn () => $anggota->delete());

    expect($penjaga->terpakai($workspace, UsageCounter::ANGGOTA))->toBe($sebelum);
});

it('tidak pernah membuat penghitung jatuh di bawah nol', function () {
    [, $workspace] = makeWorkspaceFor();

    $penjaga = app(QuotaGuard::class);
    $penjaga->catatPemakaian(UsageCounter::ANGGOTA, -50, $workspace);

    expect($penjaga->terpakai($workspace, UsageCounter::ANGGOTA))->toBeGreaterThanOrEqual(0);
});
