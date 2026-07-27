<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembungkus tipis Bot API Telegram.
 *
 * Token dibaca dari config, yang membacanya dari .env. Ia tidak pernah muncul
 * di kode, komentar, test, log, maupun pesan commit — termasuk saat memanggil
 * endpoint: URL yang memuat token sengaja tidak pernah ikut tercatat kalau ada
 * kegagalan.
 */
final class TelegramClient
{
    public function __construct(
        private readonly ?string $token = null,
    ) {}

    public function sendMessage(
        int $chatId,
        string $text,
        ?array $keyboard = null,
        ?int $replyTo = null,
    ): ?array {
        return $this->panggil('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard !== null ? json_encode(['inline_keyboard' => $keyboard]) : null,
            'reply_to_message_id' => $replyTo,
            'link_preview_options' => json_encode(['is_disabled' => true]),
        ], static fn (mixed $v): bool => $v !== null));
    }

    /**
     * Memperbarui pesan lama. Dipakai saat item inbox diselesaikan dari web:
     * tombol yang tidak lagi bisa ditekan membuat sistem terasa rusak.
     */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $keyboard = null): ?array
    {
        return $this->panggil('editMessageText', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard !== null ? json_encode(['inline_keyboard' => $keyboard]) : null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): ?array
    {
        return $this->panggil('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function setWebhook(string $url, string $secret): ?array
    {
        return $this->panggil('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query', 'edited_message']),
        ]);
    }

    public function deleteWebhook(): ?array
    {
        return $this->panggil('deleteWebhook', []);
    }

    public function getFile(string $fileId): ?array
    {
        return $this->panggil('getFile', ['file_id' => $fileId]);
    }

    public function isConfigured(): bool
    {
        return $this->token() !== null;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @return array<string, mixed>|null
     */
    private function panggil(string $metode, array $parameter): ?array
    {
        $token = $this->token();

        if ($token === null) {
            Log::warning("Telegram: {$metode} dilewati, TELEGRAM_BOT_TOKEN belum diisi.");

            return null;
        }

        $balasan = $this->http()->asForm()->post(
            "https://api.telegram.org/bot{$token}/{$metode}",
            $parameter,
        );

        if ($balasan->failed()) {
            // Sengaja tanpa URL: URL-nya memuat token bot.
            Log::warning("Telegram: {$metode} gagal", [
                'status' => $balasan->status(),
                'description' => $balasan->json('description'),
            ]);

            return null;
        }

        return $balasan->json();
    }

    private function http(): PendingRequest
    {
        // Batas waktu pendek disengaja: bot ini dipanggil dari dalam job, dan
        // job yang menggantung menahan antrean untuk semua orang.
        return Http::timeout(10)->connectTimeout(5)->retry(2, 200);
    }

    private function token(): ?string
    {
        $token = $this->token ?? config('rafin.telegram.token');

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    public static function assertSecretConfigured(): void
    {
        $secret = config('rafin.telegram.webhook_secret');

        if (! is_string($secret) || strlen($secret) < 16) {
            throw new RuntimeException(
                'TELEGRAM_WEBHOOK_SECRET belum diisi, atau terlalu pendek. Tanpa itu, '
                .'siapa pun yang tahu URL webhook bisa mengarang transaksi.'
            );
        }
    }
}
