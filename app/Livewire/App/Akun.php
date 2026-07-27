<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Models\Account;
use App\Domain\Reconciliation\Services\PerformCashCount;
use App\Domain\Tenancy\TenantContext;
use App\Support\Money;
use Livewire\Component;

class Akun extends Component
{
    public bool $sedangMenambah = false;

    public string $nama = '';

    public string $subtype = 'cash';

    public string $saldoAwal = '';

    public function bukaFormulir(): void
    {
        $this->sedangMenambah = true;
        $this->reset(['nama', 'subtype', 'saldoAwal']);
        $this->subtype = 'cash';
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
        $subtype = AccountSubtype::from($data['subtype']);

        $saldo = $data['saldoAwal'] === ''
            ? Money::zero($mataUang)
            : Money::parse($data['saldoAwal'], $mataUang);

        Account::query()->create([
            'name' => $data['nama'],
            // Utang adalah kewajiban; sisanya harta. Pengguna tidak perlu tahu
            // istilah itu, tapi pembukuannya tetap harus benar.
            'type' => $subtype === AccountSubtype::Payable ? AccountType::Liability : AccountType::Asset,
            'subtype' => $subtype,
            'currency' => $mataUang,
            'opening_balance_minor' => $saldo,
            'sort_order' => (int) Account::query()->milikPengguna()->max('sort_order') + 1,
        ]);

        $this->sedangMenambah = false;
        $this->reset(['nama', 'subtype', 'saldoAwal']);

        session()->flash('kabar', 'Akun ditambahkan.');
    }

    public string $menghitungId = '';

    public string $jumlahTerhitung = '';

    public function bukaCashOpname(string $id): void
    {
        $this->menghitungId = $id;
        $this->jumlahTerhitung = '';
        $this->resetValidation();
    }

    /**
     * Cash opname: menghitung uang sungguhan, lalu merapikan buku agar cocok.
     *
     * Selisihnya tidak pernah dihapus diam-diam — ia jadi transaksi penyesuaian
     * yang muncul di laporan, karena angka itulah yang memberi tahu ada sesuatu
     * yang perlu diperiksa.
     */
    public function simpanCashOpname(PerformCashCount $hitung): void
    {
        $data = $this->validate(
            ['jumlahTerhitung' => ['required', 'string', 'max:20']],
            [],
            ['jumlahTerhitung' => 'jumlah terhitung'],
        );

        $akun = Account::query()->findOrFail($this->menghitungId);
        $terhitung = Money::parse($data['jumlahTerhitung'], $akun->currency);

        $hasil = $hitung($akun, $terhitung);

        $this->reset(['menghitungId', 'jumlahTerhitung']);

        session()->flash('kabar', $hasil->cocok()
            ? 'Cocok dengan buku. Tidak ada penyesuaian.'
            : 'Selisih '.$hasil->difference_minor->format().' dicatat sebagai penyesuaian.');
    }

    public function tutupAkun(string $id): void
    {
        $akun = Account::query()->findOrFail($id);

        // Ditutup, bukan dihapus: entries-nya adalah bagian dari riwayat, dan
        // riwayat yang berlubang tidak bisa dipercaya.
        $akun->forceFill(['is_archived' => true])->save();

        session()->flash('kabar', 'Akun ditutup. Riwayatnya tetap tersimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:1', 'max:60'],
            'subtype' => ['required', 'in:cash,bank,ewallet,receivable,payable'],
            'saldoAwal' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nama' => 'nama akun', 'subtype' => 'jenis akun', 'saldoAwal' => 'saldo awal'];
    }

    public function render()
    {
        return view('livewire.app.akun', [
            'daftar' => Account::query()
                ->milikPengguna()
                ->aktif()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'ditutup' => Account::query()->milikPengguna()->where('is_archived', true)->orderBy('name')->get(),
            'jenis' => [
                AccountSubtype::Cash,
                AccountSubtype::Bank,
                AccountSubtype::Ewallet,
                AccountSubtype::Receivable,
                AccountSubtype::Payable,
            ],
        ])->layout('components.layouts.app', ['title' => 'Akun']);
    }
}
