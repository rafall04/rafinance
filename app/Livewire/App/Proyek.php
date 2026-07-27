<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Ledger\Services\Reports;
use App\Domain\Projects\Models\Project;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * Laba rugi per pekerjaan.
 *
 * Ada karena tim produksi event dan kontraktor kecil perlu tahu untung-rugi per
 * job, bukan per bulan. Pertanyaan "acara kemarin untung berapa" tidak bisa
 * dijawab kategori maupun laporan bulanan.
 */
class Proyek extends Component
{
    public bool $sedangMenambah = false;

    public string $nama = '';

    public function bukaFormulir(): void
    {
        $this->sedangMenambah = true;
        $this->reset('nama');
    }

    public function simpan(): void
    {
        $data = $this->validate(['nama' => ['required', 'string', 'min:2', 'max:80']]);

        Project::query()->create(['name' => $data['nama'], 'status' => 'active']);

        $this->sedangMenambah = false;
        $this->reset('nama');

        session()->flash('kabar', 'Proyek dibuat.');
    }

    public function tutup(string $id): void
    {
        Project::query()->findOrFail($id)->forceFill(['status' => 'done'])->save();

        session()->flash('kabar', 'Proyek ditandai selesai.');
    }

    public function render()
    {
        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';

        // Rentang lebar sengaja: proyek berjalan lintas bulan, dan memotongnya
        // di batas bulan akan menyembunyikan biaya yang sudah keluar duluan.
        $ringkasan = app(Reports::class)->perDimensi(
            'project',
            CarbonImmutable::now()->subYears(3),
            CarbonImmutable::now()->addYear(),
            $mataUang,
        )->keyBy('id');

        return view('livewire.app.proyek', [
            'daftar' => Project::query()->orderByRaw("status = 'done'")->orderBy('name')->get(),
            'ringkasan' => $ringkasan,
        ])->layout('components.layouts.app', ['title' => 'Proyek']);
    }
}
