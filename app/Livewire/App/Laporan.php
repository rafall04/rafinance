<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Ledger\Services\Reports;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class Laporan extends Component
{
    #[Url(as: 'periode')]
    public string $periode = 'bulan-ini';

    #[Url(as: 'lihat')]
    public string $tampilan = 'kategori';

    public string $dariKustom = '';

    public string $sampaiKustom = '';

    public function mount(): void
    {
        [$dari, $sampai] = $this->rentang();
        $this->dariKustom = $dari->toDateString();
        $this->sampaiKustom = $sampai->toDateString();
    }

    public function pilihPeriode(string $periode): void
    {
        $this->periode = $periode;

        [$dari, $sampai] = $this->rentang();
        $this->dariKustom = $dari->toDateString();
        $this->sampaiKustom = $sampai->toDateString();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentang(): array
    {
        $hariAwal = app(TenantContext::class)->workspace()?->period_start_day ?? 1;
        $sekarang = CarbonImmutable::now();

        return match ($this->periode) {
            'minggu-ini' => [$sekarang->startOfWeek(), $sekarang->endOfWeek()],
            'bulan-lalu' => [
                $this->awalPeriode($sekarang->subMonth(), $hariAwal),
                $this->awalPeriode($sekarang, $hariAwal)->subDay(),
            ],
            'tahun-ini' => [$sekarang->startOfYear(), $sekarang->endOfYear()],
            'kustom' => [
                CarbonImmutable::parse($this->dariKustom ?: $sekarang->startOfMonth()->toDateString()),
                CarbonImmutable::parse($this->sampaiKustom ?: $sekarang->toDateString()),
            ],
            default => [
                $this->awalPeriode($sekarang, $hariAwal),
                $this->awalPeriode($sekarang, $hariAwal)->addMonth()->subDay(),
            ],
        };
    }

    /**
     * Awal periode pembukuan, yang belum tentu tanggal 1.
     *
     * Banyak usaha kecil menutup buku ikut tanggal gajian atau tanggal tagihan,
     * dan laporan yang memaksa tanggal 1 akan memotong siklus itu di tengah.
     */
    private function awalPeriode(CarbonImmutable $acuan, int $hariAwal): CarbonImmutable
    {
        $awal = $acuan->startOfMonth()->addDays($hariAwal - 1);

        return $acuan->day >= $hariAwal ? $awal : $awal->subMonth();
    }

    public function render()
    {
        $laporan = app(Reports::class);
        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';
        [$dari, $sampai] = $this->rentang();

        $isi = match ($this->tampilan) {
            'akun' => $laporan->perDimensi('account', $dari, $sampai, $mataUang),
            'proyek' => $laporan->perDimensi('project', $dari, $sampai, $mataUang),
            'kontak' => $laporan->perDimensi('contact', $dari, $sampai, $mataUang),
            'arus' => $laporan->arusKas($dari, $sampai, $this->satuanArus($dari, $sampai), $mataUang),
            default => $laporan->perKategori($dari, $sampai, 'expense', $mataUang),
        };

        return view('livewire.app.laporan', [
            'dari' => $dari,
            'sampai' => $sampai,
            'isi' => $isi,
            'labaRugi' => $laporan->labaRugi($dari, $sampai, $mataUang),
            'neraca' => $laporan->neraca($sampai, $mataUang),
            'banding' => $laporan->banding($dari, $sampai, $mataUang),
            'pemasukan' => $laporan->perKategori($dari, $sampai, 'income', $mataUang),
        ])->layout('components.layouts.app', ['title' => 'Laporan']);
    }

    private function satuanArus(CarbonImmutable $dari, CarbonImmutable $sampai): string
    {
        $hari = $dari->diffInDays($sampai);

        // Grafik dengan tiga ratus enam puluh lima batang di layar 360px tidak
        // memberi tahu apa pun.
        return match (true) {
            $hari > 120 => 'month',
            $hari > 40 => 'week',
            default => 'day',
        };
    }
}
