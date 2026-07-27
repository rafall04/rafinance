<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Tenancy\TenantContext;
use Livewire\Component;

/**
 * Slot kelima bilah navigasi: semua yang tidak dipakai sepuluh kali sehari.
 *
 * Halaman ini sengaja hanya berisi tautan. Ia bukan tempat menaruh fitur yang
 * tidak jelas rumahnya — ia daftar isi.
 */
class Lainnya extends Component
{
    public function render()
    {
        $tautan = [
            [
                'judul' => 'Buku besar',
                'isi' => [
                    ['label' => 'Akun', 'rute' => 'app.akun', 'ket' => 'Kas, bank, e-wallet, saldo awal'],
                    ['label' => 'Proyek', 'rute' => 'app.proyek', 'ket' => 'Untung rugi per pekerjaan'],
                    ['label' => 'Anggaran', 'rute' => 'app.anggaran', 'ket' => 'Batas belanja dan target tabungan'],
                    ['label' => 'Tagihan', 'rute' => 'app.tagihan', 'ket' => 'Piutang pelanggan dan umurnya'],
                ],
            ],
            [
                'judul' => 'Kepercayaan',
                'isi' => [
                    ['label' => 'Log aktivitas', 'rute' => 'app.log', 'ket' => 'Setiap perubahan, beserta pelakunya'],
                    ['label' => 'Keamanan', 'rute' => 'app.keamanan', 'ket' => 'Perangkat, dua langkah, PIN, akses dukungan'],
                ],
            ],
            [
                'judul' => 'Pengaturan',
                'isi' => [
                    ['label' => 'Telegram', 'rute' => 'app.pengaturan.telegram', 'ket' => 'Catat tanpa membuka aplikasi'],
                    ['label' => 'Langganan', 'rute' => 'app.langganan', 'ket' => 'Plan, pemakaian, dan kuota'],
                ],
            ],
        ];

        return view('livewire.app.lainnya', [
            'kelompok' => $tautan,
            'workspace' => app(TenantContext::class)->workspace(),
        ])->layout('components.layouts.app', ['title' => 'Lainnya']);
    }
}
