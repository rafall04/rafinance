<?php

declare(strict_types=1);

namespace App\Domain\Logging\Listeners;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Services\RecordDevice;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

/**
 * Menulis peristiwa autentikasi lewat event, bukan dari dalam controller.
 *
 * Bagian 6 dokumen rancangan menuntut ini secara eksplisit, dan alasannya
 * praktis: jalur masuk ke Rafin ada banyak — form web, Fortify, nanti bot
 * Telegram dan PWA. Kalau pencatatan menempel di controller, satu jalur baru
 * berarti satu lubang baru di jejak audit.
 */
final class RecordAuthenticationEvents
{
    public function __construct(
        private readonly SecurityLogger $logger,
        private readonly Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->logger->log(
            SecurityEventType::LoginSuccess,
            user: $user,
            request: $this->request,
            metadata: ['guard' => $event->guard, 'remember' => $event->remember],
        );

        // Perangkat dicatat di sini juga, bukan di controller: masuk bisa
        // terjadi lewat form, lewat ingat-saya, lewat verifikasi dua langkah,
        // dan nanti lewat passkey — semuanya memicu event yang sama.
        app(RecordDevice::class)($user, $this->request);
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user;

        $this->logger->log(
            SecurityEventType::LoginFailed,
            user: $user instanceof User ? $user : null,
            request: $this->request,
            metadata: [
                'guard' => $event->guard,
                // Alamat surel yang dicoba disimpan supaya serangan beruntun
                // terlihat. Kata sandinya sendiri tidak pernah ikut.
                'attempted_email' => $this->attemptedEmail($event),
            ],
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->logger->log(
            SecurityEventType::Logout,
            user: $user,
            request: $this->request,
            metadata: ['guard' => $event->guard],
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->logger->log(
            SecurityEventType::PasswordReset,
            user: $user,
            request: $this->request,
        );
    }

    private function attemptedEmail(Failed $event): ?string
    {
        $email = $event->credentials['email'] ?? null;

        return is_string($email) ? mb_substr($email, 0, 255) : null;
    }
}
