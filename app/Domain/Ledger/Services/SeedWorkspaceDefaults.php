<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Tenancy\Models\Workspace;
use App\Support\Money;

/**
 * Mengisi workspace baru dengan akun dan kategori awal.
 *
 * Kategori bawaan ada supaya layar tambah transaksi tidak kosong di hari
 * pertama — daftar kosong memaksa orang berhenti mencatat untuk mengarang
 * taksonomi, dan sebagian besar tidak akan kembali. Semuanya bisa dihapus,
 * diganti nama, atau ditambah kapan saja.
 */
final class SeedWorkspaceDefaults
{
    /**
     * @var array<string, array<int, string>>
     */
    private const KATEGORI_PENGELUARAN = [
        'personal' => ['Makan & minum', 'Transportasi', 'Belanja', 'Tagihan & utilitas', 'Kesehatan', 'Hiburan', 'Lainnya'],
        'business' => ['Bahan & stok', 'Transportasi', 'Gaji & upah', 'Sewa', 'Listrik & air', 'Internet', 'Perbaikan', 'Lainnya'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const KATEGORI_PEMASUKAN = [
        'personal' => ['Gaji', 'Bonus', 'Hadiah', 'Lainnya'],
        'business' => ['Penjualan', 'Jasa', 'Iuran bulanan', 'Lainnya'],
    ];

    /**
     * @param  array<int, string>  $akunAwal  daftar subtype: cash, bank, ewallet
     */
    public function __invoke(Workspace $workspace, array $akunAwal = ['cash']): void
    {
        $this->buatAkun($workspace, $akunAwal);
        $this->buatKategori($workspace);
        $this->pasangLangganan($workspace);
    }

    /**
     * Setiap workspace berlangganan sejak menit pertama — plan gratis, Rp 0.
     *
     * Tanpa baris langganan, penegakan kuota tidak punya tempat berpijak dan
     * diam-diam melewatkan semua batas. Lebih baik batasnya ada sejak awal dan
     * longgar, daripada dipasang belakangan pada orang yang sudah terbiasa
     * tanpa batas.
     */
    private function pasangLangganan(Workspace $workspace): void
    {
        $plan = Plan::query()->where('code', 'free')->first();

        if ($plan === null) {
            return;
        }

        Subscription::query()->firstOrCreate(
            ['workspace_id' => $workspace->getKey()],
            [
                'plan_id' => $plan->getKey(),
                'status' => 'active',
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ],
        );
    }

    /**
     * @param  array<int, string>  $akunAwal
     */
    private function buatAkun(Workspace $workspace, array $akunAwal): void
    {
        $urutan = 0;

        foreach ($akunAwal as $kode) {
            $subtype = AccountSubtype::tryFrom($kode);

            if ($subtype === null || ! in_array($subtype, [AccountSubtype::Cash, AccountSubtype::Bank, AccountSubtype::Ewallet], true)) {
                continue;
            }

            Account::query()->create([
                'workspace_id' => $workspace->getKey(),
                'name' => $subtype->label(),
                'type' => AccountType::Asset,
                'subtype' => $subtype,
                'currency' => $workspace->currency,
                'opening_balance_minor' => Money::zero($workspace->currency),
                'sort_order' => $urutan++,
            ]);
        }
    }

    private function buatKategori(Workspace $workspace): void
    {
        $jenis = $workspace->type->value;

        foreach (self::KATEGORI_PENGELUARAN[$jenis] ?? [] as $nama) {
            Category::query()->create([
                'workspace_id' => $workspace->getKey(),
                'name' => $nama,
                'kind' => CategoryKind::Expense,
            ]);
        }

        foreach (self::KATEGORI_PEMASUKAN[$jenis] ?? [] as $nama) {
            Category::query()->create([
                'workspace_id' => $workspace->getKey(),
                'name' => $nama,
                'kind' => CategoryKind::Income,
            ]);
        }
    }
}
