<?php

declare(strict_types=1);

namespace App\Livewire\App;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Membuka kunci aplikasi dengan PIN enam digit.
 *
 * PIN ini bukan pengganti kata sandi — ia melindungi dari orang yang sedang
 * memegang ponsel yang sudah terbuka, bukan dari penyerang jarak jauh. Karena
 * itu PIN tetap di-hash, tetap dibatasi lajunya, dan pengguna yang lupa selalu
 * bisa keluar lalu masuk lagi dengan kata sandinya.
 */
class KunciAplikasi extends Component
{
    public string $pin = '';

    public function buka(): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $kunci = 'app-lock:'.$pengguna->getKey();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages([
                'pin' => 'Terlalu banyak percobaan. Coba lagi dalam '
                    .RateLimiter::availableIn($kunci).' detik, atau keluar lalu masuk dengan kata sandi.',
            ]);
        }

        $this->validate();

        $hash = $pengguna->appLockPinHash();

        if ($hash === null) {
            // Tidak ada PIN yang dipasang: tidak ada yang perlu dibuka.
            $this->selesai();

            return;
        }

        if (! Hash::check($this->pin, $hash)) {
            RateLimiter::hit($kunci, 300);

            throw ValidationException::withMessages(['pin' => 'PIN tidak cocok.']);
        }

        RateLimiter::clear($kunci);
        $this->selesai();
    }

    private function selesai(): void
    {
        $this->js("sessionStorage.removeItem('rafin.terkunci'); window.location.href = '/app';");
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return ['pin' => ['required', 'digits:6']];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'pin.required' => 'Masukkan PIN enam digit.',
            'pin.digits' => 'PIN terdiri dari enam angka.',
        ];
    }

    public function render()
    {
        return view('livewire.app.kunci-aplikasi')
            ->layout('components.layouts.tamu', ['title' => 'Terkunci']);
    }
}
