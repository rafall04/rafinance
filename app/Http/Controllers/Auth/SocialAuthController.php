<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Exceptions\PenyambunganDitolak;
use App\Domain\Tenancy\Services\ResolveSocialUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Masuk dan mendaftar lewat penyedia pihak ketiga.
 *
 * Controller ini sengaja tipis. Seluruh keputusan keamanannya ada di
 * ResolveSocialUser — di sini hanya urusan HTTP: mengarahkan ke penyedia,
 * menerima kembaliannya, dan mengubah kegagalan jadi kalimat yang bisa dibaca.
 *
 * Yang TIDAK dilakukan di sini, dan itu disengaja: `stateless()`. Socialite
 * memakai parameter `state` di sesi untuk mengikat permintaan keluar dengan
 * kembaliannya, dan itulah yang mencegah orang lain menyodorkan kode otorisasi
 * milik akun mereka ke sesi korban.
 */
final class SocialAuthController
{
    public function __construct(
        private readonly ResolveSocialUser $resolve,
        private readonly SecurityLogger $keamanan,
    ) {}

    /**
     * Mengarahkan ke penyedia.
     */
    public function redirect(Request $request, string $provider): SymfonyRedirect|RedirectResponse
    {
        $penyedia = $this->penyedia($provider);

        // Diingat supaya callback tahu ini penyambungan dari halaman Keamanan,
        // bukan upaya masuk — keduanya memakai URL kembalian yang sama.
        $request->session()->put('oauth.maksud', $request->user() !== null ? 'sambung' : 'masuk');

        return Socialite::driver($penyedia->value)->redirect();
    }

    /**
     * Menerima kembalian dari penyedia.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $penyedia = $this->penyedia($provider);
        $maksud = (string) $request->session()->pull('oauth.maksud', 'masuk');
        $sedangMasuk = $request->user();

        try {
            $dariPenyedia = Socialite::driver($penyedia->value)->user();
        } catch (Throwable) {
            // Kode otorisasi kedaluwarsa, state tidak cocok, atau pengguna
            // membatalkan di layar penyedia. Ketiganya bukan hal yang perlu
            // dijelaskan berbeda-beda kepada pengguna.
            return $this->kembaliDenganGalat(
                $maksud,
                PenyambunganDitolak::gagalDiPenyedia($penyedia)->getMessage(),
            );
        }

        try {
            $hasil = ($this->resolve)($penyedia, $dariPenyedia, $sedangMasuk);
        } catch (PenyambunganDitolak $ditolak) {
            $this->keamanan->log(
                SecurityEventType::OauthRejected,
                user: $sedangMasuk,
                request: $request,
                metadata: ['provider' => $penyedia->value, 'intent' => $maksud],
            );

            return $this->kembaliDenganGalat($maksud, $ditolak->getMessage());
        }

        // Penyambungan dari halaman Keamanan: sesinya sudah ada, tidak perlu
        // login ulang.
        if ($sedangMasuk !== null) {
            return redirect()
                ->route('app.keamanan')
                ->with('kabar', $penyedia->label().' disambungkan.');
        }

        // Sesi diputar ulang saat masuk, sama seperti jalur kata sandi.
        // Event Login yang terpicu di sini pula yang mencatat peristiwa
        // keamanan dan perangkat barunya.
        Auth::login($hasil['user'], remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('app.beranda'));
    }

    /**
     * Memutuskan sambungan, dipanggil dari halaman Keamanan.
     */
    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $penyedia = $this->penyedia($provider);
        $pengguna = $request->user();

        abort_if($pengguna === null, 404);

        try {
            $this->resolve->putuskan($pengguna, $penyedia);
        } catch (PenyambunganDitolak $ditolak) {
            return redirect()->route('app.keamanan')->withErrors(['oauth' => $ditolak->getMessage()]);
        }

        return redirect()->route('app.keamanan')->with('kabar', $penyedia->label().' diputuskan.');
    }

    /**
     * Penyedia yang tidak dikenal atau belum dikonfigurasi menjawab 404.
     *
     * Bukan 400 maupun halaman galat yang menjelaskan: URL yang menjawab
     * "penyedia itu ada tapi belum disetel" memberi tahu lebih banyak daripada
     * yang perlu diketahui siapa pun dari luar.
     */
    private function penyedia(string $provider): SocialProvider
    {
        $penyedia = SocialProvider::tryFrom($provider);

        abort_if($penyedia === null || ! $penyedia->isConfigured(), 404);

        return $penyedia;
    }

    private function kembaliDenganGalat(string $maksud, string $pesan): RedirectResponse
    {
        $tujuan = $maksud === 'sambung' ? 'app.keamanan' : 'login';

        return redirect()->route($tujuan)->withErrors(['oauth' => $pesan]);
    }
}
