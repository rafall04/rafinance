<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Channels\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * Mendaftarkan atau mencabut webhook Telegram.
 *
 * Sengaja berupa perintah, bukan langkah manual dengan curl: URL webhook harus
 * selalu didaftarkan bersama secret token-nya, dan langkah manual yang terdiri
 * dari dua bagian adalah langkah yang cepat atau lambat dikerjakan separuh.
 */
final class TelegramWebhookCommand extends Command
{
    protected $signature = 'rafin:telegram:webhook
        {--hapus : Cabut pendaftaran webhook}
        {--url= : URL webhook; bawaannya APP_URL + /webhooks/telegram}';

    protected $description = 'Mendaftarkan webhook Telegram beserta secret token-nya';

    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->components->error('TELEGRAM_BOT_TOKEN belum diisi di .env.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            $telegram->deleteWebhook();
            $this->components->info('Webhook dicabut.');

            return self::SUCCESS;
        }

        TelegramClient::assertSecretConfigured();

        $url = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/').'/webhooks/telegram');

        if (! str_starts_with($url, 'https://')) {
            $this->components->error(
                'Telegram hanya menerima webhook lewat HTTPS. Untuk pengembangan lokal, '
                .'pakai terowongan seperti ngrok lalu berikan URL-nya lewat --url.'
            );

            return self::FAILURE;
        }

        $hasil = $telegram->setWebhook($url, (string) config('rafin.telegram.webhook_secret'));

        if ($hasil === null || ($hasil['ok'] ?? false) !== true) {
            $this->components->error('Telegram menolak pendaftaran webhook.');

            return self::FAILURE;
        }

        $this->components->info("Webhook terdaftar: {$url}");

        return self::SUCCESS;
    }
}
