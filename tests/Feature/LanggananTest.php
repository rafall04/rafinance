<?php

declare(strict_types=1);

use App\Domain\Billing\Exceptions\QuotaTerlampaui;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Services\QuotaGuard;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\SeedWorkspaceDefaults;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

function berlangganan(array $batas = []): Subscription
{
    $plan = Plan::factory()->denganBatas($batas)->create();

    return Subscription::query()->create([
        'workspace_id' => test()->workspace->getKey(),
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth(),
        'current_period_end' => now()->endOfMonth(),
    ]);
}

it('menghitung pemakaian transaksi', function (): void {
    berlangganan(['transactions_per_month' => 500]);

    catatPengeluaran(50_000, $this->kas);
    catatPengeluaran(30_000, $this->kas);

    expect(app(QuotaGuard::class)->terpakai($this->workspace, UsageCounter::TRANSAKSI))->toBe(2);
});

it('tidak menghitung saldo awal ke dalam kuota', function (): void {
    berlangganan(['transactions_per_month' => 500]);

    // Saldo awal satu per akun dan bukan aktivitas mencatat.
    buatAkun('BCA', 5_000_000);

    expect(app(QuotaGuard::class)->terpakai($this->workspace, UsageCounter::TRANSAKSI))->toBe(0);
});

it('menolak transaksi baru saat kuota habis', function (): void {
    berlangganan(['transactions_per_month' => 2]);

    catatPengeluaran(10_000, $this->kas);
    catatPengeluaran(20_000, $this->kas);
    catatPengeluaran(30_000, $this->kas);
})->throws(QuotaTerlampaui::class, 'Batas 2 transaksi bulan ini sudah tercapai');

it('tetap membiarkan catatan lama dibuka setelah kuota habis', function (): void {
    berlangganan(['transactions_per_month' => 1]);

    catatPengeluaran(10_000, $this->kas, 'Yang pertama');

    try {
        catatPengeluaran(20_000, $this->kas);
    } catch (QuotaTerlampaui) {
        // memang disengaja
    }

    // Buku tidak pernah disandera: yang dibatasi hanya menambah.
    $this->actingAs($this->pengguna)->get('/app')
        ->assertOk()
        ->assertSee('Yang pertama');

    actingInWorkspace($this->workspace, $this->pengguna);

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(1);
});

it('memperlakukan -1 sebagai tanpa batas', function (): void {
    berlangganan(['transactions_per_month' => Plan::TANPA_BATAS]);

    foreach (range(1, 5) as $kali) {
        catatPengeluaran(1_000 * $kali, $this->kas);
    }

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(5);
});

it('tidak memaksakan kuota kalau belum ada langganan', function (): void {
    // Workspace yang dibuat lewat jalur lain tidak boleh terkunci hanya karena
    // baris langganannya belum sempat dibuat.
    foreach (range(1, 3) as $kali) {
        catatPengeluaran(1_000, $this->kas);
    }

    expect(Transaction::query()->bukanSaldoAwal()->count())->toBe(3);
});

it('memisahkan penghitung antar workspace', function (): void {
    berlangganan(['transactions_per_month' => 500]);
    catatPengeluaran(10_000, $this->kas);

    [$dua, $workspaceDua] = makeWorkspaceFor();
    $kasDua = buatAkun('Kas');
    catatPengeluaran(20_000, $kasDua);

    $guard = app(QuotaGuard::class);

    expect($guard->terpakai($this->workspace, UsageCounter::TRANSAKSI))->toBe(1)
        ->and($guard->terpakai($workspaceDua, UsageCounter::TRANSAKSI))->toBe(1)
        ->and($dua->getKey())->not->toBe($this->pengguna->getKey());
});

it('menyiapkan langganan gratis saat onboarding', function (): void {
    $rencana = Plan::query()->create([
        'code' => 'free',
        'name' => 'Gratis',
        'price_minor' => 0,
        'currency' => 'IDR',
        'interval' => 'monthly',
        'is_public' => true,
        'sort_order' => 0,
        'limits' => ['transactions_per_month' => 500, 'members' => 1],
    ]);

    [$user, $workspace] = makeWorkspaceFor();
    app(SeedWorkspaceDefaults::class)($workspace, ['cash']);

    $langganan = Subscription::query()->where('workspace_id', $workspace->getKey())->sole();

    expect($langganan->plan_id)->toBe($rencana->getKey())
        ->and($langganan->aktif())->toBeTrue()
        ->and($user->getKey())->not->toBeNull();
});

it('menampilkan halaman langganan beserta pemakaiannya', function (): void {
    berlangganan(['transactions_per_month' => 500, 'members' => 3]);
    catatPengeluaran(50_000, $this->kas);

    $this->actingAs($this->pengguna)->get('/app/langganan')
        ->assertOk()
        ->assertSee('Pemakaian')
        ->assertSee('Transaksi bulan ini')
        ->assertSee('buku Anda tidak pernah disandera', escape: false);
});

it('menyimpan semua harga plan sebagai nol selama beta', function (): void {
    $this->seed(PlanSeeder::class);

    Plan::query()->get()->each(function (Plan $plan): void {
        expect($plan->price_minor->isZero())->toBeTrue()
            ->and($plan->gratis())->toBeTrue();
    });

    expect(Plan::query()->count())->toBe(3);
});

it('mematikan LLM dan OCR di setiap plan', function (): void {
    $this->seed(PlanSeeder::class);

    // Aturan A12: tidak ada LLM di jalur input utama, apa pun plannya.
    Plan::query()->get()->each(function (Plan $plan): void {
        expect($plan->fitur('llm_parser'))->toBeFalse();
    });
});
