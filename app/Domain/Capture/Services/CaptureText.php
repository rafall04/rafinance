<?php

declare(strict_types=1);

namespace App\Domain\Capture\Services;

use App\Domain\Capture\Enums\ParseStatus;
use App\Domain\Capture\Models\InboxItem;
use App\Domain\Capture\Models\ParseFailure;
use App\Domain\Capture\NormalizedDraft;
use App\Domain\Capture\RuleBasedParser;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\PostTransaction;
use App\Models\User;

/**
 * Menerima teks mentah dari kanal mana pun, dan tidak pernah menolaknya.
 *
 * Inilah tempat prinsip produk ini berhenti jadi slogan. Yang bisa dibaca
 * langsung jadi transaksi; yang tidak, jadi item inbox dengan draft seadanya —
 * bukan pesan galat. Orang yang ditolak saat mencatat akan berhenti mencatat,
 * dan aplikasi keuangan yang tidak dipakai tidak menolong siapa pun.
 */
final class CaptureText
{
    public function __construct(
        private readonly RuleBasedParser $parser,
        private readonly PostTransaction $post,
    ) {}

    public function __invoke(
        string $teks,
        TransactionSource $sumber,
        ?User $pengguna = null,
        string $currency = 'IDR',
        ?int $telegramChatId = null,
        ?int $telegramMessageId = null,
    ): CaptureResult {
        $draft = ($this->parser)($teks, $currency);

        if (! $draft->isComplete()) {
            return $this->keInbox($draft, $sumber, $pengguna, $telegramChatId, $telegramMessageId);
        }

        $transaksi = $this->keBukuBesar($draft, $sumber, $pengguna);

        // Item inbox tetap dibuat meski langsung berhasil: ia jejak bahwa
        // pesan ini pernah masuk, dan tempat menyambungkan pesan Telegram
        // dengan transaksinya kalau nanti diubah dari web.
        $item = InboxItem::query()->create([
            'source' => $sumber,
            'raw_text' => $teks,
            'parse_status' => ParseStatus::Parsed,
            'parsed_draft' => $draft->toArray(),
            'transaction_id' => $transaksi->getKey(),
            'created_by' => $pengguna?->getKey(),
            'telegram_chat_id' => $telegramChatId,
            'telegram_message_id' => $telegramMessageId,
        ]);

        return new CaptureResult($draft, $transaksi, $item);
    }

    private function keBukuBesar(NormalizedDraft $draft, TransactionSource $sumber, ?User $pengguna): Transaction
    {
        $akun = Account::query()->findOrFail($draft->accountId);

        $rancangan = match ($draft->kind) {
            TransactionKind::Income => DraftTransaction::pemasukan(
                amount: $draft->amount,
                to: $akun,
                description: $draft->description,
                categoryId: $draft->categoryId,
                source: $sumber,
                projectId: $draft->projectId,
                rawInput: $draft->rawText,
                createdBy: $pengguna?->getKey(),
            ),
            TransactionKind::Transfer => DraftTransaction::pindah(
                amount: $draft->amount,
                from: $akun,
                to: Account::query()->findOrFail($draft->toAccountId),
                description: $draft->description,
                source: $sumber,
                rawInput: $draft->rawText,
                createdBy: $pengguna?->getKey(),
            ),
            default => DraftTransaction::pengeluaran(
                amount: $draft->amount,
                from: $akun,
                description: $draft->description,
                categoryId: $draft->categoryId,
                source: $sumber,
                projectId: $draft->projectId,
                rawInput: $draft->rawText,
                createdBy: $pengguna?->getKey(),
            ),
        };

        return ($this->post)($rancangan);
    }

    private function keInbox(
        NormalizedDraft $draft,
        TransactionSource $sumber,
        ?User $pengguna,
        ?int $telegramChatId,
        ?int $telegramMessageId,
    ): CaptureResult {
        $item = InboxItem::query()->create([
            'source' => $sumber,
            'raw_text' => $draft->rawText,
            'parse_status' => ParseStatus::Failed,
            'parsed_draft' => $draft->toArray(),
            'created_by' => $pengguna?->getKey(),
            'telegram_chat_id' => $telegramChatId,
            'telegram_message_id' => $telegramMessageId,
        ]);

        ParseFailure::query()->create([
            'raw_text' => $draft->rawText,
            'reason' => $draft->catatan === [] ? 'Tidak lengkap' : implode(' ', $draft->catatan),
            'inbox_item_id' => $item->getKey(),
        ]);

        return new CaptureResult($draft, null, $item);
    }
}
