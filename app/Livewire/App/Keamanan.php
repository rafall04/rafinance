<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Models\SocialAccount;
use App\Domain\Tenancy\Models\SupportAccessGrant;
use App\Domain\Tenancy\Models\UserDevice;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\PastikanAplikasiTerbuka;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Component;

/**
 * Halaman keamanan akun.
 *
 * Isinya sengaja berupa jawaban atas pertanyaan yang benar-benar ditanyakan
 * orang: siapa yang pernah masuk ke akun saya, dari mana, dan bisakah saya
 * mengusir mereka sekarang juga.
 */
class Keamanan extends Component
{
    public string $pinBaru = '';

    public string $pinUlang = '';

    public int $jamIzin = 4;

    public string $alasanIzin = '';

    public bool $sedangMenyiapkanDuaLangkah = false;

    public string $kodeDuaLangkah = '';

    /**
     * Menyalakan verifikasi dua langkah.
     *
     * Belum langsung aktif — rahasianya dibuat, QR ditampilkan, dan baru
     * dikonfirmasi setelah pengguna berhasil memasukkan satu kode. Tanpa
     * langkah konfirmasi itu, orang yang gagal memindai QR akan terkunci dari
     * akunnya sendiri dan tidak punya cara masuk lagi.
     */
    public function siapkanDuaLangkah(EnableTwoFactorAuthentication $aktifkan): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $aktifkan($pengguna, force: true);

        $this->sedangMenyiapkanDuaLangkah = true;
        $this->kodeDuaLangkah = '';
    }

    public function konfirmasiDuaLangkah(ConfirmTwoFactorAuthentication $konfirmasi): void
    {
        $this->validate(
            ['kodeDuaLangkah' => ['required', 'digits:6']],
            ['kodeDuaLangkah.digits' => 'Kode terdiri dari enam angka.'],
        );

        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        try {
            $konfirmasi($pengguna, $this->kodeDuaLangkah);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'kodeDuaLangkah' => 'Kode tidak cocok. Periksa jam di ponsel Anda, lalu coba kode berikutnya.',
            ]);
        }

        $this->sedangMenyiapkanDuaLangkah = false;
        $this->kodeDuaLangkah = '';

        app(SecurityLogger::class)->log(
            SecurityEventType::TwoFactorEnabled,
            user: $pengguna,
            request: request(),
        );

        session()->flash('kabar', 'Verifikasi dua langkah aktif. Simpan kode pemulihannya di tempat aman.');
    }

    public function matikanDuaLangkah(DisableTwoFactorAuthentication $matikan): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $matikan($pengguna);
        $this->sedangMenyiapkanDuaLangkah = false;

        app(SecurityLogger::class)->log(
            SecurityEventType::TwoFactorDisabled,
            user: $pengguna,
            request: request(),
        );

        session()->flash('kabar', 'Verifikasi dua langkah dimatikan.');
    }

    public function pasangPin(): void
    {
        $data = $this->validate([
            'pinBaru' => ['required', 'digits:6'],
            'pinUlang' => ['required', 'same:pinBaru'],
        ], [
            'pinBaru.digits' => 'PIN terdiri dari enam angka.',
            'pinUlang.same' => 'Ulangan PIN belum sama.',
        ]);

        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $pengguna->forceFill(['app_lock_pin_hash' => Hash::make($data['pinBaru'])])->save();

        // Sesi yang sedang berjalan dianggap sudah terbuka. Tanpa baris ini
        // orang yang baru saja memasang PIN langsung terlempar ke layar kunci
        // dari halaman yang sedang ia pakai, dan diminta memasukkan PIN yang
        // ia ketik dua detik lalu.
        PastikanAplikasiTerbuka::tandaiTerbuka();

        $this->reset(['pinBaru', 'pinUlang']);
        session()->flash('kabar', 'PIN dipasang. Aplikasi akan mengunci sendiri setelah '
            .config('rafin.app_lock_idle_minutes', 5).' menit menganggur.');
    }

    public function hapusPin(): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $pengguna->forceFill(['app_lock_pin_hash' => null])->save();

        session()->flash('kabar', 'PIN dihapus.');
    }

    /**
     * Mengeluarkan semua perangkat lain.
     *
     * Sesi lain dihapus dari penyimpanan, bukan sekadar ditandai: sesi yang
     * "ditandai keluar" tapi masih ada di tabel adalah sesi yang masih bisa
     * dipakai kalau satu pemeriksaan terlewat.
     */
    public function keluarkanSemua(): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        DB::connection('pgsql')->table('sessions')
            ->where('user_id', $pengguna->getKey())
            ->where('id', '!=', session()->getId())
            ->delete();

        UserDevice::query()
            ->where('user_id', $pengguna->getKey())
            ->where('session_id', '!=', session()->getId())
            ->update(['revoked_at' => now()]);

        app(SecurityLogger::class)->log(
            SecurityEventType::PasswordChanged,
            user: $pengguna,
            request: request(),
            metadata: ['action' => 'logout_other_devices'],
        );

        session()->flash('kabar', 'Semua perangkat lain sudah dikeluarkan.');
    }

    public function cabutPerangkat(string $id): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $perangkat = UserDevice::query()
            ->where('user_id', $pengguna->getKey())
            ->findOrFail($id);

        $perangkat->forceFill(['revoked_at' => now()])->save();

        DB::connection('pgsql')->table('sessions')->where('id', $perangkat->session_id)->delete();

        session()->flash('kabar', 'Perangkat dicabut.');
    }

    /**
     * Menerbitkan izin akses dukungan.
     *
     * Diterbitkan pengguna, bukan diminta admin. Panel admin tidak punya tombol
     * untuk membuka data siapa pun — yang ada hanya izin yang sudah diberikan.
     */
    public function terbitkanIzin(): void
    {
        $data = $this->validate([
            'jamIzin' => ['required', 'integer', 'min:1', 'max:'.SupportAccessGrant::MAKS_JAM],
            'alasanIzin' => ['nullable', 'string', 'max:255'],
        ]);

        $pengguna = auth()->user();
        $workspace = app(TenantContext::class)->workspace();
        abort_if($pengguna === null || $workspace === null, 404);

        SupportAccessGrant::query()->create([
            'workspace_id' => $workspace->getKey(),
            'granted_by_user_id' => $pengguna->getKey(),
            'scope' => 'read_metadata',
            'reason' => $data['alasanIzin'] ?: null,
            'expires_at' => now()->addHours($data['jamIzin']),
        ]);

        app(SecurityLogger::class)->log(
            SecurityEventType::SupportAccessGranted,
            user: $pengguna,
            request: request(),
            metadata: ['hours' => $data['jamIzin'], 'scope' => 'read_metadata'],
        );

        $this->reset('alasanIzin');
        session()->flash('kabar', 'Izin diterbitkan. Anda akan diberi tahu kalau dipakai.');
    }

    public function cabutIzin(string $id): void
    {
        $workspace = app(TenantContext::class)->workspace();
        abort_if($workspace === null, 404);

        SupportAccessGrant::query()
            ->where('workspace_id', $workspace->getKey())
            ->findOrFail($id)
            ->forceFill(['revoked_at' => now()])
            ->save();

        session()->flash('kabar', 'Izin dicabut.');
    }

    public function render()
    {
        $pengguna = auth()->user();
        $workspace = app(TenantContext::class)->workspace();

        $duaLangkahAktif = $pengguna?->duaLangkahAktif() ?? false;

        $tersambung = $pengguna === null
            ? collect()
            : SocialAccount::query()->where('user_id', $pengguna->getKey())->get()->keyBy('provider.value');

        return view('livewire.app.keamanan', [
            'punyaPin' => $pengguna?->punyaKunciAplikasi() ?? false,
            'duaLangkahAktif' => $duaLangkahAktif,
            'penyediaTersedia' => SocialProvider::tersedia(),
            'akunTersambung' => $tersambung,
            'punyaKataSandi' => $pengguna?->punyaKataSandi() ?? false,
            'qrDuaLangkah' => $this->sedangMenyiapkanDuaLangkah && ! $duaLangkahAktif
                ? $pengguna?->twoFactorQrCodeSvg()
                : null,
            'kodePemulihan' => $duaLangkahAktif ? $pengguna?->recoveryCodes() : [],
            'perangkat' => UserDevice::query()
                ->where('user_id', $pengguna?->getKey())
                ->whereNull('revoked_at')
                ->orderByDesc('last_seen_at')
                ->get(),
            'riwayat' => SecurityEvent::query()
                ->where('user_id', $pengguna?->getKey())
                ->whereIn('event', [
                    SecurityEventType::LoginSuccess->value,
                    SecurityEventType::LoginFailed->value,
                    SecurityEventType::SessionNewDevice->value,
                    SecurityEventType::PasswordChanged->value,
                    SecurityEventType::DataExported->value,
                ])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'izin' => $workspace === null
                ? collect()
                : SupportAccessGrant::query()
                    ->where('workspace_id', $workspace->getKey())
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get(),
            'maksJam' => SupportAccessGrant::MAKS_JAM,
        ])->layout('components.layouts.app', ['title' => 'Keamanan']);
    }
}
