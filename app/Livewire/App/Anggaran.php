<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Models\Goal;
use App\Domain\Budgeting\Services\BudgetProgress;
use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Tenancy\TenantContext;
use App\Support\Money;
use Livewire\Component;

class Anggaran extends Component
{
    public bool $sedangMenambah = false;

    public string $kategoriId = '';

    public string $jumlah = '';

    public string $periode = 'monthly';

    public bool $rollover = false;

    public function bukaFormulir(): void
    {
        $this->sedangMenambah = true;
        $this->reset(['kategoriId', 'jumlah', 'rollover']);
        $this->periode = 'monthly';
    }

    public function batal(): void
    {
        $this->sedangMenambah = false;
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $data = $this->validate();
        $mataUang = app(TenantContext::class)->workspace()?->currency ?? 'IDR';

        Budget::query()->updateOrCreate(
            [
                'category_id' => $data['kategoriId'],
                'period' => $data['periode'],
            ],
            [
                'amount_minor' => Money::parse($data['jumlah'], $mataUang),
                'rollover' => $data['rollover'],
                'starts_on' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ],
        );

        $this->sedangMenambah = false;
        $this->reset(['kategoriId', 'jumlah', 'rollover']);

        session()->flash('kabar', 'Anggaran disimpan.');
    }

    public function hapus(string $id): void
    {
        Budget::query()->findOrFail($id)->forceFill(['is_active' => false])->save();

        session()->flash('kabar', 'Anggaran dinonaktifkan.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kategoriId' => ['required', 'ulid'],
            'jumlah' => ['required', 'string', 'max:20'],
            'periode' => ['required', 'in:weekly,monthly'],
            'rollover' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['kategoriId' => 'kategori', 'jumlah' => 'jumlah anggaran', 'periode' => 'periode'];
    }

    public function render()
    {
        return view('livewire.app.anggaran', [
            'kemajuan' => app(BudgetProgress::class)->untukTanggal(),
            'kategoriPilihan' => Category::query()->aktif()->where('kind', CategoryKind::Expense)->orderBy('name')->get(),
            'target' => Goal::query()->with('account')->orderBy('target_date')->get(),
            'punyaAkun' => Account::query()->uang()->aktif()->exists(),
        ])->layout('components.layouts.app', ['title' => 'Anggaran']);
    }
}
