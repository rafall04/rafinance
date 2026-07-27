<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Services;

use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Models\BudgetPeriod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung berapa yang sudah terpakai dari setiap anggaran.
 *
 * Dihitung dari entries setiap kali, bukan dibaca dari kolom cache. Anggaran
 * yang menunjukkan angka berbeda dari buku besar akan membuat orang berhenti
 * mempercayai keduanya sekaligus.
 */
final class BudgetProgress
{
    /**
     * @return Collection<int, object{
     *     budget: Budget, terpakai: Money, tersedia: Money, sisa: Money,
     *     persentase: int, terlampaui: bool, periode: BudgetPeriod
     * }>
     */
    public function untukTanggal(?CarbonImmutable $tanggal = null, string $currency = 'IDR'): Collection
    {
        $tanggal ??= CarbonImmutable::now();

        return Budget::query()
            ->aktif()
            ->with('category.parent')
            ->get()
            ->map(function (Budget $budget) use ($tanggal, $currency): object {
                [$awal, $akhir] = $budget->rentangUntuk($tanggal);
                $periode = $this->periodeUntuk($budget, $awal, $akhir);

                $terpakai = $this->terpakai($budget, $awal, $akhir, $currency);
                $tersedia = $periode->allocated_minor->plus($periode->carried_in_minor);

                return (object) [
                    'budget' => $budget,
                    'periode' => $periode,
                    'terpakai' => $terpakai,
                    'tersedia' => $tersedia,
                    'sisa' => $tersedia->minus($terpakai),
                    'persentase' => $tersedia->isZero()
                        ? 0
                        : (int) min(100, round($terpakai->minor / $tersedia->minor * 100)),
                    'terlampaui' => $terpakai->compareTo($tersedia) > 0,
                ];
            });
    }

    /**
     * Membuat atau memperbarui baris periode, termasuk sisa yang dibawa dari
     * periode sebelumnya kalau rollover menyala.
     */
    public function periodeUntuk(Budget $budget, CarbonImmutable $awal, CarbonImmutable $akhir): BudgetPeriod
    {
        $periode = BudgetPeriod::query()->firstOrNew([
            'budget_id' => $budget->getKey(),
            'period_start' => $awal->toDateString(),
        ]);

        if (! $periode->exists) {
            $periode->fill([
                'workspace_id' => $budget->workspace_id,
                'period_end' => $akhir->toDateString(),
                'allocated_minor' => $budget->amount_minor,
                'carried_in_minor' => $budget->rollover
                    ? $this->sisaPeriodeSebelumnya($budget, $awal)
                    : Money::zero($budget->amount_minor->currency),
            ]);

            $periode->save();
        }

        return $periode;
    }

    /**
     * Sisa periode sebelumnya, hanya kalau positif.
     *
     * Kelebihan belanja tidak dibawa jadi utang ke bulan depan: anggaran adalah
     * alat perencanaan, bukan alat menghukum. Orang yang bulan lalu terpaksa
     * berobat tidak perlu memulai bulan ini dengan minus.
     */
    private function sisaPeriodeSebelumnya(Budget $budget, CarbonImmutable $awal): Money
    {
        $sebelumnya = BudgetPeriod::query()
            ->where('budget_id', $budget->getKey())
            ->where('period_start', '<', $awal->toDateString())
            ->orderByDesc('period_start')
            ->first();

        if ($sebelumnya === null) {
            return Money::zero($budget->amount_minor->currency);
        }

        $terpakai = $this->terpakai(
            $budget,
            $sebelumnya->period_start,
            $sebelumnya->period_end,
            $budget->amount_minor->currency,
        );

        $sisa = $sebelumnya->allocated_minor->plus($sebelumnya->carried_in_minor)->minus($terpakai);

        return $sisa->isNegative() ? Money::zero($budget->amount_minor->currency) : $sisa;
    }

    public function terpakai(Budget $budget, CarbonImmutable $awal, CarbonImmutable $akhir, string $currency): Money
    {
        $total = DB::connection('pgsql')->selectOne(
            <<<'SQL'
                SELECT COALESCE(SUM(e.amount_minor), 0) AS total
                FROM entries e
                JOIN transactions t ON t.id = e.transaction_id
                JOIN accounts a ON a.id = e.account_id
                WHERE t.status <> ?
                  AND t.category_id = ?
                  AND t.booked_date BETWEEN ? AND ?
                  AND a.type = 'expense'
            SQL,
            [
                TransactionStatus::Draft->value,
                $budget->category_id,
                $awal->toDateString(),
                $akhir->toDateString(),
            ],
        );

        return Money::ofMinor((int) ($total->total ?? 0), $currency);
    }
}
