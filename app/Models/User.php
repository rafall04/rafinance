<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\SocialAccount;
use App\Domain\Tenancy\Models\UserDevice;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Surel wajib dan unik meski pengguna hanya memakai Telegram.
 *
 * Alasannya bukan kerapian data: tanpa surel, akun yang kehilangan akses ke
 * Telegram tidak punya jalan pulang sama sekali. Bagi orang yang seluruh
 * pembukuan usahanya ada di sini, itu bukan ketidaknyamanan — itu kehilangan.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $locale
 * @property string $timezone
 */
#[Fillable(['name', 'email', 'password', 'locale', 'timezone'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'app_lock_pin_hash'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /**
     * Gerbang panel admin platform.
     *
     * Perlu ditegaskan supaya tidak salah baca: menjadi admin platform TIDAK
     * memberi akses ke data transaksi siapa pun. Panel itu sendiri memang tidak
     * punya kode untuk membacanya (aturan A5) — bendera ini hanya menentukan
     * siapa yang boleh membuka pintunya.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return (bool) ($this->getAttributes()['is_platform_admin'] ?? false);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Punya kata sandi sungguhan.
     *
     * Pengguna yang mendaftar lewat Google tidak punya kata sandi sama sekali,
     * dan itu keadaan yang sah — bukan data yang belum lengkap.
     */
    public function punyaKataSandi(): bool
    {
        $hash = $this->getAttributes()['password'] ?? null;

        return is_string($hash) && $hash !== '';
    }

    /**
     * Hash kata sandi untuk pemeriksaan autentikasi.
     *
     * Dikembalikan sebagai string kosong, bukan null, saat pengguna memang tidak
     * punya kata sandi. Alasannya bukan gaya: pemeriksa hash bawaan memanggil
     * password_get_info() pada nilai ini, dan null di sana melempar di PHP 8.
     * String kosong ditolak dengan rapi — akun tanpa kata sandi memang tidak
     * bisa dimasuki lewat formulir kata sandi, tapi caranya harus berupa
     * penolakan, bukan galat 500.
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->getAttributes()['password'] ?? '');
    }

    /**
     * @return array<int, SocialProvider>
     */
    public function penyediaTersambung(): array
    {
        return $this->socialAccounts
            ->map(fn (SocialAccount $akun): SocialProvider => $akun->provider)
            ->all();
    }

    public function roleIn(Workspace|string $workspace): ?WorkspaceRole
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->getKey() : $workspace;

        return $this->memberships()
            ->where('workspace_id', $workspaceId)
            ->first()
            ?->role;
    }

    public function belongsToWorkspace(Workspace|string $workspace): bool
    {
        return $this->roleIn($workspace) !== null;
    }

    /**
     * Hash PIN kunci aplikasi, dibaca dari atribut mentah.
     *
     * getAttributes() dan bukan $this->app_lock_pin_hash: model yang lahir dari
     * create() tidak memuat kolom yang tidak ikut diisi, dan
     * Model::shouldBeStrict() melempar begitu kolom itu disentuh. Layout
     * memeriksanya di setiap halaman, jadi jalur ini harus aman untuk model
     * yang baru saja dibuat maupun yang diambil utuh dari database.
     */
    public function appLockPinHash(): ?string
    {
        $hash = $this->getAttributes()['app_lock_pin_hash'] ?? null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function punyaKunciAplikasi(): bool
    {
        return $this->appLockPinHash() !== null;
    }

    /**
     * Verifikasi dua langkah sudah dikonfirmasi.
     *
     * Dibaca dari atribut mentah, dengan alasan yang sama seperti
     * appLockPinHash(): model yang baru dibuat tidak memuat kolom yang tidak
     * ikut diisi, dan halaman keamanan memeriksanya di setiap render.
     */
    public function duaLangkahAktif(): bool
    {
        return ($this->getAttributes()['two_factor_confirmed_at'] ?? null) !== null;
    }

    /**
     * Zona waktu pengguna menentukan booked_date sebuah transaksi (aturan A10).
     * Server berjalan di UTC; tanggal buku tidak.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: (string) config('rafin.default_timezone', 'Asia/Jakarta');
    }
}
