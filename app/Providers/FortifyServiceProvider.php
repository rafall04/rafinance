<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Fortify dipakai tanpa satu pun tampilan bawaannya.
 *
 * Yang diambil dari Fortify adalah bagian yang membosankan sekaligus mudah
 * salah kalau ditulis sendiri: rotasi sesi saat masuk, token reset kata sandi,
 * verifikasi surel, konfirmasi kata sandi, dan nanti TOTP di FASE 5. Yang
 * ditulis sendiri adalah seluruh tampilannya, supaya tidak ada satu pun Blade
 * berbahasa Inggris yang harus dibongkar agar cocok dengan sistem desain.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.masuk'));
        Fortify::registerView(fn () => view('auth.daftar'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.lupa-sandi'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.sandi-baru', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verifikasi-surel'));
        Fortify::confirmPasswordView(fn () => view('auth.konfirmasi-sandi'));
        Fortify::twoFactorChallengeView(fn () => view('auth.dua-langkah'));

        $this->rateLimiters();
    }

    /**
     * Batas laju masuk dipatok per surel DAN per IP sekaligus.
     *
     * Per IP saja terlalu longgar untuk serangan terdistribusi, sekaligus
     * terlalu ketat untuk satu warung dengan tiga orang di balik satu koneksi.
     * Per surel saja membiarkan penebakan menyebar ke banyak akun.
     */
    private function rateLimiters(): void
    {
        // Batas yang terlampaui dijawab di halaman masuk itu sendiri, bukan
        // dengan layar 429. Orang yang salah ketik kata sandi lima kali sedang
        // kesulitan, bukan sedang menyerang — dan kalaupun ia penyerang,
        // halaman galat yang ramah tidak memberinya keuntungan apa pun.
        $jawaban = fn (Request $request, array $headers) => back()
            ->withInput($request->only(Fortify::username()))
            ->withErrors([
                Fortify::username() => trans('auth.throttle', [
                    'seconds' => $headers['Retry-After'] ?? 60,
                ]),
            ]);

        RateLimiter::for('login', function (Request $request) use ($jawaban) {
            $email = (string) $request->input(Fortify::username());

            return [
                Limit::perMinute(5)->by(Str::transliterate(Str::lower($email)))->response($jawaban),
                Limit::perMinute(20)->by($request->ip() ?? 'tanpa-ip')->response($jawaban),
            ];
        });

        RateLimiter::for(
            'two-factor',
            fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')),
        );
    }
}
