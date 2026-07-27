<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Billing\Services\PlatformStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dasbor admin platform.
 *
 * Setiap angka di sini adalah hitungan. Widget ini tidak pernah menyentuh model
 * finansial — seluruh datanya datang dari PlatformStats, yang secara bentuk
 * hanya bisa mengembalikan int.
 */
class RingkasanPlatform extends StatsOverviewWidget
{
    protected ?string $heading = 'Kesehatan platform';

    protected function getStats(): array
    {
        $angka = app(PlatformStats::class);

        $gagalParse = $angka->tingkatGagalParse();

        return [
            Stat::make('Pengguna', number_format($angka->jumlahPengguna(), 0, ',', '.')),

            Stat::make('Workspace', number_format($angka->jumlahWorkspace(), 0, ',', '.'))
                ->description($angka->workspaceAktif().' aktif 30 hari terakhir'),

            Stat::make('Transaksi', number_format($angka->jumlahTransaksi(), 0, ',', '.'))
                ->description(number_format($angka->transaksiBulanIni(), 0, ',', '.').' bulan ini')
                // Ditegaskan di antarmukanya sendiri, bukan hanya di dokumen.
                ->descriptionIcon('heroicon-m-hashtag'),

            Stat::make('Gagal parse', $gagalParse.'%')
                ->description('30 hari terakhir')
                ->color($gagalParse > 20 ? 'danger' : ($gagalParse > 10 ? 'warning' : 'success')),

            Stat::make('Antrean', number_format($angka->antreanMenunggu(), 0, ',', '.'))
                ->description($angka->jobGagal().' job gagal')
                ->color($angka->jobGagal() > 0 ? 'danger' : 'success'),

            Stat::make('Webhook gagal', number_format($angka->webhookGagal(), 0, ',', '.'))
                ->description($angka->izinDukunganBerlaku().' izin dukungan berlaku')
                ->color($angka->webhookGagal() > 0 ? 'warning' : 'success'),
        ];
    }
}
