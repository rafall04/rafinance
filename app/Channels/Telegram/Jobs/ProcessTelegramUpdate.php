<?php

declare(strict_types=1);

namespace App\Channels\Telegram\Jobs;

use App\Channels\Telegram\CommandRouter;
use App\Channels\Telegram\Models\TelegramUpdate;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memproses satu update Telegram di luar jalur request.
 *
 * Sengaja hanya membawa update_id, bukan seluruh payload: job yang membawa
 * salinan data akan memproses data basi kalau sempat tertunda, dan payload
 * Telegram bisa memuat teks pribadi yang tidak perlu berkeliaran di tabel
 * antrean lebih lama dari seharusnya.
 */
class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $updateId,
    ) {}

    public function handle(CommandRouter $router, TenantContext $tenant): void
    {
        $update = TelegramUpdate::query()->find($this->updateId);

        if ($update === null || $update->status === 'processed') {
            return;
        }

        try {
            $router->tangani($update->payload);
            $update->tandaiSelesai();
        } catch (Throwable $galat) {
            $update->tandaiGagal($galat->getMessage());

            Log::error('Telegram: gagal memproses update', [
                'update_id' => $this->updateId,
                'exception' => $galat->getMessage(),
            ]);

            throw $galat;
        } finally {
            // Worker berumur panjang: konteks tenant tidak boleh menular ke
            // job berikutnya (aturan A4).
            $tenant->clear();
        }
    }
}
