<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Channels\Telegram\Models\TelegramLink;
use App\Channels\Telegram\TelegramClient;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Models\UserDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mencatat perangkat yang dipakai masuk, dan memberi tahu kalau ia baru.
 *
 * Pemberitahuan dikirim lewat Telegram, bukan Web Push. Bukan karena Web Push
 * kurang baik, tapi karena ia tidak ada di Safari sebelum iOS 16.4 dan hanya
 * berjalan setelah aplikasi dipasang ke layar utama. Notifikasi keamanan yang
 * hanya sampai ke sebagian pengguna bukan notifikasi keamanan.
 */
final class RecordDevice
{
    public function __construct(
        private readonly SecurityLogger $keamanan,
    ) {}

    public function __invoke(User $pengguna, Request $request): UserDevice
    {
        $sidik = $this->sidikPerangkat($request);

        $perangkat = UserDevice::query()
            ->where('user_id', $pengguna->getKey())
            ->where('session_id', $sidik)
            ->first();

        $baru = $perangkat === null;

        $perangkat ??= new UserDevice(['user_id' => $pengguna->getKey(), 'session_id' => $sidik]);

        $perangkat->forceFill([
            'user_id' => $pengguna->getKey(),
            'session_id' => $sidik,
            'label' => $this->label($request),
            'last_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        if ($baru) {
            $this->keamanan->log(
                SecurityEventType::SessionNewDevice,
                user: $pengguna,
                request: $request,
                metadata: ['label' => $perangkat->label],
            );

            $this->beriTahu($pengguna, $perangkat);
        }

        return $perangkat;
    }

    /**
     * Sidik perangkat dari user agent dan IP, bukan dari ID sesi.
     *
     * ID sesi berubah setiap kali masuk — memakainya berarti setiap login
     * dianggap perangkat baru, dan pemberitahuan yang berbunyi setiap hari akan
     * diabaikan persis pada hari ia benar-benar penting.
     */
    private function sidikPerangkat(Request $request): string
    {
        return hash('sha256', implode('|', [
            (string) $request->userAgent(),
            // Hanya blok jaringan, bukan IP penuh: IP seluler berganti terus,
            // dan orang yang sama di jaringan yang sama bukan perangkat baru.
            implode('.', array_slice(explode('.', (string) $request->ip()), 0, 2)),
        ]));
    }

    private function label(Request $request): string
    {
        $ua = (string) $request->userAgent();

        $sistem = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Perangkat lain',
        };

        $peramban = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'peramban lain',
        };

        return $sistem.' · '.$peramban;
    }

    private function beriTahu(User $pengguna, UserDevice $perangkat): void
    {
        $link = TelegramLink::query()->aktif()->where('user_id', $pengguna->getKey())->first();

        if ($link === null) {
            return;
        }

        app(TelegramClient::class)->sendMessage(
            $link->chat_id,
            implode("\n", [
                '🔐 <b>Masuk dari perangkat baru</b>',
                '   '.htmlspecialchars($perangkat->label ?? 'Perangkat baru', ENT_NOQUOTES, 'UTF-8'),
                '   IP '.htmlspecialchars((string) $perangkat->last_ip, ENT_NOQUOTES, 'UTF-8'),
                '',
                'Kalau ini bukan Anda, segera ganti kata sandi dan keluarkan semua perangkat.',
            ]),
        );
    }
}
