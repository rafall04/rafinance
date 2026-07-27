<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Exceptions\PenyambunganDitolak;
use App\Domain\Tenancy\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Menentukan pengguna Rafin mana yang dimaksud oleh sebuah akun pihak ketiga.
 *
 * Seluruh keamanan masuk lewat Google dan sejenisnya ada di kelas ini, dan
 * urutan pemeriksaannya bukan selera:
 *
 *   1. Akun penyedia ini sudah pernah disambungkan → itu orangnya. Selesai.
 *   2. Ada yang sedang masuk → ia sedang menyambungkan dari halaman Keamanan.
 *   3. Penyedia tidak memberi surel → tolak. Surel wajib sebagai jalan pulang.
 *   4. Surelnya cocok dengan akun yang sudah ada:
 *        a. penyedia MENJAMIN surel terverifikasi → sambungkan, aman.
 *        b. penyedia tidak menjamin → TOLAK.
 *   5. Belum ada siapa-siapa → buat pengguna baru.
 *
 * Langkah 4b adalah alasan kelas ini ada. Menyambungkan otomatis berdasarkan
 * surel yang tidak terverifikasi berarti siapa pun yang bisa mendaftar di
 * penyedia dengan surel orang lain bisa membuka buku kas orang itu. Bagi
 * sebagian pengguna Rafin, isi buku itu adalah seluruh pembukuan usahanya.
 */
final class ResolveSocialUser
{
    public function __construct(
        private readonly SecurityLogger $keamanan,
    ) {}

    /**
     * @return array{user: User, baru: bool, tersambung: bool}
     */
    public function __invoke(
        SocialProvider $penyedia,
        SocialiteUser $dariPenyedia,
        ?User $sedangMasuk = null,
    ): array {
        $providerUserId = (string) $dariPenyedia->getId();

        $tersimpan = SocialAccount::query()
            ->where('provider', $penyedia->value)
            ->where('provider_user_id', $providerUserId)
            ->first();

        // 1. Sudah pernah disambungkan.
        if ($tersimpan !== null) {
            // Kalau ada orang lain yang sedang masuk, akun ini bukan miliknya.
            if ($sedangMasuk !== null && $tersimpan->user_id !== $sedangMasuk->getKey()) {
                throw PenyambunganDitolak::sudahDipakaiOrangLain($penyedia);
            }

            $this->segarkan($tersimpan, $dariPenyedia, $penyedia);

            return ['user' => $tersimpan->user, 'baru' => false, 'tersambung' => false];
        }

        // 2. Penyambungan dari halaman Keamanan oleh orang yang sudah masuk.
        if ($sedangMasuk !== null) {
            $this->sambungkan($sedangMasuk, $penyedia, $dariPenyedia);

            return ['user' => $sedangMasuk, 'baru' => false, 'tersambung' => true];
        }

        $surel = $this->surel($dariPenyedia);

        // 3. Surel wajib — ia satu-satunya jalan pulang kalau perangkat hilang.
        if ($surel === null) {
            throw PenyambunganDitolak::tanpaSurel($penyedia);
        }

        $sudahAda = User::query()->where('email', $surel)->first();

        // 4. Surel cocok dengan akun yang sudah ada.
        if ($sudahAda !== null) {
            if (! $penyedia->verifiesEmail()) {
                throw PenyambunganDitolak::surelSudahDipakai($penyedia);
            }

            $this->sambungkan($sudahAda, $penyedia, $dariPenyedia);

            return ['user' => $sudahAda, 'baru' => false, 'tersambung' => true];
        }

        // 5. Orang baru.
        return ['user' => $this->buatPengguna($penyedia, $dariPenyedia, $surel), 'baru' => true, 'tersambung' => true];
    }

    /**
     * Memutuskan sambungan.
     *
     * Menolak kalau ini satu-satunya cara pengguna masuk. Orang yang mengunci
     * dirinya sendiri dari pembukuannya tidak akan menyalahkan dirinya — ia akan
     * berhenti memakai Rafin, dan ia benar.
     */
    public function putuskan(User $pengguna, SocialProvider $penyedia): void
    {
        $akun = SocialAccount::query()
            ->where('user_id', $pengguna->getKey())
            ->where('provider', $penyedia->value)
            ->firstOrFail();

        $punyaKataSandi = $pengguna->punyaKataSandi();
        $sisaPenyedia = SocialAccount::query()
            ->where('user_id', $pengguna->getKey())
            ->where('provider', '!=', $penyedia->value)
            ->count();

        if (! $punyaKataSandi && $sisaPenyedia === 0) {
            throw PenyambunganDitolak::satuSatunyaCaraMasuk($penyedia);
        }

        $akun->delete();

        $this->keamanan->log(
            SecurityEventType::OauthUnlinked,
            user: $pengguna,
            request: request(),
            metadata: ['provider' => $penyedia->value],
        );
    }

    private function sambungkan(User $pengguna, SocialProvider $penyedia, SocialiteUser $dariPenyedia): void
    {
        DB::connection('pgsql')->transaction(function () use ($pengguna, $penyedia, $dariPenyedia): void {
            SocialAccount::query()->create([
                'user_id' => $pengguna->getKey(),
                'provider' => $penyedia,
                'provider_user_id' => (string) $dariPenyedia->getId(),
                'provider_email' => $dariPenyedia->getEmail(),
                'provider_nickname' => $dariPenyedia->getNickname() ?? $dariPenyedia->getName(),
                'avatar_url' => $this->avatar($dariPenyedia),
                'email_verified_by_provider' => $penyedia->verifiesEmail(),
                'last_login_at' => now(),
            ]);

            // Penyedia yang memverifikasi surel sekaligus memverifikasinya untuk
            // kita: memaksa orang membuka surel konfirmasi setelah Google sudah
            // memastikannya hanya menambah langkah tanpa menambah keamanan.
            if ($penyedia->verifiesEmail() && $pengguna->email_verified_at === null) {
                $pengguna->forceFill(['email_verified_at' => now()])->save();
            }
        });

        $this->keamanan->log(
            SecurityEventType::OauthLinked,
            user: $pengguna,
            request: request(),
            metadata: [
                'provider' => $penyedia->value,
                'email_verified_by_provider' => $penyedia->verifiesEmail(),
            ],
        );
    }

    private function buatPengguna(SocialProvider $penyedia, SocialiteUser $dariPenyedia, string $surel): User
    {
        return DB::connection('pgsql')->transaction(function () use ($penyedia, $dariPenyedia, $surel): User {
            $pengguna = User::query()->create([
                'name' => $this->nama($dariPenyedia, $surel),
                'email' => $surel,
                // Sengaja tanpa kata sandi. Kolomnya nullable, dan memaksakan
                // kata sandi acak yang tidak pernah diketahui pemiliknya hanya
                // akan membingungkan saat ia mencoba masuk dengan cara biasa.
                'password' => null,
                'locale' => 'id',
                'timezone' => (string) config('rafin.default_timezone', 'Asia/Jakarta'),
            ]);

            if ($penyedia->verifiesEmail()) {
                $pengguna->forceFill(['email_verified_at' => now()])->save();
            }

            SocialAccount::query()->create([
                'user_id' => $pengguna->getKey(),
                'provider' => $penyedia,
                'provider_user_id' => (string) $dariPenyedia->getId(),
                'provider_email' => $dariPenyedia->getEmail(),
                'provider_nickname' => $dariPenyedia->getNickname() ?? $dariPenyedia->getName(),
                'avatar_url' => $this->avatar($dariPenyedia),
                'email_verified_by_provider' => $penyedia->verifiesEmail(),
                'last_login_at' => now(),
            ]);

            return $pengguna->refresh();
        });
    }

    private function segarkan(SocialAccount $akun, SocialiteUser $dariPenyedia, SocialProvider $penyedia): void
    {
        $akun->forceFill([
            'provider_email' => $dariPenyedia->getEmail(),
            'provider_nickname' => $dariPenyedia->getNickname() ?? $dariPenyedia->getName(),
            'avatar_url' => $this->avatar($dariPenyedia),
            'last_login_at' => now(),
        ])->save();
    }

    private function surel(SocialiteUser $dariPenyedia): ?string
    {
        $surel = $dariPenyedia->getEmail();

        if (! is_string($surel) || trim($surel) === '') {
            return null;
        }

        return Str::lower(trim($surel));
    }

    private function nama(SocialiteUser $dariPenyedia, string $surel): string
    {
        $nama = $dariPenyedia->getName() ?? $dariPenyedia->getNickname();

        // Sebagian penyedia — Apple terutama — hanya mengirim nama sekali, saat
        // izin pertama diberikan. Bagian depan surel lebih baik daripada kosong.
        if (! is_string($nama) || trim($nama) === '') {
            return Str::title(str_replace(['.', '_', '-'], ' ', Str::before($surel, '@')));
        }

        return Str::limit(trim($nama), 255, '');
    }

    private function avatar(SocialiteUser $dariPenyedia): ?string
    {
        $avatar = $dariPenyedia->getAvatar();

        return is_string($avatar) && $avatar !== '' ? Str::limit($avatar, 512, '') : null;
    }
}
