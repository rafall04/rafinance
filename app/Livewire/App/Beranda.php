<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Services\LedgerView;
use App\Domain\Tenancy\TenantContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Buku besar sebagai halaman utama, bukan dasbor.
 *
 * Yang orang butuhkan sepuluh kali sehari adalah "berapa saldo saya dan apa
 * yang barusan saya catat" — bukan diagram lingkaran.
 */
class Beranda extends Component
{
    #[Url(as: 'akun', except: '')]
    public string $akunTerpilih = '';

    public function pilihAkun(string $id = ''): void
    {
        $this->akunTerpilih = $this->akunTerpilih === $id ? '' : $id;
    }

    public function render()
    {
        $ledger = app(LedgerView::class);
        $workspace = app(TenantContext::class)->workspace();
        $mataUang = $workspace?->currency ?? 'IDR';

        $akun = $this->akunTerpilih !== ''
            ? Account::query()->find($this->akunTerpilih)
            : null;

        return view('livewire.app.beranda', [
            'workspace' => $workspace,
            'akunUang' => $ledger->akunUang(),
            'akunAktif' => $akun,
            'saldoTotal' => $ledger->saldoTotal($ledger->idAkunUang($akun), $mataUang),
            'hari' => $ledger->harian($akun, currency: $mataUang),
        ])->layout('components.layouts.app', ['title' => 'Beranda']);
    }
}
