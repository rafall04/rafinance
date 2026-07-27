<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Ledger\Enums\TransactionSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboxItem>
 */
class InboxItemFactory extends Factory
{
    protected $model = InboxItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => TransactionSource::Telegram,
            'raw_text' => fake()->randomElement(['bensin', 'makan siang', 'token listrik']),
            'parse_status' => ParseStatus::Failed,
        ];
    }

    public function dariTelegram(int $chatId = 12345, int $messageId = 678): static
    {
        return $this->state(fn (array $attributes): array => [
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => $messageId,
        ]);
    }
}
