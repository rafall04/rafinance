<?php

declare(strict_types=1);

namespace App\Domain\Capture\Models;

use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Models\User;
use Database\Factories\InboxItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apa pun yang masuk, lengkap atau tidak.
 *
 * Inilah wujud nyata "capture dulu, klasifikasi belakangan". Pesan yang gagal
 * dibaca parser tidak ditolak — ia mendarat di sini, dan pengguna melengkapinya
 * kapan pun ia sempat, dari kanal mana pun.
 *
 * @property string $id
 * @property ParseStatus $parse_status
 * @property array<string, mixed>|null $parsed_draft
 */
class InboxItem extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<InboxItemFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'source',
        'raw_text',
        'raw_payload',
        'media_path',
        'parse_status',
        'parsed_draft',
        'transaction_id',
        'created_by',
        'telegram_chat_id',
        'telegram_message_id',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => TransactionSource::class,
            'parse_status' => ParseStatus::class,
            'raw_payload' => 'array',
            'parsed_draft' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<InboxItem>  $query
     */
    public function scopeBelumSelesai(Builder $query): void
    {
        $query->whereIn('parse_status', [ParseStatus::Pending->value, ParseStatus::Failed->value]);
    }

    public function isSelesai(): bool
    {
        return $this->parse_status === ParseStatus::Parsed && $this->transaction_id !== null;
    }

    /**
     * Pesan Telegram yang perlu diperbarui saat item ini diselesaikan dari web.
     */
    public function punyaPesanTelegram(): bool
    {
        return $this->telegram_chat_id !== null && $this->telegram_message_id !== null;
    }
}
