<?php

declare(strict_types=1);

namespace App\Livewire\App\Pengaturan;

use App\Channels\Telegram\Models\TelegramLink;
use App\Channels\Telegram\Models\TelegramLinkCode;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use Livewire\Component;

/**
 * Menerbitkan kode enam digit untuk menghubungkan akun Telegram.
 *
 * Arahnya sengaja begini — dari web ke Telegram, bukan sebaliknya. Kalau bot
 * yang menerbitkan kode, siapa pun yang bisa mengirim pesan ke bot bisa memulai
 * proses penautan atas nama orang lain. Dengan arah ini, penautan hanya bisa
 * dimulai oleh orang yang sudah membuktikan dirinya di web.
 */
class HubungkanTelegram extends Component
{
    public ?string $kode = null;

    public ?string $kedaluwarsa = null;

    public function terbitkanKode(): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $tiket = TelegramLinkCode::terbitkanUntuk($pengguna);

        $this->kode = $tiket->code;
        $this->kedaluwarsa = $tiket->expires_at->diffForHumans();
    }

    public function putuskan(): void
    {
        $pengguna = auth()->user();
        abort_if($pengguna === null, 404);

        $link = TelegramLink::query()->aktif()->where('user_id', $pengguna->getKey())->first();

        if ($link === null) {
            return;
        }

        $link->forceFill(['unlinked_at' => now()])->save();

        app(SecurityLogger::class)->log(
            SecurityEventType::TelegramUnlinked,
            user: $pengguna,
            request: request(),
            metadata: ['channel' => 'telegram'],
        );

        $this->kode = null;
        session()->flash('kabar', 'Telegram diputuskan.');
    }

    public function render()
    {
        $pengguna = auth()->user();

        return view('livewire.app.pengaturan.hubungkan-telegram', [
            'terhubung' => TelegramLink::query()->aktif()->where('user_id', $pengguna?->getKey())->first(),
            'namaBot' => (string) config('rafin.telegram.username', 'rafinanceid_bot'),
        ])->layout('components.layouts.app', ['title' => 'Telegram']);
    }
}
