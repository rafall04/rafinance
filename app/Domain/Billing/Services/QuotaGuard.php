<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Exceptions\QuotaTerlampaui;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\TenantContext;

/**
 * Penegakan kuota.
 *
 * Dibangun penuh meski semua plan berharga nol, karena urutannya penting:
 * melonggarkan batas pada sistem yang sudah berjalan itu mudah, sementara
 * memasang batas pada sistem yang sudah dipakai orang selalu terasa seperti
 * pengkhianatan.
 *
 * Yang dijaga adalah PEMBUATAN data baru, tidak pernah pembacaannya. Buku kas
 * seseorang tidak pernah disandera: melewati kuota berarti tidak bisa menambah,
 * bukan tidak bisa melihat apa yang sudah ada.
 */
final class QuotaGuard
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function pastikanBolehMenambah(string $metric, int $tambahan = 1, ?Workspace $workspace = null): void
    {
        $workspace ??= $this->tenant->workspace();

        if ($workspace === null) {
            return;
        }

        $plan = $this->plan($workspace);

        if ($plan === null) {
            return;
        }

        $batas = $plan->batas($this->kunciBatas($metric));

        if ($batas === Plan::TANPA_BATAS) {
            return;
        }

        $terpakai = $this->terpakai($workspace, $metric);

        if ($terpakai + $tambahan > $batas) {
            throw new QuotaTerlampaui($metric, $terpakai, $batas);
        }
    }

    public function catatPemakaian(string $metric, int $tambahan = 1, ?Workspace $workspace = null): void
    {
        $workspace ??= $this->tenant->workspace();

        if ($workspace === null) {
            return;
        }

        $baris = UsageCounter::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->getKey(),
                'metric' => $metric,
                'period_key' => UsageCounter::kunciPeriode($metric),
            ],
            ['value' => 0],
        );

        $baris->increment('value', $tambahan);
    }

    public function terpakai(Workspace $workspace, string $metric): int
    {
        return (int) UsageCounter::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('metric', $metric)
            ->where('period_key', UsageCounter::kunciPeriode($metric))
            ->value('value');
    }

    /**
     * Ringkasan pemakaian versus kuota untuk halaman langganan.
     *
     * @return array<int, object{metric: string, label: string, terpakai: int, batas: int, persen: int}>
     */
    public function ringkasan(Workspace $workspace): array
    {
        $plan = $this->plan($workspace);

        $daftar = [
            [UsageCounter::TRANSAKSI, 'Transaksi bulan ini'],
            [UsageCounter::ANGGOTA, 'Anggota'],
            [UsageCounter::LAMPIRAN_BYTE, 'Lampiran (byte)'],
        ];

        return array_map(function (array $satu) use ($workspace, $plan): object {
            [$metric, $label] = $satu;

            $batas = $plan?->batas($this->kunciBatas($metric)) ?? Plan::TANPA_BATAS;
            $terpakai = $this->terpakai($workspace, $metric);

            return (object) [
                'metric' => $metric,
                'label' => $label,
                'terpakai' => $terpakai,
                'batas' => $batas,
                'persen' => $batas <= 0 ? 0 : (int) min(100, round($terpakai / $batas * 100)),
            ];
        }, $daftar);
    }

    public function plan(Workspace $workspace): ?Plan
    {
        return Subscription::query()
            ->with('plan')
            ->where('workspace_id', $workspace->getKey())
            ->first()
            ?->plan;
    }

    /**
     * Nama metrik di tabel penghitung berbeda dari nama batas di JSON plan.
     * Pemetaannya dinyatakan di satu tempat, bukan disebar ke pemanggil.
     */
    private function kunciBatas(string $metric): string
    {
        return match ($metric) {
            UsageCounter::TRANSAKSI => 'transactions_per_month',
            UsageCounter::ANGGOTA => 'members',
            UsageCounter::LAMPIRAN_BYTE => 'attachments_mb',
            UsageCounter::OCR => 'ocr',
            default => $metric,
        };
    }
}
